<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit();
}
require_once '../plugins/conn.php';

$page_title = 'Requisition and Issue Slip (RIS)';

$user_id = $_SESSION['user_id'];

// Get user information
$user_query = "SELECT full_name, position, department FROM users WHERE id = ?";
$stmt_user = $conn->prepare($user_query);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user_info = $user_result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Generate RIS number in YY-MM-### format with monthly sequence tracking
    $current_date = date('Y-m-d');
    $year_month = date('y-m'); // YY-MM format
    
    // Get the last RIS number for the current month and year
    $seq_query = "SELECT ris_no FROM requests 
                  WHERE ris_no LIKE ? 
                  ORDER BY ris_no DESC LIMIT 1";
    $stmt_seq = $conn->prepare($seq_query);
    $search_pattern = $year_month . '-%'; // Search for YY-MM-% pattern
    $stmt_seq->bind_param("s", $search_pattern);
    $stmt_seq->execute();
    $result_seq = $stmt_seq->get_result();
    
    // Debug: Show what we're looking for and what we found
    error_log("DEBUG: Looking for RIS with pattern: " . $search_pattern);
    if ($result_seq->num_rows > 0) {
        $last_ris = $result_seq->fetch_assoc()['ris_no'];
        error_log("DEBUG: Found last RIS: " . $last_ris);
        
        // Extract the sequence number (last 3 digits)
        $last_sequence = intval(substr($last_ris, -3));
        $new_sequence = $last_sequence + 1;
        error_log("DEBUG: New sequence: " . $new_sequence);
    } else {
        $new_sequence = 1; // First RIS for this month
        error_log("DEBUG: No RIS found for month " . $year_month . ", starting with sequence 1");
    }
    
    // Format as 3-digit number with leading zeros
    $sequence = str_pad($new_sequence, 3, '0', STR_PAD_LEFT);
    $ris_no = $year_month . '-' . $sequence;
    
    // Get form data
    $entity_name = $_POST['entity_name'] ?? '';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $responsibility_center = $_POST['responsibility_center'] ?? '';
    $division = $_POST['division'] ?? '';
    $office = $_POST['office'] ?? '';
    $requisition_date = $_POST['requisition_date'] ?? date('Y-m-d');
    $purpose = $_POST['purpose'] ?? '';
    $priority = $_POST['priority'] ?? 'Normal';
    
    // Validate required fields
    if (empty($entity_name) || empty($purpose)) {
        $_SESSION['error'] = "Entity Name and Purpose are required fields";
    } else {
        // Insert main RIS record
        $insert_ris = "INSERT INTO requests 
                      (ris_no, requested_by, purpose, request_date, status) 
                      VALUES (?, ?, ?, ?, 'Pending')";
        
        $stmt_ris = $conn->prepare($insert_ris);
        $stmt_ris->bind_param("ssss", $ris_no, $user_info['full_name'], $purpose, $requisition_date);
        
        if ($stmt_ris->execute()) {
            $ris_id = $stmt_ris->insert_id;
            
            // Add items if any were selected
            if (!empty($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    $stock_number = $item['stock_no'] ?? '';
                    $item_description = $item['item_description'] ?? '';
                    $quantity_requested = $item['quantity_requested'] ?? 0;
                    $quantity_issued = $item['quantity_issued'] ?? 0;
                    $remarks = $item['remarks'] ?? '';
                    
                    if (!empty($stock_number) && $quantity_requested > 0) {
                        // Get item_id from stock_no
                        $item_query = "SELECT id FROM items WHERE stock_no = ?";
                        $stmt_item = $conn->prepare($item_query);
                        $stmt_item->bind_param("s", $stock_number);
                        $stmt_item->execute();
                        $item_result = $stmt_item->get_result();
                        $item_data = $item_result->fetch_assoc();
                        
                        if ($item_data) {
                            $item_id = $item_data['id'];
                            
                            // Insert item using request_items table structure
                            $insert_item = "INSERT INTO request_items 
                                          (request_id, item_id, quantity_requested, quantity_issued, remarks) 
                                          VALUES (?, ?, ?, ?, ?)";
                            
                            $stmt_item = $conn->prepare($insert_item);
                            $stmt_item->bind_param("iiiis", 
                                $ris_id, $item_id, $quantity_requested, $quantity_issued, $remarks);
                            $stmt_item->execute();
                        }
                    }
                }
            }
            
            $_SESSION['success'] = "RIS #$ris_no has been submitted successfully!";
            header("Location: ris.php");
            exit();
        } else {
            $_SESSION['error'] = "Error creating RIS. Please try again.";
        }
    }
}

