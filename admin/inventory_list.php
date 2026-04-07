<?php
include 'admin_sidebar.php';
$page_title = 'Inventory Management';
include '../plugins/conn.php';
include 'admin_navbar.php';

// AJAX: suggest unit of measurement
if (isset($_GET['ajax']) && $_GET['ajax'] === 'suggest_unit') {
    header('Content-Type: application/json');
    $itemName = isset($_GET['item']) ? trim(strtolower($_GET['item'])) : '';
    $default_units = [
        'bond paper'       => 'ream',
        'a4 bond paper'    => 'ream',
        'short bond paper' => 'ream',
        'long bond paper'  => 'ream',
        'bond'             => 'ream',
        'ballpen'          => 'piece',
        'ball pen'         => 'piece',
        'folder'           => 'piece',
        'stapler'          => 'piece',
        'ink cartridge'    => 'piece',
        'printer ink'      => 'bottle',
        'marker'           => 'piece',
        'notebook'         => 'piece',
    ];
    $suggested = '';
    if ($itemName !== '') {
        foreach ($default_units as $key => $unit) {
            if (strpos($itemName, $key) !== false) {
                $suggested = $unit;
                break;
            }
        }
    }
    echo json_encode(['unit' => $suggested]);
    exit();
}

// Handle Add Item POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_item') {

    $existing_item_id = isset($_POST['existing_item_id']) ? trim($_POST['existing_item_id']) : '';
    if ($existing_item_id && $existing_item_id !== '__new__') {
        $stmt_item = $conn->prepare("SELECT item FROM items WHERE id = ? LIMIT 1");
        $stmt_item->bind_param("i", $existing_item_id);
        $stmt_item->execute();
        $res_item  = $stmt_item->get_result();
        $row_item  = $res_item->fetch_assoc();
        $item = $row_item ? $row_item['item'] : trim($_POST['item'] ?? '');
    } else {
        $item = trim($_POST['item'] ?? '');
    }

    $description      = $_POST['description']      ?? '';
    $item_type        = $_POST['item_type']        ?? 'Expendable';
    $unit_measurement = $_POST['unit_measurement'] ?? '';
    $unit_value       = $_POST['unit_value']       ?? 0;
    $balance_qty      = $_POST['balance_qty']      ?? 0;
    $date_acquired    = $_POST['date_acquired']    ?? date('Y-m-d');

    $prefix = '';
    switch ($item_type) {
        case 'Expendable':      $prefix = 'XP-';  break;
        case 'Non-Expendable':  $prefix = 'NXP-'; break;
        case 'Semi-Expendable': $prefix = 'SXP-'; break;
        default:                $prefix = 'UNK-'; break;
    }

    $stmt_check = $conn->prepare("SELECT stock_no FROM items WHERE stock_no LIKE ? ORDER BY LENGTH(stock_no) DESC, stock_no DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt_check->bind_param("s", $like_pattern);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    $next_num = 1;
    if ($row_check = $result_check->fetch_assoc()) {
        $last_stock = $row_check['stock_no'];
        $num_part   = substr($last_stock, strlen($prefix));
        if (is_numeric($num_part)) {
            $next_num = intval($num_part) + 1;
        }
    }
    $stock_no = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO items (item, description, stock_no, unit_measurement, unit_value, balance_qty, item_type, date_acquired) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdiss", $item, $description, $stock_no, $unit_measurement, $unit_value, $balance_qty, $item_type, $date_acquired);

    if ($stmt->execute()) {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'New item added successfully',
                        icon: 'success'
                    }).then(() => { window.location.href = 'inventory_list.php'; });
                });
              </script>";
    } else {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to add item: " . addslashes($conn->error) . "',
                        icon: 'error'
                    });
                });
              </script>";
    }
}

// Stat counts
$low_stock_threshold = 10;
$count_total  = $conn->query("SELECT COUNT(*) FROM items")->fetch_row()[0];
$count_exp    = $conn->query("SELECT COUNT(*) FROM items WHERE item_type='Expendable'")->fetch_row()[0];
$count_nonexp = $conn->query("SELECT COUNT(*) FROM items WHERE item_type='Non-Expendable'")->fetch_row()[0];
$count_semi   = $conn->query("SELECT COUNT(*) FROM items WHERE item_type='Semi-Expendable'")->fetch_row()[0];
$count_low    = $conn->query("SELECT COUNT(*) FROM items WHERE status = 'Active' AND balance_qty > 0 AND balance_qty < $low_stock_threshold")->fetch_row()[0];
$count_out    = $conn->query("SELECT COUNT(*) FROM items WHERE status = 'Active' AND balance_qty = 0")->fetch_row()[0];

