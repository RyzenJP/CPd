<?php
require_once 'superadmin_sidebar.php';
include '../plugins/conn.php';

function createStaffNotificationForRequest(mysqli $conn, int $requestId, string $status, string $reason = ''): void
{
    $stmt = $conn->prepare("SELECT requested_by, ris_no FROM requests WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $requestId);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $res = $stmt->get_result();
    $requestRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$requestRow || empty($requestRow['requested_by'])) {
        return;
    }
    $requestedBy = $requestRow['requested_by'];
    $risNo = $requestRow['ris_no'] ?? '';
    $stmtUser = $conn->prepare("SELECT id FROM users WHERE CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) = ? LIMIT 1");
    if (!$stmtUser) {
        return;
    }
    $stmtUser->bind_param("s", $requestedBy);
    if (!$stmtUser->execute()) {
        $stmtUser->close();
        return;
    }
    $resUser = $stmtUser->get_result();
    $userRow = $resUser ? $resUser->fetch_assoc() : null;
    $stmtUser->close();
    if (!$userRow || empty($userRow['id'])) {
        return;
    }
    $userId = (int)$userRow['id'];
    $title = '';
    if ($status === 'Approved') {
        $title = 'RIS Approved';
    } elseif ($status === 'Rejected') {
        $title = 'RIS Rejected';
    } else {
        $title = 'RIS Update';
    }
    $messageParts = [];
    if ($risNo !== '') {
        $messageParts[] = 'RIS ' . $risNo;
    }
    $messageParts[] = 'has been ' . strtolower($status) . '.';
    if ($status === 'Rejected' && $reason !== '') {
        $messageParts[] = 'Reason: ' . $reason;
    }
    $message = implode(' ', $messageParts);
    $actionUrl = 'ris.php#ris-' . $requestId;
    $priority = $status === 'Approved' || $status === 'Rejected' ? 'high' : 'normal';
    $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, priority, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    if (!$stmtNotif) {
        return;
    }
    $type = 'request';
    $stmtNotif->bind_param("isssss", $userId, $title, $message, $type, $actionUrl, $priority);
    $stmtNotif->execute();
    $stmtNotif->close();
}

// Get Request ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: request.php");
    exit();
}

$request_id = intval($_GET['id']);
$error_msg = '';

