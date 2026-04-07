<?php
require_once 'superadmin_sidebar.php';
$page_title = 'Dashboard Overview';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
include '../plugins/conn.php';

// Fetch Statistics
// 1. Total Items
$sql_items = "SELECT COUNT(*) as count, SUM(balance_qty * unit_value) as total_value FROM items WHERE status = 'Active'";
$result_items = $conn->query($sql_items);
$row_items = $result_items->fetch_assoc();
$total_items = $row_items['count'];
$total_value = $row_items['total_value'];

// 2. Low Stock Items (Less than 5)
$sql_low = "SELECT COUNT(*) as count FROM items WHERE balance_qty < 5 AND status = 'Active'";
$result_low = $conn->query($sql_low);
$low_stock = $result_low->fetch_assoc()['count'];

// 3. Active Assignments
$sql_assign = "SELECT COUNT(*) as count FROM assignments WHERE status = 'Active'";
$result_assign = $conn->query($sql_assign);
$active_assignments = $result_assign->fetch_assoc()['count'];

// 4. Pending Requests
$sql_req = "SELECT COUNT(*) as count FROM requests WHERE status = 'Pending'";
$result_req = $conn->query($sql_req);
$pending_requests = $result_req->fetch_assoc()['count'];

?>

<div class="main-content">
    <div class="container-fluid">
        
        <div class="row g-4 mb-4">
            <!-- Total Items Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Total Items</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($total_items); ?></h3>
                        </div>
                        <div class="text-primary bg-primary bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                             <i class="bi bi-box-seam fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Requests Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Pending Requests</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($pending_requests); ?></h3>
                        </div>
                        <div class="text-info bg-info bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                             <i class="bi bi-clock-history fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted fw-normal mb-1">Low Stock</h6>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($low_stock); ?></h3>
                        </div>
                        <div class="text-warning bg-warning bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                             <i class="bi bi-exclamation-triangle fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Value Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted fw-normal mb-1">Total Value</h6>
                        <h3 class="fw-bold mb-0 text-dark fs-4 text-wrap">₱<?php echo number_format($total_value, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities / Quick Links Section could go here -->
        <div class="row">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-primary"><i class="bi bi-clock me-2"></i>Recent Transactions</span>
                        <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Item</th>
                                        <th>Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql_recent = "SELECT t.*, i.item FROM inventory_transactions t 
                                                   JOIN items i ON t.item_id = i.id 
                                                   ORDER BY t.transaction_date DESC LIMIT 5";
                                    $result_recent = $conn->query($sql_recent);
                                    if ($result_recent->num_rows > 0) {
                                        while($row = $result_recent->fetch_assoc()) {
                                            $badge_class = 'bg-secondary';
                                            if($row['transaction_type'] == 'Acquisition') $badge_class = 'bg-success';
                                            if($row['transaction_type'] == 'Issuance') $badge_class = 'bg-primary';
                                            if($row['transaction_type'] == 'Disposal') $badge_class = 'bg-danger';
                                            
                                            echo "<tr>";
                                            echo "<td>" . date('M d, Y', strtotime($row['transaction_date'])) . "</td>";
                                            echo "<td><span class='badge rounded-pill $badge_class'>" . $row['transaction_type'] . "</span></td>";
                                            echo "<td>" . $row['item'] . "</td>";
                                            echo "<td>" . $row['quantity'] . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center text-muted'>No recent transactions</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header text-primary">
                        <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                    </div>
                    <div class="card-body d-grid gap-2">
                        <a href="request.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-inbox me-2"></i> Manage Requests
                        </a>
                        <a href="inventory_list.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-box-seam me-2"></i> Manage Inventory (All Items)
                        </a>
                        <a href="reports.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i> Generate Reports
                        </a>
                        <a href="manage_accounts.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-people me-2"></i> Manage Accounts
                        </a>
                        <a href="physical_count.php" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-clipboard-check me-2"></i> Physical Count (RPCI/RPCPPE)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