// Build items-for-select dropdown
$items_for_select = [];
$items_for_select_result = $conn->query("SELECT MIN(id) as id, item FROM items GROUP BY item ORDER BY item ASC");
if ($items_for_select_result && $items_for_select_result->num_rows > 0) {
    while ($row_item = $items_for_select_result->fetch_assoc()) {
        $items_for_select[] = $row_item;
    }
}

// Build description map for JS auto-fill
$description_map = [];
$desc_result = $conn->query("SELECT item, description, unit_measurement, unit_value, item_type FROM items WHERE description IS NOT NULL AND description <> ''");
if ($desc_result && $desc_result->num_rows > 0) {
    while ($row_desc = $desc_result->fetch_assoc()) {
        $key = $row_desc['item'];
        if ($row_desc['description'] === '') continue;
        if (!isset($description_map[$key])) $description_map[$key] = [];
        $description_map[$key][] = [
            'description'      => $row_desc['description'],
            'unit_measurement' => $row_desc['unit_measurement'],
            'unit_value'       => (float)$row_desc['unit_value'],
            'item_type'        => $row_desc['item_type'],
        ];
    }
}

// Fetch all items
$sql    = "SELECT * FROM items ORDER BY item ASC";
$result = $conn->query($sql);
$all_items = [];
while ($row = $result->fetch_assoc()) {
    $all_items[] = $row;
}
?>