// Get available items for selection
$items_query = "SELECT id, item, description, stock_no, unit_measurement, unit_value, balance_qty, item_type
               FROM items 
               WHERE status = 'Active' AND balance_qty > 0 
               ORDER BY item";
$items_result = $conn->query($items_query);

// Get default entity and responsibility center from user
$default_entity = $user_info['department'] ?? '';
$default_rc = $user_info['office'] ?? '';

require_once 'staff_sidebar.php';
include 'staff_navbar.php';
?>

<div class="lg:ml-[260px] pt-20 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center mr-3">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    Create Requisition and Issue Slip
                </h1>
                <p class="text-sm text-gray-500 mt-1">Fill out the form below to create a new RIS</p>
            </div>
            <a href="ris.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-600 text-sm font-bold rounded-xl hover:bg-gray-50 transition-all duration-200 shadow-sm">
                <i class="bi bi-clock-history mr-2 text-primary"></i> My RIS History
            </a>
        </div>
        
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-primary px-8 py-6">
                <h2 class="text-lg font-bold text-white flex items-center">
                    <i class="bi bi-plus-circle mr-3 text-accent"></i> RIS Information
                </h2>
                <p class="text-primary-light text-xs mt-1">Please provide accurate information for your requisition</p>
            </div>
            
            <div class="p-8">
                <form method="POST" action="" id="risForm" class="space-y-8">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="entity_name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Entity Name <span class="text-secondary">*</span>
                            </label>
                            <input type="text" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium text-gray-700 focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="entity_name" name="entity_name" 
                                   value="Commission on Population and Development Region-VI" required>
                        </div>
                        <div class="space-y-2">
                            <label for="fund_cluster" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Fund Cluster
                            </label>
                            <input type="text" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="fund_cluster" name="fund_cluster" 
                                   placeholder="Enter fund cluster">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label for="division" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Division
                            </label>
                            <input type="text" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="division" name="division" value="Admin">
                        </div>
                        <div class="space-y-2">
                            <label for="responsibility_center" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Responsibility Center Code
                            </label>
                            <input type="text" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="responsibility_center" name="responsibility_center" 
                                   placeholder="Enter code">
                        </div>
                        <div class="space-y-2">
                            <label for="office" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Office
                            </label>
                            <input type="text" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="office" name="office" 
                                   placeholder="Enter office">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-8 border-b border-gray-50">
                        <div class="space-y-2">
                            <label for="ris_no_display" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                RIS Number (Auto-generated)
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="bi bi-hash text-primary"></i>
                                </div>
                                <input type="text" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-bold text-primary outline-none" id="ris_no_display" name="ris_no_display" 
                                       placeholder="<?php echo date('y-m') . '-001'; ?>" readonly>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="purpose" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">
                                Purpose of Requisition <span class="text-secondary">*</span>
                            </label>
                            <textarea class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none resize-none" id="purpose" name="purpose" rows="2" 
                                      placeholder="Specify the purpose of this requisition..." required></textarea>
                        </div>
                    </div>
                    
                    <!-- Items Section -->
                    <div class="bg-gray-50/50 rounded-3xl border border-gray-100 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100 bg-white flex justify-between items-center">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 flex items-center">
                                    <i class="bi bi-box-seam mr-2 text-primary"></i> Items Requisition
                                </h3>
                                <p class="text-[10px] text-gray-500 font-medium mt-0.5">Add items you need to requisition</p>
                            </div>
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-primary text-white text-[11px] font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20" onclick="addItem()">
                                <i class="bi bi-plus-lg mr-2"></i> Add Item
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="itemsTable">
                                <thead>
                                    <tr class="bg-gray-50/80">
                                        <th rowspan="2" class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center border-r border-gray-100">#</th>
                                        <th colspan="4" class="px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center border-b border-r border-gray-100 bg-gray-50/30">Requisition</th>
                                        <th colspan="2" class="px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center border-b border-r border-gray-100 bg-gray-50/30">Stock Avail.</th>
                                        <th colspan="2" class="px-4 py-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center border-b border-r border-gray-100 bg-gray-50/30">Issue</th>
                                        <th rowspan="2" class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center">Action</th>
                                    </tr>
                                    <tr class="bg-gray-50/80">
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100">Stock No.</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100">Unit</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100">Description</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100">Qty</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center border-r border-gray-100">Yes</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center border-r border-gray-100">No</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center border-r border-gray-100">Qty</th>
                                        <th class="px-4 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-r border-gray-100">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody" class="bg-white divide-y divide-gray-50">
                                    <tr id="emptyState">
                                        <td colspan="10" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 rounded-3xl bg-gray-50 flex items-center justify-center mb-4">
                                                    <i class="bi bi-inbox text-3xl text-gray-200"></i>
                                                </div>
                                                <p class="text-sm font-bold text-gray-400">No items added yet.</p>
                                                <p class="text-xs text-gray-400 mt-1">Click "Add Item" to start your requisition.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-8 border-t border-gray-50">
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button type="button" onclick="window.history.back()" class="flex-1 sm:flex-none px-6 py-2.5 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors">
                                <i class="bi bi-arrow-left mr-2"></i> Back
                            </button>
                            <button type="reset" class="flex-1 sm:flex-none px-6 py-2.5 text-xs font-bold text-secondary hover:text-secondary-dark transition-colors">
                                <i class="bi bi-x-circle mr-2"></i> Clear Form
                            </button>
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-10 py-3 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20">
                            <i class="bi bi-send mr-2 text-accent"></i> Submit RIS
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Instructions Card -->
        <div class="mt-8 bg-blue-50/50 rounded-3xl border border-blue-100 overflow-hidden">
            <div class="px-8 py-4 bg-blue-50 border-b border-blue-100 flex items-center">
                <i class="bi bi-info-circle text-blue-600 mr-3"></i>
                <h3 class="text-sm font-bold text-blue-800 uppercase tracking-wider">RIS Instructions</h3>
            </div>
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-xs font-bold text-blue-700 uppercase tracking-widest mb-4">How to Fill Out RIS:</h4>
                        <ul class="space-y-3">
                            <li class="flex items-start text-xs text-blue-900/70 font-medium">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] mr-3 shrink-0 mt-0.5">1</span>
                                Fill in all required header information.
                            </li>
                            <li class="flex items-start text-xs text-blue-900/70 font-medium">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] mr-3 shrink-0 mt-0.5">2</span>
                                Select appropriate fund cluster.
                            </li>
                            <li class="flex items-start text-xs text-blue-900/70 font-medium">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] mr-3 shrink-0 mt-0.5">3</span>
                                Specify responsibility center.
                            </li>
                            <li class="flex items-start text-xs text-blue-900/70 font-medium">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] mr-3 shrink-0 mt-0.5">4</span>
                                Provide clear purpose for requisition.
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-blue-700 uppercase tracking-widest mb-4">Important Notes:</h4>
                        <ul class="space-y-4">
                            <li class="flex items-center text-xs text-blue-900/70 font-medium">
                                <i class="bi bi-check2 text-blue-600 mr-3"></i> Only available items can be requisitioned.
                            </li>
                            <li class="flex items-center text-xs text-blue-900/70 font-medium">
                                <i class="bi bi-check2 text-blue-600 mr-3"></i> Unit costs are automatically calculated.
                            </li>
                            <li class="flex items-center text-xs text-blue-900/70 font-medium">
                                <i class="bi bi-check2 text-blue-600 mr-3"></i> RIS will be routed for approval.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Selection Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <div class="modal-header bg-gray-50/50 border-b border-gray-100 px-8 py-6">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-xl bg-primary/5 text-primary flex items-center justify-center mr-4">
                        <i class="bi bi-box-seam text-lg"></i>
                    </div>
                    <div>
                        <h5 class="text-xl font-bold text-primary">Select Item from Inventory</h5>
                        <p class="text-xs text-gray-500 mt-1">Search and pick an available stock to add to this RIS.</p>
                    </div>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body p-8">
                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-primary">
                            <i class="bi bi-search"></i>
                        </div>
                        <input type="text" class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none" id="itemSearch" placeholder="Search by stock number, item name, or description...">
                    </div>
                    <div class="px-4 py-2 bg-primary/5 rounded-xl border border-primary/10">
                        <p class="text-[10px] font-bold text-primary leading-tight">
                            <i class="bi bi-info-circle mr-1"></i> Only active items with available stock are listed.
                        </p>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div class="overflow-y-auto max-h-[420px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-gray-50 border-b border-gray-100 z-10">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Stock Number</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Item Details</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Unit</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Available</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Unit Value</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemList" class="divide-y divide-gray-50">
                                <?php 
                                if ($items_result->num_rows > 0):
                                    $items_result->data_seek(0);
                                    while ($item = $items_result->fetch_assoc()): 
                                ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($item['stock_no']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($item['item']); ?></div>
                                            <div class="text-xs text-gray-500 mt-0.5 line-clamp-1">
                                                <?php echo htmlspecialchars($item['description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600 text-center"><?php echo htmlspecialchars($item['unit_measurement']); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="px-2.5 py-1 bg-green-50 text-green-600 text-[11px] font-bold rounded-full border border-green-100">
                                                <?php echo number_format($item['balance_qty']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-800 text-right italic">
                                            ₱<?php echo number_format($item['unit_value'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <button type="button" class="inline-flex items-center px-4 py-2 bg-primary text-white text-[11px] font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/10" 
                                                    onclick="selectItem('<?php echo htmlspecialchars($item['stock_no']); ?>', 
                                                                     '<?php echo htmlspecialchars($item['item']); ?>', 
                                                                     '<?php echo htmlspecialchars($item['unit_measurement']); ?>', 
                                                                     '<?php echo $item['unit_value']; ?>', 
                                                                     '<?php echo $item['balance_qty']; ?>')">
                                                <i class="bi bi-plus-lg mr-2"></i>Select
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                else:
                                ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center">
                                                <i class="bi bi-inbox text-4xl text-gray-200 mb-2"></i>
                                                <p class="text-sm text-gray-500 font-medium">No available items found.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let itemCounter = 0;
let selectedItems = [];

// Make addItem function global
window.addItem = function() {
    const modal = new bootstrap.Modal(document.getElementById('itemModal'));
    modal.show();
};

// Make selectItem function global
window.selectItem = function(stockNumber, article, unit, unitValue, availableQty) {
    // Remove empty state if it exists
    const emptyState = document.getElementById('emptyState');
    if (emptyState) {
        emptyState.remove();
    }
    
    if (selectedItems.includes(stockNumber)) {
        Swal.fire({
            icon: 'info',
            title: 'Already added',
            text: 'This item is already added to the RIS',
            customClass: {
                popup: 'rounded-3xl border-0 shadow-2xl',
                confirmButton: 'px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20'
            },
            buttonsStyling: false
        });
        return;
    }
    
    itemCounter++;
    selectedItems.push(stockNumber);
    
    const tbody = document.getElementById('itemsTableBody');
    const row = document.createElement('tr');
    row.id = `itemRow${itemCounter}`;
    row.className = 'hover:bg-gray-50/50 transition-colors border-b border-gray-50';
    
    // Automatic stock availability based on inventory
    const isAvailable = availableQty > 0;
    const maxQuantity = isAvailable ? availableQty : 0;
    
    row.innerHTML = `
        <td class="px-4 py-4 text-center text-sm text-gray-500 font-medium border-r border-gray-100">${itemCounter}</td>
        <td class="px-4 py-4 text-sm font-bold text-primary border-r border-gray-100">
            <input type="hidden" name="items[${itemCounter}][stock_no]" value="${stockNumber}">
            ${stockNumber}
        </td>
        <td class="px-4 py-4 text-sm text-gray-600 font-medium border-r border-gray-100">${unit}</td>
        <td class="px-4 py-4 text-sm text-gray-800 font-bold border-r border-gray-100">
            <input type="hidden" name="items[${itemCounter}][item_description]" value="${article}">
            ${article}
        </td>
        <td class="px-4 py-4 text-center border-r border-gray-100">
            <span class="px-2.5 py-1 ${availableQty > 0 ? 'bg-green-50 text-green-600 border-green-100' : 'bg-red-50 text-red-600 border-red-100'} text-[11px] font-bold rounded-full border">
                ${availableQty}
            </span>
        </td>
        <td class="px-4 py-4 text-center border-r border-gray-100">
            <input type="radio" name="items[${itemCounter}][stock_available]" value="Yes" ${isAvailable ? 'checked' : ''} disabled class="w-4 h-4 text-primary border-gray-300 focus:ring-primary/20">
        </td>
        <td class="px-4 py-4 text-center border-r border-gray-100">
            <input type="radio" name="items[${itemCounter}][stock_available]" value="No" ${!isAvailable ? 'checked' : ''} disabled class="w-4 h-4 text-secondary border-gray-300 focus:ring-secondary/20">
        </td>
        <td class="px-4 py-4 border-r border-gray-100">
            <input type="number" class="w-20 mx-auto block px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-center font-bold text-gray-700 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all sm:text-sm" 
                   name="items[${itemCounter}][quantity_issued]" 
                   value="${isAvailable ? 1 : 0}" min="0" max="${maxQuantity}" 
                   id="issueQty${itemCounter}" ${!isAvailable ? 'disabled' : ''}>
        </td>
        <td class="px-4 py-4 border-r border-gray-100">
            <input type="text" class="w-full px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" 
                   name="items[${itemCounter}][remarks]" 
                   placeholder="Enter remarks">
        </td>
        <td class="px-4 py-4 text-center">
            <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition-all duration-200" onclick="window.removeItem(${itemCounter}, '${stockNumber}')">
                <i class="bi bi-trash text-sm"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    
    // Close modal
    const modalElement = document.getElementById('itemModal');
    const modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) modal.hide();
    
    // Update item numbers
    window.updateItemNumbers();
};

// Make removeItem function global
window.removeItem = function(counter, stockNumber) {
    const row = document.getElementById(`itemRow${counter}`);
    if (row) row.remove();
    selectedItems = selectedItems.filter(item => item !== stockNumber);
    window.updateItemNumbers();
    
    // Show empty state if no items left
    const tbody = document.getElementById('itemsTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyState">
                <td colspan="10" class="px-6 py-16 text-center">
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 rounded-3xl bg-gray-50 flex items-center justify-center mb-4">
                            <i class="bi bi-inbox text-3xl text-gray-200"></i>
                        </div>
                        <p class="text-sm font-bold text-gray-400">No items added yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Click "Add Item" to start your requisition.</p>
                    </div>
                </td>
            </tr>
        `;
    }
};

// Make updateItemNumbers function global
window.updateItemNumbers = function() {
    const rows = document.querySelectorAll('#itemsTableBody tr:not(#emptyState)');
    rows.forEach((row, index) => {
        const counterInput = row.querySelector('td:first-child');
        if (counterInput) {
            counterInput.textContent = index + 1;
        }
    });
    
    const emptyState = document.getElementById('emptyState');
    if (emptyState) {
        emptyState.style.display = rows.length === 0 ? '' : 'none';
    }
};

// Search functionality
document.getElementById('itemSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#itemList tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Form validation
document.getElementById('risForm').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('#itemsTableBody tr:not(#emptyState)');
    if (items.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'No items',
            text: 'Please add at least one item to the RIS',
            customClass: {
                popup: 'rounded-3xl border-0 shadow-2xl',
                confirmButton: 'px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20'
            },
            buttonsStyling: false
        });
        return false;
    }
});
</script>

</body>
</html>
