<?php
include 'admin_sidebar.php';
$page_title = 'Manage Requests';
include '../plugins/conn.php';
include 'admin_navbar.php';

$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

// Stat counts
$count_pending  = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Pending'")->fetch_row()[0];
$count_approved = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Approved'")->fetch_row()[0];
$count_rejected = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Rejected'")->fetch_row()[0];
$count_issued   = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'Issued'")->fetch_row()[0];

// Pending requests
$pending_result = $conn->query("SELECT * FROM requests WHERE status = 'Pending' ORDER BY created_at ASC");

// History (all non-pending)
$history_result = $conn->query("SELECT * FROM requests WHERE status != 'Pending' ORDER BY created_at DESC LIMIT 20");
?>

<div class="main-content">
<div class="container-fluid py-2">

    <!-- ── Toast Notifications ── -->
    <?php if ($msg): ?>
    <div id="toastMsg" class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm shadow-sm">
        <i class="bi bi-check-circle-fill text-green-500 shrink-0"></i>
        <span class="flex-1">
            <?php
            if ($msg === 'approved')      echo 'Request has been <strong>approved</strong> successfully.';
            elseif ($msg === 'rejected')  echo 'Request has been <strong>rejected</strong> successfully.';
            else                          echo htmlspecialchars($msg);
            ?>
        </span>
        <button onclick="this.closest('#toastMsg').remove()" class="text-green-400 hover:text-green-600 transition ml-2">
            <i class="bi bi-x-lg text-xs"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div id="toastErr" class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm shadow-sm">
        <i class="bi bi-exclamation-triangle-fill text-red-400 shrink-0"></i>
        <span class="flex-1"><?php echo htmlspecialchars($error); ?></span>
        <button onclick="this.closest('#toastErr').remove()" class="text-red-400 hover:text-red-600 transition ml-2">
            <i class="bi bi-x-lg text-xs"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- ── Page Header ── -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Admin</p>
            <h1 class="text-2xl font-bold text-[#1a237e] leading-tight">Manage Requests</h1>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center shrink-0">
                <i class="bi bi-hourglass-split text-yellow-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_pending; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Pending</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <i class="bi bi-check-circle text-blue-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_approved; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Approved</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
                <i class="bi bi-box-seam text-green-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_issued; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Issued</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <i class="bi bi-x-circle text-red-400 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_rejected; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Rejected</p>
            </div>
        </div>

    </div>

    <!-- ── Pending Requests ── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">

        <!-- Card Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <i class="bi bi-hourglass-split text-yellow-500"></i>
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Pending Requests</h2>
            </div>
            <?php if ($count_pending > 0): ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-yellow-50 text-yellow-600 text-xs font-semibold ring-1 ring-yellow-100">
                <?php echo $count_pending; ?> pending
            </span>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <?php if ($pending_result && $pending_result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">RIS No.</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Requested By</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Purpose</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Items</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php while ($row = $pending_result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-colors">

                        <!-- RIS No -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-mono text-xs font-bold text-[#1a237e] bg-blue-50 px-2.5 py-1 rounded-md">
                                <?php echo htmlspecialchars($row['ris_no']); ?>
                            </span>
                        </td>

                        <!-- Requested By -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-[#1a237e] text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <?php echo strtoupper(substr($row['requested_by'], 0, 1)); ?>
                                </div>
                                <span class="text-sm font-medium text-gray-700">
                                    <?php echo htmlspecialchars($row['requested_by']); ?>
                                </span>
                            </div>
                        </td>

                        <!-- Date -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600">
                                <?php echo date('M d, Y', strtotime($row['request_date'])); ?>
                            </span>
                        </td>

                        <!-- Purpose -->
                        <td class="px-4 py-4 max-w-[180px]">
                            <span class="text-sm text-gray-600 line-clamp-2">
                                <?php echo htmlspecialchars(substr($row['purpose'], 0, 60)) . (strlen($row['purpose']) > 60 ? '…' : ''); ?>
                            </span>
                        </td>

                        <!-- Items -->
                        <td class="px-4 py-4 max-w-[200px]">
                            <?php
                            $req_id    = $row['id'];
                            $items_res = $conn->query("SELECT i.item, ri.quantity_requested
                                                       FROM request_items ri
                                                       JOIN items i ON ri.item_id = i.id
                                                       WHERE ri.request_id = $req_id");
                            if ($items_res && $items_res->num_rows > 0):
                                $item_list = [];
                                while ($it = $items_res->fetch_assoc()) {
                                    $item_list[] = $it;
                                }
                            ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($item_list as $it): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-xs">
                                    <?php echo htmlspecialchars($it['item']); ?>
                                    <span class="font-bold text-[#1a237e]">×<?php echo $it['quantity_requested']; ?></span>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-gray-400 italic">No items</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-4 text-center whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-yellow-50 text-yellow-700 ring-1 ring-yellow-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 shrink-0"></span>
                                Pending
                            </span>
                        </td>

                        <!-- Action -->
                        <td class="px-4 py-4 text-center whitespace-nowrap">
                            <a href="view_request.php?id=<?php echo $row['id']; ?>"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#1a237e] text-white text-xs font-semibold hover:bg-[#283593] transition shadow-sm">
                                <i class="bi bi-eye text-xs"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- Empty State -->
        <div class="flex flex-col items-center justify-center py-16 text-center px-4">
            <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mb-4">
                <i class="bi bi-inbox text-3xl text-gray-300"></i>
            </div>
            <p class="text-sm font-semibold text-gray-500">No pending requests</p>
            <p class="text-xs text-gray-400 mt-1">All requests have been processed.</p>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Request History ── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

        <!-- Card Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <i class="bi bi-clock-history text-gray-400"></i>
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Request History</h2>
            </div>
            <span class="text-xs text-gray-400">Last 20 records</span>
        </div>

        <!-- Table -->
        <?php if ($history_result && $history_result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">RIS No.</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Requested By</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Purpose</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php while ($row = $history_result->fetch_assoc()):
                        $s = $row['status'];
                        $badge = match($s) {
                            'Approved' => 'bg-blue-50 text-blue-600 ring-blue-100',
                            'Issued'   => 'bg-green-50 text-green-600 ring-green-100',
                            'Rejected' => 'bg-red-50 text-red-600 ring-red-100',
                            'Cancelled'=> 'bg-gray-100 text-gray-500 ring-gray-200',
                            default    => 'bg-gray-100 text-gray-500 ring-gray-200',
                        };
                        $dot = match($s) {
                            'Approved' => 'bg-blue-400',
                            'Issued'   => 'bg-green-500',
                            'Rejected' => 'bg-red-400',
                            default    => 'bg-gray-400',
                        };
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">

                        <!-- RIS No -->
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="font-mono text-xs font-semibold text-gray-600 bg-gray-100 px-2.5 py-1 rounded-md">
                                <?php echo htmlspecialchars($row['ris_no']); ?>
                            </span>
                        </td>

                        <!-- Requested By -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs font-bold shrink-0">
                                    <?php echo strtoupper(substr($row['requested_by'], 0, 1)); ?>
                                </div>
                                <span class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['requested_by']); ?>
                                </span>
                            </div>
                        </td>

                        <!-- Date -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($row['request_date'])); ?>
                            </span>
                        </td>

                        <!-- Purpose -->
                        <td class="px-4 py-4 max-w-[200px]">
                            <span class="text-sm text-gray-500">
                                <?php echo htmlspecialchars(substr($row['purpose'] ?? '', 0, 60)) . (strlen($row['purpose'] ?? '') > 60 ? '…' : ''); ?>
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-4 text-center whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ring-1 <?php echo $badge; ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo $dot; ?> shrink-0"></span>
                                <?php echo htmlspecialchars($s); ?>
                            </span>
                        </td>

                        <!-- Action -->
                        <td class="px-4 py-4 text-center whitespace-nowrap">
                            <a href="view_request.php?id=<?php echo $row['id']; ?>"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 bg-white text-gray-600 text-xs font-semibold hover:bg-gray-50 hover:border-gray-300 transition shadow-sm">
                                <i class="bi bi-eye text-xs"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="flex flex-col items-center justify-center py-16 text-center px-4">
            <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mb-4">
                <i class="bi bi-clock-history text-3xl text-gray-300"></i>
            </div>
            <p class="text-sm font-semibold text-gray-500">No request history</p>
            <p class="text-xs text-gray-400 mt-1">Processed requests will appear here.</p>
        </div>
        <?php endif; ?>

    </div>

</div>
</div>

<?php include 'admin_footer.php'; ?>
