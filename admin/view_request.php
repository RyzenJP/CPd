<?php
include 'admin_sidebar.php';
$page_title = 'View Request Details';
include '../plugins/conn.php';
include 'admin_navbar.php';

// Get Request ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href='requests.php';</script>";
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
                    throw new Exception("Insufficient stock for item: " . $item['item'] .
                        " (Requested: " . $item['quantity_requested'] . ", Available: " . $item['balance_qty'] . ")");
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
            if (!$stmt_deduct->execute()) throw new Exception("Error updating inventory.");

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

            $update_req = "UPDATE requests SET status = 'Approved', approved_by = 'Admin', approved_date = NOW() WHERE id = ?";
            $stmt_update = $conn->prepare($update_req);
            $stmt_update->bind_param("i", $request_id);
            if (!$stmt_update->execute()) throw new Exception("Error updating request status.");

            $conn->commit();
            echo "<script>window.location.href='requests.php?msg=approved';</script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }

    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'No reason provided';
        $update_req = "UPDATE requests SET status = 'Rejected', approved_by = 'Admin', approved_date = NOW(), remarks = ? WHERE id = ?";
        $stmt = $conn->prepare($update_req);
        $stmt->bind_param("si", $reason, $request_id);

        if ($stmt->execute()) {
            echo "<script>window.location.href='requests.php?msg=rejected';</script>";
            exit();
        } else {
            $error_msg = "Error rejecting request.";
        }
    }
}

// Fetch Request Details
$sql = "SELECT * FROM requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo "<div class='main-content'><div class='container-fluid'><div class='alert alert-danger'>Request not found.</div></div></div>";
    include 'admin_footer.php';
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

// Pre-check stock availability
$can_approve = true;
$all_items = [];
while ($row = $items_result->fetch_assoc()) {
    if ($row['balance_qty'] < $row['quantity_requested']) $can_approve = false;
    $all_items[] = $row;
}

// Status helpers
$status = $request['status'];
$status_color = match($status) {
    'Approved' => 'bg-green-100 text-green-700 ring-green-200',
    'Rejected' => 'bg-red-100 text-red-700 ring-red-200',
    'Pending'  => 'bg-yellow-100 text-yellow-700 ring-yellow-200',
    default    => 'bg-gray-100 text-gray-600 ring-gray-200',
};
$status_dot = match($status) {
    'Approved' => 'bg-green-500',
    'Rejected' => 'bg-red-500',
    'Pending'  => 'bg-yellow-400',
    default    => 'bg-gray-400',
};
?>

