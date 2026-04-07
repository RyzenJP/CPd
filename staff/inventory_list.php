<?php
$page_title = "Inventory List";
require_once '../plugins/conn.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 25;
    $offset = ($page - 1) * $per_page;

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM items i WHERE i.status = 'Active'";
    $count_params = [];
    $count_types = "";
    
    if ($category !== '' && $category !== 'all') {
        $count_sql .= " AND i.item_type = ?";
        $count_types .= "s";
        $count_params[] = $category;
    }
    
    if ($search !== '') {
        $count_sql .= " AND (i.stock_no LIKE ? OR i.item LIKE ? OR i.description LIKE ?)";
        $count_types .= "sss";
        $like = "%" . $search . "%";
        $count_params[] = $like;
        $count_params[] = $like;
        $count_params[] = $like;
    }
    
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
    $total_rows = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $per_page);

    $sql = "SELECT 
                i.id,
                i.stock_no,
                i.item,
                i.description,
                i.unit_measurement,
                i.balance_qty,
                i.unit_value,
                (i.balance_qty * i.unit_value) AS total_value,
                i.item_type,
                i.status
            FROM items i
            WHERE i.status = 'Active'";

    $params = [];
    $types = "";

    if ($category !== '' && $category !== 'all') {
        $sql .= " AND i.item_type = ?";
        $types .= "s";
        $params[] = $category;
    }

    if ($search !== '') {
        $sql .= " AND (i.stock_no LIKE ? OR i.item LIKE ? OR i.description LIKE ?)";
        $types .= "sss";
        $like = "%" . $search . "%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY i.item ASC LIMIT ? OFFSET ?";
    $types .= "ii";
    $params[] = $per_page;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows_html = "";

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status_color = $row['status'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
            $status_text = htmlspecialchars($row['status']);

            $rows_html .= "<tr class=\"hover:bg-gray-50/50 transition-colors group\">";
            $rows_html .= "<td class=\"px-4 py-4 text-center\">";
            $rows_html .= "<input type=\"checkbox\" class=\"item-select-checkbox w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20 transition-all\""
                . " data-id=\"" . (int)$row['id'] . "\""
                . " data-stock=\"" . htmlspecialchars($row['stock_no'], ENT_QUOTES) . "\""
                . " data-item=\"" . htmlspecialchars($row['item'], ENT_QUOTES) . "\""
                . " data-description=\"" . htmlspecialchars($row['description'], ENT_QUOTES) . "\""
                . " data-unit=\"" . htmlspecialchars($row['unit_measurement'], ENT_QUOTES) . "\""
                . " data-instock=\"" . (float)$row['balance_qty'] . "\""
                . " data-item-type=\"" . htmlspecialchars($row['item_type'], ENT_QUOTES) . "\">";
            $rows_html .= "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm font-medium text-gray-600\">" . htmlspecialchars($row['stock_no']) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm font-bold text-gray-800\">" . htmlspecialchars($row['item']) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-xs text-gray-500 max-w-xs truncate\">" . htmlspecialchars($row['description']) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm text-gray-600\">" . htmlspecialchars($row['unit_measurement']) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm font-bold text-gray-800 text-right\">" . number_format((float)$row['balance_qty'], 2) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm text-gray-600 text-right\">" . number_format((float)$row['unit_value'], 2) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4 text-sm font-bold text-primary text-right\">" . number_format((float)$row['total_value'], 2) . "</td>";
            $rows_html .= "<td class=\"px-4 py-4\"><span class=\"px-2 py-1 bg-gray-100 text-gray-600 rounded-md text-[10px] font-bold uppercase tracking-wider\">" . htmlspecialchars($row['item_type']) . "</span></td>";
            $rows_html .= "<td class=\"px-4 py-4\"><span class=\"px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider $status_color\">" . $status_text . "</span></td>";
            $rows_html .= "<td class=\"px-4 py-4 text-center\">";
            $rows_html .= "<button type=\"button\" class=\"request-btn inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/5 text-primary hover:bg-primary hover:text-white rounded-lg text-xs font-bold transition-all duration-200 active:scale-95\""
                . " data-id=\"" . (int)$row['id'] . "\""
                . " data-stock=\"" . htmlspecialchars($row['stock_no'], ENT_QUOTES) . "\""
                . " data-item=\"" . htmlspecialchars($row['item'], ENT_QUOTES) . "\""
                . " data-description=\"" . htmlspecialchars($row['description'], ENT_QUOTES) . "\""
                . " data-unit=\"" . htmlspecialchars($row['unit_measurement'], ENT_QUOTES) . "\""
                . " data-instock=\"" . (float)$row['balance_qty'] . "\""
                . " data-item-type=\"" . htmlspecialchars($row['item_type'], ENT_QUOTES) . "\">";
            $rows_html .= "<i class=\"bi bi-send-plus\"></i> Request</button>";
            $rows_html .= "</td>";
            $rows_html .= "</tr>";
        }
    } else {
        $rows_html .= "<tr>";
        $rows_html .= "<td colspan=\"11\" class=\"px-4 py-12 text-center\">";
        $rows_html .= "<div class=\"flex flex-col items-center gap-2 text-gray-400\">";
        $rows_html .= "<i class=\"bi bi-inbox text-4xl\"></i>";
        $rows_html .= "<p class=\"font-medium text-sm\">No items found matching your criteria.</p>";
        $rows_html .= "</div></td></tr>";
    }

    echo json_encode([
        'html' => $rows_html,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_rows' => $total_rows,
            'per_page' => $per_page,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ]);
    exit();
}