<div class="main-content">
<div class="container-fluid py-2">

    <!-- ── Page Header ── -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-0.5">Inventory</p>
            <h1 class="text-2xl font-bold text-[#1a237e] leading-tight">Inventory Management</h1>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <i class="bi bi-box-seam text-blue-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_total; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Total Items</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-cyan-50 flex items-center justify-center shrink-0">
                <i class="bi bi-layers text-cyan-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_exp; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Expendable</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-triangle text-yellow-500 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_low; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Low in Stock (&lt;<?php echo (int)$low_stock_threshold; ?>)</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <i class="bi bi-x-circle text-red-400 text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $count_out; ?></p>
                <p class="text-xs text-gray-400 mt-1 font-medium">Out of Stock</p>
            </div>
        </div>

    </div>

    <!-- ── Inventory Table Card ── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

        <!-- Card Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-box-seam text-blue-500"></i>
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Inventory Items</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600 text-xs font-semibold ring-1 ring-blue-100">
                    <?php echo $count_total; ?> items
                </span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Type Filter -->
                <div class="flex items-center gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
                    <span class="px-3 py-2 bg-gray-50 text-gray-400 border-r border-gray-200">
                        <i class="bi bi-funnel text-sm"></i>
                    </span>
                    <select id="typeFilter" class="text-sm text-gray-600 px-3 py-2 bg-white focus:outline-none pr-8" style="min-width:160px;">
                        <option value="">All Types</option>
                        <option value="Expendable">Expendable</option>
                        <option value="Semi-Expendable">Semi-Expendable</option>
                        <option value="Non-Expendable">Non-Expendable</option>
                    </select>
                </div>
                <!-- Add Item Button -->
                <button type="button" onclick="openAddModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#1a237e] text-white text-sm font-semibold hover:bg-[#283593] transition shadow-sm">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm datatable" id="inventoryTable">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Stock No.</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Item</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Unit</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Unit Value</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Qty</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide no-sort">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($all_items as $row):

                        // Qty color
                        if ($row['balance_qty'] == 0)       $qty_class = 'text-red-600 font-bold';
                        elseif ($row['balance_qty'] < $low_stock_threshold)   $qty_class = 'text-yellow-500 font-bold';
                        else                                $qty_class = 'text-green-600 font-semibold';

                        // Type badge
                        $type_color = match($row['item_type']) {
                            'Expendable'      => 'bg-cyan-50 text-cyan-700 ring-cyan-100',
                            'Non-Expendable'  => 'bg-purple-50 text-purple-700 ring-purple-100',
                            'Semi-Expendable' => 'bg-orange-50 text-orange-700 ring-orange-100',
                            default           => 'bg-gray-100 text-gray-600 ring-gray-200',
                        };

                        // Status
                        if ($row['status'] == 'Active') {
                            if ($row['balance_qty'] == 0) {
                                $status_text  = 'Out of Stock';
                                $status_color = 'bg-red-50 text-red-600 ring-red-100';
                                $status_dot   = 'bg-red-500';
                            } elseif ($row['balance_qty'] < $low_stock_threshold) {
                                $status_text  = 'Low in Stock';
                                $status_color = 'bg-yellow-50 text-yellow-600 ring-yellow-100';
                                $status_dot   = 'bg-yellow-400';
                            } else {
                                $status_text  = 'In Stock';
                                $status_color = 'bg-green-50 text-green-600 ring-green-100';
                                $status_dot   = 'bg-green-500';
                            }
                        } else {
                            $status_text = $row['status'];
                            $status_color = match($row['status']) {
                                'Condemned'   => 'bg-red-50 text-red-600 ring-red-100',
                                'Transferred' => 'bg-yellow-50 text-yellow-600 ring-yellow-100',
                                'Lost'        => 'bg-gray-100 text-gray-500 ring-gray-200',
                                default       => 'bg-gray-100 text-gray-500 ring-gray-200',
                            };
                            $status_dot = match($row['status']) {
                                'Condemned'   => 'bg-red-400',
                                'Transferred' => 'bg-yellow-400',
                                default       => 'bg-gray-400',
                            };
                        }
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">

                        <!-- Stock No -->
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="font-mono text-xs font-bold text-[#1a237e] bg-blue-50 px-2.5 py-1 rounded-md">
                                <?php echo htmlspecialchars($row['stock_no']); ?>
                            </span>
                        </td>

                        <!-- Item -->
                        <td class="px-4 py-3">
                            <span class="font-semibold text-gray-800 text-sm">
                                <?php echo htmlspecialchars($row['item']); ?>
                            </span>
                        </td>

                        <!-- Description -->
                        <td class="px-4 py-3 max-w-[200px]">
                            <span class="text-xs text-gray-500 line-clamp-2">
                                <?php echo htmlspecialchars(substr($row['description'], 0, 60)) . (strlen($row['description']) > 60 ? '…' : ''); ?>
                            </span>
                        </td>

                        <!-- Unit -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($row['unit_measurement']); ?></span>
                        </td>

                        <!-- Unit Value -->
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-700">
                                ₱<?php echo number_format($row['unit_value'], 2); ?>
                            </span>
                        </td>

                        <!-- Qty -->
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm <?php echo $qty_class; ?>">
                                <?php echo $row['balance_qty']; ?>
                            </span>
                        </td>

                        <!-- Type -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 <?php echo $type_color; ?>">
                                <?php echo htmlspecialchars($row['item_type']); ?>
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 <?php echo $status_color; ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo $status_dot; ?> shrink-0"></span>
                                <?php echo $status_text; ?>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <a href="edit_item.php?id=<?php echo $row['id']; ?>"
                                   title="Edit"
                                   class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition">
                                    <i class="bi bi-pencil text-xs"></i>
                                </a>
                                <a href="stock_card.php?id=<?php echo $row['id']; ?>"
                                   title="History"
                                   class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-cyan-300 hover:text-cyan-600 hover:bg-cyan-50 transition">
                                    <i class="bi bi-clock-history text-xs"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $row['id']; ?>)"
                                        title="Delete"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition">
                                    <i class="bi bi-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($all_items)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2">
                                <i class="bi bi-inbox text-4xl text-gray-300"></i>
                                <p class="text-sm font-semibold text-gray-400">No items found</p>
                                <p class="text-xs text-gray-400">Click Add Item to get started.</p>
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

<!-- ══════════════════════════════════════
     Add Item Modal (Tailwind)
