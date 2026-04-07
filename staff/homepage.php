<?php
$page_title = "Dashboard";
require_once 'staff_sidebar.php';
require_once '../plugins/conn.php';
require_once 'staff_navbar.php';

$user_id = $_SESSION['user_id'];

$current_user = null;

if ($stmt = $conn->prepare("SELECT id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, role FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $current_user = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$current_user) {
    header("Location: ../logout.php");
    exit();
}

$full_name = $current_user['full_name'];

$total_items = 0;
$pending_requests = 0;
$issued_requests = 0;

if ($result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'Active'")) {
    $row = $result->fetch_assoc();
    $total_items = (int)$row['count'];
}

if ($stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE status = 'Pending' AND requested_by = ?")) {
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $pending_requests = (int)$res->fetch_assoc()['count'];
    }
    $stmt->close();
}

if ($stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE status = 'Issued' AND requested_by = ?")) {
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $issued_requests = (int)$res->fetch_assoc()['count'];
    }
    $stmt->close();
}
?>

<div class="lg:ml-[260px] pt-20 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Items -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-primary/10 text-primary w-12 h-12 rounded-2xl flex items-center justify-center mr-4">
                        <i class="bi bi-box text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Items</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_items); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-accent/10 text-accent w-12 h-12 rounded-2xl flex items-center justify-center mr-4">
                        <i class="bi bi-hourglass-split text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Pending Requests</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($pending_requests); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Issued Requests -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-50 text-green-600 w-12 h-12 rounded-2xl flex items-center justify-center mr-4">
                        <i class="bi bi-check-circle text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Issued Requests</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($issued_requests); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activity Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden h-full">
                    <div class="px-6 py-5 border-b border-gray-50 flex justify-between items-center bg-white">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Recent RIS Activity</h3>
                            <p class="text-xs text-gray-500">Latest requests you created</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50">
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">RIS No.</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php
                                $sql_recent = "SELECT * FROM requests WHERE requested_by = ? ORDER BY created_at DESC LIMIT 5";
                                if ($stmt_recent = $conn->prepare($sql_recent)) {
                                    $stmt_recent->bind_param("s", $full_name);
                                    $stmt_recent->execute();
                                    $result_recent = $stmt_recent->get_result();
                                    if ($result_recent->num_rows > 0) {
                                        while ($row = $result_recent->fetch_assoc()) {
                                            $status_styles = match($row['status']) {
                                                'Pending' => 'bg-amber-50 text-amber-600 border-amber-100',
                                                'Approved' => 'bg-blue-50 text-blue-600 border-blue-100',
                                                'Issued' => 'bg-green-50 text-green-600 border-green-100',
                                                'Rejected' => 'bg-red-50 text-red-600 border-red-100',
                                                'Cancelled' => 'bg-gray-50 text-gray-600 border-gray-100',
                                                default => 'bg-gray-50 text-gray-600 border-gray-100'
                                            };
                                            ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($row['ris_no']); ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    <?php echo date('M d, Y', strtotime($row['request_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?php echo $status_styles; ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <a href="ris.php#ris-<?php echo (int)$row['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary/5 text-primary hover:bg-primary hover:text-white transition-all duration-200">
                                                        <i class="bi bi-eye text-sm"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-10 text-center">
                                                <div class="flex flex-col items-center">
                                                    <i class="bi bi-inbox text-4xl text-gray-200 mb-2"></i>
                                                    <p class="text-sm text-gray-500 font-medium">No recent RIS activity found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    $stmt_recent->close();
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 h-full">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">Quick Actions</h3>
                    <div class="space-y-4">
                        <a href="inventory_list.php" class="group flex items-center p-4 rounded-2xl border border-gray-50 hover:border-primary/20 hover:bg-primary/5 transition-all duration-300">
                            <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mr-4 group-hover:bg-primary group-hover:text-white transition-colors">
                                <i class="bi bi-search text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">Search Inventory</p>
                                <p class="text-xs text-gray-500">Browse items available</p>
                            </div>
                        </a>
                        <a href="ris.php" class="group flex items-center p-4 rounded-2xl border border-gray-50 hover:border-green-100 hover:bg-green-50 transition-all duration-300">
                            <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center mr-4 group-hover:bg-green-600 group-hover:text-white transition-colors">
                                <i class="bi bi-plus-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">Create RIS</p>
                                <p class="text-xs text-gray-500">Start new requisition</p>
                            </div>
                        </a>
                        <a href="ris.php" class="group flex items-center p-4 rounded-2xl border border-gray-50 hover:border-blue-100 hover:bg-blue-50 transition-all duration-300">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mr-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <i class="bi bi-clock-history text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">View History</p>
                                <p class="text-xs text-gray-500">Review previous records</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
