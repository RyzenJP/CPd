<?php
include 'admin_sidebar.php';
$page_title = 'Dashboard Overview';
include '../plugins/conn.php';
include 'admin_navbar.php';

// Get comprehensive metrics
$sql_items = "SELECT COUNT(*) as count, SUM(balance_qty * unit_value) as total_value FROM items WHERE status = 'Active'";
$result_items = $conn->query($sql_items);
$row_items = $result_items->fetch_assoc();
$total_items = $row_items['count'];
$total_value = $row_items['total_value'];

$low_stock_threshold = 10;
$sql_low = "SELECT COUNT(*) as count FROM items WHERE balance_qty > 0 AND balance_qty < $low_stock_threshold AND status = 'Active'";
$result_low = $conn->query($sql_low);
$low_stock = $result_low->fetch_assoc()['count'];

$sql_req = "SELECT COUNT(*) as count FROM requests WHERE status = 'Pending'";
$result_req = $conn->query($sql_req);
$pending_requests = $result_req->fetch_assoc()['count'];

// Additional metrics
$sql_completed = "SELECT COUNT(*) as count FROM requests WHERE status = 'Completed'";
$result_completed = $conn->query($sql_completed);
$completed_requests = $result_completed->fetch_assoc()['count'];

$sql_critical = "SELECT COUNT(*) as count FROM items WHERE balance_qty = 0 AND status = 'Active'";
$result_critical = $conn->query($sql_critical);
$critical_stock = $result_critical->fetch_assoc()['count'];

$sql_total = "SELECT COUNT(*) as count FROM items";
$result_total = $conn->query($sql_total);
$total_all_items = $result_total->fetch_assoc()['count'];

// Get transaction data for chart
$sql_chart = "SELECT DATE(transaction_date) as trans_date, COUNT(*) as count FROM inventory_transactions WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(transaction_date) ORDER BY trans_date";
$result_chart = $conn->query($sql_chart);
$chart_dates = [];
$chart_counts = [];
while($row = $result_chart->fetch_assoc()) {
    $chart_dates[] = date('M d', strtotime($row['trans_date']));
    $chart_counts[] = $row['count'];
}
?>

