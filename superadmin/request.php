<?php
require_once 'superadmin_sidebar.php';
$page_title = 'Manage Requests';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
include '../plugins/conn.php';

// Check for action messages
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Pagination settings
$req_per_page = 20;
$pending_page = isset($_GET['pr_page']) ? max(1, (int)$_GET['pr_page']) : 1;
$history_page = isset($_GET['rh_page']) ? max(1, (int)$_GET['rh_page']) : 1;

// Pending requests pagination
$pending_total = 0;
$pending_pages = 1;
$pending_offset = 0;

$pending_count_sql = "SELECT COUNT(*) AS total FROM requests WHERE status = 'Pending'";
$pending_count_res = $conn->query($pending_count_sql);
if ($pending_count_res && $pending_count_res->num_rows > 0) {
    $row_cnt = $pending_count_res->fetch_assoc();
    $pending_total = (int)$row_cnt['total'];
    $pending_pages = $pending_total > 0 ? (int)ceil($pending_total / $req_per_page) : 1;
}
if ($pending_page > $pending_pages) {
    $pending_page = $pending_pages;
}
$pending_offset = ($pending_page - 1) * $req_per_page;

// History requests pagination
$history_total = 0;
$history_pages = 1;
$history_offset = 0;

$history_count_sql = "SELECT COUNT(*) AS total FROM requests WHERE status != 'Pending'";
$history_count_res = $conn->query($history_count_sql);
if ($history_count_res && $history_count_res->num_rows > 0) {
    $row_cnt_h = $history_count_res->fetch_assoc();
    $history_total = (int)$row_cnt_h['total'];
    $history_pages = $history_total > 0 ? (int)ceil($history_total / $req_per_page) : 1;
}
if ($history_page > $history_pages) {
    $history_page = $history_pages;
}
$history_offset = ($history_page - 1) * $req_per_page;
?>

