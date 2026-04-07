<?php
// Include the sidebar file which contains the layout functions
require_once 'superadmin_sidebar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../index.php");
    exit();
}

include '../plugins/conn.php';
$user_id = $_SESSION['user_id'];
$success_msg = $_GET['msg'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Handle System Settings Update (Mock implementation for now)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $success_msg = "Settings saved successfully.";
}

$page_title = 'System Settings';

// Render the page layout
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <ul class="nav nav-tabs card-header-tabs" id="settingsTab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                    <i class="bi bi-sliders me-2"></i>General
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Backup & Restore
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">
                                    <i class="bi bi-box-seam me-2"></i>Inventory
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="save_settings" value="1">
                            
                            <div class="tab-content" id="settingsTabContent">
                                <!-- General Settings -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <h5 class="fw-bold mb-4">Application Information</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">System Name</label>
                                            <input type="text" class="form-control" name="system_name" value="CPD-NIR Inventory System">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">System Short Name</label>
                                            <input type="text" class="form-control" name="system_short_name" value="CPD-NIR">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small fw-bold">Organization Name</label>
                                            <input type="text" class="form-control" name="org_name" value="Department of Health - CPD-NIR">
                                        </div>
                                    </div>
                                    
                                    <h5 class="fw-bold mt-5 mb-4">Regional & Localization</h5>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Timezone</label>
                                            <select class="form-select" name="timezone">
                                                <option value="Asia/Manila" selected>Asia/Manila (GMT+8)</option>
                                                <option value="UTC">UTC</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Date Format</label>
                                            <select class="form-select" name="date_format">
                                                <option value="Y-m-d" selected>YYYY-MM-DD</option>
                                                <option value="M d, Y">Jan 01, 2024</option>
                                                <option value="d/m/Y">01/01/2024</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Currency Symbol</label>
                                            <input type="text" class="form-control" name="currency" value="₱">
                                        </div>
                                    </div>
                                </div>

                                <!-- Backup & Restore Settings -->
                                <div class="tab-pane fade" id="backup" role="tabpanel">
                                    <h5 class="fw-bold mb-4">Database Management</h5>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <div class="card border shadow-none h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                                            <i class="bi bi-download text-primary fs-4"></i>
                                                        </div>
                                                        <h6 class="fw-bold mb-0">Backup Database</h6>
                                                    </div>
                                                    <p class="text-muted small">Download a full backup of your system's database (.sql file).</p>
                                                    <a href="backup_db.php" class="btn btn-outline-primary w-100 fw-bold mt-2">
                                                        <i class="bi bi-cloud-download me-2"></i>Generate Backup
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card border shadow-none h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="bg-warning bg-opacity-10 p-2 rounded me-3">
                                                            <i class="bi bi-upload text-warning fs-4"></i>
                                                        </div>
                                                        <h6 class="fw-bold mb-0">Restore Database</h6>
                                                    </div>
                                                    <p class="text-muted small">Restore your database from a previously saved .sql file.</p>
                                                    <button type="button" class="btn btn-outline-warning w-100 fw-bold mt-2" data-bs-toggle="modal" data-bs-target="#restoreModal">
                                                        <i class="bi bi-cloud-upload me-2"></i>Upload & Restore
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Inventory Settings -->
                                <div class="tab-pane fade" id="inventory" role="tabpanel">
                                    <h5 class="fw-bold mb-4">Stock Management</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Low Stock Threshold</label>
                                            <input type="number" class="form-control" name="low_stock_threshold" value="10">
                                            <div class="form-text">Notify when item quantity falls below this value.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Default Unit of Measure</label>
                                            <input type="text" class="form-control" name="default_uom" value="pcs">
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-check form-switch mt-3">
                                                <input class="form-check-input" type="checkbox" id="auto_approve_requests" checked>
                                                <label class="form-check-label fw-bold" for="auto_approve_requests">Allow automatic request processing</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-4 border-top">
                                <button type="submit" class="btn btn-primary px-4 fw-bold">
                                    <i class="bi bi-save me-2"></i>Save All Settings
                                </button>
                                <button type="reset" class="btn btn-light px-4 ms-2">Reset Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Restore Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="restore_db.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-warning border-0 small">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> Restoring will overwrite all current data. This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Backup File (.sql)</label>
                        <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="bi bi-cloud-upload me-2"></i>Restore Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    // Tab persistence
    document.addEventListener('DOMContentLoaded', function() {
        var triggerTabList = [].slice.call(document.querySelectorAll('#settingsTab button'))
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl)
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault()
                tabTrigger.show()
            })
        })
    });
</script>

<?php renderFooter(); ?>