══════════════════════════════════════ -->
<div id="addItemModal" class="fixed inset-0 flex items-center justify-center hidden" style="z-index: 1100;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeAddModal()"></div>

    <!-- Panel -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">

        <form method="POST" action="" id="addItemForm">
            <input type="hidden" name="action" value="add_item">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-50 text-[#1a237e] flex items-center justify-center shrink-0">
                        <i class="bi bi-plus-lg text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800 leading-tight">Add New Item</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Create a new stock card with complete item details.</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddModal()"
                        class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Left: Item + Description -->
                    <div class="flex flex-col gap-4">

                        <!-- Item / Article -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Item / Article <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition bg-white">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-[#1a237e]">
                                    <i class="bi bi-box-seam text-sm"></i>
                                </span>
                                <select id="item_select" name="existing_item_id"
                                        class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                                    <option value="">Select an item from inventory</option>
                                    <?php foreach ($items_for_select as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['item']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__new__">+ Add new item…</option>
                                </select>
                            </div>
                            <!-- New item text input -->
                            <input type="text" id="item" name="item"
                                   placeholder="Enter new item name"
                                   class="hidden mt-2 w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <!-- Existing descriptions dropdown -->
                            <select id="description_select"
                                    class="hidden w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition mb-2">
                                <option value="">-- Select existing description --</option>
                            </select>
                            <textarea id="description" name="description" rows="4" required
                                      placeholder="Detailed description, specifications, or serial number"
                                      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-300 transition resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Right: Other fields -->
                    <div class="grid grid-cols-2 gap-4 content-start">

                        <!-- Unit of Measurement -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Unit of Measurement
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-400">
                                    <i class="bi bi-tag text-sm"></i>
                                </span>
                                <input type="text" id="unit_measurement" name="unit_measurement"
                                       placeholder="e.g. pc, ream"
                                       class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                            </div>
                        </div>

                        <!-- Unit Value -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Unit Value <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-500 text-sm font-medium">₱</span>
                                <input type="number" step="0.01" id="unit_value" name="unit_value" required
                                       placeholder="0.00"
                                       class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                            </div>
                        </div>

                        <!-- Initial Quantity -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Initial Quantity <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-400">
                                    <i class="bi bi-stack text-sm"></i>
                                </span>
                                <input type="number" id="balance_qty" name="balance_qty" required min="0" value="0"
                                       class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                            </div>
                        </div>

                        <!-- Item Type -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Item Type <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-400">
                                    <i class="bi bi-diagram-3 text-sm"></i>
                                </span>
                                <select id="item_type" name="item_type" required
                                        class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                                    <option value="Expendable">Expendable</option>
                                    <option value="Semi-Expendable">Semi-Expendable</option>
                                    <option value="Non-Expendable">Non-Expendable</option>
                                </select>
                            </div>
                        </div>

                        <!-- Date Acquired -->
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                                Date Acquired
                            </label>
                            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-300 focus-within:border-blue-300 transition">
                                <span class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-400">
                                    <i class="bi bi-calendar-event text-sm"></i>
                                </span>
                                <input type="date" id="date_acquired" name="date_acquired"
                                       value="<?php echo date('Y-m-d'); ?>"
                                       class="flex-1 px-3 py-2.5 text-sm text-gray-700 bg-white focus:outline-none">
                            </div>
                        </div>

                    </div><!-- end right grid -->
                </div><!-- end main grid -->
            </div><!-- end body -->

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 font-medium hover:bg-gray-100 transition">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-[#1a237e] text-white text-sm font-semibold hover:bg-[#283593] transition shadow-sm">
                    <i class="bi bi-save me-1"></i> Save Item
                </button>
            </div>

        </form>
    </div><!-- end panel -->
</div><!-- end modal -->

<?php include 'admin_footer.php'; ?>

<script>
// ── Modal open / close ──────────────────────────────────
function openAddModal() {
    document.getElementById('addItemModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeAddModal() {
    document.getElementById('addItemModal').classList.add('hidden');
    document.body.style.overflow = '';
    resetAddModal();
}

// ── Description map from PHP ────────────────────────────
const itemDescriptionMap = <?php echo json_encode($description_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const itemSelect        = document.getElementById('item_select');
    const itemInput         = document.getElementById('item');
    const descriptionSelect = document.getElementById('description_select');
    const descriptionArea   = document.getElementById('description');
    const unitInput         = document.getElementById('unit_measurement');
    const unitValueInput    = document.getElementById('unit_value');
    const itemTypeSelect    = document.getElementById('item_type');

    function loadDescriptions() {
        descriptionSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Select existing description --';
        descriptionSelect.appendChild(placeholder);

        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const key  = selectedOption ? selectedOption.text.trim() : '';
        const list = (key && itemDescriptionMap[key]) ? itemDescriptionMap[key] : [];

        list.forEach(function (entry, index) {
            const opt = document.createElement('option');
            opt.value = String(index);
            opt.textContent = entry.description;
            descriptionSelect.appendChild(opt);
        });

        const customOpt = document.createElement('option');
        customOpt.value = '__custom__';
        customOpt.textContent = 'Custom description…';
        descriptionSelect.appendChild(customOpt);
    }

    function updateVisibility() {
        const val         = itemSelect.value;
        const isNew       = val === '__new__';
        const hasExisting = val && !isNew;

        if (isNew) {
            itemInput.classList.remove('hidden');
            itemInput.disabled = false;
        } else {
            itemInput.classList.add('hidden');
            itemInput.disabled = true;
            itemInput.value = '';
        }

        if (hasExisting) {
            loadDescriptions();
            descriptionSelect.classList.remove('hidden');
            descriptionArea.classList.add('hidden');
            descriptionArea.value = '';
        } else if (isNew) {
            descriptionSelect.classList.add('hidden');
            descriptionArea.classList.remove('hidden');
        } else {
            descriptionSelect.classList.add('hidden');
            descriptionArea.classList.remove('hidden');
            descriptionArea.value = '';
        }
    }

    itemSelect.addEventListener('change', updateVisibility);
    updateVisibility();

    descriptionSelect.addEventListener('change', function () {
        const val = descriptionSelect.value;
        if (val === '__custom__') {
            descriptionArea.classList.remove('hidden');
            descriptionArea.value = '';
            descriptionArea.focus();
            return;
        }
        if (val === '' || val === null) {
            descriptionArea.classList.add('hidden');
            descriptionArea.value = '';
            return;
        }
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const key   = selectedOption ? selectedOption.text.trim() : '';
        const list  = (key && itemDescriptionMap[key]) ? itemDescriptionMap[key] : [];
        const index = parseInt(val, 10);
        const entry = !isNaN(index) ? list[index] : null;

        if (entry) {
            descriptionArea.value = entry.description || '';
            descriptionArea.classList.add('hidden');
            if (entry.unit_measurement != null) unitInput.value      = entry.unit_measurement;
            if (entry.unit_value       != null) unitValueInput.value = entry.unit_value;
            if (entry.item_type)                itemTypeSelect.value = entry.item_type;
        } else {
            descriptionArea.value = '';
            descriptionArea.classList.remove('hidden');
        }
    });

    // AJAX unit suggestion
    let suggestTimeout = null;
    itemInput.addEventListener('input', function () {
        const value = itemInput.value.trim();
        clearTimeout(suggestTimeout);
        if (value.length < 3) return;
        suggestTimeout = setTimeout(function () {
            const params = new URLSearchParams({ ajax: 'suggest_unit', item: value });
            fetch('inventory_list.php?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => { if (data && data.unit) unitInput.value = data.unit; })
            .catch(() => {});
        }, 300);
    });
});

// ── Reset modal fields ──────────────────────────────────
function resetAddModal() {
    const itemSelect        = document.getElementById('item_select');
    const itemInput         = document.getElementById('item');
    const descriptionSelect = document.getElementById('description_select');
    const descriptionArea   = document.getElementById('description');

    itemSelect.value = '';
    itemInput.value  = '';
    itemInput.classList.add('hidden');
    itemInput.disabled = true;
    descriptionSelect.classList.add('hidden');
    descriptionArea.classList.remove('hidden');
    descriptionArea.value = '';
    document.getElementById('unit_measurement').value = '';
    document.getElementById('unit_value').value       = '';
    document.getElementById('balance_qty').value      = '0';
    document.getElementById('item_type').value        = 'Expendable';
    document.getElementById('date_acquired').value    = '<?php echo date("Y-m-d"); ?>';
}

// ── Type filter → DataTables column 6 ──────────────────
document.addEventListener('DOMContentLoaded', function () {
    var typeFilter = document.getElementById('typeFilter');
    if (typeFilter) {
        typeFilter.addEventListener('change', function () {
            var table = $('.datatable').DataTable();
            var val   = $.fn.dataTable.util.escapeRegex(this.value);
            table.column(6).search(val ? '^' + val + '$' : '', true, false).draw();
        });
    }
});

// ── Delete confirmation ─────────────────────────────────
function confirmDelete(id) {
    Swal.fire({
        title: 'Delete this item?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete_item.php?id=' + id;
        }
    });
}
</script>