require_once 'staff_sidebar.php';
require_once 'staff_navbar.php';
?>

<div class="lg:ml-[260px] pt-20 px-4 pb-8 min-h-screen bg-bg-light">
    <div class="max-w-[1600px] mx-auto">
        <!-- Page Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-end gap-4">
            <div class="flex items-center bg-white rounded-2xl shadow-sm border border-black/5 overflow-hidden focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-all">
                <div class="relative group">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors"></i>
                    <input type="text" id="searchInput" class="pl-11 pr-4 py-3 bg-transparent border-none text-sm focus:ring-0 w-64 transition-all placeholder-gray-400" placeholder="Search items...">
                </div>
                <div class="h-6 w-px bg-gray-200"></div>
                <select id="categoryFilter" class="pr-10 pl-4 py-3 bg-transparent border-none text-sm font-bold text-gray-700 focus:ring-0 transition-all cursor-pointer appearance-none outline-none">
                    <option value="all">All Categories</option>
                    <option value="Expendable">Expendable</option>
                    <option value="Semi-Expendable">Semi-Expendable</option>
                    <option value="Non-Expendable">Non-Expendable</option>
                </select>
            </div>
        </div>

        <!-- Inventory Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-black/5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-4 py-4 text-center w-12">
                                <input type="checkbox" class="w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20 transition-all cursor-pointer" id="selectAllItems">
                            </th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap w-32">Stock No.</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-48">Item Name</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Description</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-20">Unit</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right w-24">Instocks</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right w-24">Value</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right w-24">Total</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-32">Category</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-24">Status</th>
                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center w-28">Action</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="divide-y divide-gray-50">
                        <?php
                        $page = 1;
                        $per_page = 25;
                        $offset = ($page - 1) * $per_page;
                        
                        $count_sql = "SELECT COUNT(*) as total FROM items WHERE status = 'Active'";
                        $count_result = $conn->query($count_sql);
                        $total_rows = $count_result->fetch_assoc()['total'];
                        $total_pages = ceil($total_rows / $per_page);
                        
                        $sql_initial = "SELECT 
                                            i.id,
                                            i.stock_no,
                                            i.item,
                                            i.description,
                                            i.unit_measurement,
                                            i.balance_qty,
                                            i.unit_value,
                                            (i.balance_qty * i.unit_value) AS total_value,
                                            i.item_type,
                                            i.status
                                        FROM items i
                                        WHERE i.status = 'Active'
                                        ORDER BY i.item ASC
                                        LIMIT ? OFFSET ?";

                        $stmt_initial = $conn->prepare($sql_initial);
                        $stmt_initial->bind_param("ii", $per_page, $offset);
                        $stmt_initial->execute();
                        $result_initial = $stmt_initial->get_result();

                        if ($result_initial && $result_initial->num_rows > 0) {
                            while ($row = $result_initial->fetch_assoc()) {
                                $status_color = $row['status'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
                                ?>
                                <tr class="hover:bg-gray-50/50 transition-colors group">
                                    <td class="px-4 py-4 text-center">
                                        <input
                                            type="checkbox"
                                            class="item-select-checkbox w-5 h-5 text-primary border-gray-300 rounded focus:ring-primary/20 transition-all cursor-pointer"
                                            data-id="<?php echo (int)$row['id']; ?>"
                                            data-stock="<?php echo htmlspecialchars($row['stock_no'], ENT_QUOTES); ?>"
                                            data-item="<?php echo htmlspecialchars($row['item'], ENT_QUOTES); ?>"
                                            data-description="<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>"
                                            data-unit="<?php echo htmlspecialchars($row['unit_measurement'], ENT_QUOTES); ?>"
                                            data-instock="<?php echo (float)$row['balance_qty']; ?>"
                                            data-item-type="<?php echo htmlspecialchars($row['item_type'], ENT_QUOTES); ?>"
                                        >
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium text-gray-600"><?php echo htmlspecialchars($row['stock_no']); ?></td>
                                    <td class="px-4 py-4 text-sm font-bold text-gray-800"><?php echo htmlspecialchars($row['item']); ?></td>
                                    <td class="px-4 py-4 text-xs text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                    <td class="px-4 py-4 text-sm font-bold text-gray-800 text-right"><?php echo number_format((float)$row['balance_qty'], 2); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-600 text-right"><?php echo number_format((float)$row['unit_value'], 2); ?></td>
                                    <td class="px-4 py-4 text-sm font-bold text-primary text-right"><?php echo number_format((float)$row['total_value'], 2); ?></td>
                                    <td class="px-4 py-4">
                                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-md text-[10px] font-bold uppercase tracking-wider">
                                            <?php echo htmlspecialchars($row['item_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <button
                                            type="button"
                                            class="request-btn inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/5 text-primary hover:bg-primary hover:text-white rounded-lg text-xs font-bold transition-all duration-200 active:scale-95"
                                            data-id="<?php echo (int)$row['id']; ?>"
                                            data-stock="<?php echo htmlspecialchars($row['stock_no'], ENT_QUOTES); ?>"
                                            data-item="<?php echo htmlspecialchars($row['item'], ENT_QUOTES); ?>"
                                            data-description="<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>"
                                            data-unit="<?php echo htmlspecialchars($row['unit_measurement'], ENT_QUOTES); ?>"
                                            data-instock="<?php echo (float)$row['balance_qty']; ?>"
                                            data-item-type="<?php echo htmlspecialchars($row['item_type'], ENT_QUOTES); ?>"
                                        >
                                            <i class="bi bi-send-plus"></i> Request
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <i class="bi bi-inbox text-4xl"></i>
                                        <p class="font-medium text-sm">No items found.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest" id="paginationInfo">
                    Showing 1-<?php echo min($per_page, $total_rows); ?> of <?php echo $total_rows; ?> items
                </div>
                <nav id="paginationContainer">
                    <ul class="flex items-center gap-1" id="paginationUl">
                        <!-- Pagination buttons injected by JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Floating Selection Bar -->
<div id="multiSelectBar" class="fixed left-1/2 bottom-8 -translate-x-1/2 z-[1050] bg-white border border-black/5 rounded-full shadow-2xl px-6 py-3 flex items-center gap-6 transition-all duration-500 transform translate-y-32 opacity-0 pointer-events-none max-w-[90vw]">
    <div class="flex items-center gap-3 pr-6 border-r border-gray-100">
        <div class="w-10 h-10 bg-primary/10 text-primary rounded-full flex items-center justify-center font-bold" id="selectedItemsCount">0</div>
        <div class="flex flex-col">
            <span class="text-xs font-bold text-gray-800 uppercase tracking-tight">Items Selected</span>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Bulk Action</span>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" class="px-4 py-2 text-gray-500 hover:text-gray-800 font-bold text-xs uppercase tracking-wider transition-colors" id="viewSelectionBtn">View</button>
        <button type="button" class="px-4 py-2 text-gray-500 hover:text-gray-800 font-bold text-xs uppercase tracking-wider transition-colors" id="clearSelectionBtn">Clear</button>
        <button type="button" class="px-6 py-2 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-full hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95 disabled:opacity-50" id="bulkRequestBtn">
            <i class="bi bi-send-plus mr-2"></i> Submit Request
        </button>
    </div>
</div>

<!-- Single Request Modal -->
<div id="requestModal" class="fixed inset-0 z-[1100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('requestModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center shadow-sm">
                            <i class="bi bi-box-arrow-in-down text-xl"></i>
                        </div>
                        <div>
                            <h5 class="text-xl font-bold text-gray-800">Request Item</h5>
                            <p class="text-gray-500 text-xs font-medium mt-0.5">Enter the quantity and details for your request.</p>
                        </div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('requestModal')">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
                <div class="px-8 py-8">
                    <form id="requestForm" class="space-y-8">
                        <input type="hidden" id="requestItemId" name="item_id">
                        <input type="hidden" id="requestAvailableQty" name="available_qty">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Item Details Column -->
                            <div class="space-y-6">
                                <div>
                                    <h6 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-3">Item Details</h6>
                                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                                        <h4 id="modalItemName" class="text-lg font-bold text-gray-800 leading-tight mb-2"></h4>
                                        <p id="modalDescription" class="text-gray-500 text-xs leading-relaxed mb-6"></p>
                                        
                                        <div class="grid grid-cols-2 gap-y-4">
                                            <div>
                                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Stock No.</span>
                                                <span id="modalStockNo" class="text-sm font-bold text-gray-700"></span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Category</span>
                                                <span id="modalItemType" class="text-sm font-bold text-gray-700"></span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Unit</span>
                                                <span id="modalUnit" class="text-sm font-bold text-gray-700"></span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Available</span>
                                                <span id="modalInstock" class="text-sm font-bold text-green-600"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Inputs Column -->
                            <div class="space-y-6">
                                <div>
                                    <h6 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-3">Request Info</h6>
                                    <div class="space-y-5">
                                        <div class="space-y-2">
                                            <label class="text-sm font-bold text-gray-700 ml-1">Quantity to Request</label>
                                            <div class="relative group">
                                                <i class="bi bi-hash absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-primary transition-colors"></i>
                                                <input type="number" id="requestQuantity" name="quantity" min="1" step="1" required class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-bold text-gray-800" placeholder="0">
                                            </div>
                                            <p id="requestQuantityError" class="text-[10px] font-bold text-red-500 mt-1 hidden"></p>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-bold text-gray-700 ml-1">Remarks</label>
                                            <textarea id="requestRemark" name="remark" rows="3" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm text-gray-700" placeholder="Any specific instructions..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Requesting</span>
                                        <span class="text-sm font-bold text-gray-800"><span id="summaryRequestedQty">0</span> <span id="summaryUnitLabel"></span></span>
                                    </div>
                                    <button type="button" class="px-8 py-3 bg-primary text-white font-bold text-sm uppercase tracking-widest rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95 focus:outline-none" id="confirmRequestBtn">
                                        Confirm
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Request Modal -->
<div id="bulkRequestModal" class="fixed inset-0 z-[1100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('bulkRequestModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
                <form id="bulkRequestForm" method="post" action="ris.php">
                    <input type="hidden" name="action" value="create_multi_from_inventory">
                    <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center shadow-sm">
                                <i class="bi bi-layers text-xl"></i>
                            </div>
                            <div>
                                <h5 class="text-xl font-bold text-gray-800">Bulk Request</h5>
                                <p class="text-gray-500 text-xs font-medium mt-0.5">Enter quantities for each selected item.</p>
                            </div>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('bulkRequestModal')">
                            <i class="bi bi-x-lg text-xl"></i>
                        </button>
                    </div>
                    <div class="px-8 py-8">
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-gray-700 ml-1">Purpose of Request</label>
                                <textarea id="bulkPurpose" name="purpose" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm text-gray-700" placeholder="Describe why you are requesting these items..."></textarea>
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-gray-100 shadow-sm">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50/50 border-b border-gray-100">
                                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Item</th>
                                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Description</th>
                                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Unit</th>
                                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right">Available</th>
                                            <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center w-40">Qty Request</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkItemsBody" class="divide-y divide-gray-50">
                                        <!-- Bulk items injected by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50/50 border-t border-gray-100 px-8 py-6 flex justify-end gap-3">
                        <button type="button" class="px-6 py-2.5 text-gray-600 font-bold hover:bg-gray-100 rounded-xl transition-all focus:outline-none" onclick="closeModal('bulkRequestModal')">Cancel</button>
                        <button type="submit" class="px-8 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary-light hover:shadow-lg hover:shadow-primary/20 transition-all active:scale-95 focus:outline-none" id="confirmBulkRequestBtn">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Selection Modal -->
<div id="viewSelectedModal" class="fixed inset-0 z-[1100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('viewSelectedModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
                <div class="bg-gray-50/50 border-b border-gray-100 px-8 py-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 text-gray-500 rounded-2xl flex items-center justify-center shadow-sm">
                            <i class="bi bi-list-check text-xl"></i>
                        </div>
                        <div>
                            <h5 class="text-xl font-bold text-gray-800">Selected Items</h5>
                            <p class="text-gray-500 text-xs font-medium mt-0.5">Review items currently in your selection.</p>
                        </div>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('viewSelectedModal')">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
                <div class="px-8 py-8">
                    <div class="overflow-hidden rounded-2xl border border-gray-100 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100">
                                    <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Stock No.</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Item</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Unit</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right">Available</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Type</th>
                                </tr>
                            </thead>
                            <tbody id="viewSelectedBody" class="divide-y divide-gray-50">
                                <!-- Selected items injected by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-gray-50/50 border-t border-gray-100 px-8 py-6 flex justify-end">
                    <button type="button" class="px-8 py-2.5 bg-gray-800 text-white font-bold rounded-xl hover:bg-gray-900 transition-all active:scale-95 focus:outline-none" onclick="closeModal('viewSelectedModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="quickRequestForm" method="post" action="ris.php" class="hidden">
    <input type="hidden" name="action" value="create_from_inventory">
    <input type="hidden" name="item_id" id="quickRequestItemId">
    <input type="hidden" name="quantity" id="quickRequestQuantity">
</form>

<!-- External Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var inventoryTableBody = document.getElementById('inventoryTableBody');
    var requestForm = document.getElementById('requestForm');
    var requestItemIdInput = document.getElementById('requestItemId');
    var requestAvailableQtyInput = document.getElementById('requestAvailableQty');
    var requestQuantityInput = document.getElementById('requestQuantity');
    var requestQuantityError = document.getElementById('requestQuantityError');
    var summaryRequestedQty = document.getElementById('summaryRequestedQty');
    var summaryUnitLabel = document.getElementById('summaryUnitLabel');
    var modalItemName = document.getElementById('modalItemName');
    var modalStockNo = document.getElementById('modalStockNo');
    var modalDescription = document.getElementById('modalDescription');
    var modalUnit = document.getElementById('modalUnit');
    var modalInstock = document.getElementById('modalInstock');
    var modalItemType = document.getElementById('modalItemType');
    var confirmRequestBtn = document.getElementById('confirmRequestBtn');
    var quickRequestForm = document.getElementById('quickRequestForm');
    var quickRequestItemId = document.getElementById('quickRequestItemId');
    var quickRequestQuantity = document.getElementById('quickRequestQuantity');
    var multiSelectBar = document.getElementById('multiSelectBar');
    var selectedItemsCount = document.getElementById('selectedItemsCount');
    var clearSelectionBtn = document.getElementById('clearSelectionBtn');
    var bulkRequestBtn = document.getElementById('bulkRequestBtn');
    var selectAllItemsCheckbox = document.getElementById('selectAllItems');
    var viewSelectionBtn = document.getElementById('viewSelectionBtn');
    var viewSelectedBody = document.getElementById('viewSelectedBody');
    var bulkItemsBody = document.getElementById('bulkItemsBody');
    var bulkRequestForm = document.getElementById('bulkRequestForm');
    var selectedItems = {};
    var paginationInfo = document.getElementById('paginationInfo');
    var paginationUl = document.getElementById('paginationUl');
    var currentPage = 1;

    function updateSelectionBar() {
        var count = Object.keys(selectedItems).length;
        if (selectedItemsCount) {
            selectedItemsCount.textContent = count;
        }
        if (multiSelectBar) {
            if (count > 0) {
                multiSelectBar.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
            } else {
                multiSelectBar.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
            }
        }
        if (bulkRequestBtn) {
            bulkRequestBtn.disabled = count === 0;
        }
    }

    function resetSelection() {
        selectedItems = {};
        if (selectAllItemsCheckbox) {
            selectAllItemsCheckbox.checked = false;
        }
        if (inventoryTableBody) {
            var checkboxes = inventoryTableBody.querySelectorAll('.item-select-checkbox');
            checkboxes.forEach(function (cb) {
                cb.checked = false;
            });
        }
        updateSelectionBar();
    }

    function handleItemCheckboxChange(checkbox) {
        var id = checkbox.getAttribute('data-id');
        if (!id) return;

        if (checkbox.checked) {
            selectedItems[id] = {
                id: id,
                stock: checkbox.getAttribute('data-stock') || '',
                item: checkbox.getAttribute('data-item') || '',
                description: checkbox.getAttribute('data-description') || '',
                unit: checkbox.getAttribute('data-unit') || '',
                instock: checkbox.getAttribute('data-instock') || '0',
                itemType: checkbox.getAttribute('data-item-type') || ''
            };
        } else {
            delete selectedItems[id];
        }

        if (selectAllItemsCheckbox && inventoryTableBody) {
            if (!checkbox.checked) {
                selectAllItemsCheckbox.checked = false;
            } else {
                var checkboxes = inventoryTableBody.querySelectorAll('.item-select-checkbox');
                var allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(function (cb) {
                    return cb.checked;
                });
                selectAllItemsCheckbox.checked = allChecked;
            }
        }

        updateSelectionBar();
    }

    function loadInventory(page) {
        if (typeof page === 'undefined') {
            page = 1;
        }
        currentPage = page;
        resetSelection();
        var search = searchInput.value;
        var category = categoryFilter.value;

        var params = new URLSearchParams();
        params.append('ajax', 'filter');
        params.append('search', search);
        params.append('category', category);
        params.append('page', page);

        fetch('inventory_list.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (inventoryTableBody && data && typeof data.html === 'string') {
                inventoryTableBody.innerHTML = data.html;
            }
            if (data.pagination) {
                updatePagination(data.pagination);
            }
        })
        .catch(function () {});
    }

    function updatePagination(pagination) {
        if (!paginationInfo || !paginationUl) return;

        var start = (pagination.current_page - 1) * pagination.per_page + 1;
        var end = Math.min(pagination.current_page * pagination.per_page, pagination.total_rows);
        paginationInfo.textContent = 'Showing ' + start + '-' + end + ' of ' + pagination.total_rows + ' items';

        paginationUl.innerHTML = '';

        if (pagination.total_pages > 1) {
            // Previous button
            var prevLi = document.createElement('li');
            var prevClass = pagination.has_prev ? 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200' : 'bg-gray-50 text-gray-300 border border-gray-100 pointer-events-none';
            prevLi.innerHTML = `<button class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 ${prevClass}">Previous</button>`;
            if (pagination.has_prev) {
                prevLi.querySelector('button').addEventListener('click', function() { loadInventory(pagination.current_page - 1); });
            }
            paginationUl.appendChild(prevLi);

            // Page numbers
            var startPage = Math.max(1, pagination.current_page - 2);
            var endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (var i = startPage; i <= endPage; i++) {
                var pageLi = createPageLink(i, pagination.current_page);
                paginationUl.appendChild(pageLi);
            }

            // Next button
            var nextLi = document.createElement('li');
            var nextClass = pagination.has_next ? 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200' : 'bg-gray-50 text-gray-300 border border-gray-100 pointer-events-none';
            nextLi.innerHTML = `<button class="px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 ${nextClass}">Next</button>`;
            if (pagination.has_next) {
                nextLi.querySelector('button').addEventListener('click', function() { loadInventory(pagination.current_page + 1); });
            }
            paginationUl.appendChild(nextLi);
        }
    }

    function createPageLink(pageNum, currentPage) {
        var li = document.createElement('li');
        var isActive = pageNum === currentPage;
        var activeClass = isActive ? 'bg-primary text-white border-primary shadow-lg shadow-primary/20' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-200';
        li.innerHTML = `<button class="w-10 h-10 flex items-center justify-center rounded-xl text-xs font-bold border transition-all duration-200 ${activeClass}">${pageNum}</button>`;
        if (!isActive) {
            li.querySelector('button').addEventListener('click', function() { loadInventory(pageNum); });
        }
        return li;
    }

    function debounce(fn, delay) {
        var timeoutId;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    var debouncedLoadInventory = debounce(loadInventory, 300);
    searchInput.addEventListener('input', debouncedLoadInventory);
    categoryFilter.addEventListener('change', function() { loadInventory(1); });

    function openRequestModal(button) {
        if (!button) return;
        var itemId = button.getAttribute('data-id') || '';
        var stockNo = button.getAttribute('data-stock') || '';
        var itemName = button.getAttribute('data-item') || '';
        var description = button.getAttribute('data-description') || '';
        var unit = button.getAttribute('data-unit') || '';
        var instock = button.getAttribute('data-instock') || '0';
        var itemType = button.getAttribute('data-item-type') || '';

        requestItemIdInput.value = itemId;
        requestAvailableQtyInput.value = instock;
        quickRequestItemId.value = itemId;

        modalItemName.textContent = itemName;
        modalStockNo.textContent = stockNo;
        modalDescription.textContent = description || 'No description provided.';
        modalUnit.textContent = unit || '-';
        modalInstock.textContent = instock;
        modalItemType.textContent = itemType || '-';

        requestQuantityInput.value = '';
        requestQuantityInput.classList.remove('ring-2', 'ring-red-500/20', 'border-red-500');
        requestQuantityError.classList.add('hidden');
        summaryRequestedQty.textContent = '0';
        summaryUnitLabel.textContent = unit ? ' ' + unit : '';

        openModal('requestModal');
        setTimeout(function () { requestQuantityInput.focus(); }, 150);
    }

    function validateQuantity() {
        var value = requestQuantityInput.value.trim();
        var available = parseFloat(requestAvailableQtyInput.value || '0');
        var error = '';

        if (value === '') {
            error = 'Enter quantity.';
        } else if (!/^\d+$/.test(value)) {
            error = 'Whole number only.';
        } else {
            var numeric = parseInt(value, 10);
            if (numeric <= 0) {
                error = 'Must be > 0.';
            } else if (numeric > available) {
                error = 'Max available: ' + available;
            }
        }

        if (error) {
            requestQuantityInput.classList.add('ring-2', 'ring-red-500/20', 'border-red-500');
            requestQuantityError.textContent = error;
            requestQuantityError.classList.remove('hidden');
            return false;
        } else {
            requestQuantityInput.classList.remove('ring-2', 'ring-red-500/20', 'border-red-500');
            requestQuantityError.classList.add('hidden');
            return true;
        }
    }

    inventoryTableBody.addEventListener('click', function (e) {
        var button = e.target.closest('.request-btn');
        if (button) openRequestModal(button);
    });

    inventoryTableBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('item-select-checkbox')) {
            handleItemCheckboxChange(e.target);
        }
    });

    requestQuantityInput.addEventListener('input', function () {
        summaryRequestedQty.textContent = this.value.trim() || '0';
        if (!requestQuantityError.classList.contains('hidden')) validateQuantity();
    });

    confirmRequestBtn.addEventListener('click', function () {
        if (validateQuantity()) {
            quickRequestQuantity.value = requestQuantityInput.value.trim();
            quickRequestForm.submit();
        }
    });

    selectAllItemsCheckbox.addEventListener('change', function () {
        var checkboxes = inventoryTableBody.querySelectorAll('.item-select-checkbox');
        checkboxes.forEach(function (cb) {
            cb.checked = selectAllItemsCheckbox.checked;
            var id = cb.getAttribute('data-id');
            if (cb.checked) {
                selectedItems[id] = {
                    id: id,
                    stock: cb.getAttribute('data-stock'),
                    item: cb.getAttribute('data-item'),
                    description: cb.getAttribute('data-description'),
                    unit: cb.getAttribute('data-unit'),
                    instock: cb.getAttribute('data-instock'),
                    itemType: cb.getAttribute('data-item-type')
                };
            } else {
                delete selectedItems[id];
            }
        });
        updateSelectionBar();
    });

    clearSelectionBtn.addEventListener('click', resetSelection);

    viewSelectionBtn.addEventListener('click', function () {
        viewSelectedBody.innerHTML = '';
        Object.values(selectedItems).forEach(function (item) {
            var row = `<tr>
                <td class="px-4 py-3 text-sm font-medium text-gray-600">${item.stock}</td>
                <td class="px-4 py-3 text-sm font-bold text-gray-800">${item.item}</td>
                <td class="px-4 py-3 text-sm text-gray-600 text-center">${item.unit}</td>
                <td class="px-4 py-3 text-sm font-bold text-green-600 text-right">${item.instock}</td>
                <td class="px-4 py-3"><span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-md text-[10px] font-bold uppercase tracking-wider">${item.itemType}</span></td>
            </tr>`;
            viewSelectedBody.insertAdjacentHTML('beforeend', row);
        });
        openModal('viewSelectedModal');
    });

    bulkRequestBtn.addEventListener('click', function () {
        bulkItemsBody.innerHTML = '';
        Object.values(selectedItems).forEach(function (item) {
            var row = `<tr>
                <td class="px-4 py-4 text-sm font-bold text-gray-800">${item.item}</td>
                <td class="px-4 py-4 text-xs text-gray-500 max-w-xs truncate">${item.description}</td>
                <td class="px-4 py-4 text-sm text-gray-600 text-center">${item.unit}</td>
                <td class="px-4 py-4 text-sm font-bold text-green-600 text-right">${item.instock}</td>
                <td class="px-4 py-4 text-center">
                    <input type="hidden" name="item_ids[]" value="${item.id}">
                    <input type="number" name="quantities[]" min="1" max="${item.instock}" required 
                           class="w-24 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                </td>
            </tr>`;
            bulkItemsBody.insertAdjacentHTML('beforeend', row);
        });
        openModal('bulkRequestModal');
    });

    loadInventory(1);
});
</script>
</body>
</html>