<div class="main-content">
    <div class="max-w-7xl mx-auto px-6 py-6">

        <!-- Page Header -->
        <div class="mb-6 pb-5 border-b border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 mb-1">Dashboard</h1>
            <p class="text-sm text-gray-600">Inventory Management System - Overview</p>
        </div>

        <!-- ── Primary KPI Cards ── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

            <!-- Active Items -->
            <div class="bg-white rounded-xl border border-indigo-100 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <i class="bi bi-box-seam text-lg text-[#1a237e]"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Items</p>
                            <p class="text-3xl font-black text-gray-900 leading-tight"><?php echo number_format($total_items); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white rounded-xl border border-blue-100 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="bi bi-clock-history text-lg text-blue-700"></i>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pending Requests</p>
                            <p class="text-3xl font-black text-gray-900 leading-tight"><?php echo number_format($pending_requests); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Warning -->
            <div class="bg-red-50 rounded-xl border border-red-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center border border-red-200">
                        <i class="bi bi-exclamation-circle text-lg text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Low Stock (&lt;<?php echo (int)$low_stock_threshold; ?>)</p>
                        <p class="text-3xl font-black <?php echo $low_stock > 0 ? 'text-red-600' : 'text-gray-900'; ?> leading-tight"><?php echo number_format($low_stock); ?></p>
                    </div>
                </div>
            </div>

            <!-- Inventory Value -->
            <div class="bg-white rounded-xl border border-emerald-100 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i class="bi bi-cash-stack text-lg text-emerald-700"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Value</p>
                        <p class="text-xl font-black text-gray-900 leading-tight">₱<?php echo number_format($total_value ?? 0, 0); ?></p>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Main Content Row ── -->
        <div class="grid grid-cols-1 lg:grid-cols-10 gap-5 items-start">

            <!-- Left Column (70%) -->
            <div class="lg:col-span-7 flex flex-col gap-6 min-w-0">
                <!-- Recent Transactions -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                        <h3 class="font-semibold text-gray-900 text-sm">Recent Transactions</h3>
                        <a href="inventory_list.php" style="text-decoration:none;" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                            View All
                        </a>
                    </div>

                    <div class="overflow-hidden">
                        <table class="w-full text-sm table-fixed">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="w-28 text-left px-6 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide">Date</th>
                                    <th class="w-28 text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide">Type</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide">Item</th>
                                    <th class="w-16 text-center px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide">Qty</th>
                                    <th class="w-20 text-center px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide">Balance</th>
                                    <th class="w-64 text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wide hidden lg:table-cell">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql_recent = "SELECT t.*, i.item FROM inventory_transactions t
                                               JOIN items i ON t.item_id = i.id
                                               ORDER BY t.transaction_date DESC LIMIT 7";
                                $result_recent = $conn->query($sql_recent);
                                if ($result_recent && $result_recent->num_rows > 0):
                                    while ($row = $result_recent->fetch_assoc()):
                                        $raw_type = isset($row['transaction_type']) ? trim((string)$row['transaction_type']) : '';
                                        $row_remarks = isset($row['remarks']) ? trim((string)$row['remarks']) : '';
                                        if ($raw_type === '') {
                                            if ($row_remarks !== '' && preg_match('/\bRIS\b/i', $row_remarks)) {
                                                $type = 'Issuance';
                                            } elseif ($row_remarks !== '' && stripos($row_remarks, 'return') !== false) {
                                                $type = 'Return';
                                            } elseif ($row_remarks !== '' && stripos($row_remarks, 'disposal') !== false) {
                                                $type = 'Disposal';
                                            } else {
                                                $type = 'Adjustment';
                                            }
                                        } else {
                                            $type = $raw_type;
                                        }
                                        $badge_class = match($type) {
                                            'Acquisition' => 'bg-green-50 text-green-700 border-l-2 border-green-500',
                                            'Issuance'    => 'bg-blue-50 text-blue-700 border-l-2 border-blue-500',
                                            'Return'      => 'bg-amber-50 text-amber-800 border-l-2 border-amber-500',
                                            'Disposal'    => 'bg-red-50 text-red-700 border-l-2 border-red-500',
                                            'Transfer'    => 'bg-purple-50 text-purple-700 border-l-2 border-purple-500',
                                            'Adjustment'  => 'bg-gray-50 text-gray-700 border-l-2 border-gray-500',
                                            default       => 'bg-gray-50 text-gray-700 border-l-2 border-gray-500',
                                        };
                                ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-3 text-sm text-gray-900 whitespace-nowrap font-medium">
                                        <?php echo date('M d, Y', strtotime($row['transaction_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="text-xs font-semibold px-2 py-1 rounded <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($type); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 truncate">
                                        <?php echo htmlspecialchars($row['item']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-center text-gray-900">
                                        <?php echo intval($row['quantity']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-center text-gray-600">
                                        <?php echo intval($row['balance_after']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 hidden lg:table-cell truncate">
                                        <?php echo htmlspecialchars($row['remarks']); ?>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center">
                                        <p class="text-sm text-gray-500">No recent transactions</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column (30%) -->
            <div class="lg:col-span-3 flex flex-col gap-6 min-w-0">

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900 text-sm mb-4">Quick Actions</h3>
                    <div class="flex flex-col gap-3">
                        <a href="requests.php" style="text-decoration:none;" class="px-4 py-3 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors text-center">
                            Manage Requests
                        </a>
                        <a href="inventory_list.php" style="text-decoration:none;" class="px-4 py-3 text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 rounded-lg border border-blue-200 transition-colors text-center">
                            View Inventory
                        </a>
                        <a href="reports.php" style="text-decoration:none;" class="px-4 py-3 text-sm font-semibold text-blue-700 bg-white hover:bg-blue-50 rounded-lg border border-blue-200 transition-colors text-center">
                            Generate Reports
                        </a>
                    </div>
                </div>

                <?php if ($low_stock > 0): ?>
                <!-- Low Stock Alert Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-triangle text-red-600"></i>
                        <h3 class="font-semibold text-gray-900 text-sm">Low Stock Alerts</h3>
                    </div>
                    <div class="space-y-2 max-h-32 overflow-y-auto mb-4">
                        <?php
                        $sql_low_items = "SELECT item, balance_qty FROM items WHERE balance_qty > 0 AND balance_qty < $low_stock_threshold AND status = 'Active' ORDER BY balance_qty ASC LIMIT 5";
                        $res_low_items = $conn->query($sql_low_items);
                        while ($li = $res_low_items->fetch_assoc()):
                            $li_qty = (int)$li['balance_qty'];
                            $li_badge_class = match (true) {
                                $li_qty === 1 => 'bg-orange-50 text-orange-700 border-orange-200',
                                default => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                            };
                        ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <span class="text-sm text-gray-900 truncate"><?php echo htmlspecialchars($li['item']); ?></span>
                            <span class="text-xs font-bold px-2 py-1 rounded border <?php echo $li_badge_class; ?>">
                                <?php echo $li_qty; ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                        <?php if ($low_stock > 5): ?>
                        <p class="text-xs text-gray-600 text-center pt-2">+<?php echo $low_stock - 5; ?> more items</p>
                        <?php endif; ?>
                    </div>
                    <a href="inventory_list.php" style="text-decoration:none;" class="block w-full py-2 text-center text-xs font-semibold text-red-600 bg-white hover:bg-red-50 rounded border border-red-200 transition-colors">
                        View All
                    </a>
                </div>
                <?php endif; ?>

            </div><!-- end right col -->
        </div><!-- end main row -->

    </div><!-- end max-w -->
</div><!-- end main-content -->

<?php include 'admin_footer.php'; ?>
