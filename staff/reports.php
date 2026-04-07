<?php
require_once 'staff_sidebar.php';
$page_title = 'Generate Report';
include '../plugins/conn.php';

// Default dates for RSMI filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Render the sidebar (staff_sidebar.php handles header and sidebar rendering)
include 'staff_navbar.php';
?>

<div class="lg:ml-[260px] pt-20 px-4 pb-8 min-h-screen bg-bg-light">
    <div class="max-w-[1600px] mx-auto">
        <div class="bg-white rounded-3xl shadow-sm border border-black/5 overflow-hidden">
            <div class="p-6 sm:p-8">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Report Generator</h1>
                    <p class="text-gray-500 text-sm mt-1">Select a report type to begin generating documentation.</p>
                </div>

                <!-- Step 1: Report Type Selection -->
                <div id="step1ReportType" class="space-y-6">
                    <div class="flex items-center gap-3">
                        <span class="px-4 py-1.5 bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider rounded-full">
                            Step 1: Select Report Type
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <!-- RPCI -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="rpci" data-section="rpci">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-clipboard-data text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Inventory Card (RPCI)</h3>
                                <p class="text-xs text-gray-500 mt-1">Per-item inventory balance with physical count.</p>
                            </div>
                        </button>

                        <!-- RPCPPE -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="rpcppe" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-building text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Property, Plant and Equipment (RPCPPE)</h3>
                                <p class="text-xs text-gray-500 mt-1">Accountable PPE items with quantities and values.</p>
                            </div>
                        </button>

                        <!-- RSMI -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="rsmi" data-section="rsmi">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-box-arrow-up-right text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Supplies and Materials Issued (RSMI)</h3>
                                <p class="text-xs text-gray-500 mt-1">Issued supplies by date, RIS number and amount.</p>
                            </div>
                        </button>

                        <!-- SC -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="sc" data-section="rpci">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-journal-text text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Stock Card (SC)</h3>
                                <p class="text-xs text-gray-500 mt-1">Movement of stock items and balances.</p>
                            </div>
                        </button>

                        <!-- SLC -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="slc" data-section="rpci">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-card-checklist text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Supplies Ledger Card (SLC)</h3>
                                <p class="text-xs text-gray-500 mt-1">Receipt, issue and balance of supplies.</p>
                            </div>
                        </button>

                        <!-- ICS -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="ics" data-section="rpci">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-box-seam text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Inventory Custodian Slip (ICS)</h3>
                                <p class="text-xs text-gray-500 mt-1">Accountability for semi-expendable items.</p>
                            </div>
                        </button>

                        <!-- IIRUP -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="iirup" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-recycle text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">IIRUP</h3>
                                <p class="text-xs text-gray-500 mt-1">Unserviceable property items for disposal.</p>
                            </div>
                        </button>

                        <!-- PAR -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="par" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-file-earmark-text text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Property Acknowledgment Receipt (PAR)</h3>
                                <p class="text-xs text-gray-500 mt-1">Acknowledgment for issued PPE items.</p>
                            </div>
                        </button>

                        <!-- PC -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="pc" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-collection text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Property Card (PC)</h3>
                                <p class="text-xs text-gray-500 mt-1">Ledger of PPE by property number.</p>
                            </div>
                        </button>

                        <!-- PPELC -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="ppelc" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-grid-3x3-gap text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">PPE Ledger Card (PPELC)</h3>
                                <p class="text-xs text-gray-500 mt-1">Ledger for property, plant and equipment.</p>
                            </div>
                        </button>

                        <!-- PTR -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="ptr" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-arrow-left-right text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">Property Transfer Report (PTR)</h3>
                                <p class="text-xs text-gray-500 mt-1">Transfers of property between custodians.</p>
                            </div>
                        </button>

                        <!-- RLSDDP -->
                        <button type="button" class="report-type-option group relative flex items-center p-5 bg-white border border-gray-100 rounded-2xl text-left transition-all duration-300 hover:border-primary/30 hover:shadow-md hover:-translate-y-0.5" data-report-type="rlsddp" data-section="rpcppe">
                            <div class="w-12 h-12 flex items-center justify-center bg-primary/5 text-primary rounded-xl group-hover:bg-primary group-hover:text-white transition-colors duration-300 mr-4 shrink-0">
                                <i class="bi bi-exclamation-triangle text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 group-hover:text-primary transition-colors">RLSDDP</h3>
                                <p class="text-xs text-gray-500 mt-1">Lost, stolen, damaged or destroyed properties.</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Items and Filters -->
                <div id="step2Items" class="hidden">
                    <div id="step2Loading" class="text-center py-16 hidden">
                        <div class="inline-block w-8 h-8 border-4 border-primary/20 border-t-primary rounded-full animate-spin mb-4"></div>
                        <p class="text-gray-500 font-medium">Preparing items and filters...</p>
                    </div>

                    <div id="step2Content" class="hidden space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Item Selection & Filters</h2>
                                <p id="selectedReportLabel" class="text-primary font-medium text-sm mt-0.5"></p>
                            </div>
                            <button type="button" id="btnChangeType" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition-all active:scale-95 shrink-0">
                                <i class="bi bi-arrow-left"></i> Change Type
                            </button>
                        </div>

                        <!-- RPCI Section -->
                        <div id="rpciSection" class="report-items-section hidden animate-fadeIn">
                            <form id="rpciForm" action="print_file.php" method="POST" target="_blank">
                                <input type="hidden" name="report_type" value="rpci">
                                
                                <div class="flex justify-end mb-6">
                                    <button type="button" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rpciGenerateBtn">
                                        <i class="bi bi-file-earmark-pdf text-lg"></i>
                                        Generate Report
                                    </button>
                                </div>

                                <!-- RPCI Settings Modal -->
                                <div class="modal fade" id="rpciSettingsModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
                                            <div class="modal-header bg-gray-50/50 border-b border-gray-100 px-8 py-6">
                                                <h5 class="text-xl font-bold text-gray-800">RPCI Report Settings</h5>
                                                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg text-xl"></i>
                                                </button>
                                            </div>
                                            <div class="modal-body px-8 py-8">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 pb-6 border-b border-gray-100">
                                                    <div class="col-span-full">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider">Form Information</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Form No.</label>
                                                        <input type="text" name="form_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. RPCI-FMD-FM091">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Version No.</label>
                                                        <input type="text" name="version_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 06">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Effectivity Date</label>
                                                        <input type="text" name="effectivity_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. October 15, 2024">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Type of Inventory</label>
                                                        <select name="inventory_type" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                                            <option value="Inventory Item">Inventory Item</option>
                                                            <option value="Semi-Expendable Property">Semi-Expendable Property</option>
                                                            <option value="Property, Plant and Equipment">Property, Plant and Equipment</option>
                                                        </select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">As of Date</label>
                                                        <input type="date" name="as_of_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Fund Cluster</label>
                                                        <input type="text" name="fund_cluster" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 101">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Number of Pages</label>
                                                        <input type="text" name="num_pages" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. Two (2)" value="Two (2)">
                                                    </div>
                                                    <div class="col-span-full border-t border-gray-100 my-2 pt-6">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider mb-4">Accountability Details</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Accountable Officer</label>
                                                        <input type="text" name="accountable_officer" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. LORILYN M. MONTILIJAO" value="LORILYN M. MONTILIJAO">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Position Title</label>
                                                        <input type="text" name="position_title" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. Acting Administrative Officer I" value="Acting Administrative Officer I">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Accountability Assumed Date</label>
                                                        <input type="date" name="accountability_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="2013-06-17">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-gray-50/50 border-t border-gray-100 px-8 py-6 gap-3">
                                                <button type="button" class="px-6 py-2.5 text-gray-600 font-bold hover:bg-gray-100 rounded-xl transition-all" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="px-8 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rpciModalGenerateBtn">
                                                    Generate Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50/50 rounded-2xl p-4 mb-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">Search Item</label>
                                            <input type="text" id="rpci_search_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Search name or description..." value="<?php echo htmlspecialchars($_GET['rpci_search'] ?? ''); ?>">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">Filter Type</label>
                                            <?php
                                            $current_rt = $_GET['rt'] ?? 'rpci';
                                            if ($current_rt === 'ics'): ?>
                                                <select id="rpci_type_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" disabled>
                                                    <option value="Semi-Expendable" selected>Semi-Expendable Only</option>
                                                </select>
                                            <?php else: ?>
                                                <select id="rpci_type_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm">
                                                    <option value="">All Types</option>
                                                    <option value="Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Expendable' ? 'selected' : ''; ?>>Expendable</option>
                                                    <option value="Semi-Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Semi-Expendable' ? 'selected' : ''; ?>>Semi-Expendable</option>
                                                    <option value="Non-Expendable" <?php echo ($_GET['rpci_type'] ?? '') === 'Non-Expendable' ? 'selected' : ''; ?>>Non-Expendable</option>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">From (Transactions)</label>
                                            <input type="date" id="rpci_start_date_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" value="<?php echo htmlspecialchars($_GET['rpci_start_date'] ?? ''); ?>">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">To (Transactions)</label>
                                            <input type="date" id="rpci_end_date_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" value="<?php echo htmlspecialchars($_GET['rpci_end_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-span-full flex gap-3 mt-2">
                                            <button type="button" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light transition-all text-sm" onclick="applyRpciFilter()">
                                                <i class="bi bi-search mr-2"></i>Search
                                            </button>
                                            <button type="button" class="px-6 py-2.5 bg-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-300 transition-all text-sm" onclick="clearRpciFilter()">Clear</button>
                                        </div>
                                    </div>
                                    <?php if (!empty($_GET['rpci_search']) || !empty($_GET['rpci_type']) || !empty($_GET['rpci_start_date'])): ?>
                                        <div class="mt-3 text-xs text-gray-500 border-t border-gray-100 pt-3">
                                            Filters: 
                                            <?php if (!empty($_GET['rpci_search'])) echo '<span class="font-bold">Search:</span> "' . htmlspecialchars($_GET['rpci_search']) . '" '; ?>
                                            <?php if (!empty($_GET['rpci_type'])) echo '<span class="font-bold">Type:</span> ' . htmlspecialchars($_GET['rpci_type']) . ' '; ?>
                                            <?php if (!empty($_GET['rpci_start_date'])) echo '<span class="font-bold">Date Range:</span> ' . htmlspecialchars($_GET['rpci_start_date']) . ' to ' . htmlspecialchars($_GET['rpci_end_date'] ?? ''); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="overflow-x-auto rounded-2xl border border-gray-100">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50/50">
                                                <th class="px-4 py-4 text-center">
                                                    <input type="checkbox" id="selectAll_rpci" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                </th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Article / Item</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Stock No.</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Unit</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Unit Value</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center bg-blue-50/50">Balance</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center bg-green-50/50">Physical Count</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <?php
                                            $rpci_search = $_GET['rpci_search'] ?? '';
                                            $rpci_type = $_GET['rpci_type'] ?? '';
                                            $current_rt = $_GET['rt'] ?? 'rpci';
                                            $rpci_start = $_GET['rpci_start_date'] ?? '';
                                            $rpci_end = $_GET['rpci_end_date'] ?? '';
                                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                            $items_per_page = 10;
                                            $offset = ($page - 1) * $items_per_page;

                                            $conditions = ["i.status = 'Active'"];
                                            if ($rpci_start && $rpci_end) {
                                                $start_esc = $conn->real_escape_string($rpci_start);
                                                $end_esc = $conn->real_escape_string($rpci_end);
                                                $conditions[] = "DATE(t.transaction_date) BETWEEN '$start_esc' AND '$end_esc'";
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
                                            $where = implode(' AND ', $conditions);

                                            if ($rpci_start && $rpci_end) {
                                                $count_sql = "SELECT COUNT(DISTINCT i.id) as total FROM items i JOIN inventory_transactions t ON t.item_id = i.id WHERE $where";
                                            } else {
                                                $count_sql = "SELECT COUNT(*) as total FROM items i WHERE $where";
                                            }
                                            $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                            $total_pages = ceil($total_items / $items_per_page);

                                            if ($rpci_start && $rpci_end) {
                                                $sql = "SELECT DISTINCT i.* FROM items i JOIN inventory_transactions t ON t.item_id = i.id WHERE $where ORDER BY i.item ASC LIMIT $items_per_page OFFSET $offset";
                                            } else {
                                                $sql = "SELECT i.* FROM items i WHERE $where ORDER BY i.item ASC LIMIT $items_per_page OFFSET $offset";
                                            }
                                            
                                            $result = $conn->query($sql);
                                            if ($result->num_rows > 0) {
                                                while($row = $result->fetch_assoc()) {
                                                    ?>
                                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                                        <td class="px-4 py-4 text-center">
                                                            <input type="checkbox" name="items[<?php echo $row['id']; ?>][selected]" value="1" class="item-checkbox-rpci w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][description]" value="<?php echo htmlspecialchars($row['description']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][balance_qty]" value="<?php echo $row['balance_qty']; ?>">
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <span class="font-bold text-gray-800"><?php echo htmlspecialchars($row['item']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <span class="text-xs text-gray-500 line-clamp-2"><?php echo htmlspecialchars($row['description']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                                        <td class="px-4 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                                        <td class="px-4 py-4 text-sm font-bold text-gray-800 text-right">
                                                            <?php echo number_format($row['unit_value'], 2); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-center bg-blue-50/30">
                                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                                                                <?php echo $row['balance_qty']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 bg-green-50/30">
                                                            <input type="number" name="items[<?php echo $row['id']; ?>][physical_count]" class="w-20 mx-auto block px-2 py-1 bg-white border border-green-200 rounded-lg text-center font-bold text-green-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 transition-all sm:text-sm" value="<?php echo $row['balance_qty']; ?>">
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
                                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4 px-4">
                                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($page > 1): ?>
                                                <button type="button" onclick="changeRpciPage(<?php echo $page - 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-1">
                                                <?php
                                                $start_p = max(1, $page - 2);
                                                $end_p = min($total_pages, $page + 2);
                                                
                                                if ($start_p > 1) {
                                                    echo '<button type="button" onclick="changeRpciPage(1)" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">1</button>';
                                                    if ($start_p > 2) echo '<span class="text-gray-400">...</span>';
                                                }
                                                
                                                for ($i = $start_p; $i <= $end_p; $i++) {
                                                    $activeClass = ($page == $i) ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-gray-100 text-gray-600';
                                                    echo '<button type="button" onclick="changeRpciPage(' . $i . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all ' . $activeClass . '">' . $i . '</button>';
                                                }
                                                
                                                if ($end_p < $total_pages) {
                                                    if ($end_p < $total_pages - 1) echo '<span class="text-gray-400">...</span>';
                                                    echo '<button type="button" onclick="changeRpciPage(' . $total_pages . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">' . $total_pages . '</button>';
                                                }
                                                ?>
                                            </div>

                                            <?php if ($page < $total_pages): ?>
                                                <button type="button" onclick="changeRpciPage(<?php echo $page + 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- RPCPPE Section -->
                        <div id="rpcppeSection" class="report-items-section hidden animate-fadeIn">
                            <form id="rpcppeForm" action="print_file.php" method="POST" target="_blank">
                                <input type="hidden" name="report_type" value="rpcppe">
                                
                                <div class="flex justify-end mb-6">
                                    <button type="button" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rpcppeGenerateBtn">
                                        <i class="bi bi-file-earmark-pdf text-lg"></i>
                                        Generate Report
                                    </button>
                                </div>

                                <!-- RPCPPE Settings Modal -->
                                <div class="modal fade" id="rpcppeSettingsModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
                                            <div class="modal-header bg-gray-50/50 border-b border-gray-100 px-8 py-6">
                                                <h5 class="text-xl font-bold text-gray-800">RPCPPE Report Settings</h5>
                                                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg text-xl"></i>
                                                </button>
                                            </div>
                                            <div class="modal-body px-8 py-8">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 pb-6 border-b border-gray-100">
                                                    <div class="col-span-full">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider">Form Information</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Form No.</label>
                                                        <input type="text" name="form_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. RPCPPE-FMD-FM091">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Version No.</label>
                                                        <input type="text" name="version_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 06">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Effectivity Date</label>
                                                        <input type="text" name="effectivity_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. October 15, 2024">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Type of Property</label>
                                                        <select name="inventory_type" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                                            <option value="Property, Plant and Equipment">Property, Plant and Equipment</option>
                                                            <option value="Motor Vehicles">Motor Vehicles</option>
                                                            <option value="Land">Land</option>
                                                            <option value="Buildings">Buildings</option>
                                                            <option value="Other Property, Plant and Equipment">Other Property, Plant and Equipment</option>
                                                        </select>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">As of Date</label>
                                                        <input type="date" name="as_of_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Fund Cluster</label>
                                                        <input type="text" name="fund_cluster" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 101">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Number of Pages</label>
                                                        <input type="text" name="pages_count_str" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. Six (6)" value="Six (6)">
                                                    </div>
                                                    <div class="col-span-full border-t border-gray-100 my-2 pt-6">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider mb-4">Accountability Details</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Accountable Officer</label>
                                                        <input type="text" name="accountable_officer" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. LORILYN M. MONTILIJAO" value="LORILYN M. MONTILIJAO">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Position Title</label>
                                                        <input type="text" name="position_title" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. Administrative Officer I" value="Administrative Officer I">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Accountability Assumed Date</label>
                                                        <input type="date" name="accountability_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="2023-08-30">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-gray-50/50 border-t border-gray-100 px-8 py-6 gap-3">
                                                <button type="button" class="px-6 py-2.5 text-gray-600 font-bold hover:bg-gray-100 rounded-xl transition-all" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="px-8 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rpcppeModalGenerateBtn">
                                                    Generate Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50/50 rounded-2xl p-4 mb-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">Search Item</label>
                                            <input type="text" id="rpcppe_search_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Search name or description..." value="<?php echo htmlspecialchars($_GET['rpcppe_search'] ?? ''); ?>">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">Report Filter</label>
                                            <?php
                                            $current_rt = $_GET['rt'] ?? 'rpcppe';
                                            $rpcppe_type_label = 'Equipment Only (Non-Expendable)';
                                            if ($current_rt === 'par' || $current_rt === 'ptr' || $current_rt === 'pc' || $current_rt === 'ppelc' || $current_rt === 'iirup') {
                                                $rpcppe_type_label = 'Semi-Expendable & Non-Expendable';
                                            }
                                            ?>
                                            <select id="rpcppe_type_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" disabled>
                                                <option value="dynamic" selected><?php echo $rpcppe_type_label; ?></option>
                                            </select>
                                        </div>
                                        <div class="flex gap-3 mt-2">
                                            <button type="button" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light transition-all text-sm" onclick="applyRpcppeFilter()">
                                                <i class="bi bi-search mr-2"></i>Search
                                            </button>
                                            <button type="button" class="px-6 py-2.5 bg-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-300 transition-all text-sm" onclick="clearRpcppeFilter()">Clear</button>
                                        </div>
                                    </div>
                                    <?php if (!empty($_GET['rpcppe_search']) || !empty($_GET['rpcppe_type'])): ?>
                                        <div class="mt-3 text-xs text-gray-500 border-t border-gray-100 pt-3">
                                            Filters: 
                                            <?php if (!empty($_GET['rpcppe_search'])) echo '<span class="font-bold">Search:</span> "' . htmlspecialchars($_GET['rpcppe_search']) . '" '; ?>
                                            <?php if (!empty($_GET['rpcppe_type'])) echo '<span class="font-bold">Type:</span> ' . htmlspecialchars($_GET['rpcppe_type']) . ' '; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="overflow-x-auto rounded-2xl border border-gray-100">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50/50">
                                                <th class="px-4 py-4 text-center">
                                                    <input type="checkbox" id="selectAll_rpcppe" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                </th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Article</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Property No.</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Unit</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Unit Value</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center bg-blue-50/50">Qty (Card)</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center bg-green-50/50">Qty (Physical)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
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
                                            $rpcppe_total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                            $rpcppe_total_pages = ceil($rpcppe_total_items / $rpcppe_items_per_page);

                                            $sql = "SELECT * FROM items WHERE $rpcppe_where ORDER BY item ASC LIMIT $rpcppe_items_per_page OFFSET $rpcppe_offset";
                                            $result = $conn->query($sql);
                                            if ($result->num_rows > 0) {
                                                while($row = $result->fetch_assoc()) {
                                                    ?>
                                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                                        <td class="px-4 py-4 text-center">
                                                            <input type="checkbox" name="items[<?php echo $row['id']; ?>][selected]" value="1" class="item-checkbox-rpcppe w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][description]" value="<?php echo htmlspecialchars($row['description']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][balance_qty]" value="<?php echo $row['balance_qty']; ?>">
                                                            <input type="hidden" name="items[<?php echo $row['id']; ?>][date_acquired]" value="<?php echo $row['date_acquired']; ?>">
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <span class="font-bold text-gray-800"><?php echo htmlspecialchars($row['item']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <span class="text-xs text-gray-500 line-clamp-2"><?php echo htmlspecialchars($row['description']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                                        <td class="px-4 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                                        <td class="px-4 py-4 text-sm font-bold text-gray-800 text-right">
                                                            <?php echo number_format($row['unit_value'], 2); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-center bg-blue-50/30">
                                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                                                                <?php echo $row['balance_qty']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 bg-green-50/30">
                                                            <input type="number" name="items[<?php echo $row['id']; ?>][physical_count]" class="w-20 mx-auto block px-2 py-1 bg-white border border-green-200 rounded-lg text-center font-bold text-green-700 focus:ring-2 focus:ring-green-500/20 focus:border-green-500 transition-all sm:text-sm" value="<?php echo $row['balance_qty']; ?>">
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($rpcppe_total_pages > 1): ?>
                                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4 px-4">
                                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                            Showing <?php echo $rpcppe_offset + 1; ?> to <?php echo min($rpcppe_offset + $rpcppe_items_per_page, $rpcppe_total_items); ?> of <?php echo $rpcppe_total_items; ?> items
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($rpcppe_page > 1): ?>
                                                <button type="button" onclick="changeRpcppePage(<?php echo $rpcppe_page - 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-1">
                                                <?php
                                                $start_p = max(1, $rpcppe_page - 2);
                                                $end_p = min($rpcppe_total_pages, $rpcppe_page + 2);
                                                
                                                if ($start_p > 1) {
                                                    echo '<button type="button" onclick="changeRpcppePage(1)" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">1</button>';
                                                    if ($start_p > 2) echo '<span class="text-gray-400">...</span>';
                                                }
                                                
                                                for ($i = $start_p; $i <= $end_p; $i++) {
                                                    $activeClass = ($rpcppe_page == $i) ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-gray-100 text-gray-600';
                                                    echo '<button type="button" onclick="changeRpcppePage(' . $i . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all ' . $activeClass . '">' . $i . '</button>';
                                                }
                                                
                                                if ($end_p < $rpcppe_total_pages) {
                                                    if ($end_p < $rpcppe_total_pages - 1) echo '<span class="text-gray-400">...</span>';
                                                    echo '<button type="button" onclick="changeRpcppePage(' . $rpcppe_total_pages . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">' . $rpcppe_total_pages . '</button>';
                                                }
                                                ?>
                                            </div>

                                            <?php if ($rpcppe_page < $rpcppe_total_pages): ?>
                                                <button type="button" onclick="changeRpcppePage(<?php echo $rpcppe_page + 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- RSMI Section -->
                        <div id="rsmiSection" class="report-items-section hidden animate-fadeIn">
                            <form id="rsmiForm" action="print_file.php" method="POST" target="_blank">
                                <input type="hidden" name="report_type" value="rsmi">
                                
                                <div class="flex justify-end items-center mb-6 gap-3">
                                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 border border-primary text-primary font-bold rounded-xl hover:bg-primary/5 transition-all active:scale-95" onclick="filterPreview()">
                                        <i class="bi bi-funnel"></i>
                                        Filter Range
                                    </button>
                                    <button type="button" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rsmiGenerateBtn">
                                        <i class="bi bi-file-earmark-pdf text-lg"></i>
                                        Generate Report
                                    </button>
                                </div>

                                <!-- RSMI Settings Modal -->
                                <div class="modal fade" id="rsmiSettingsModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
                                            <div class="modal-header bg-gray-50/50 border-b border-gray-100 px-8 py-6">
                                                <h5 class="text-xl font-bold text-gray-800">RSMI Report Settings</h5>
                                                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal">
                                                    <i class="bi bi-x-lg text-xl"></i>
                                                </button>
                                            </div>
                                            <div class="modal-body px-8 py-8">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 pb-6 border-b border-gray-100">
                                                    <div class="col-span-full">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider">Form Information</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Form No.</label>
                                                        <input type="text" name="form_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. RSMI-FMD-FM091">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Version No.</label>
                                                        <input type="text" name="version_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 06">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Effectivity Date</label>
                                                        <input type="text" name="effectivity_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. October 15, 2024">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Start Date</label>
                                                        <input type="date" id="start_date_input" name="start_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo $start_date; ?>" required>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">End Date</label>
                                                        <input type="date" id="end_date_input" name="end_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo $end_date; ?>" required>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">RIS No. Series (Optional)</label>
                                                        <input type="text" name="ris_series" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 2023-10-001 to 050">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Fund Cluster</label>
                                                        <input type="text" name="fund_cluster" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. 101">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Serial No.</label>
                                                        <input type="text" name="serial_no" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo date('Y'); ?>-001">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Report Date</label>
                                                        <input type="date" name="report_date" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="<?php echo $end_date; ?>">
                                                    </div>
                                                    <div class="col-span-full space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Entity Name</label>
                                                        <input type="text" name="entity_name" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                                                    </div>
                                                    <div class="col-span-full border-t border-gray-100 my-2 pt-6">
                                                        <h6 class="text-sm font-bold text-primary uppercase tracking-wider mb-4">Certification Details</h6>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Certification Officer</label>
                                                        <input type="text" name="certification_officer" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. ROSELLE M. MISO">
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label class="text-sm font-bold text-gray-700 ml-1">Position Title</label>
                                                        <input type="text" name="certification_position" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="e.g. Accountant III">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-gray-50/50 border-t border-gray-100 px-8 py-6 gap-3">
                                                <button type="button" class="px-6 py-2.5 text-gray-600 font-bold hover:bg-gray-100 rounded-xl transition-all" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="px-8 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95" id="rsmiModalGenerateBtn">
                                                    Generate Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50/50 rounded-2xl p-4 mb-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">Search RIS / Item</label>
                                            <input type="text" id="rsmi_search_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Search RIS no or item..." value="<?php echo htmlspecialchars($_GET['rsmi_search'] ?? ''); ?>">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">From</label>
                                            <input type="date" id="rsmi_start_date_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider ml-1">To</label>
                                            <input type="date" id="rsmi_end_date_input" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                                        </div>
                                        <div class="flex gap-3 mt-2">
                                            <button type="button" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light transition-all text-sm" onclick="applyRsmiFilter()">
                                                <i class="bi bi-search mr-2"></i>Search
                                            </button>
                                            <button type="button" class="px-6 py-2.5 bg-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-300 transition-all text-sm" onclick="clearRsmiFilter()">Clear</button>
                                        </div>
                                    </div>
                                    <?php if (!empty($_GET['rsmi_search']) || (isset($_GET['start_date']) && isset($_GET['end_date']))): ?>
                                        <div class="mt-3 text-xs text-gray-500 border-t border-gray-100 pt-3">
                                            Filters: 
                                            <?php if (!empty($_GET['rsmi_search'])) echo '<span class="font-bold">Search:</span> "' . htmlspecialchars($_GET['rsmi_search']) . '" '; ?>
                                            <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])) echo '<span class="font-bold">Range:</span> ' . htmlspecialchars($start_date) . ' to ' . htmlspecialchars($end_date); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="overflow-x-auto rounded-2xl border border-gray-100">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-50/50">
                                                <th class="px-4 py-4 text-center">
                                                    <input type="checkbox" id="selectAll_rsmi" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                </th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Date Issued</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">RIS No.</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Stock No.</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Item</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Unit</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Qty Issued</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Unit Cost</th>
                                                <th class="px-4 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <?php
                                            $rsmi_search = $_GET['rsmi_search'] ?? '';
                                            $rsmi_page = isset($_GET['rsmi_page']) ? (int)$_GET['rsmi_page'] : 1;
                                            $rsmi_items_per_page = 10;
                                            $rsmi_offset = ($rsmi_page - 1) * $rsmi_items_per_page;

                                            $rsmi_conditions = ["r.status IN ('Issued','Approved')", "r.request_date BETWEEN '$start_date' AND '$end_date'"];
                                            if (!empty($rsmi_search)) {
                                                $search_esc = $conn->real_escape_string($rsmi_search);
                                                $rsmi_conditions[] = "(r.ris_no LIKE '%$search_esc%' OR i.item LIKE '%$search_esc%' OR i.description LIKE '%$search_esc%')";
                                            }
                                            $rsmi_where = implode(' AND ', $rsmi_conditions);

                                            $count_sql = "SELECT COUNT(*) as total FROM request_items ri JOIN requests r ON ri.request_id = r.id JOIN items i ON ri.item_id = i.id WHERE $rsmi_where";
                                            $rsmi_total_items = $conn->query($count_sql)->fetch_assoc()['total'];
                                            $rsmi_total_pages = ceil($rsmi_total_items / $rsmi_items_per_page);

                                            $sql = "SELECT 
                                                        r.request_date,
                                                        r.ris_no AS ris_no,
                                                        i.stock_no,
                                                        i.item,
                                                        i.unit_measurement,
                                                        COALESCE(ri.quantity_issued, ri.quantity_requested) AS quantity_issued,
                                                        i.unit_value,
                                                        (COALESCE(ri.quantity_issued, ri.quantity_requested) * i.unit_value) AS amount
                                                    FROM request_items ri
                                                    JOIN requests r ON ri.request_id = r.id
                                                    JOIN items i ON ri.item_id = i.id
                                                    WHERE $rsmi_where
                                                    ORDER BY r.request_date ASC, r.ris_no ASC
                                                    LIMIT $rsmi_items_per_page OFFSET $rsmi_offset";
                                            
                                            $result = $conn->query($sql);
                                            if ($result->num_rows > 0) {
                                                $counter = $rsmi_offset;
                                                while($row = $result->fetch_assoc()) {
                                                    $counter++;
                                                    ?>
                                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                                        <td class="px-4 py-4 text-center">
                                                            <input type="checkbox" name="items[<?php echo $counter; ?>][selected]" value="1" class="item-checkbox-rsmi w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20" checked>
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][date]" value="<?php echo $row['request_date']; ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][ris_no]" value="<?php echo htmlspecialchars($row['ris_no']); ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][qty]" value="<?php echo $row['quantity_issued']; ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][unit_value]" value="<?php echo $row['unit_value']; ?>">
                                                            <input type="hidden" name="items[<?php echo $counter; ?>][amount]" value="<?php echo $row['amount']; ?>">
                                                        </td>
                                                        <td class="px-4 py-4 text-center text-sm text-gray-600">
                                                            <?php echo date('M d, Y', strtotime($row['request_date'])); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-center font-bold text-primary">
                                                            <?php echo htmlspecialchars($row['ris_no']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-sm text-gray-600">
                                                            <?php echo htmlspecialchars($row['stock_no']); ?>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <span class="font-bold text-gray-800"><?php echo htmlspecialchars($row['item']); ?></span>
                                                        </td>
                                                        <td class="px-4 py-4 text-center text-sm text-gray-600">
                                                            <?php echo htmlspecialchars($row['unit_measurement']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-center">
                                                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">
                                                                <?php echo $row['quantity_issued']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 text-right text-sm text-gray-600">
                                                            <?php echo number_format($row['unit_value'], 2); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-right font-bold text-gray-800">
                                                            <?php echo number_format($row['amount'], 2); ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="9" class="px-4 py-16 text-center">
                                                        <div class="flex flex-col items-center gap-2">
                                                            <div class="w-16 h-16 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center">
                                                                <i class="bi bi-inbox text-3xl"></i>
                                                            </div>
                                                            <p class="text-gray-500 font-medium">No issued items found for this date range.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($rsmi_total_pages > 1): ?>
                                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4 px-4">
                                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                            Showing <?php echo $rsmi_offset + 1; ?> to <?php echo min($rsmi_offset + $rsmi_items_per_page, $rsmi_total_items); ?> of <?php echo $rsmi_total_items; ?> items
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($rsmi_page > 1): ?>
                                                <button type="button" onclick="changeRsmiPage(<?php echo $rsmi_page - 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-1">
                                                <?php
                                                $start_p = max(1, $rsmi_page - 2);
                                                $end_p = min($rsmi_total_pages, $rsmi_page + 2);
                                                
                                                if ($start_p > 1) {
                                                    echo '<button type="button" onclick="changeRsmiPage(1)" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">1</button>';
                                                    if ($start_p > 2) echo '<span class="text-gray-400">...</span>';
                                                }
                                                
                                                for ($i = $start_p; $i <= $end_p; $i++) {
                                                    $activeClass = ($rsmi_page == $i) ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-gray-100 text-gray-600';
                                                    echo '<button type="button" onclick="changeRsmiPage(' . $i . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all ' . $activeClass . '">' . $i . '</button>';
                                                }
                                                
                                                if ($end_p < $rsmi_total_pages) {
                                                    if ($end_p < $rsmi_total_pages - 1) echo '<span class="text-gray-400">...</span>';
                                                    echo '<button type="button" onclick="changeRsmiPage(' . $rsmi_total_pages . ')" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all hover:bg-gray-100 text-gray-600">' . $rsmi_total_pages . '</button>';
                                                }
                                                ?>
                                            </div>

                                            <?php if ($rsmi_page < $rsmi_total_pages): ?>
                                                <button type="button" onclick="changeRsmiPage(<?php echo $rsmi_page + 1; ?>)" class="p-2 text-gray-400 hover:text-primary transition-colors">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn {
        animation: fadeIn 0.3s ease-out forwards;
    }
    .report-type-option.active {
        border-color: #1a237e;
        background-color: rgba(26, 35, 126, 0.05);
        box-shadow: 0 4px 12px rgba(26, 35, 126, 0.1);
    }
</style>

<script>
    function applyRpciFilter() {
        const search = document.getElementById('rpci_search_input')?.value || '';
        const type = document.getElementById('rpci_type_input')?.value || '';
        const start = document.getElementById('rpci_start_date_input')?.value || '';
        const end = document.getElementById('rpci_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rpci';
        if (search) url += `&rpci_search=${encodeURIComponent(search)}`;
        if (type) url += `&rpci_type=${encodeURIComponent(type)}`;
        if (start) url += `&rpci_start_date=${start}`;
        if (end) url += `&rpci_end_date=${end}`;
        
        window.location.href = url;
    }

    function changeRpciPage(page) {
        const search = document.getElementById('rpci_search_input')?.value || '';
        const type = document.getElementById('rpci_type_input')?.value || '';
        const start = document.getElementById('rpci_start_date_input')?.value || '';
        const end = document.getElementById('rpci_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rpci&page=' + page;
        if (search) url += `&rpci_search=${encodeURIComponent(search)}`;
        if (type) url += `&rpci_type=${encodeURIComponent(type)}`;
        if (start) url += `&rpci_start_date=${start}`;
        if (end) url += `&rpci_end_date=${end}`;
        
        window.location.href = url;
    }

    function clearRpciFilter() {
        window.location.href = 'reports.php?rt=rpci';
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
        const start = document.getElementById('rsmi_start_date_input')?.value || '';
        const end = document.getElementById('rsmi_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rsmi';
        if (search) url += `&rsmi_search=${encodeURIComponent(search)}`;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
        
        window.location.href = url;
    }

    function changeRsmiPage(page) {
        const search = document.getElementById('rsmi_search_input')?.value || '';
        const start = document.getElementById('rsmi_start_date_input')?.value || '';
        const end = document.getElementById('rsmi_end_date_input')?.value || '';
        
        let url = 'reports.php?rt=rsmi&rsmi_page=' + page;
        if (search) url += `&rsmi_search=${encodeURIComponent(search)}`;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
        
        window.location.href = url;
    }

    function clearRsmiFilter() {
        window.location.href = 'reports.php?rt=rsmi';
    }

    function filterPreview() {
        const startDate = document.getElementById('rsmi_start_date_input').value;
        const endDate = document.getElementById('rsmi_end_date_input').value;
        window.location.href = `reports.php?rt=rsmi&start_date=${startDate}&end_date=${endDate}`;
    }

    // Checkbox selection logic
    function setupCheckboxGroup(selectAllId, checkboxClass) {
        const selectAll = document.getElementById(selectAllId);
        if (!selectAll) return;
        
        const checkboxes = document.querySelectorAll('.' + checkboxClass);

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (!this.checked) {
                    selectAll.checked = false;
                } else {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    selectAll.checked = allChecked;
                }
            });
        });
    }

    setupCheckboxGroup('selectAll_rpci', 'item-checkbox-rpci');
    setupCheckboxGroup('selectAll_rpcppe', 'item-checkbox-rpcppe');
    setupCheckboxGroup('selectAll_rsmi', 'item-checkbox-rsmi');
</script>

<!-- Format Selection Modal -->
<div class="modal fade" id="printSelectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl">
            <div class="modal-header bg-gray-50/50 border-b border-gray-100 px-8 py-6">
                <h5 class="text-xl font-bold text-gray-800">Select Report Format</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <div class="modal-body px-8 py-8 text-center">
                <p class="text-gray-600 mb-8">Please select the report format to generate for this item:</p>
                <div class="flex flex-col gap-3 max-w-[280px] mx-auto">
                    <button class="w-full py-3 px-6 border-2 border-primary/20 text-primary font-bold rounded-2xl hover:bg-primary hover:text-white hover:border-primary transition-all active:scale-95" onclick="submitPrint('rsmi')">
                        RSMI Report
                    </button>
                    <button class="w-full py-3 px-6 border-2 border-primary/20 text-primary font-bold rounded-2xl hover:bg-primary hover:text-white hover:border-primary transition-all active:scale-95" onclick="submitPrint('rpci')">
                        RPCI Report
                    </button>
                    <button class="w-full py-3 px-6 border-2 border-primary/20 text-primary font-bold rounded-2xl hover:bg-primary hover:text-white hover:border-primary transition-all active:scale-95" onclick="submitPrint('rpcppe')">
                        RPCPPE Report
                    </button>
                </div>
                <input type="hidden" id="selectedItemId">
            </div>
        </div>
    </div>
</div>

<script>
function openPrintModal(itemId) {
    document.getElementById('selectedItemId').value = itemId;
    var modal = new bootstrap.Modal(document.getElementById('printSelectionModal'));
    modal.show();
}

function submitPrint(reportType) {
    var itemId = document.getElementById('selectedItemId').value;
    var targetUrl = 'print_file.php';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = targetUrl;
    form.target = '_blank';

    var typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'report_type';
    typeInput.value = reportType;
    form.appendChild(typeInput);

    var sourceFormId = '';
    if (reportType === 'rpci') sourceFormId = 'rpciForm';
    else if (reportType === 'rpcppe') sourceFormId = 'rpcppeForm';
    else if (reportType === 'rsmi') sourceFormId = 'rsmiForm';

    if (sourceFormId) {
        var sourceForm = document.getElementById(sourceFormId);
        if (sourceForm) {
            var inputs = sourceForm.querySelectorAll('input:not([name^="items"]), select, textarea');
            inputs.forEach(function(input) {
                if (input.name && input.name !== 'report_type') {
                     var clone = input.cloneNode(true);
                     clone.value = input.value; 
                     if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) return;
                     form.appendChild(clone);
                }
            });
        }
    }

    var itemInputs = document.querySelectorAll('input[name^="items[' + itemId + ']"]');
    itemInputs.forEach(function(input) {
        var clone = input.cloneNode(true);
        clone.value = input.value;
        if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) return;
        form.appendChild(clone);
    });

    var selectedInput = document.createElement('input');
    selectedInput.type = 'hidden';
    selectedInput.name = 'items[' + itemId + '][selected]';
    selectedInput.value = '1';
    form.appendChild(selectedInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    var modalEl = document.getElementById('printSelectionModal');
    var modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
}

document.addEventListener('DOMContentLoaded', function() {
    const reportTypeButtons = document.querySelectorAll('.report-type-option[data-report-type]');
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
        sc: 'Stock Card (SC)',
        slc: 'Supplies Ledger Card (SLC)',
        ics: 'Inventory Custodian Slip (ICS)',
        iirup: 'IIRUP',
        par: 'Property Acknowledgment Receipt (PAR)',
        pc: 'Property Card (PC)',
        ppelc: 'PPE Ledger Card (PPELC)',
        ptr: 'Property Transfer Report (PTR)',
        rlsddp: 'RLSDDP'
    };

    let selectedType = null;
    let selectedSection = null;

    function updateReportTypeFields() {
        if (!selectedType) return;
        
        const formIds = ['rpciForm', 'rpcppeForm', 'rsmiForm'];
        formIds.forEach(id => {
            const input = document.querySelector(`#${id} input[name="report_type"]`);
            if (input) input.value = selectedType;
        });
    }

    function showSection(sectionKey) {
        document.querySelectorAll('.report-items-section').forEach(sec => sec.classList.add('hidden'));
        const target = document.getElementById(sectionKey + 'Section');
        if (target) target.classList.remove('hidden');
    }

    reportTypeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            selectedType = this.getAttribute('data-report-type');
            selectedSection = this.getAttribute('data-section');
            
            // UI Transition
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
            step2Loading.classList.remove('hidden');
            step2Content.classList.add('hidden');

            selectedReportLabel.textContent = reportLabels[selectedType] || selectedType.toUpperCase();
            updateReportTypeFields();

            setTimeout(() => {
                step2Loading.classList.add('hidden');
                step2Content.classList.remove('hidden');
                showSection(selectedSection);
            }, 600);
        });
    });

    btnChangeType.addEventListener('click', function() {
        step2.classList.add('hidden');
        step1.classList.remove('hidden');
    });

    // Check for report type parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const rtParam = urlParams.get('rt');
    if (rtParam) {
        const btn = document.querySelector(`.report-type-option[data-report-type="${rtParam}"]`);
        if (btn) {
            btn.click();
        }
    }

    // Enter key listeners for search inputs
    const rpciSearchInput = document.getElementById('rpci_search_input');
    if (rpciSearchInput) {
        rpciSearchInput.addEventListener('keypress', function(e) {
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
});

    // Modal Trigger Buttons
    document.getElementById('rpciGenerateBtn')?.addEventListener('click', () => {
        const modalEl = document.getElementById('rpciSettingsModal');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });
    document.getElementById('rpcppeGenerateBtn')?.addEventListener('click', () => {
        const modalEl = document.getElementById('rpcppeSettingsModal');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });
    document.getElementById('rsmiGenerateBtn')?.addEventListener('click', () => {
        const modalEl = document.getElementById('rsmiSettingsModal');
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    });

    // Form Submissions
    document.getElementById('rpciModalGenerateBtn')?.addEventListener('click', () => {
        document.getElementById('rpciForm').submit();
        const modalEl = document.getElementById('rpciSettingsModal');
        if (modalEl) {
            bootstrap.Modal.getInstance(modalEl).hide();
        }
    });
    document.getElementById('rpcppeModalGenerateBtn')?.addEventListener('click', () => {
        document.getElementById('rpcppeForm').submit();
        const modalEl = document.getElementById('rpcppeSettingsModal');
        if (modalEl) {
            bootstrap.Modal.getInstance(modalEl).hide();
        }
    });
    document.getElementById('rsmiModalGenerateBtn')?.addEventListener('click', () => {
        document.getElementById('rsmiForm').submit();
        const modalEl = document.getElementById('rsmiSettingsModal');
        if (modalEl) {
            bootstrap.Modal.getInstance(modalEl).hide();
        }
    });
});
</script>
</body>
</html>