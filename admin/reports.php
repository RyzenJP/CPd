    <?php
include 'admin_sidebar.php';
$page_title = 'Generate Report';
include '../plugins/conn.php';
include 'admin_navbar.php';

// Issued items filter variables
$inv_period_issued = $_GET['inv_period_issued'] ?? 'daily';
$tx_type_issued = $_GET['tx_type_issued'] ?? '';

// Calculate date range for issued items based on period
$inv_start_date = date('Y-m-01');
$inv_end_date = date('Y-m-t');

if ($inv_period_issued === 'daily') {
    $inv_start_date = date('Y-m-d');
    $inv_end_date = date('Y-m-d');
} elseif ($inv_period_issued === 'weekly') {
    $inv_start_date = date('Y-m-d', strtotime('monday this week'));
    $inv_end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($inv_period_issued === 'range' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $inv_start_date = $_GET['start_date'];
    $inv_end_date = $_GET['end_date'];
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// RPCI-specific date range filter (does not use start_date/end_date to avoid clashing with other reports)
function validate_ymd_date($value) {
    if (!is_string($value) || $value === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt) return '';
    return $dt->format('Y-m-d');
}

$rpci_start_date = validate_ymd_date($_GET['rpci_start_date'] ?? '');
$rpci_end_date = validate_ymd_date($_GET['rpci_end_date'] ?? '');
$rpci_filter_active = ($rpci_start_date !== '' && $rpci_end_date !== '');
if ($rpci_filter_active && $rpci_start_date > $rpci_end_date) {
    $tmp = $rpci_start_date;
    $rpci_start_date = $rpci_end_date;
    $rpci_end_date = $tmp;
}

$active_tab = 'rpci';
if(isset($_GET['rt']) && $_GET['rt'] == 'inventory_system') {
    $active_tab = 'system';
} elseif(isset($_GET['start_date']) || isset($_GET['end_date'])) {
    $active_tab = 'rsmi';
}
if(isset($_GET['rt']) && $_GET['rt'] == 'ris') {
    $active_tab = 'ris';
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-4">
                    <h4 class="mb-1">Report Generator</h4>
                </div>

                <div id="step1ReportType">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-2">
                            Report type
                        </div>
                    </div>
                    <div class="card border-0 bg-light mb-3">
                        <button type="button" class="btn w-100 text-start p-3 d-flex justify-content-between align-items-center" onclick="toggleReportSection('governmentStandardReports')">
                            <div class="d-flex align-items-center">
                                <div class="report-type-icon me-3">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Government Standard Reports</div>
                                    <div class="text-muted small">Click to show all report types.</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-down ms-2" id="icon-governmentStandardReports"></i>
                        </button>
                        <div id="governmentStandardReports" class="mt-2" style="display: block;">
                            <div class="card-body pt-0">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="rpci" data-section="rpci">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-clipboard-data"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Inventory Card (RPCI)</div>
                                                    <div class="report-type-subtitle">Per-item inventory balance with physical count.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="rpcppe" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-building"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Property, Plant and Equipment (RPCPPE)</div>
                                                    <div class="report-type-subtitle">Accountable PPE items with quantities and values.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="rsmi" data-section="rsmi">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Supplies and Materials Issued (RSMI)</div>
                                                    <div class="report-type-subtitle">Issued supplies by date, RIS number and amount.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="ris" data-section="ris">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Requisition and Issue Slip (RIS)</div>
                                                    <div class="report-type-subtitle">Print RIS using government-standard layout.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="sc" data-section="rpci">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-journal-text"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Stock Card (SC)</div>
                                                    <div class="report-type-subtitle">Movement of stock items and balances.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="slc" data-section="rpci">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-card-checklist"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Supplies Ledger Card (SLC)</div>
                                                    <div class="report-type-subtitle">Receipt, issue and balance of supplies.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="ics" data-section="rpci">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-box-seam"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Inventory Custodian Slip (ICS)</div>
                                                    <div class="report-type-subtitle">Accountability for semi-expendable items.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="iirup" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-recycle"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">IIRUP</div>
                                                    <div class="report-type-subtitle">Unserviceable property items for disposal.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="par" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Property Acknowledgment Receipt (PAR)</div>
                                                    <div class="report-type-subtitle">Acknowledgment for issued PPE items.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="pc" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-collection"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Property Card (PC)</div>
                                                    <div class="report-type-subtitle">Ledger of PPE by property number.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="ppelc" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-grid-3x3-gap"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">PPE Ledger Card (PPELC)</div>
                                                    <div class="report-type-subtitle">Ledger for property, plant and equipment.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="ptr" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Property Transfer Report (PTR)</div>
                                                    <div class="report-type-subtitle">Transfers of property between custodians.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="rlsddp" data-section="rpcppe">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">RLSDDP</div>
                                                    <div class="report-type-subtitle">Lost, stolen, damaged or destroyed properties.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light">
                        <button type="button" class="btn w-100 text-start p-3 d-flex justify-content-between align-items-center" onclick="toggleReportSection('systemReports')">
                            <div class="d-flex align-items-center">
                                <div class="report-type-icon me-3">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">System Reports</div>
                                    <div class="text-muted small">Application-level and internal monitoring reports.</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-down ms-2" id="icon-systemReports"></i>
                        </button>
                        <div id="systemReports" class="mt-2" style="display:block;">
                            <div class="card-body pt-0">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button type="button" class="btn report-type-option w-100 text-start" data-report-type="inventory_system" data-section="system">
                                            <div class="d-flex align-items-center">
                                                <div class="report-type-icon me-3">
                                                    <i class="bi bi-clipboard-data"></i>
                                                </div>
                                                <div class="report-type-body">
                                                    <div class="report-type-title">Inventory Report</div>
                                                    <div class="report-type-subtitle">All move-in and move-out item transactions.</div>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="step2Items" class="d-none">
                    <div id="step2Loading" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                        <div class="text-muted">Preparing items and filters.</div>
                    </div>
                    <div id="step2Content" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Item selection and filters</h5>
                                <div id="selectedReportLabel" class="text-muted small"></div>
                            </div>
                            <button type="button" id="btnChangeType" class="btn btn-outline-secondary btn-sm">Back</button>
                        </div>

                        <div class="tab-content p-4 border bg-white shadow-sm rounded" id="reportTabsContent">

            <!-- RPCI Section -->
            <div class="tab-pane fade <?php echo $active_tab == 'rpci' ? 'show active' : ''; ?>" id="rpci" role="tabpanel">
                <form id="rpciForm" action="print_file.php" method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="rpci">
                    <?php if ($rpci_filter_active): ?>
                        <input type="hidden" name="rpci_start_date" value="<?php echo htmlspecialchars($rpci_start_date); ?>">
                        <input type="hidden" name="rpci_end_date" value="<?php echo htmlspecialchars($rpci_end_date); ?>">
                    <?php endif; ?>

                    <div class="modal fade report-modal" id="rpciSettingsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">RPCI Report Settings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12"><h6 class="fw-bold text-primary mb-2">Form Information</h6></div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Form No.</label>
                                            <input type="text" name="form_no" class="form-control form-control-sm" placeholder="e.g. RPCI-FMD-FM091">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Version No.</label>
                                            <input type="text" name="version_no" class="form-control form-control-sm" placeholder="e.g. 06">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Effectivity Date</label>
                                            <input type="text" name="effectivity_date" class="form-control form-control-sm" placeholder="e.g. October 15, 2024">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Type of Inventory</label>
                                            <select name="inventory_type" class="form-select">
                                                <option value="Inventory Item">Inventory Item</option>
                                                <option value="Semi-Expendable Property">Semi-Expendable Property</option>
                                                <option value="Property, Plant and Equipment">Property, Plant and Equipment</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">As of Date</label>
                                            <input type="date" name="as_of_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Number of Pages</label>
                                            <input type="text" name="num_pages" class="form-control" placeholder="e.g. Two (2)" value="Two (2)">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6 accountability-fields-only">
                                            <label class="form-label text-secondary fw-semibold small">Accountable Officer</label>
                                            <input type="text" name="accountable_officer" class="form-control" placeholder="e.g. LORILYN M. MONTILIJAO" value="LORILYN M. MONTILIJAO">
                                        </div>
                                        <div class="col-md-6 accountability-fields-only">
                                            <label class="form-label text-secondary fw-semibold small">Position Title</label>
                                            <input type="text" name="position_title" class="form-control" placeholder="e.g. Acting Administrative Officer I" value="Acting Administrative Officer I">
                                        </div>
                                        <div class="col-md-6 accountability-fields-only">
                                            <label class="form-label text-secondary fw-semibold small">Accountability Assumed Date</label>
                                            <input type="date" name="accountability_date" class="form-control" value="2013-06-17">
                                        </div>
                                        <div class="col-12 rpci-certification-only"><hr class="my-3"><h6 class="fw-bold text-secondary mb-3">Certification Section</h6></div>
                                        <div class="col-md-4 rpci-certification-only">
                                            <label class="form-label text-secondary fw-semibold small">Certified Officer V - Name</label>
                                            <input type="text" name="rpci_certified_name_officer_v" class="form-control" value="MIMIA C. GUMBAN">
                                        </div>
                                        <div class="col-md-4 rpci-certification-only">
                                            <label class="form-label text-secondary fw-semibold small">Certified Acting Officer I - Name</label>
                                            <input type="text" name="rpci_certified_name_acting_officer_i" class="form-control" value="MARIE JEANNE A. JAGONIO">
                                        </div>
                                        <div class="col-md-4 rpci-certification-only">
                                            <label class="form-label text-secondary fw-semibold small">Certified Accountant II - Name</label>
                                            <input type="text" name="rpci_certified_name_accountant_ii" class="form-control" value="RACHELLE E. LABORDO">
                                        </div>
                                        <div class="col-md-6 rpci-certification-only">
                                            <label class="form-label text-secondary fw-semibold small">Approved By - Name</label>
                                            <input type="text" name="rpci_approved_name" class="form-control" value="HAROLD ALFRED P. MARSHALL">
                                        </div>
                                        <div class="col-md-6 rpci-certification-only">
                                            <label class="form-label text-secondary fw-semibold small">Verified By - Name</label>
                                            <input type="text" name="rpci_verified_name" class="form-control" value="MS. SIMONETTE D. CATALUÑA">
                                        </div>
                                        <div class="col-12 entity-settings-only d-none"><hr></div>
                                        <div class="col-md-6 entity-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 ledger-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Re-order Point</label>
                                            <input type="text" name="reorder_point" class="form-control" placeholder="e.g. 10 units">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">ICS No.</label>
                                            <input type="text" name="ics_no" class="form-control" placeholder="e.g. 2024-001">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved By - Name</label>
                                            <input type="text" name="ics_approved_name" class="form-control" placeholder="Name of approver">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved By - Position</label>
                                            <input type="text" name="ics_approved_position" class="form-control" placeholder="Position of approver">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received From - Name</label>
                                            <input type="text" name="ics_received_from_name" class="form-control" placeholder="Name of person releasing items">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received From - Position/Office</label>
                                            <input type="text" name="ics_received_from_position" class="form-control" placeholder="Position/Office of person releasing items">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received By - Name</label>
                                            <input type="text" name="ics_received_by_name" class="form-control" placeholder="Name of recipient">
                                        </div>
                                        <div class="col-md-6 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received By - Position/Office</label>
                                            <input type="text" name="ics_received_by_position" class="form-control" placeholder="Position/Office of recipient">
                                        </div>
                                        <div class="col-12 ics-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Reason for Transfer</label>
                                            <textarea name="ics_reason" class="form-control" rows="3" placeholder="Enter reason for transfer"></textarea>
                                        </div>
                                        <div class="col-md-6 sc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Inspected by</label>
                                            <input type="text" name="inspected_by" class="form-control" placeholder="Name of inspector">
                                        </div>
                                        <div class="col-md-6 sc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Inspected Date</label>
                                            <input type="date" name="inspected_date" class="form-control">
                                        </div>
                                        <div class="col-md-6 sc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Verified by</label>
                                            <input type="text" name="verified_by" class="form-control" placeholder="Name of verifier">
                                        </div>
                                        <div class="col-md-6 sc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Verified Date</label>
                                            <input type="date" name="verified_date" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary" id="rpciModalGenerateBtn">
                                        Generate report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                        <button type="button" class="btn btn-primary px-4" id="rpciGenerateBtn">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Generate report
                        </button>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Search Item</label>
                                    <input type="text" id="rpci_search_input" class="form-control form-control-sm" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($_GET['rpci_search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Filter Type</label>
                                    <?php
                                    $current_rt = $_GET['rt'] ?? 'rpci';
                                    if ($current_rt === 'ics'): ?>
                                        <select id="rpci_type_input" class="form-select form-select-sm" disabled>
                                            <option value="Semi-Expendable" selected>Semi-Expendable Only</option>
                                        </select>
                                    <?php else: ?>
                                        <select id="rpci_type_input" class="form-select form-select-sm">
                                            <option value="">All Types</option>
                                            <option value="Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Expendable' ? 'selected' : ''; ?>>Expendable</option>
                                            <option value="Semi-Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Semi-Expendable' ? 'selected' : ''; ?>>Semi-Expendable</option>
                                            <option value="Non-Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Non-Expendable' ? 'selected' : ''; ?>>Non-Expendable</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">From (Transactions)</label>
                                    <input type="date" id="rpci_start_date_input" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rpci_start_date); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">To (Transactions)</label>
                                    <input type="date" id="rpci_end_date_input" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rpci_end_date); ?>">
                                </div>
                                <div class="col-md-3 col-12 d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="applyRpciFilter()">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRpciFilter()">Clear</button>
                                </div>
                            </div>
                            <?php if ($rpci_filter_active || !empty($_GET['rpci_search']) || !empty($_GET['rpci_type'])): ?>
                                <div class="mt-2 small text-muted">
                                    Filters applied: 
                                    <?php if (!empty($_GET['rpci_search'])) echo '<strong>Search:</strong> "' . htmlspecialchars($_GET['rpci_search']) . '" '; ?>
                                    <?php if (!empty($_GET['rpci_type'])) echo '<strong>Type:</strong> ' . htmlspecialchars($_GET['rpci_type']) . ' '; ?>
                                    <?php if ($rpci_filter_active) echo '<strong>Transactions:</strong> ' . htmlspecialchars($rpci_start_date) . ' to ' . htmlspecialchars($rpci_end_date); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="8" class="text-primary fw-bold fs-5 py-3 ps-3">Generate Report</th>
                                </tr>
                                <tr class="text-uppercase small text-secondary">
                                    <th style="width: 50px;" class="text-center">
                                        <input type="checkbox" id="selectAll_rpci" class="form-check-input" checked>
                                    </th>
                                    <th>Article / Item</th>
                                    <th>Description</th>
                                    <th>Stock No.</th>
                                    <th>Unit</th>
                                    <th>Unit Value</th>
                                    <th class="bg-light-info text-dark">Balance</th>
                                    <th class="bg-light-success text-dark" style="width: 120px;">Physical Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rpci_search = $_GET['rpci_search'] ?? '';
                                $rpci_type = $_GET['rpci_type'] ?? '';
                                $current_rt = $_GET['rt'] ?? 'rpci';
                                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                $items_per_page = 10;
                                $offset = ($page - 1) * $items_per_page;
                                
                                $conditions = ["i.status = 'Active'"];
                                
                                if ($rpci_filter_active) {
                                    $rpci_start_esc = $conn->real_escape_string($rpci_start_date);
                                    $rpci_end_esc = $conn->real_escape_string($rpci_end_date);
                                    $conditions[] = "DATE(t.transaction_date) BETWEEN '$rpci_start_esc' AND '$rpci_end_esc'";
                                }
                                
                                if (!empty($rpci_search)) {
                                    $search_esc = $conn->real_escape_string($rpci_search);
                                    $conditions[] = "(i.item LIKE '%$search_esc%' OR i.description LIKE '%$search_esc%')";
                                }
                                
                                // Set type condition
                                if ($current_rt === 'ics') {
                                    $conditions[] = "i.item_type = 'Semi-Expendable'";
                                } elseif (!empty($rpci_type)) {
                                    $type_esc = $conn->real_escape_string($rpci_type);
                                    $conditions[] = "i.item_type = '$type_esc'";
                                }
                                
                                $where_clause = implode(' AND ', $conditions);
                                
                                // Count total items for pagination
                                if ($rpci_filter_active) {
                                    $count_sql = "SELECT COUNT(DISTINCT i.id) as total FROM items i JOIN inventory_transactions t ON t.item_id = i.id WHERE $where_clause";
                                } else {
                                    $count_sql = "SELECT COUNT(*) as total FROM items i WHERE $where_clause";
                                }
                                $count_result = $conn->query($count_sql);
                                $total_items = $count_result->fetch_assoc()['total'];
                                $total_pages = ceil($total_items / $items_per_page);
                                
                                if ($rpci_filter_active) {
                                    $sql = "
                                        SELECT DISTINCT i.*
                                        FROM items i
                                        JOIN inventory_transactions t ON t.item_id = i.id
                                        WHERE $where_clause
                                        ORDER BY i.item ASC
                                        LIMIT $items_per_page OFFSET $offset
                                    ";
                                } else {
                                    $sql = "SELECT i.* FROM items i WHERE $where_clause ORDER BY i.item ASC LIMIT $items_per_page OFFSET $offset";
                                }
                                
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="items[<?php echo $row['id']; ?>][selected]" value="1" class="form-check-input item-checkbox-rpci" checked>
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][description]" value="<?php echo htmlspecialchars($row['description']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][balance_qty]" value="<?php echo $row['balance_qty']; ?>">
                                            </td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></small></td>
                                            <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                                            <td class="text-center bg-light-info fw-bold"><?php echo $row['balance_qty']; ?></td>
                                            <td class="bg-light-success p-1">
                                                <input type="number" name="items[<?php echo $row['id']; ?>][physical_count]" class="form-control form-control-sm text-center fw-bold" value="<?php echo $row['balance_qty']; ?>">
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center py-5 text-muted">No items found for the selected date range.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                            <div class="small text-muted">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRpciPage(<?php echo $page - 1; ?>)">Previous</a>
                                    </li>
                                    <?php
                                    $start_p = max(1, $page - 2);
                                    $end_p = min($total_pages, $page + 2);
                                    
                                    if ($start_p > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRpciPage(1)">1</a></li>';
                                        if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    for ($i = $start_p; $i <= $end_p; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="#" onclick="changeRpciPage(' . $i . ')">' . $i . '</a></li>';
                                    }
                                    
                                    if ($end_p < $total_pages) {
                                        if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRpciPage(' . $total_pages . ')">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRpciPage(<?php echo $page + 1; ?>)">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- RPCPPE Section -->
            <div class="tab-pane fade <?php echo $active_tab == 'rpcppe' ? 'show active' : ''; ?>" id="rpcppe" role="tabpanel">
                <form id="rpcppeForm" action="print_file.php" method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="rpcppe">

                    <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                        <button type="button" class="btn btn-primary px-4" id="rpcppeGenerateBtn">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Generate report
                        </button>
                    </div>

                    <div class="modal fade report-modal" id="rpcppeSettingsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">RPCPPE Report Settings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12"><h6 class="fw-bold text-primary mb-2">Form Information</h6></div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Form No.</label>
                                            <input type="text" name="form_no" class="form-control form-control-sm" placeholder="e.g. RPCPPE-FMD-FM091">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Version No.</label>
                                            <input type="text" name="version_no" class="form-control form-control-sm" placeholder="e.g. 06">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Effectivity Date</label>
                                            <input type="text" name="effectivity_date" class="form-control form-control-sm" placeholder="e.g. October 15, 2024">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Type of Property</label>
                                            <select name="inventory_type" class="form-select">
                                                <option value="Property, Plant and Equipment">Property, Plant and Equipment</option>
                                                <option value="Motor Vehicles">Motor Vehicles</option>
                                                <option value="Land">Land</option>
                                                <option value="Buildings">Buildings</option>
                                                <option value="Other Property, Plant and Equipment">Other Property, Plant and Equipment</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">As of Date</label>
                                            <input type="date" name="as_of_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Number of Pages</label>
                                            <input type="text" name="pages_count_str" class="form-control" placeholder="e.g. Six (6)" value="Six (6)">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="iirup_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="iirup_fund_cluster" class="form-control" placeholder="e.g. 01">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Location</label>
                                            <input type="text" name="iirup_location" class="form-control" placeholder="e.g. Iloilo City">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Inventory No.</label>
                                            <input type="text" name="iirup_inventory_no" class="form-control" placeholder="e.g. 2024-001">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Date</label>
                                            <input type="date" name="iirup_report_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-12 iirup-settings-only d-none"><hr></div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Requested By - Name</label>
                                            <input type="text" name="iirup_requested_by_name" class="form-control" placeholder="Name of accountable officer">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Requested By - Designation</label>
                                            <input type="text" name="iirup_requested_by_designation" class="form-control" placeholder="Designation of accountable officer">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved By - Name</label>
                                            <input type="text" name="iirup_approved_by_name" class="form-control" placeholder="Name of authorized official">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved By - Designation</label>
                                            <input type="text" name="iirup_approved_by_designation" class="form-control" placeholder="Designation of authorized official">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Inspection Officer - Name</label>
                                            <input type="text" name="iirup_inspection_officer_name" class="form-control" placeholder="Name of inspection officer">
                                        </div>
                                        <div class="col-md-6 iirup-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Witness - Name</label>
                                            <input type="text" name="iirup_witness_name" class="form-control" placeholder="Name of witness">
                                        </div>
                                        <div class="col-12 par-settings-only d-none"><hr></div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="par_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">PAR No.</label>
                                            <input type="text" name="par_no" class="form-control" placeholder="e.g. 2024-001">
                                        </div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received By - Name</label>
                                            <input type="text" name="par_received_by_name" class="form-control" placeholder="Name of end user">
                                        </div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received By - Position/Office</label>
                                            <input type="text" name="par_received_by_position" class="form-control" placeholder="Position/Office of end user">
                                        </div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Issued By - Name</label>
                                            <input type="text" name="par_issued_by_name" class="form-control" placeholder="Name of supply/property custodian">
                                        </div>
                                        <div class="col-md-6 par-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Issued By - Position/Office</label>
                                            <input type="text" name="par_issued_by_position" class="form-control" placeholder="Position/Office of custodian">
                                        </div>
                                        <div class="col-12 pc-settings-only d-none"><hr></div>
                                        <div class="col-md-6 pc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="pc_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 pc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="pc_fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 pc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Property, Plant and Equipment</label>
                                            <input type="text" name="pc_ppe" class="form-control" placeholder="e.g. Furniture and Fixtures">
                                        </div>
                                        <div class="col-md-6 pc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Property Number</label>
                                            <input type="text" name="pc_property_number" class="form-control" placeholder="e.g. NXP-001">
                                        </div>
                                        <div class="col-md-12 pc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Description</label>
                                            <input type="text" name="pc_description" class="form-control" placeholder="e.g. Detailed description of property">
                                        </div>
                                        <div class="col-12 ppelc-settings-only d-none"><hr></div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="ppelc_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="ppelc_fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Property, Plant and Equipment</label>
                                            <input type="text" name="ppelc_ppe" class="form-control" placeholder="e.g. Furniture and Fixtures">
                                        </div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Object Account Code</label>
                                            <input type="text" name="ppelc_object_code" class="form-control" placeholder="e.g. 1060401000">
                                        </div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Estimated Useful Life</label>
                                            <input type="text" name="ppelc_estimated_life" class="form-control" placeholder="e.g. 10 years">
                                        </div>
                                        <div class="col-md-6 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Rate of Depreciation</label>
                                            <input type="text" name="ppelc_rate" class="form-control" placeholder="e.g. 10%">
                                        </div>
                                        <div class="col-md-12 ppelc-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Description</label>
                                            <input type="text" name="ppelc_description" class="form-control" placeholder="e.g. Detailed description of property">
                                        </div>
                                        <div class="col-12 ptr-settings-only d-none"><hr></div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="ptr_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="ptr_fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">From Accountable Officer</label>
                                            <input type="text" name="ptr_from_officer" class="form-control" placeholder="Name of transferring officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">To Accountable Officer</label>
                                            <input type="text" name="ptr_to_officer" class="form-control" placeholder="Name of receiving officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">PTR No.</label>
                                            <input type="text" name="ptr_no" class="form-control" placeholder="Leave blank to auto-generate">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Transfer Date</label>
                                            <input type="date" name="ptr_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-12 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Transfer Type</label>
                                            <div class="mt-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="ptr_transfer_type" id="ptr_donation" value="Donation">
                                                    <label class="form-check-label" for="ptr_donation">Donation</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="ptr_transfer_type" id="ptr_reassignment" value="Reassignment">
                                                    <label class="form-check-label" for="ptr_reassignment">Reassignment</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="ptr_transfer_type" id="ptr_relocate" value="Relocate">
                                                    <label class="form-check-label" for="ptr_relocate">Relocate</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="ptr_transfer_type" id="ptr_others" value="Others">
                                                    <label class="form-check-label" for="ptr_others">Others (Specify)</label>
                                                    <input type="text" name="ptr_others_specify" class="form-control mt-1" placeholder="Specify transfer type">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Reason for Transfer</label>
                                            <textarea name="ptr_reason" class="form-control" rows="3" placeholder="Enter reason for transfer"></textarea>
                                        </div>
                                        <div class="col-12 ptr-settings-only d-none"><hr></div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved by - Name</label>
                                            <input type="text" name="ptr_approved_by_name" class="form-control" placeholder="Name of approving officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Approved by - Designation</label>
                                            <input type="text" name="ptr_approved_by_designation" class="form-control" placeholder="Designation of approving officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Released by - Name</label>
                                            <input type="text" name="ptr_released_by_name" class="form-control" placeholder="Name of releasing officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Released by - Designation</label>
                                            <input type="text" name="ptr_released_by_designation" class="form-control" placeholder="Designation of releasing officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received by - Name</label>
                                            <input type="text" name="ptr_received_by_name" class="form-control" placeholder="Name of receiving officer">
                                        </div>
                                        <div class="col-md-6 ptr-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Received by - Designation</label>
                                            <input type="text" name="ptr_received_by_designation" class="form-control" placeholder="Designation of receiving officer">
                                        </div>
                                        <div class="col-12 rlsddp-settings-only d-none"><hr></div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="rlsddp_entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Type of Property</label>
                                            <input type="text" name="rlsddp_property_type" class="form-control" placeholder="e.g. Equipment, Furniture">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Department/Office</label>
                                            <input type="text" name="rlsddp_department" class="form-control" placeholder="e.g. Administrative Division">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Accountable Officer</label>
                                            <input type="text" name="rlsddp_accountable_officer" class="form-control" placeholder="Name of accountable officer">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Office Address</label>
                                            <input type="text" name="rlsddp_office_address" class="form-control" placeholder="Office address">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Position Title</label>
                                            <input type="text" name="rlsddp_position" class="form-control" placeholder="Position title">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Tel No.</label>
                                            <input type="text" name="rlsddp_tel_no" class="form-control" placeholder="Telephone number">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="rlsddp_fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Date</label>
                                            <input type="date" name="rlsddp_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Number of Pages</label>
                                            <input type="text" name="rlsddp_pages" class="form-control" placeholder="e.g. One (1)">
                                        </div>
                                        <div class="col-12 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Nature of Property</label>
                                            <div class="mt-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="rlsddp_nature[]" id="rlsddp_lost" value="Lost">
                                                    <label class="form-check-label" for="rlsddp_lost">Lost</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="rlsddp_nature[]" id="rlsddp_stolen" value="Stolen">
                                                    <label class="form-check-label" for="rlsddp_stolen">Stolen</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="rlsddp_nature[]" id="rlsddp_damaged" value="Damaged">
                                                    <label class="form-check-label" for="rlsddp_damaged">Damaged</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="rlsddp_nature[]" id="rlsddp_destroyed" value="Destroyed">
                                                    <label class="form-check-label" for="rlsddp_destroyed">Destroyed</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Circumstances</label>
                                            <textarea name="rlsddp_circumstances" class="form-control" rows="3" placeholder="Describe circumstances of loss/damage"></textarea>
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Verified By</label>
                                            <input type="text" name="rlsddp_verified_by" class="form-control" placeholder="Name of immediate supervisor">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Verification Date</label>
                                            <input type="date" name="rlsddp_verified_date" class="form-control">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Government Issued ID</label>
                                            <input type="text" name="rlsddp_id_type" class="form-control" placeholder="e.g. Passport, Driver's License">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">ID No.</label>
                                            <input type="text" name="rlsddp_id_no" class="form-control" placeholder="ID number">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Date Issued</label>
                                            <input type="date" name="rlsddp_id_issued_date" class="form-control">
                                        </div>
                                        <div class="col-12 rlsddp-settings-only d-none"><hr></div>
                                        <div class="col-md-4 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Doc No.</label>
                                            <input type="text" name="rlsddp_doc_no" class="form-control" placeholder="Document number">
                                        </div>
                                        <div class="col-md-4 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Page No.</label>
                                            <input type="text" name="rlsddp_page_no" class="form-control" placeholder="Page number">
                                        </div>
                                        <div class="col-md-4 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Book No.</label>
                                            <input type="text" name="rlsddp_book_no" class="form-control" placeholder="Book number">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Series of</label>
                                            <input type="text" name="rlsddp_series" class="form-control" placeholder="Series">
                                        </div>
                                        <div class="col-md-6 rlsddp-settings-only d-none">
                                            <label class="form-label text-secondary fw-semibold small">Notary Public</label>
                                            <input type="text" name="rlsddp_notary" class="form-control" placeholder="Notary public name">
                                        </div>
                                        <div class="col-12 rpcppe-main-only"><hr></div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Accountable Officer</label>
                                            <input type="text" name="accountable_officer" class="form-control" placeholder="e.g. LORILYN M. MONTILIJAO" value="LORILYN M. MONTILIJAO">
                                        </div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Position Title</label>
                                            <input type="text" name="position_title" class="form-control" placeholder="e.g. Administrative Officer I" value="Administrative Officer I">
                                        </div>
                                        <div class="col-md-6 rpcppe-main-only">
                                            <label class="form-label text-secondary fw-semibold small">Accountability Assumed Date</label>
                                            <input type="date" name="accountability_date" class="form-control" value="2023-08-30">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="rpcppeModalGenerateBtn">
                                        Generate report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Search Item</label>
                                    <input type="text" id="rpcppe_search_input" class="form-control form-control-sm" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($_GET['rpcppe_search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Report Filter</label>
                                    <?php
                                    $current_rt = $_GET['rt'] ?? 'rpcppe';
                                    $rpcppe_type_label = 'Equipment Only (Non-Expendable)';
                                    if ($current_rt === 'par' || $current_rt === 'ptr' || $current_rt === 'pc' || $current_rt === 'ppelc' || $current_rt === 'iirup') {
                                        $rpcppe_type_label = 'Semi-Expendable & Non-Expendable';
                                    }
                                    ?>
                                    <select id="rpcppe_type_input" class="form-select form-select-sm" disabled>
                                        <option value="dynamic" selected><?php echo $rpcppe_type_label; ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6 col-12 d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="applyRpcppeFilter()">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRpcppeFilter()">Clear</button>
                                </div>
                            </div>
                            <?php if (!empty($_GET['rpcppe_search']) || !empty($_GET['rpcppe_type'])): ?>
                                <div class="mt-2 small text-muted">
                                    Filters applied: 
                                    <?php if (!empty($_GET['rpcppe_search'])) echo '<strong>Search:</strong> "' . htmlspecialchars($_GET['rpcppe_search']) . '" '; ?>
                                    <?php if (!empty($_GET['rpcppe_type'])) echo '<strong>Type:</strong> ' . htmlspecialchars($_GET['rpcppe_type']) . ' '; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="8" class="text-primary fw-bold fs-5 py-3 ps-3">Generate Report</th>
                                </tr>
                                <tr class="text-uppercase small text-secondary">
                                    <th style="width: 50px;" class="text-center">
                                        <input type="checkbox" id="selectAll_rpcppe" class="form-check-input" checked>
                                    </th>
                                    <th>Article</th>
                                    <th>Description</th>
                                    <th>Property No.</th>
                                    <th>Unit</th>
                                    <th>Unit Value</th>
                                    <th class="bg-light-info text-dark">Qty (Card)</th>
                                    <th class="bg-light-success text-dark" style="width: 120px;">Qty (Physical)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rpcppe_search = $_GET['rpcppe_search'] ?? '';
                                $current_rt = $_GET['rt'] ?? 'rpcppe';
                                $rpcppe_page = isset($_GET['rpcppe_page']) ? (int)$_GET['rpcppe_page'] : 1;
                                $rpcppe_items_per_page = 10;
                                $rpcppe_offset = ($rpcppe_page - 1) * $rpcppe_items_per_page;
                                
                                $rpcppe_conditions = ["status = 'Active'"];
                                
                                // Set type condition based on report type
                                if ($current_rt === 'par' || $current_rt === 'ptr' || $current_rt === 'pc' || $current_rt === 'ppelc' || $current_rt === 'iirup') {
                                    $rpcppe_conditions[] = "item_type IN ('Semi-Expendable', 'Non-Expendable')";
                                } else {
                                    $rpcppe_conditions[] = "item_type = 'Non-Expendable'";
                                }

                                if (!empty($rpcppe_search)) {
                                    $search_esc = $conn->real_escape_string($rpcppe_search);
                                    $rpcppe_conditions[] = "(item LIKE '%$search_esc%' OR description LIKE '%$search_esc%')";
                                }
                                $rpcppe_where = implode(' AND ', $rpcppe_conditions);
                                
                                $count_sql = "SELECT COUNT(*) as total FROM items WHERE $rpcppe_where";
                                $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                $total_pages = ceil($total_items / $rpcppe_items_per_page);
                                
                                $sql = "SELECT * FROM items WHERE $rpcppe_where ORDER BY item ASC LIMIT $rpcppe_items_per_page OFFSET $rpcppe_offset";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        ?>
                                        <tr data-item-type="<?php echo htmlspecialchars($row['item_type']); ?>">
                                            <td class="text-center">
                                                <input type="checkbox" name="items[<?php echo $row['id']; ?>][selected]" value="1" class="form-check-input item-checkbox-rpcppe" checked>
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][description]" value="<?php echo htmlspecialchars($row['description']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][balance_qty]" value="<?php echo $row['balance_qty']; ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][date_acquired]" value="<?php echo $row['date_acquired']; ?>">
                                                <input type="hidden" name="items[<?php echo $row['id']; ?>][item_type]" value="<?php echo htmlspecialchars($row['item_type']); ?>">
                                            </td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></small></td>
                                            <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                                            <td class="text-center bg-light-info fw-bold"><?php echo $row['balance_qty']; ?></td>
                                            <td class="bg-light-success p-1">
                                                <input type="number" name="items[<?php echo $row['id']; ?>][physical_count]" class="form-control form-control-sm text-center fw-bold" value="<?php echo $row['balance_qty']; ?>">
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                            <div class="small text-muted">
                                Showing <?php echo $rpcppe_offset + 1; ?> to <?php echo min($rpcppe_offset + $rpcppe_items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $rpcppe_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRpcppePage(<?php echo $rpcppe_page - 1; ?>)">Previous</a>
                                    </li>
                                    <?php
                                    $start_p = max(1, $rpcppe_page - 2);
                                    $end_p = min($total_pages, $rpcppe_page + 2);
                                    
                                    if ($start_p > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRpcppePage(1)">1</a></li>';
                                        if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    for ($i = $start_p; $i <= $end_p; $i++) {
                                        echo '<li class="page-item ' . ($rpcppe_page == $i ? 'active' : '') . '"><a class="page-link" href="#" onclick="changeRpcppePage(' . $i . ')">' . $i . '</a></li>';
                                    }
                                    
                                    if ($end_p < $total_pages) {
                                        if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRpcppePage(' . $total_pages . ')">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <li class="page-item <?php echo $rpcppe_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRpcppePage(<?php echo $rpcppe_page + 1; ?>)">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- RSMI Section -->
            <div class="tab-pane fade <?php echo $active_tab == 'rsmi' ? 'show active' : ''; ?>" id="rsmi" role="tabpanel">
                <form id="rsmiForm" action="print_file.php" method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="rsmi">

                    <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="filterPreview()">
                            <i class="bi bi-funnel me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-primary px-4" id="rsmiGenerateBtn">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Generate report
                        </button>
                    </div>

                    <div class="modal fade report-modal" id="rsmiSettingsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">RSMI Report Settings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12"><h6 class="fw-bold text-primary mb-2">Form Information</h6></div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Form No.</label>
                                            <input type="text" name="form_no" class="form-control form-control-sm" placeholder="e.g. RSMI-FMD-FM091">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Version No.</label>
                                            <input type="text" name="version_no" class="form-control form-control-sm" placeholder="e.g. 06">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Effectivity Date</label>
                                            <input type="text" name="effectivity_date" class="form-control form-control-sm" placeholder="e.g. October 15, 2024">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Start Date</label>
                                            <input type="date" id="start_date_input" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">End Date</label>
                                            <input type="date" id="end_date_input" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">RIS No. Series (Optional)</label>
                                            <input type="text" name="ris_series" class="form-control" placeholder="e.g. 2023-10-001 to 050">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Serial No.</label>
                                            <input type="text" name="serial_no" class="form-control" value="<?php echo date('Y'); ?>-001">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Report Date</label>
                                            <input type="date" name="report_date" class="form-control" value="<?php echo $end_date; ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Supply and/or Property Custodian</label>
                                            <input type="text" name="rsmi_supply_custodian_label" class="form-control" placeholder="e.g. JUAN DELA CRUZ">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Designated Accounting Staff</label>
                                            <input type="text" name="rsmi_accounting_staff_label" class="form-control" placeholder="e.g. ROSELLE M. MISO">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Position Title (Custodian, optional)</label>
                                            <input type="text" name="certification_position" class="form-control" placeholder="e.g. Administrative Officer">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="rsmiModalGenerateBtn">
                                        Generate report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Search RIS / Item</label>
                                    <input type="text" id="rsmi_search_input" class="form-control form-control-sm" placeholder="Search RIS no or item..." value="<?php echo htmlspecialchars($_GET['rsmi_search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">From</label>
                                    <input type="date" id="rsmi_start_date_input" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">To</label>
                                    <input type="date" id="rsmi_end_date_input" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-5 col-12 d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="applyRsmiFilter()">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRsmiFilter()">Clear</button>
                                </div>
                            </div>
                            <?php if (!empty($_GET['rsmi_search']) || (isset($_GET['start_date']) && isset($_GET['end_date']))): ?>
                                <div class="mt-2 small text-muted">
                                    Filters applied: 
                                    <?php if (!empty($_GET['rsmi_search'])) echo '<strong>Search:</strong> "' . htmlspecialchars($_GET['rsmi_search']) . '" '; ?>
                                    <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])) echo '<strong>Range:</strong> ' . htmlspecialchars($start_date) . ' to ' . htmlspecialchars($end_date); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="9" class="text-primary fw-bold fs-5 py-3 ps-3">Generate Report</th>
                                </tr>
                                <tr class="text-center align-middle text-uppercase small text-secondary">
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="selectAll_rsmi" class="form-check-input" checked>
                                    </th>
                                    <th>Date Issued</th>
                                    <th>RIS No.</th>
                                    <th>Stock No.</th>
                                    <th>Item</th>
                                    <th>Unit</th>
                                    <th>Qty Issued</th>
                                    <th>Unit Cost</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rsmi_search = $_GET['rsmi_search'] ?? '';
                                $rsmi_page = isset($_GET['rsmi_page']) ? (int)$_GET['rsmi_page'] : 1;
                                $rsmi_items_per_page = 10;
                                $rsmi_offset = ($rsmi_page - 1) * $rsmi_items_per_page;

                                $rsmi_conditions = ["r.status IN ('Issued','Approved')", "DATE(IFNULL(r.approved_date, r.created_at)) BETWEEN '$start_date' AND '$end_date'"];
                                if (!empty($rsmi_search)) {
                                    $search_esc = $conn->real_escape_string($rsmi_search);
                                    $rsmi_conditions[] = "(r.ris_no LIKE '%$search_esc%' OR i.item LIKE '%$search_esc%' OR i.description LIKE '%$search_esc%')";
                                }
                                $rsmi_where = implode(' AND ', $rsmi_conditions);

                                $count_sql = "SELECT COUNT(*) as total FROM request_items ri JOIN requests r ON ri.request_id = r.id JOIN items i ON ri.item_id = i.id WHERE $rsmi_where";
                                $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                $total_pages = ceil($total_items / $rsmi_items_per_page);

                                $sql = "SELECT
                                            DATE(IFNULL(r.approved_date, r.created_at)) AS issued_date,
                                            r.ris_no AS ris_no,
                                            i.stock_no,
                                            i.item,
                                            i.unit_measurement,
                                            COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) AS quantity_issued,
                                            i.unit_value,
                                            (COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) * i.unit_value) AS amount
                                        FROM request_items ri
                                        JOIN requests r ON ri.request_id = r.id
                                        JOIN items i ON ri.item_id = i.id
                                        WHERE $rsmi_where
                                        ORDER BY issued_date ASC, r.ris_no ASC
                                        LIMIT $rsmi_items_per_page OFFSET $rsmi_offset";

                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    $counter = $rsmi_offset;
                                    while($row = $result->fetch_assoc()) {
                                        $counter++;
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="items[<?php echo $counter; ?>][selected]" value="1" class="form-check-input item-checkbox-rsmi" checked>
                                                <input type="hidden" name="items[<?php echo $counter; ?>][date]" value="<?php echo $row['issued_date']; ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][ris_no]" value="<?php echo htmlspecialchars($row['ris_no']); ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][qty]" value="<?php echo $row['quantity_issued']; ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                <input type="hidden" name="items[<?php echo $counter; ?>][amount]" value="<?php echo $row['amount']; ?>">
                                            </td>
                                            <td class="text-center"><?php echo date('M d, Y', strtotime($row['issued_date'])); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($row['ris_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                            <td class="text-center fw-bold"><?php echo $row['quantity_issued']; ?></td>
                                            <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($row['amount'], 2); ?></td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No issued items found for this date range.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                            <div class="small text-muted">
                                Showing <?php echo $rsmi_offset + 1; ?> to <?php echo min($rsmi_offset + $rsmi_items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $rsmi_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRsmiPage(<?php echo $rsmi_page - 1; ?>)">Previous</a>
                                    </li>
                                    <?php
                                    $start_p = max(1, $rsmi_page - 2);
                                    $end_p = min($total_pages, $rsmi_page + 2);
                                    
                                    if ($start_p > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRsmiPage(1)">1</a></li>';
                                        if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    for ($i = $start_p; $i <= $end_p; $i++) {
                                        echo '<li class="page-item ' . ($rsmi_page == $i ? 'active' : '') . '"><a class="page-link" href="#" onclick="changeRsmiPage(' . $i . ')">' . $i . '</a></li>';
                                    }
                                    
                                    if ($end_p < $total_pages) {
                                        if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRsmiPage(' . $total_pages . ')">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <li class="page-item <?php echo $rsmi_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRsmiPage(<?php echo $rsmi_page + 1; ?>)">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- RIS Section -->
            <div class="tab-pane fade <?php echo $active_tab == 'ris' ? 'show active' : ''; ?>" id="ris" role="tabpanel">
                <form id="risForm" action="print_file.php" method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="ris">
                    <input type="hidden" name="request_id" id="ris_request_id" value="">

                    <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="filterRisPreview()">
                            <i class="bi bi-funnel me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-primary px-4" id="risGenerateBtn">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Generate RIS
                        </button>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">Search RIS / Person</label>
                                    <input type="text" id="ris_search_input" class="form-control form-control-sm" placeholder="Search RIS no, person, or purpose..." value="<?php echo htmlspecialchars($_GET['ris_search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">From</label>
                                    <input type="date" id="ris_filter_start" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-2 col-6">
                                    <label class="form-label text-secondary fw-semibold small mb-1">To</label>
                                    <input type="date" id="ris_filter_end" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-5 col-12 d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="filterRisPreview()">
                                        <i class="bi bi-search me-1"></i>Search
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRisFilter()">Clear</button>
                                </div>
                            </div>
                            <?php if (!empty($_GET['ris_search']) || (isset($_GET['start_date']) && isset($_GET['end_date']))): ?>
                                <div class="mt-2 small text-muted">
                                    Filters applied: 
                                    <?php if (!empty($_GET['ris_search'])) echo '<strong>Search:</strong> "' . htmlspecialchars($_GET['ris_search']) . '" '; ?>
                                    <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])) echo '<strong>Range:</strong> ' . htmlspecialchars($start_date) . ' to ' . htmlspecialchars($end_date); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="6" class="text-primary fw-bold fs-5 py-3 ps-3">Select RIS to Print</th>
                                </tr>
                                <tr class="text-center align-middle text-uppercase small text-secondary">
                                    <th style="width: 50px;">SELECT</th>
                                    <th>DATE</th>
                                    <th>RIS NO.</th>
                                    <th>REQUESTED BY</th>
                                    <th>PURPOSE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ris_search = $_GET['ris_search'] ?? '';
                                $ris_page = isset($_GET['ris_page']) ? (int)$_GET['ris_page'] : 1;
                                $ris_items_per_page = 10;
                                $ris_offset = ($ris_page - 1) * $ris_items_per_page;

                                $ris_conditions = ["r.status IN ('Approved', 'Issued')", "r.request_date BETWEEN '$start_date' AND '$end_date'"];
                                if (!empty($ris_search)) {
                                    $search_esc = $conn->real_escape_string($ris_search);
                                    $ris_conditions[] = "(r.ris_no LIKE '%$search_esc%' OR r.requested_by LIKE '%$search_esc%' OR r.purpose LIKE '%$search_esc%')";
                                }
                                $ris_where = implode(' AND ', $ris_conditions);

                                $count_sql = "SELECT COUNT(*) as total FROM requests r WHERE $ris_where";
                                $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                $total_pages = ceil($total_items / $ris_items_per_page);

                                $sql = "SELECT
                                            r.id,
                                            r.request_date,
                                            r.ris_no,
                                            r.requested_by,
                                            r.purpose,
                                            r.status
                                        FROM requests r
                                        WHERE $ris_where
                                        ORDER BY r.request_date DESC, r.ris_no DESC
                                        LIMIT $ris_items_per_page OFFSET $ris_offset";

                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="radio" name="selected_request" value="<?php echo $row['id']; ?>" class="form-check-input ris-radio" required>
                                            </td>
                                            <td class="text-center"><?php echo date('M d, Y h:i A', strtotime($row['request_date'])); ?></td>
                                            <td class="text-center fw-bold"><?php echo htmlspecialchars($row['ris_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                            <td><?php echo htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $row['status'] == 'Issued' ? 'success' : 'primary'; ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No RIS requests found for this date range.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                            <div class="small text-muted">
                                Showing <?php echo $ris_offset + 1; ?> to <?php echo min($ris_offset + $ris_items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $ris_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRisPage(<?php echo $ris_page - 1; ?>)">Previous</a>
                                    </li>
                                    <?php
                                    $start_p = max(1, $ris_page - 2);
                                    $end_p = min($total_pages, $ris_page + 2);
                                    
                                    if ($start_p > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRisPage(1)">1</a></li>';
                                        if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    for ($i = $start_p; $i <= $end_p; $i++) {
                                        echo '<li class="page-item ' . ($ris_page == $i ? 'active' : '') . '"><a class="page-link" href="#" onclick="changeRisPage(' . $i . ')">' . $i . '</a></li>';
                                    }
                                    
                                    if ($end_p < $total_pages) {
                                        if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="#" onclick="changeRisPage(' . $total_pages . ')">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <li class="page-item <?php echo $ris_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="#" onclick="changeRisPage(<?php echo $ris_page + 1; ?>)">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                    <!-- RIS Settings Modal -->
                    <div class="modal fade report-modal" id="risSettingsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>RIS Report Settings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12"><h6 class="fw-bold text-primary mb-2">Form Information</h6></div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Form No.</label>
                                            <input type="text" name="form_no" class="form-control form-control-sm" placeholder="e.g. RIS-FMD-FM091">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Version No.</label>
                                            <input type="text" name="version_no" class="form-control form-control-sm" placeholder="e.g. 06">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-secondary fw-semibold small">Effectivity Date</label>
                                            <input type="text" name="effectivity_date" class="form-control form-control-sm" placeholder="e.g. October 15, 2024">
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Entity Name</label>
                                            <input type="text" name="entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Fund Cluster</label>
                                            <input type="text" name="fund_cluster" class="form-control" placeholder="e.g. 101">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Division</label>
                                            <input type="text" name="division" class="form-control" placeholder="Division">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Responsibility Center Code</label>
                                            <input type="text" name="responsibility_center" class="form-control" placeholder="Code">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Office</label>
                                            <input type="text" name="office" class="form-control" placeholder="Office">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">RIS No.</label>
                                            <input type="text" name="ris_no" class="form-control" id="modal_ris_no" readonly>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-secondary fw-semibold small">Purpose</label>
                                            <textarea name="purpose" class="form-control" rows="2" id="modal_purpose" placeholder="Purpose of request"></textarea>
                                        </div>
                                        <div class="col-12"><hr></div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Requested by</label>
                                            <input type="text" name="requested_by" class="form-control" id="modal_requested_by" placeholder="Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Approved by</label>
                                            <input type="text" name="approved_by" class="form-control" id="modal_approved_by" placeholder="Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Issued by</label>
                                            <input type="text" name="issued_by" class="form-control" id="modal_issued_by" placeholder="Name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-semibold small">Received by</label>
                                            <input type="text" name="received_by" class="form-control" id="modal_received_by" placeholder="Name">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="risModalGenerateBtn">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>Generate RIS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- System Section (Inventory Report) -->
            <div class="tab-pane fade <?php echo $active_tab == 'system' ? 'show active' : ''; ?>" id="system" role="tabpanel">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <button type="button" class="btn w-100 d-flex align-items-center justify-content-between px-2 py-2 report-type-option" onclick="toggleInventorySection('inventory')">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="report-type-icon"><i class="bi bi-arrow-left-right"></i></div>
                                        <div class="report-type-title">Inventory Transactions</div>
                                    </div>
                                    <i class="bi bi-chevron-down text-muted"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <button type="button" class="btn w-100 d-flex align-items-center justify-content-between px-2 py-2 report-type-option" onclick="toggleInventorySection('issued')">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="report-type-icon"><i class="bi bi-box-arrow-up-right"></i></div>
                                        <div class="report-type-title">Issued Items</div>
                                    </div>
                                    <i class="bi bi-chevron-down text-muted"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="inventoryTransactionsPanel" class="card border-0 bg-light mb-3 <?php echo $active_tab == 'system' ? '' : 'd-none'; ?>">
                    <div class="card-body">
                        <?php
                        // Calculate date range for inventory transactions at top level
                        $tx_period = $_GET['inv_period'] ?? 'daily';
                        $tx_type = $_GET['tx_type'] ?? '';
                        $tx_start = date('Y-m-d');
                        $tx_end = date('Y-m-d');

                        if ($tx_period === 'weekly') {
                            $tx_start = date('Y-m-d', strtotime('monday this week'));
                            $tx_end = date('Y-m-d', strtotime('sunday this week'));
                        } elseif ($tx_period === 'range' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
                            $tx_start = $_GET['start_date'];
                            $tx_end = $_GET['end_date'];
                        } elseif ($tx_period === 'annual') {
                            $tx_start = date('Y') . '-01-01';
                            $tx_end = date('Y') . '-12-31';
                        }
                        ?>
                        <div class="mb-3">
                            <form method="GET" id="inventoryTxFilterForm">
                                <input type="hidden" name="rt" value="inventory_system">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label text-secondary fw-semibold small">Period</label>
                                        <select name="inv_period" id="inv_period" class="form-select" onchange="this.form.submit()">
                                            <option value="daily" <?php echo $tx_period === 'daily' ? 'selected' : ''; ?>>Daily (today)</option>
                                            <option value="weekly" <?php echo $tx_period === 'weekly' ? 'selected' : ''; ?>>Per week</option>
                                            <option value="range" <?php echo $tx_period === 'range' ? 'selected' : ''; ?>>Date range</option>
                                            <option value="annual" <?php echo $tx_period === 'annual' ? 'selected' : ''; ?>>Annual (full year)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-secondary fw-semibold small">Item type</label>
                                        <select name="tx_type" id="tx_type" class="form-select" onchange="this.form.submit()">
                                            <option value="" <?php echo $tx_type === '' ? 'selected' : ''; ?>>All types</option>
                                            <option value="Expendable" <?php echo $tx_type === 'Expendable' ? 'selected' : ''; ?>>Expendable</option>
                                            <option value="Semi-Expendable" <?php echo $tx_type === 'Semi-Expendable' ? 'selected' : ''; ?>>Semi-Expendable</option>
                                            <option value="Non-Expendable" <?php echo $tx_type === 'Non-Expendable' ? 'selected' : ''; ?>>Non-Expendable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <?php
                                    if ($tx_period === 'daily') {
                                        echo 'Showing transactions for ' . date('M d, Y', strtotime($tx_start));
                                    } else {
                                        echo 'Showing transactions from ' . date('M d, Y', strtotime($tx_start)) . ' to ' . date('M d, Y', strtotime($tx_end));
                                    }
                                    echo ' for ' . (!empty($tx_type) ? htmlspecialchars($tx_type) : 'all types') . '.';
                                    ?>
                                </div>
                            </form>
                        </div>
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="printInventoryTransactions()">
                                <i class="bi bi-printer me-1"></i> Print inventory transactions
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr class="text-center text-uppercase small text-secondary">
                                        <th>Item</th><th>Description</th><th>Date</th><th>Item Type</th>
                                        <th class="text-end">In-stocks</th><th class="text-end">Quantity add</th><th class="text-end">End Balance</th><th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql_inventory_tx = "SELECT
                                            t.transaction_date,
                                            t.transaction_type,
                                            t.quantity,
                                            t.balance_after,
                                            t.remarks,
                                            i.item,
                                            i.description,
                                            i.item_type,
                                            i.stock_no,
                                            i.unit_measurement
                                        FROM inventory_transactions t
                                        JOIN items i ON t.item_id = i.id
                                        WHERE DATE(t.transaction_date) BETWEEN '$tx_start' AND '$tx_end'";

                                    if (!empty($tx_type)) {
                                        $type_esc = $conn->real_escape_string($tx_type);
                                        $sql_inventory_tx .= " AND i.item_type = '$type_esc'";
                                    }

                                    $sql_inventory_tx .= " ORDER BY t.transaction_date DESC LIMIT 100";
                                    $result_inventory_tx = $conn->query($sql_inventory_tx);

                                    if ($result_inventory_tx && $result_inventory_tx->num_rows > 0) {
                                        while ($tx = $result_inventory_tx->fetch_assoc()) {
                                            $qty = intval($tx['quantity']);
                                            $end_bal = intval($tx['balance_after']);
                                            $tx_type_lower = strtolower($tx['transaction_type']);
                                            $inbound_types = ['acquisition', 'approved', 'in'];
                                            $instocks_before = in_array($tx_type_lower, $inbound_types, true)
                                                ? $end_bal - $qty
                                                : $end_bal + $qty;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tx['item']); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($tx['description']); ?></small></td>
                                                <td class="text-center"><?php echo date('M d, Y h:i A', strtotime($tx['transaction_date'])); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($tx['item_type']); ?></td>
                                                <td class="text-end"><?php echo $instocks_before; ?></td>
                                                <td class="text-end fw-bold"><?php echo $qty; ?></td>
                                                <td class="text-end"><?php echo $end_bal; ?></td>
                                                <td><span class="fw-bold"><?php echo htmlspecialchars($tx['remarks'] ?? $tx['transaction_type']); ?></span></td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No inventory transactions found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="issuedItemsPanel" class="card border-0 bg-light mb-3 d-none">
                    <div class="card-body">
                        <div class="mb-3">
                            <form method="GET" id="issuedItemsFilterForm">
                                <input type="hidden" name="rt" value="inventory_system">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label text-secondary fw-semibold small">Period</label>
                                        <select name="inv_period_issued" id="inv_period_issued" class="form-select" onchange="this.form.submit()">
                                            <option value="daily" <?php echo $inv_period_issued === 'daily' ? 'selected' : ''; ?>>Daily (today)</option>
                                            <option value="weekly" <?php echo $inv_period_issued === 'weekly' ? 'selected' : ''; ?>>Per week</option>
                                            <option value="range" <?php echo $inv_period_issued === 'range' ? 'selected' : ''; ?>>Date range</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-secondary fw-semibold small">Item type</label>
                                        <select name="tx_type_issued" id="tx_type_issued" class="form-select" onchange="this.form.submit()">
                                            <option value="" <?php echo $tx_type_issued === '' ? 'selected' : ''; ?>>All types</option>
                                            <option value="Expendable" <?php echo $tx_type_issued === 'Expendable' ? 'selected' : ''; ?>>Expendable</option>
                                            <option value="Semi-Expendable" <?php echo $tx_type_issued === 'Semi-Expendable' ? 'selected' : ''; ?>>Semi-Expendable</option>
                                            <option value="Non-Expendable" <?php echo $tx_type_issued === 'Non-Expendable' ? 'selected' : ''; ?>>Non-Expendable</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="printIssuedItems()">
                                <i class="bi bi-printer me-1"></i> Print issued items
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr class="text-center text-uppercase small text-secondary">
                                        <th>Date</th><th>RIS No.</th><th>Stock No.</th><th>Item</th><th>Unit</th><th>Qty</th><th>Unit Cost</th><th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Use the date range calculated at top of file
                                    $sql_issued_items = "SELECT
                                            r.request_date,
                                            r.ris_no,
                                            i.stock_no,
                                            i.item,
                                            i.unit_measurement,
                                            COALESCE(ri.quantity_issued, ri.quantity_requested) AS quantity_issued,
                                            i.unit_value,
                                            (COALESCE(ri.quantity_issued, ri.quantity_requested) * i.unit_value) AS amount
                                        FROM request_items ri
                                        JOIN requests r ON ri.request_id = r.id
                                        JOIN items i ON ri.item_id = i.id
                                        WHERE r.status IN ('Issued','Approved')
                                        AND r.request_date BETWEEN '$inv_start_date' AND '$inv_end_date'";

                                    // Add item type filter
                                    if (!empty($tx_type_issued)) {
                                        $type_esc = $conn->real_escape_string($tx_type_issued);
                                        $sql_issued_items .= " AND i.item_type = '$type_esc'";
                                    }

                                    $sql_issued_items .= " ORDER BY r.request_date ASC, r.ris_no ASC";
                                    $result_issued_items = $conn->query($sql_issued_items);

                                    if ($result_issued_items && $result_issued_items->num_rows > 0) {
                                        while ($row = $result_issued_items->fetch_assoc()) {
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo date('M d, Y h:i A', strtotime($row['request_date'])); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($row['ris_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['item']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                                <td class="text-center fw-bold"><?php echo $row['quantity_issued']; ?></td>
                                                <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                                                <td class="text-end fw-bold"><?php echo number_format($row['amount'], 2); ?></td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No issued items found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<style>
    .report-type-option {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, 0.4);
        padding: 1rem 1.25rem;
        background-color: #f8fafc;
        transition: all 0.15s ease-in-out;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .report-type-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(59,130,246,0.02));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        font-size: 1.25rem;
    }
    .report-type-option .report-type-title {
        font-weight: 600;
        font-size: 0.95rem;
    }
    .report-type-option .report-type-subtitle {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .report-type-option:hover,
    .report-type-option:focus {
        background-color: #ffffff;
        border-color: rgba(13,110,253,0.4);
        box-shadow: 0 0.5rem 1rem rgba(13,110,253,0.08);
        transform: translateY(-1px);
    }
    .report-type-option.active {
        border-color: #0d6efd;
        background: linear-gradient(135deg, rgba(13,110,253,0.05), #ffffff);
        box-shadow: 0 0.5rem 1.2rem rgba(13,110,253,0.15);
    }
    .report-type-option:focus-visible {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
    }
    .report-modal .modal-dialog {
        margin-top: 4.5rem;
    }
</style>

<script>
    function toggleReportSection(id) {
        const section = document.getElementById(id);
        const icon = document.getElementById('icon-' + id);
        if (section.style.display === 'none') {
            section.style.display = 'block';
            icon.classList.remove('bi-chevron-right');
            icon.classList.add('bi-chevron-down');
        } else {
            section.style.display = 'none';
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-right');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const reportTypeButtons = document.querySelectorAll('.report-type-option[data-report-type]');
        const tabPanes = document.querySelectorAll('#reportTabsContent .tab-pane');
        const step1 = document.getElementById('step1ReportType');
        const step2 = document.getElementById('step2Items');
        const step2Loading = document.getElementById('step2Loading');
        const step2Content = document.getElementById('step2Content');
        const btnChangeType = document.getElementById('btnChangeType');
        const selectedReportLabel = document.getElementById('selectedReportLabel');

        const reportLabels = {
            rpci: 'Inventory Card (RPCI)',
            rpcppe: 'Property, Plant and Equipment (RPCPPE)',
            rsmi: 'Supplies and Materials Issued (RSMI)',
            ris: 'Requisition and Issue Slip (RIS)',
            sc: 'Stock Card (SC)',
            slc: 'Supplies Ledger Card (SLC)',
            ics: 'Inventory Custodian Slip (ICS)',
            iirup: 'IIRUP',
            par: 'Property Acknowledgment Receipt (PAR)',
            pc: 'Property Card (PC)',
            ppelc: 'PPE Ledger Card (PPELC)',
            ptr: 'Property Transfer Report (PTR)',
            rlsddp: 'RLSDDP',
            inventory_system: 'Inventory Report'
        };

        let selectedType = null;
        let selectedSection = null;

        function applyItemTypeFilters() {
            // Filter for RPCPPE pane (PC, IIRUP, PPELC - semi/non-expendable only)
            const rpcppePane = document.getElementById('rpcppe');
            if (rpcppePane) {
                const selectAll = document.getElementById('selectAll_rpcppe');
                const rows = rpcppePane.querySelectorAll('tbody tr[data-item-type]');
                const isPcOrIirupOrPpelc = selectedType === 'pc' || selectedType === 'iirup' || selectedType === 'ppelc';

                if (selectAll) {
                    // Enable select all for all reports in this section
                    selectAll.disabled = false;
                }

                rows.forEach(function(row) {
                    const itemType = (row.getAttribute('data-item-type') || '').toLowerCase();
                    const shouldHide = isPcOrIirupOrPpelc && itemType === 'expendable';
                    row.classList.toggle('d-none', shouldHide);

                    if (shouldHide) {
                        const cb = row.querySelector('input.item-checkbox-rpcppe');
                        if (cb) cb.checked = false;
                    }
                });
            }

            // Filter for RPCI pane (RPCI, ICS, SC - semi/non-expendable only; SLC - expendable only)
            const rpciPane = document.getElementById('rpci');
            if (rpciPane) {
                const selectAll = document.getElementById('selectAll_rpci');
                const rows = rpciPane.querySelectorAll('tbody tr[data-item-type]');
                const isSlc = selectedType === 'slc';
                const isIcsOrScOrRpci = selectedType === 'ics' || selectedType === 'sc' || selectedType === 'rpci';

                if (selectAll) {
                    // Enable select all for all reports in this section
                    selectAll.disabled = false;
                }

                rows.forEach(function(row) {
                    const itemType = (row.getAttribute('data-item-type') || '').toLowerCase();
                    const shouldHideForSlc = isSlc && itemType !== 'expendable';
                    const shouldHideForIcs = isIcsOrScOrRpci && itemType === 'expendable';
                    const shouldHide = shouldHideForSlc || shouldHideForIcs;
                    
                    row.classList.toggle('d-none', shouldHide);

                    if (shouldHide) {
                        const cb = row.querySelector('input.item-checkbox-rpci');
                        if (cb) cb.checked = false;
                    }
                });
            }
        }

        function applyRpcppeItemTypeFilters() {
            applyItemTypeFilters();
        }

        function showTab(type) {
            tabPanes.forEach(function(pane) {
                if (pane.id === type) {
                    pane.classList.add('show', 'active');
                } else {
                    pane.classList.remove('show', 'active');
                }
            });
        }

        function updateReportTypeFields() {
            if (!selectedType) {
                return;
            }
            if (selectedSection === 'rpci') {
                const input = document.querySelector('#rpciForm input[name="report_type"]');
                if (input) {
                    input.value = selectedType;
                }
            } else if (selectedSection === 'rpcppe') {
                const input = document.querySelector('#rpcppeForm input[name="report_type"]');
                if (input) {
                    input.value = selectedType;
                }
            } else if (selectedSection === 'rsmi') {
                const input = document.querySelector('#rsmiForm input[name="report_type"]');
                if (input) {
                    input.value = selectedType;
                }
            }
        }

        function goToItems() {
            if (!selectedType) {
                return;
            }

            updateReportTypeFields();

            if (selectedReportLabel) {
                selectedReportLabel.textContent = reportLabels[selectedType] || '';
            }

            if (step1 && step2 && step2Loading && step2Content) {
                step1.classList.add('d-none');
                step2.classList.remove('d-none');
                step2Loading.classList.remove('d-none');
                step2Content.classList.add('d-none');

                setTimeout(function() {
                    step2Loading.classList.add('d-none');
                step2Content.classList.remove('d-none');
                    showTab(selectedSection);
                    applyRpcppeItemTypeFilters();
                }, 400);
            } else {
                showTab(selectedType);
                applyRpcppeItemTypeFilters();
            }
        }

        reportTypeButtons.forEach(function(button) {
            button.setAttribute('aria-pressed', 'false');
            button.addEventListener('click', function() {
                reportTypeButtons.forEach(function(other) {
                    other.classList.remove('active');
                    other.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');
                selectedType = this.getAttribute('data-report-type');
                selectedSection = this.getAttribute('data-section') || selectedType;
                goToItems();
            });
        });

        // Property Card (PC): only semi/non-expendable, single-item selection, and auto-fill header fields
        const rpcppePane = document.getElementById('rpcppe');
        if (rpcppePane) {
            rpcppePane.addEventListener('change', function(e) {
                const cb = e.target && e.target.classList && e.target.classList.contains('item-checkbox-rpcppe')
                    ? e.target
                    : null;
                if (!cb) return;

                if (selectedType === 'pc' && cb.checked) {
                    rpcppePane.querySelectorAll('input.item-checkbox-rpcppe').forEach(function(other) {
                        if (other !== cb) other.checked = false;
                    });

                    const base = cb.name ? cb.name.replace(/\\[selected\\]$/, '') : '';
                    if (base) {
                        const stockNo = rpcppePane.querySelector(`input[name=\"${base}[stock_no]\"]`)?.value || '';
                        const item = rpcppePane.querySelector(`input[name=\"${base}[item]\"]`)?.value || '';
                        const desc = rpcppePane.querySelector(`input[name=\"${base}[description]\"]`)?.value || '';
                        const modalEl = document.getElementById('rpcppeSettingsModal');
                        if (modalEl) {
                            const pcProp = modalEl.querySelector('input[name=\"pc_property_number\"]');
                            const pcDesc = modalEl.querySelector('input[name=\"pc_description\"]');
                            if (pcProp && stockNo) pcProp.value = stockNo;
                            if (pcDesc) pcDesc.value = [item, desc].filter(Boolean).join(' - ');
                        }
                    }
                }
            });
        }

        if (btnChangeType) {
            btnChangeType.addEventListener('click', function() {
                selectedType = null;
                if (step1 && step2) {
                    step2.classList.add('d-none');
                    step1.classList.remove('d-none');
                }
                if (step2Loading && step2Content) {
                    step2Loading.classList.add('d-none');
                    step2Content.classList.add('d-none');
                }
                reportTypeButtons.forEach(function(button) {
                    button.classList.remove('active');
                    button.setAttribute('aria-pressed', 'false');
                });
                if (selectedReportLabel) {
                    selectedReportLabel.textContent = '';
                }
            });
        }

        const rpciGenerateBtn = document.getElementById('rpciGenerateBtn');
        const rpciModalGenerateBtn = document.getElementById('rpciModalGenerateBtn');
        const rpcppeGenerateBtn = document.getElementById('rpcppeGenerateBtn');
        const rpcppeModalGenerateBtn = document.getElementById('rpcppeModalGenerateBtn');
        const rsmiGenerateBtn = document.getElementById('rsmiGenerateBtn');
        const rsmiModalGenerateBtn = document.getElementById('rsmiModalGenerateBtn');

        if (rpciGenerateBtn && rpciModalGenerateBtn) {
            rpciGenerateBtn.addEventListener('click', function() {
                const modalEl = document.getElementById('rpciSettingsModal');
                if (modalEl) {
                    const titleEl = modalEl.querySelector('.modal-title');
                    if (titleEl) {
                        if (selectedType === 'sc') {
                            titleEl.textContent = 'Stock Card Settings';
                        } else if (selectedType === 'slc') {
                            titleEl.textContent = 'Supplies Ledger Card Settings';
                        } else if (selectedType === 'ics') {
                            titleEl.textContent = 'ICS Report Settings';
                        } else {
                            titleEl.textContent = 'RPCI Report Settings';
                        }
                    }
                    // Update report_type hidden field
                    const reportTypeField = document.querySelector('#rpciForm input[name="report_type"]');
                    if (reportTypeField) {
                        if (selectedType === 'sc' || selectedType === 'slc' || selectedType === 'ics') {
                            reportTypeField.value = selectedType;
                        } else {
                            reportTypeField.value = 'rpci';
                        }
                    }
                    const entityFields = modalEl.querySelectorAll('.entity-settings-only');
                    entityFields.forEach(function(el) {
                        if (selectedType === 'sc' || selectedType === 'slc' || selectedType === 'ics') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const ledgerFields = modalEl.querySelectorAll('.ledger-settings-only');
                    ledgerFields.forEach(function(el) {
                        if (selectedType === 'sc' || selectedType === 'slc') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const scFields = modalEl.querySelectorAll('.sc-settings-only');
                    scFields.forEach(function(el) {
                        if (selectedType === 'sc') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const accountabilityFields = modalEl.querySelectorAll('.accountability-fields-only');
                    accountabilityFields.forEach(function(el) {
                        if (selectedType === 'slc' || selectedType === 'ics') {
                            el.classList.add('d-none');
                        } else {
                            el.classList.remove('d-none');
                        }
                    });
                    const icsFields = modalEl.querySelectorAll('.ics-settings-only');
                    icsFields.forEach(function(el) {
                        if (selectedType === 'ics') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const certificationFields = modalEl.querySelectorAll('.rpci-certification-only');
                    certificationFields.forEach(function(el) {
                        if (selectedType === 'rpci') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
            rpciModalGenerateBtn.addEventListener('click', function() {
                const form = document.getElementById('rpciForm');
                if (form) {
                    form.submit();
                }
            });
        }

        if (rpcppeGenerateBtn && rpcppeModalGenerateBtn) {
            rpcppeGenerateBtn.addEventListener('click', function() {
                const modalEl = document.getElementById('rpcppeSettingsModal');
                if (modalEl) {
                    const titleEl = modalEl.querySelector('.modal-title');
                    if (titleEl) {
                        if (selectedType === 'iirup') {
                            titleEl.textContent = 'IIRUP Settings';
                        } else if (selectedType === 'par') {
                            titleEl.textContent = 'PAR Report Settings';
                        } else if (selectedType === 'pc') {
                            titleEl.textContent = 'PC Report Settings';
                        } else if (selectedType === 'ppelc') {
                            titleEl.textContent = 'PPELC Report Settings';
                        } else if (selectedType === 'ptr') {
                            titleEl.textContent = 'PTR Report Settings';
                        } else if (selectedType === 'rlsddp') {
                            titleEl.textContent = 'RLSDDP Report Settings';
                        } else {
                            titleEl.textContent = 'RPCPPE Report Settings';
                        }
                    }
                    // Update report_type hidden field
                    const reportTypeField = document.querySelector('#rpcppeForm input[name="report_type"]');
                    if (reportTypeField) {
                        if (selectedType === 'iirup' || selectedType === 'par' || selectedType === 'pc' || selectedType === 'ppelc' || selectedType === 'ptr' || selectedType === 'rlsddp') {
                            reportTypeField.value = selectedType;
                        } else {
                            reportTypeField.value = 'rpcppe';
                        }
                    }
                    const rpcppeMainFields = modalEl.querySelectorAll('.rpcppe-main-only');
                    rpcppeMainFields.forEach(function(el) {
                        if (selectedType === 'rpcppe' || selectedType === 'rlsddp') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const iirupFields = modalEl.querySelectorAll('.iirup-settings-only');
                    iirupFields.forEach(function(el) {
                        if (selectedType === 'iirup') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const parFields = modalEl.querySelectorAll('.par-settings-only');
                    parFields.forEach(function(el) {
                        if (selectedType === 'par') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const pcFields = modalEl.querySelectorAll('.pc-settings-only');
                    pcFields.forEach(function(el) {
                        if (selectedType === 'pc') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const ppelcFields = modalEl.querySelectorAll('.ppelc-settings-only');
                    ppelcFields.forEach(function(el) {
                        if (selectedType === 'ppelc') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const ptrFields = modalEl.querySelectorAll('.ptr-settings-only');
                    ptrFields.forEach(function(el) {
                        if (selectedType === 'ptr') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const rlsddpFields = modalEl.querySelectorAll('.rlsddp-settings-only');
                    rlsddpFields.forEach(function(el) {
                        if (selectedType === 'rlsddp') {
                            el.classList.remove('d-none');
                        } else {
                            el.classList.add('d-none');
                        }
                    });
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
            rpcppeModalGenerateBtn.addEventListener('click', function() {
                const form = document.getElementById('rpcppeForm');
                if (form) {
                    form.submit();
                }
            });
        }

        if (rsmiGenerateBtn && rsmiModalGenerateBtn) {
            rsmiGenerateBtn.addEventListener('click', function() {
                const modalEl = document.getElementById('rsmiSettingsModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
            rsmiModalGenerateBtn.addEventListener('click', function() {
                const form = document.getElementById('rsmiForm');
                if (form) {
                    form.submit();
                }
            });
        }

        // RIS Generate Buttons
        const risGenerateBtn = document.getElementById('risGenerateBtn');
        const risGenerateBtnSmall = document.getElementById('risGenerateBtnSmall');
        const risRadios = document.querySelectorAll('.ris-radio');
        const risRequestId = document.getElementById('ris_request_id');
        const risModalGenerateBtn = document.getElementById('risModalGenerateBtn');

        // Update hidden request_id when radio button changes
        risRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (risRequestId) {
                    risRequestId.value = this.value;
                }
            });
        });

        function showRisModal() {
            const selectedRadio = document.querySelector('input[name="selected_request"]:checked');
            if (!selectedRadio) {
                alert('Please select an RIS request to print.');
                return;
            }

            // Get the row data
            const row = selectedRadio.closest('tr');
            const risNo = row.querySelector('td:nth-child(3)').textContent.trim();
            const purpose = row.querySelector('td:nth-child(5)').textContent.trim();
            const requestedBy = row.querySelector('td:nth-child(4)').textContent.trim();

            // Populate modal fields
            document.getElementById('modal_ris_no').value = risNo;
            document.getElementById('modal_purpose').value = purpose !== 'N/A' ? purpose : '';
            document.getElementById('modal_requested_by').value = requestedBy;

            // Ensure request_id is set
            if (risRequestId) {
                risRequestId.value = selectedRadio.value;
            }

            // Show modal
            const modalEl = document.getElementById('risSettingsModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        function handleRisGenerate() {
            const form = document.getElementById('risForm');
            if (form) {
                form.submit();
            }
        }

        if (risGenerateBtn) {
            risGenerateBtn.addEventListener('click', showRisModal);
        }
        if (risGenerateBtnSmall) {
            risGenerateBtnSmall.addEventListener('click', showRisModal);
        }
        if (risModalGenerateBtn) {
            risModalGenerateBtn.addEventListener('click', handleRisGenerate);
        }

        // Check URL parameters on page load for inventory_system
        const urlParams = new URLSearchParams(window.location.search);
        const rtParam = urlParams.get('rt');
        if (rtParam === 'inventory_system') {
            selectedType = 'inventory_system';
            selectedSection = 'system';
            // Mark the inventory_system button as active
            const invButton = document.querySelector('.report-type-option[data-report-type="inventory_system"]');
            if (invButton) {
                invButton.classList.add('active');
                invButton.setAttribute('aria-pressed', 'true');
            }
            goToItems();
        } else if (rtParam === 'rpci') {
            selectedType = 'rpci';
            selectedSection = 'rpci';
            const btn = document.querySelector('.report-type-option[data-report-type="rpci"]');
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
            }
            goToItems();
        } else if (rtParam === 'rpcppe') {
            selectedType = 'rpcppe';
            selectedSection = 'rpcppe';
            const btn = document.querySelector('.report-type-option[data-report-type="rpcppe"]');
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
            }
            goToItems();
        } else if (rtParam === 'rsmi') {
            selectedType = 'rsmi';
            selectedSection = 'rsmi';
            const btn = document.querySelector('.report-type-option[data-report-type="rsmi"]');
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
            }
            goToItems();
        } else if (rtParam === 'ris') {
            selectedType = 'ris';
            selectedSection = 'ris';
            const btn = document.querySelector('.report-type-option[data-report-type="ris"]');
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
            }
            goToItems();
        }

        // Add Enter key listener for search input
        const searchInput = document.getElementById('rpci_search_input');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyRpciFilter();
                }
            });
        }

        const rpcppeSearchInput = document.getElementById('rpcppe_search_input');
        if (rpcppeSearchInput) {
            rpcppeSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyRpcppeFilter();
                }
            });
        }

        const rsmiSearchInput = document.getElementById('rsmi_search_input');
        if (rsmiSearchInput) {
            rsmiSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyRsmiFilter();
                }
            });
        }

        const risSearchInput = document.getElementById('ris_search_input');
        if (risSearchInput) {
            risSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterRisPreview();
                }
            });
        }
    });
</script>

<script>
    function filterPreview() {
        const startDate = document.getElementById('start_date_input').value;
        const endDate = document.getElementById('end_date_input').value;
        window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
    }

    function filterRisPreview() {
        const startDate = document.getElementById('ris_filter_start')?.value || '';
        const endDate = document.getElementById('ris_filter_end')?.value || '';
        const search = document.getElementById('ris_search_input')?.value || '';
        
        let url = 'reports.php?rt=ris';
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        if (search) url += `&ris_search=${encodeURIComponent(search)}`;
        
        window.location.href = url;
    }

    function applyRpciFilter() {
        const startDate = document.getElementById('rpci_start_date_input')?.value || '';
        const endDate = document.getElementById('rpci_end_date_input')?.value || '';
        const search = document.getElementById('rpci_search_input')?.value || '';
        const type = document.getElementById('rpci_type_input')?.value || '';
        
        let url = 'reports.php?rt=rpci';
        if (startDate) url += `&rpci_start_date=${startDate}`;
        if (endDate) url += `&rpci_end_date=${endDate}`;
        if (search) url += `&rpci_search=${encodeURIComponent(search)}`;
        if (type) url += `&rpci_type=${encodeURIComponent(type)}`;
        
        window.location.href = url;
    }

    function changeRpciPage(page) {
        const startDate = document.getElementById('rpci_start_date_input')?.value || '';
        const endDate = document.getElementById('rpci_end_date_input')?.value || '';
        const search = document.getElementById('rpci_search_input')?.value || '';
        const type = document.getElementById('rpci_type_input')?.value || '';
        
        let url = 'reports.php?rt=rpci&page=' + page;
        if (startDate) url += `&rpci_start_date=${startDate}`;
        if (endDate) url += `&rpci_end_date=${endDate}`;
        if (search) url += `&rpci_search=${encodeURIComponent(search)}`;
        if (type) url += `&rpci_type=${encodeURIComponent(type)}`;
        
        window.location.href = url;
    }

    function applyRpcppeFilter() {
        const search = document.getElementById('rpcppe_search_input')?.value || '';
        
        let url = 'reports.php?rt=rpcppe';
        if (search) url += `&rpcppe_search=${encodeURIComponent(search)}`;
        
        window.location.href = url;
    }

    function changeRpcppePage(page) {
        const search = document.getElementById('rpcppe_search_input')?.value || '';
        
        let url = 'reports.php?rt=rpcppe&rpcppe_page=' + page;
        if (search) url += `&rpcppe_search=${encodeURIComponent(search)}`;
        
        window.location.href = url;
    }

    function clearRpcppeFilter() {
        window.location.href = 'reports.php?rt=rpcppe';
    }

    function applyRsmiFilter() {
        const search = document.getElementById('rsmi_search_input')?.value || '';
        const startDate = document.getElementById('rsmi_start_date_input')?.value || '';
        const endDate = document.getElementById('rsmi_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rsmi';
        if (search) url += `&rsmi_search=${encodeURIComponent(search)}`;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        
        window.location.href = url;
    }

    function changeRsmiPage(page) {
        const search = document.getElementById('rsmi_search_input')?.value || '';
        const startDate = document.getElementById('rsmi_start_date_input')?.value || '';
        const endDate = document.getElementById('rsmi_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rsmi&rsmi_page=' + page;
        if (search) url += `&rsmi_search=${encodeURIComponent(search)}`;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        
        window.location.href = url;
    }

    function clearRsmiFilter() {
        window.location.href = 'reports.php?rt=rsmi';
    }

    function changeRisPage(page) {
        const startDate = document.getElementById('ris_filter_start')?.value || '';
        const endDate = document.getElementById('ris_filter_end')?.value || '';
        const search = document.getElementById('ris_search_input')?.value || '';
        
        let url = 'reports.php?rt=ris&ris_page=' + page;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        if (search) url += `&ris_search=${encodeURIComponent(search)}`;
        
        window.location.href = url;
    }

    function clearRisFilter() {
        window.location.href = 'reports.php?rt=ris';
    }

    function clearRpciFilter() {
        window.location.href = 'reports.php?rt=rpci';
    }

    function printIssuedItems() {
        const startDate = document.getElementById('start_date_input')?.value || '';
        const endDate = document.getElementById('end_date_input')?.value || '';
        const txType = document.getElementById('tx_type_issued')?.value || '';
        window.open(`print_file.php?rt=issued_items&start_date=${startDate}&end_date=${endDate}&tx_type=${txType}`, '_blank');
    }

    function printInventoryTransactions() {
        const startDate = document.getElementById('start_date_input')?.value || '';
        const endDate = document.getElementById('end_date_input')?.value || '';
        const txType = document.getElementById('tx_type')?.value || '';
        window.open(`print_file.php?rt=inventory_transactions&start_date=${startDate}&end_date=${endDate}&tx_type=${txType}`, '_blank');
    }

    function toggleInventorySection(section) {
        const inventoryPanel = document.getElementById('inventoryTransactionsPanel');
        const issuedPanel = document.getElementById('issuedItemsPanel');
        if (!inventoryPanel || !issuedPanel) return;

        if (section === 'inventory') {
            issuedPanel.classList.add('d-none');
            inventoryPanel.classList.toggle('d-none');
        } else if (section === 'issued') {
            inventoryPanel.classList.add('d-none');
            issuedPanel.classList.toggle('d-none');
        }
    }

    // Select All functionality for RPCI
    const selectAllRpci = document.getElementById('selectAll_rpci');
    const checkboxesRpci = document.querySelectorAll('.item-checkbox-rpci');

    selectAllRpci.addEventListener('change', function() {
        checkboxesRpci.forEach(cb => cb.checked = this.checked);
    });

    checkboxesRpci.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked) {
                selectAllRpci.checked = false;
            } else {
                const allChecked = Array.from(checkboxesRpci).every(c => c.checked);
                selectAllRpci.checked = allChecked;
            }
        });
    });

    // Select All functionality for RPCPPE
    const selectAllRpcppe = document.getElementById('selectAll_rpcppe');
    const checkboxesRpcppe = document.querySelectorAll('.item-checkbox-rpcppe');

    selectAllRpcppe.addEventListener('change', function() {
        checkboxesRpcppe.forEach(cb => cb.checked = this.checked);
    });

    checkboxesRpcppe.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked) {
                selectAllRpcppe.checked = false;
            } else {
                const allChecked = Array.from(checkboxesRpcppe).every(c => c.checked);
                selectAllRpcppe.checked = allChecked;
            }
        });
    });

    // Select All functionality for RSMI
    const selectAllRsmi = document.getElementById('selectAll_rsmi');
    const checkboxesRsmi = document.querySelectorAll('.item-checkbox-rsmi');

    selectAllRsmi.addEventListener('change', function() {
        checkboxesRsmi.forEach(cb => cb.checked = this.checked);
    });

    checkboxesRsmi.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!this.checked) {
                selectAllRsmi.checked = false;
            } else {
                const allChecked = Array.from(checkboxesRsmi).every(c => c.checked);
                selectAllRsmi.checked = allChecked;
            }
        });
    });
</script>

<?php include 'admin_footer.php'; ?>