// Handle Approval/Rejection Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $conn->begin_transaction();
        try {
            $ris_no = '';
            $requested_by = '';
            $stmt_reqinfo = $conn->prepare("SELECT ris_no, requested_by FROM requests WHERE id = ? LIMIT 1");
            if ($stmt_reqinfo) {
                $stmt_reqinfo->bind_param("i", $request_id);
                $stmt_reqinfo->execute();
                $reqinfo_res = $stmt_reqinfo->get_result();
                $reqinfo_row = $reqinfo_res ? $reqinfo_res->fetch_assoc() : null;
                if ($reqinfo_row) {
                    $ris_no = (string)($reqinfo_row['ris_no'] ?? '');
                    $requested_by = (string)($reqinfo_row['requested_by'] ?? '');
                }
                $stmt_reqinfo->close();
            }

            // Check stock again to ensure availability
            $check_stock = "SELECT ri.item_id, ri.quantity_requested, i.balance_qty, i.item
                          FROM request_items ri
                          JOIN items i ON ri.item_id = i.id
                          WHERE ri.request_id = ?";
            $stmt_check = $conn->prepare($check_stock);
            $stmt_check->bind_param("i", $request_id);
            $stmt_check->execute();
            $stock_result = $stmt_check->get_result();

            $items_to_issue = [];
            while ($item = $stock_result->fetch_assoc()) {
                if ($item['balance_qty'] < $item['quantity_requested']) {
                    throw new Exception("Insufficient stock for item: " . $item['item'] . " (Requested: " . $item['quantity_requested'] . ", Available: " . $item['balance_qty'] . ")");
                }
                $items_to_issue[] = [
                    'item_id' => (int)$item['item_id'],
                    'qty' => (int)$item['quantity_requested'],
                ];
            }

            $deduct_sql = "UPDATE items i
                           JOIN request_items ri ON i.id = ri.item_id
                           SET i.balance_qty = i.balance_qty - ri.quantity_requested
                           WHERE ri.request_id = ?";
            $stmt_deduct = $conn->prepare($deduct_sql);
            $stmt_deduct->bind_param("i", $request_id);
            if (!$stmt_deduct->execute()) {
                throw new Exception("Error updating inventory.");
            }

            // Mark as issued (so issued items report shows correct qty)
            $stmt_mark_issued = $conn->prepare("UPDATE request_items SET quantity_issued = quantity_requested WHERE request_id = ?");
            if ($stmt_mark_issued) {
                $stmt_mark_issued->bind_param("i", $request_id);
                if (!$stmt_mark_issued->execute()) {
                    throw new Exception("Error updating issued quantities.");
                }
                $stmt_mark_issued->close();
            }

            // Log stock-out transactions for Stock Card / Inventory Transactions history
            $stmt_balance = $conn->prepare("SELECT balance_qty FROM items WHERE id = ? LIMIT 1");
            $stmt_tx = $conn->prepare("INSERT INTO inventory_transactions (item_id, transaction_date, transaction_type, quantity, balance_after, remarks) VALUES (?, NOW(), 'Issue', ?, ?, ?)");
            if ($stmt_balance && $stmt_tx) {
                foreach ($items_to_issue as $it) {
                    $item_id = (int)$it['item_id'];
                    $qty = (int)$it['qty'];

                    $stmt_balance->bind_param("i", $item_id);
                    $stmt_balance->execute();
                    $bal_res = $stmt_balance->get_result();
                    $bal_row = $bal_res ? $bal_res->fetch_assoc() : null;
                    $balance_after = (int)($bal_row['balance_qty'] ?? 0);

                    // Reference should be the RIS number only (no person/office names)
                    $remarks = $ris_no !== '' ? $ris_no : 'RIS Issue';

                    $stmt_tx->bind_param("iiis", $item_id, $qty, $balance_after, $remarks);
                    if (!$stmt_tx->execute()) {
                        throw new Exception("Error logging inventory transaction.");
                    }
                }
                $stmt_balance->close();
                $stmt_tx->close();
            }

            $update_req = "UPDATE requests SET status = 'Approved', approved_by = 'Superadmin', approved_date = NOW() WHERE id = ?";
            $stmt_update = $conn->prepare($update_req);
            $stmt_update->bind_param("i", $request_id);
            if (!$stmt_update->execute()) {
                throw new Exception("Error updating request status.");
            }
            createStaffNotificationForRequest($conn, $request_id, 'Approved');

            $conn->commit();
            $_SESSION['success'] = 'Request approved successfully.';
            session_write_close();
            header("Location: request.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            session_write_close();
            header("Location: view_request.php?id=" . $request_id);
            exit();
        }
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'No reason provided';
        $update_req = "UPDATE requests SET status = 'Rejected', approved_by = 'Superadmin', approved_date = NOW(), remarks = ? WHERE id = ?";
        $stmt = $conn->prepare($update_req);
        $stmt->bind_param("si", $reason, $request_id);

        if ($stmt->execute()) {
            createStaffNotificationForRequest($conn, $request_id, 'Rejected', $reason);
            $_SESSION['success'] = 'Request rejected successfully.';
            session_write_close();
            header("Location: request.php");
            exit();
        } else {
            $_SESSION['error'] = "Error rejecting request.";
            session_write_close();
            header("Location: view_request.php?id=" . $request_id);
            exit();
        }
    }
}

$page_title = 'Manage Requests';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';

// Fetch Request Details
$sql = "SELECT * FROM requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo "<div class='main-content'><div class='container-fluid'><div class='alert alert-danger'>Request not found.</div></div></div>";
    renderFooter();
    exit();
}

// Fetch Requested Items
$items_sql = "SELECT ri.*, i.item, i.unit_measurement, i.stock_no, i.balance_qty
              FROM request_items ri
              JOIN items i ON ri.item_id = i.id
              WHERE ri.request_id = ?";