<div class="main-content">
    <div class="container-fluid">

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">Pending Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>RIS No.</th>
                                <th>Requested By</th>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Requested Items (Qty)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch only Pending requests with pagination
                            $sql = "SELECT * FROM requests WHERE status = 'Pending' ORDER BY created_at ASC LIMIT $req_per_page OFFSET $pending_offset";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['ris_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($row['purpose'], 0, 50)) . (strlen($row['purpose']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <?php
                                            $req_id = $row['id'];
                                            $items_sql = "SELECT i.item, ri.quantity_requested FROM request_items ri JOIN items i ON ri.item_id = i.id WHERE ri.request_id = $req_id";
                                            $items_res = $conn->query($items_sql);
                                            if($items_res && $items_res->num_rows > 0){
                                                echo '<ul class="list-unstyled mb-0 small">';
                                                while($item = $items_res->fetch_assoc()){
                                                    echo '<li>' . htmlspecialchars($item['item']) . ' <span class="fw-bold text-primary">(' . $item['quantity_requested'] . ')</span></li>';
                                                }
                                                echo '</ul>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>

                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pending_total > 0 && $pending_pages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-end mb-0">
                            <?php for ($p = 1; $p <= $pending_pages; $p++): ?>
                                <?php
                                $query = [
                                    'pr_page' => $p,
                                    'rh_page' => $history_page,
                                ];
                                $url = 'request.php?' . http_build_query($query);
                                $active = $p === $pending_page ? ' active' : '';
                                ?>
                                <li class="page-item<?php echo $active; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Section -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-secondary">Request History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>RIS No.</th>
                                <th>Requested By</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch non-Pending requests (Approved, Rejected, Issued) with pagination
                            $sql_history = "SELECT * FROM requests WHERE status != 'Pending' ORDER BY created_at DESC LIMIT $req_per_page OFFSET $history_offset";
                            $result_history = $conn->query($sql_history);

                            if ($result_history && $result_history->num_rows > 0) {
                                while ($row = $result_history->fetch_assoc()) {
                                    $status_class = match($row['status']) {
                                        'Approved' => 'bg-info',
                                        'Issued' => 'bg-success',
                                        'Rejected' => 'bg-danger',
                                        'Cancelled' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ris_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>

                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center text-muted py-4">No request history found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($history_total > 0 && $history_pages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-end mb-0">
                            <?php for ($p = 1; $p <= $history_pages; $p++): ?>
                                <?php
                                $query = [
                                    'pr_page' => $pending_page,
                                    'rh_page' => $p,
                                ];
                                $url = 'request.php?' . http_build_query($query);
                                $active = $p === $history_page ? ' active' : '';
                                ?>
                                <li class="page-item<?php echo $active; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-uppercase small text-muted">Request</span>
                        <span class="badge rounded-pill px-3 py-1" id="modalStatusBadge"></span>
                    </div>
                    <h5 class="modal-title fw-bold text-primary mb-0" id="requestDetailsModalLabel">Request Details</h5>
                    <div class="small text-muted">Review the RIS information and requested items</div>
                </div>
                <div class="ms-auto d-flex align-items-start gap-3">
                    <span class="badge bg-light text-dark border px-3 py-2">
                        RIS No: <span id="modalRisNo" class="fw-bold"></span>
                    </span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body pt-0 pb-3 px-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="border rounded-3 h-100 p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-secondary">Request Information</span>
                            </div>
                            <div class="small">
                                <div class="mb-3">
                                    <div class="text-muted text-uppercase small mb-1">Requested By</div>
                                    <div class="fw-semibold" id="modalRequestedBy"></div>
                                </div>
                                <div class="mb-3">
                                    <div class="text-muted text-uppercase small mb-1">Date Requested</div>
                                    <div id="modalRequestDate"></div>
                                </div>
                                <div>
                                    <div class="text-muted text-uppercase small mb-1">Purpose</div>
                                    <div id="modalPurpose" class="text-wrap"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="border rounded-3 h-100 p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-secondary">Requested Items</span>
                                <span class="badge bg-light text-secondary border" id="modalItemsCount"></span>
                            </div>
                            <div id="modalItemsWrapper" class="border rounded-3 p-3 bg-light-subtle">
                                <div id="modalItemsList" class="small"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <div id="modalActions" class="d-flex justify-content-end gap-2 w-100">
                    <form id="modalRejectForm" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="reason" value="No reason provided">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-x-lg me-1"></i>
                            Reject
                        </button>
                    </form>
                    <form id="modalApproveForm" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="approve">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveConfirmModal">
                            <i class="bi bi-check-lg me-1"></i>
                            Accept
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success">Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to approve this request? This will deduct items from inventory.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('modalApproveForm').submit();">Yes, approve</button>
            </div>
        </div>
    </div>
</div>

<script>
function openRequestModal(button) {
    var ris = button.getAttribute('data-ris') || '';
    var requestedBy = button.getAttribute('data-requested-by') || '';
    var date = button.getAttribute('data-date') || '';
    var purpose = button.getAttribute('data-purpose') || '';
    var id = button.getAttribute('data-id') || '';
    var status = button.getAttribute('data-status') || '';

    var row = button.closest('tr');
    var itemsCell = row ? row.querySelector('td:nth-child(5)') : null;
    var itemsHtml = '<span class="text-muted">No items found for this request.</span>';
    var itemsCount = 0;
    if (itemsCell) {
        var list = itemsCell.querySelector('ul');
        if (list) {
            itemsHtml = list.outerHTML;
            itemsCount = list.querySelectorAll('li').length;
        }
    }

    document.getElementById('modalRisNo').textContent = ris;
    document.getElementById('modalRequestedBy').textContent = requestedBy;
    document.getElementById('modalRequestDate').textContent = date;
    document.getElementById('modalPurpose').textContent = purpose || '—';
    document.getElementById('modalItemsList').innerHTML = itemsHtml;

    var countBadge = document.getElementById('modalItemsCount');
    if (countBadge) {
        if (itemsCount > 0) {
            countBadge.textContent = itemsCount + ' item' + (itemsCount > 1 ? 's' : '');
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.textContent = 'No items';
            countBadge.style.display = 'inline-block';
        }
    }

    var statusBadge = document.getElementById('modalStatusBadge');
    if (statusBadge) {
        var cls = 'bg-secondary text-white';
        if (status === 'Pending') cls = 'bg-warning text-dark';
        else if (status === 'Approved') cls = 'bg-info text-dark';
        else if (status === 'Issued') cls = 'bg-success text-white';
        else if (status === 'Rejected') cls = 'bg-danger text-white';
        statusBadge.className = 'badge rounded-pill px-3 py-1 ' + cls;
        statusBadge.textContent = status || 'N/A';
    }

    var actionsWrap = document.getElementById('modalActions');
    var approveForm = document.getElementById('modalApproveForm');
    var rejectForm = document.getElementById('modalRejectForm');

    if (actionsWrap) {
        if (status === 'Pending' && id) {
            actionsWrap.style.display = 'flex';
            if (approveForm) {
                approveForm.action = 'view_request.php?id=' + encodeURIComponent(id);
            }
            if (rejectForm) {
                rejectForm.action = 'view_request.php?id=' + encodeURIComponent(id);
            }
        } else {
            actionsWrap.style.display = 'none';
            if (approveForm) {
                approveForm.removeAttribute('action');
            }
            if (rejectForm) {
                rejectForm.removeAttribute('action');
            }
        }
    }

    var modalEl = document.getElementById('requestDetailsModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>

<?php renderFooter(); ?>