<div class="main-content">
<div class="container-fluid py-2">

    <!-- ── Page Header ── -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="requests.php"
               class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white border border-gray-200 shadow-sm text-gray-500 hover:text-blue-600 hover:border-blue-300 transition">
                <i class="bi bi-arrow-left text-sm"></i>
            </a>
            <div>
                <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide font-medium">Requests</p>
                <h1 class="text-2xl font-bold text-[#1a237e] leading-tight">Request Details</h1>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm text-sm font-medium text-gray-700">
                <i class="bi bi-file-earmark-text text-blue-500"></i>
                RIS No: <span class="font-bold text-[#1a237e]"><?php echo htmlspecialchars($request['ris_no']); ?></span>
            </span>
        </div>
    </div>

    <?php if ($error_msg): ?>
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
        <i class="bi bi-exclamation-triangle-fill mt-0.5 shrink-0"></i>
        <span><?php echo htmlspecialchars($error_msg); ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Main Grid ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- ── Left: Request Info ── -->
        <div class="lg:col-span-1 flex flex-col gap-5">

            <!-- Info Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="bi bi-info-circle text-blue-500"></i>
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Request Information</h2>
                </div>
                <div class="divide-y divide-gray-50">

                    <!-- Requested By -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Requested By</p>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-[#1a237e] text-white flex items-center justify-center text-xs font-bold shrink-0">
                                <?php echo strtoupper(substr($request['requested_by'], 0, 1)); ?>
                            </div>
                            <span class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($request['requested_by']); ?></span>
                        </div>
                    </div>

                    <!-- Date Requested -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Date Requested</p>
                        <div class="flex items-center gap-2 text-sm text-gray-700">
                            <i class="bi bi-calendar2 text-blue-400"></i>
                            <?php echo date('F d, Y', strtotime($request['created_at'])); ?>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5">
                            <i class="bi bi-clock text-gray-300"></i>
                            <?php echo date('h:i A', strtotime($request['created_at'])); ?>
                        </div>
                    </div>

                    <!-- Purpose -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Purpose</p>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($request['purpose'] ?: '—')); ?>
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Current Status</p>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ring-1 <?php echo $status_color; ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo $status_dot; ?>"></span>
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>

                    <?php if ($status !== 'Pending' && !empty($request['approved_by'])): ?>
                    <!-- Approved/Rejected By -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">
                            <?php echo $status === 'Rejected' ? 'Rejected By' : 'Approved By'; ?>
                        </p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($request['approved_by']); ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <i class="bi bi-calendar-check me-1"></i>
                            <?php echo date('M d, Y', strtotime($request['approved_date'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($request['remarks'])): ?>
                    <!-- Remarks -->
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Remarks</p>
                        <div class="flex items-start gap-2 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                            <i class="bi bi-chat-left-text text-red-400 mt-0.5 shrink-0 text-xs"></i>
                            <p class="text-sm text-red-600"><?php echo htmlspecialchars($request['remarks']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- ── Right: Items & Actions ── -->
        <div class="lg:col-span-2 flex flex-col gap-5">

            <!-- Items Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-box-seam text-blue-500"></i>
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Requested Items</h2>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600 text-xs font-semibold ring-1 ring-blue-100">
                        <?php echo count($all_items); ?> Item<?php echo count($all_items) !== 1 ? 's' : ''; ?>
                    </span>
                </div>

                <?php if (!$can_approve && $status === 'Pending'): ?>
                <div class="mx-5 mt-4 flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-700">
                    <i class="bi bi-exclamation-triangle-fill mt-0.5 shrink-0"></i>
                    <span>One or more items have <strong>insufficient stock</strong>. This request cannot be approved until stock is restocked.</span>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock No.</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Req. Qty</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Available</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (!empty($all_items)): ?>
                                <?php foreach ($all_items as $i => $item):
                                    $is_available = $item['balance_qty'] >= $item['quantity_requested'];
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 text-gray-400 text-xs"><?php echo $i + 1; ?></td>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['item']); ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($item['unit_measurement']); ?></p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md">
                                            <?php echo htmlspecialchars($item['stock_no']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-block w-10 h-10 rounded-full bg-blue-50 text-[#1a237e] font-bold text-base flex items-center justify-center mx-auto">
                                            <?php echo $item['quantity_requested']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            <?php echo $item['balance_qty'] > 0
                                                ? 'bg-gray-100 text-gray-700'
                                                : 'bg-red-100 text-red-600'; ?>">
                                            <?php echo $item['balance_qty']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if ($is_available): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-50 text-green-600 text-xs font-semibold ring-1 ring-green-100">
                                                <i class="bi bi-check-circle-fill text-xs"></i> OK
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-50 text-red-600 text-xs font-semibold ring-1 ring-red-100">
                                                <i class="bi bi-x-circle-fill text-xs"></i> Low
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-10 text-gray-400 text-sm">
                                        <i class="bi bi-inbox text-3xl block mb-2 text-gray-300"></i>
                                        No items found for this request.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Action Buttons (Pending only) ── -->
            <?php if ($status === 'Pending'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Ready to process this request?</p>
                        <p class="text-xs text-gray-400 mt-0.5">Approving will immediately deduct items from inventory.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Reject -->
                        <button type="button"
                                onclick="openRejectModal()"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-red-200 bg-red-50 text-red-600 text-sm font-semibold hover:bg-red-100 transition">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>

                        <!-- Approve -->
                        <?php if ($can_approve): ?>
                        <button type="button"
                                onclick="confirmApprove()"
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition shadow-sm">
                            <i class="bi bi-check-lg"></i> Approve Request
                        </button>
                        <form method="POST" id="approveForm">
                            <input type="hidden" name="action" value="approve">
                        </form>
                        <?php else: ?>
                        <button disabled
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-gray-200 text-gray-400 text-sm font-semibold cursor-not-allowed"
                                title="Cannot approve — insufficient stock">
                            <i class="bi bi-check-lg"></i> Approve Request
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</div>

<!-- ── Reject Modal ── -->
<div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <!-- Panel -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="bi bi-x-lg text-red-500 text-sm"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-800">Reject Request</h3>
                </div>
                <button type="button" onclick="closeRejectModal()"
                        class="text-gray-400 hover:text-gray-600 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5">
                <p class="text-sm text-gray-500 mb-4">Please provide a reason for rejecting this request. The requester will be notified.</p>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    Reason for Rejection <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" id="rejectReason" rows="3" required
                          placeholder="Enter reason for rejection..."
                          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-300 resize-none transition"></textarea>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 font-medium hover:bg-gray-100 transition">
                    Cancel
                </button>
                <button type="button" onclick="submitReject()"
                        class="px-5 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition shadow-sm">
                    <i class="bi bi-x-lg me-1"></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<script>
function openRejectModal() {
    document.getElementById('rejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter a reason for rejection.', confirmButtonColor: '#1a237e' });
        return;
    }

    Swal.fire({
        title: 'Reject this request?',
        html: 'This action <strong>cannot be undone</strong>. The request will be marked as Rejected.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Reject',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('rejectForm').submit();
        }
    });
}

function confirmApprove() {
    Swal.fire({
        title: 'Approve this request?',
        html: 'This will <strong>deduct the requested items</strong> from inventory. This action cannot be undone.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Yes, Approve',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.querySelector('[onclick="confirmApprove()"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span> Processing...';
            }
            document.getElementById('approveForm').submit();
        }
    });
}
</script>