$stmt_items = $conn->prepare($items_sql);
$stmt_items->bind_param("i", $request_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
?>

<div class="main-content">
    <div class="max-w-7xl mx-auto">

        <?php if (isset($_SESSION['success'])): ?>
        <div id="flash-success" class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-4 shadow-sm">
            <i class="bi bi-check-circle-fill text-green-500 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div id="flash-error" class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-5 py-4 shadow-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg"></i>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <a href="request.php"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-400 hover:text-[#1a237e] transition-colors mb-1"
                   style="text-decoration:none;">
                    <i class="bi bi-arrow-left text-base"></i> Back to Requests
                </a>
                <h2 class="text-2xl font-black text-[#1a237e] tracking-tight leading-none">Request Details</h2>
            </div>
            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-2xl px-4 py-2 shadow-sm">
                <i class="bi bi-file-earmark-text text-[#1a237e] text-base"></i>
                <span class="text-xs text-gray-400 font-semibold uppercase tracking-wider">RIS No.</span>
                <span class="text-sm font-black text-[#1a237e]"><?php echo htmlspecialchars($request['ris_no']); ?></span>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Left: Info Card ── -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden h-full">
                    <!-- Card Header -->
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-gray-100 flex items-center justify-center">
                            <i class="bi bi-info-circle text-[#1a237e]"></i>
                        </div>
                        <h3 class="font-bold text-gray-600 text-sm uppercase tracking-wider">Information</h3>
                    </div>

                    <!-- Info Fields -->
                    <div class="divide-y divide-gray-50">
                        <!-- Requested By -->
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Requested By</p>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-[#1a237e] text-white flex items-center justify-center text-sm font-black shrink-0">
                                    <?php echo strtoupper(substr($request['requested_by'], 0, 1)); ?>
                                </div>
                                <span class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($request['requested_by']); ?></span>
                            </div>
                        </div>

                        <!-- Date Requested -->
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Date Requested</p>
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <i class="bi bi-calendar3 text-gray-400"></i>
                                <?php echo date('F d, Y', strtotime($request['created_at'])); ?>
                                <span class="text-gray-400"><?php echo date('h:i A', strtotime($request['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Purpose -->
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Purpose</p>
                            <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></p>
                        </div>

                        <!-- Status -->
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Current Status</p>
                            <?php
                            $tw_status = match($request['status']) {
                                'Approved' => ['bg-green-100 text-green-700 border-green-200',  'bi-check-circle-fill text-green-500'],
                                'Rejected' => ['bg-red-100 text-red-700 border-red-200',         'bi-x-circle-fill text-red-500'],
                                'Pending'  => ['bg-yellow-100 text-yellow-700 border-yellow-200','bi-clock-fill text-yellow-500'],
                                default    => ['bg-gray-100 text-gray-600 border-gray-200',      'bi-dash-circle text-gray-400'],
                            };
                            ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold <?php echo $tw_status[0]; ?>">
                                <i class="bi <?php echo $tw_status[1]; ?>"></i>
                                <?php echo htmlspecialchars($request['status']); ?>
                            </span>
                        </div>

                        <?php if ($request['status'] !== 'Pending' && !empty($request['approved_by'])): ?>
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                                <?php echo $request['status'] === 'Rejected' ? 'Rejected By' : 'Approved By'; ?>
                            </p>
                            <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($request['approved_by']); ?></p>
                            <p class="text-xs text-gray-400 mt-0.5"><?php echo date('M d, Y', strtotime($request['approved_date'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($request['remarks'])): ?>
                        <div class="px-6 py-4">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Remarks</p>
                            <div class="flex items-start gap-2 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
                                <i class="bi bi-exclamation-triangle-fill text-red-400 text-sm mt-0.5 shrink-0"></i>
                                <p class="text-sm text-red-600"><?php echo htmlspecialchars($request['remarks']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Right: Items + Actions ── -->
            <div class="lg:col-span-2 flex flex-col gap-4">

                <!-- Items Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Card Header -->
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center">
                                <i class="bi bi-box-seam text-[#1a237e]"></i>
                            </div>
                            <h3 class="font-bold text-[#1a237e] text-base">Requested Items</h3>
                        </div>
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold border border-gray-200">
                            <i class="bi bi-layers text-xs"></i>
                            <?php echo $items_result->num_rows; ?> Item(s)
                        </span>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100">
                                    <th class="text-left px-6 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Item Name</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Stock No.</th>
                                    <th class="text-center px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Req. Qty</th>
                                    <th class="text-center px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Available</th>
                                    <th class="text-center px-4 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php
                                $can_approve = true;
                                if ($items_result->num_rows > 0):
                                    while ($item = $items_result->fetch_assoc()):
                                        $is_available = $item['balance_qty'] >= $item['quantity_requested'];
                                        if (!$is_available) $can_approve = false;
                                ?>
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($item['item']); ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($item['unit_measurement']); ?></p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-lg"><?php echo htmlspecialchars($item['stock_no']); ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="text-2xl font-black text-[#1a237e]"><?php echo $item['quantity_requested']; ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if ($item['balance_qty'] > 0): ?>
                                            <span class="inline-flex items-center justify-center w-10 h-8 bg-gray-100 border border-gray-200 rounded-lg text-sm font-bold text-gray-700">
                                                <?php echo $item['balance_qty']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center w-10 h-8 bg-red-50 border border-red-200 rounded-lg text-sm font-bold text-red-600">
                                                <?php echo $item['balance_qty']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if ($is_available): ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 bg-green-100 rounded-full">
                                                <i class="bi bi-check-lg text-green-600 font-bold"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center w-8 h-8 bg-red-100 rounded-full">
                                                <i class="bi bi-x-lg text-red-600 font-bold"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-2 text-gray-400">
                                            <i class="bi bi-inbox text-3xl"></i>
                                            <span class="text-sm font-semibold">No items found for this request.</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Action Buttons ── -->
                <?php if ($request['status'] === 'Pending'): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                            <?php if (!$can_approve): ?>
                                <span class="inline-flex items-center gap-1.5 text-amber-600">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    Some items have insufficient stock
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 text-green-600">
                                    <i class="bi bi-check-circle-fill"></i>
                                    All items are available
                                </span>
                            <?php endif; ?>
                        </p>
                        <div class="flex items-center gap-3">
                            <!-- Reject Button -->
                            <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl border-2 border-red-200 text-red-600 bg-red-50 hover:bg-red-600 hover:text-white hover:border-red-600 font-bold text-sm transition-all duration-200 focus:outline-none">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>

                            <!-- Approve Button -->
                            <?php if ($can_approve): ?>
                            <form method="POST" id="approveForm">
                                <input type="hidden" name="action" value="approve">
                                <button type="button" onclick="confirmApprove()"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#1a237e] text-white hover:bg-[#283593] font-bold text-sm shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none">
                                    <i class="bi bi-check-lg"></i> Approve Request
                                </button>
                            </form>
                            <?php else: ?>
                            <button disabled
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 text-gray-400 border border-gray-200 font-bold text-sm cursor-not-allowed">
                                <i class="bi bi-check-lg"></i> Approve Request
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- end right col -->
        </div><!-- end grid -->
    </div><!-- end max-w -->
</div><!-- end main-content -->

<!-- ══ Reject Modal ══ -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('rejectModal').classList.add('hidden')"></div>

    <!-- Panel -->
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden z-10">
        <form method="POST">
            <input type="hidden" name="action" value="reject">

            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-red-100 flex items-center justify-center">
                        <i class="bi bi-x-circle-fill text-red-500 text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-gray-800 text-base">Reject Request</h3>
                        <p class="text-xs text-gray-400">This action cannot be undone.</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')"
                        class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors focus:outline-none">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-5">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2" for="reason">
                    Reason for Rejection <span class="text-red-500">*</span>
                </label>
                <textarea id="reason" name="reason" rows="4" required
                          placeholder="Provide a reason for rejecting this request..."
                          class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-300 resize-none transition-all"></textarea>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')"
                        class="px-5 py-2.5 rounded-2xl bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 font-bold text-sm transition-all focus:outline-none">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all focus:outline-none">
                    <i class="bi bi-x-circle"></i> Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Approve Confirm Modal ══ -->
<div id="approveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('approveModal').classList.add('hidden')"></div>
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden z-10">

        <!-- Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-green-100 flex items-center justify-center">
                    <i class="bi bi-check-circle-fill text-green-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-black text-gray-800 text-base">Approve Request</h3>
                    <p class="text-xs text-gray-400">This will deduct items from inventory.</p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')"
                    class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors focus:outline-none">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-5">
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3">
                <i class="bi bi-exclamation-triangle-fill text-amber-500 mt-0.5 shrink-0"></i>
                <p class="text-sm text-amber-700 font-semibold leading-relaxed">
                    Are you sure you want to approve this request?
                    Stock quantities will be deducted immediately and this action cannot be undone.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
            <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')"
                    class="px-5 py-2.5 rounded-2xl bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 font-bold text-sm transition-all focus:outline-none">
                Cancel
            </button>
            <button type="button" onclick="document.getElementById('approveForm').submit()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#1a237e] hover:bg-[#283593] text-white font-bold text-sm shadow-md hover:shadow-lg transition-all focus:outline-none">
                <i class="bi bi-check-lg"></i> Yes, Approve
            </button>
        </div>
    </div>
</div>

<script>
function confirmApprove() {
    document.getElementById('approveModal').classList.remove('hidden');
}

// Auto-dismiss flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    ['flash-success', 'flash-error'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity   = '0';
                setTimeout(function () { el.remove(); }, 500);
            }, 4000);
        }
    });

    // Close modals on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('rejectModal').classList.add('hidden');
            document.getElementById('approveModal').classList.add('hidden');
        }
    });
});
</script>

<?php renderFooter(); ?>
