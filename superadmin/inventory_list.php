<?php
require_once 'superadmin_sidebar.php';
include '../plugins/conn.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'suggest_unit') {
    header('Content-Type: application/json');

    $itemName = isset($_GET['item']) ? trim(strtolower($_GET['item'])) : '';

    $default_units = [
        'bond paper' => 'ream',
        'a4 bond paper' => 'ream',
        'short bond paper' => 'ream',
        'long bond paper' => 'ream',
        'bond' => 'ream',
        'ballpen' => 'piece',
        'ball pen' => 'piece',
        'folder' => 'piece',
        'stapler' => 'piece',
        'ink cartridge' => 'piece',
        'printer ink' => 'bottle',
        'marker' => 'piece',
        'notebook' => 'piece',
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

    echo json_encode([
        'unit' => $suggested,
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_item') {
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $item = $_POST['item'];
    $description = $_POST['description'];
    $unit_measurement = $_POST['unit_measurement'];
    $unit_value = $_POST['unit_value'];
    $balance_qty = $_POST['balance_qty'];
    $item_type = $_POST['item_type'];
    $date_acquired = $_POST['date_acquired'];

    if ($item_id > 0) {
        $stmt = $conn->prepare("UPDATE items SET item = ?, description = ?, unit_measurement = ?, unit_value = ?, balance_qty = ?, item_type = ?, date_acquired = ? WHERE id = ?");
        $stmt->bind_param("sssdissi", $item, $description, $unit_measurement, $unit_value, $balance_qty, $item_type, $date_acquired, $item_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Item updated successfully';
            session_write_close();
            header("Location: inventory_list.php");
            exit();
        } else {
            $_SESSION['error'] = 'Failed to update item: ' . $conn->error;
            session_write_close();
            header("Location: inventory_list.php");
            exit();
        }
    }
}

$page_title = 'Inventory Management';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';

$add_status = isset($_GET['add_status']) ? $_GET['add_status'] : null;

$history_data = [];
$history_sql = "SELECT item_id, transaction_date, transaction_type, quantity, balance_after, remarks FROM inventory_transactions ORDER BY transaction_date DESC";
$history_result = $conn->query($history_sql);
if ($history_result && $history_result->num_rows > 0) {
    while ($tx = $history_result->fetch_assoc()) {
        $itemId = (int)$tx['item_id'];
        if (!isset($history_data[$itemId])) {
            $history_data[$itemId] = [];
        }
        $history_data[$itemId][] = $tx;
    }
}

$items_for_select = [];
$items_for_select_result = $conn->query("SELECT MIN(id) as id, item FROM items GROUP BY item ORDER BY item ASC");
if ($items_for_select_result && $items_for_select_result->num_rows > 0) {
    while ($row_item = $items_for_select_result->fetch_assoc()) {
        $items_for_select[] = $row_item;
    }
}

$description_map = [];
$desc_result = $conn->query("SELECT item, description, unit_measurement, unit_value, item_type FROM items WHERE description IS NOT NULL AND description <> ''");
if ($desc_result && $desc_result->num_rows > 0) {
    while ($row_desc = $desc_result->fetch_assoc()) {
        $key = $row_desc['item'];
        $val = $row_desc['description'];
        if ($val === '') {
            continue;
        }
        if (!isset($description_map[$key])) {
            $description_map[$key] = [];
        }
        $description_map[$key][] = [
            'description' => $val,
            'unit_measurement' => $row_desc['unit_measurement'],
            'unit_value' => (float)$row_desc['unit_value'],
            'item_type' => $row_desc['item_type'],
        ];
    }
}
?>

<style>
    body {
        font-size: 1.05rem;
    }
    .table td,
    .table th {
        vertical-align: middle;
        font-size: 1.05rem;
    }
    .status-badge {
        font-size: 0.95em;
        padding: 0.6em 0.9em;
    }
    .action-menu-toggle {
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
    }
    .action-menu-toggle::after {
        display: none !important;
    }
    .modal-content {
        font-size: 1.05rem;
    }
    .modal-title {
        font-size: 1.4rem;
    }
    .form-label {
        font-size: 1.05rem;
    }
    .form-control,
    .form-select,
    .input-group-text,
    .btn {
        font-size: 1.05rem;
    }
    h4 {
        font-size: 1.6rem;
    }
</style>
<div class="main-content">
    <div class="container-fluid">

        <div class="card shadow-sm">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center bg-white py-3 border-bottom gap-2">
                <h4 class="mb-0 text-primary fw-bold">
                    <i class="bi bi-box-seam me-2"></i>Inventory Items
                </h4>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="input-group" style="width:220px;">
                        <span class="input-group-text bg-white text-muted border-end-0">
                            <i class="bi bi-funnel"></i>
                        </span>
                        <select id="typeFilter" class="form-select border-start-0 ps-0" style="font-size:0.9rem;">
                            <option value="">All Types</option>
                            <option value="Expendable">Expendable</option>
                            <option value="Semi-Expendable">Semi-Expendable</option>
                            <option value="Non-Expendable">Non-Expendable</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="bi bi-plus-lg me-2"></i> Add Item
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="p-3">
                    <table class="table table-hover table-bordered align-middle datatable w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Stock No.</th>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Unit of Measurement</th>
                                <th>Unit Value</th>
                                <th>Balance Qty</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="no-sort">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM items ORDER BY item ASC";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['stock_no']) . "</td>";
                                    echo "<td class='fw-bold'>" . htmlspecialchars($row['item']) . "</td>";
                                    echo "<td>" . htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['unit_measurement']) . "</td>";
                                    echo "<td>₱" . number_format($row['unit_value'], 2) . "</td>";

                                    // Stock Level Logic
                                    $stock_class = 'text-success';
                                    if($row['balance_qty'] < 5) $stock_class = 'text-danger fw-bold';
                                    elseif($row['balance_qty'] < 15) $stock_class = 'text-warning fw-bold';

                                    echo "<td class='$stock_class'>" . $row['balance_qty'] . "</td>";
                                    echo "<td><span class='badge bg-info text-dark'>" . $row['item_type'] . "</span></td>";

                                    $status_text = $row['status'];
                                    $status_badge = 'bg-secondary';

                                    if ($row['status'] == 'Active') {
                                        if ($row['balance_qty'] == 0) {
                                            $status_text = 'Out of Stocks';
                                            $status_badge = 'bg-danger';
                                        } elseif ($row['balance_qty'] < 15) {
                                            $status_text = 'Low in Stocks';
                                            $status_badge = 'bg-warning text-dark';
                                        } else {
                                            $status_text = 'In Stocks';
                                            $status_badge = 'bg-success';
                                        }
                                    } else {
                                        if($row['status'] == 'Condemned') $status_badge = 'bg-danger';
                                        if($row['status'] == 'Transferred') $status_badge = 'bg-warning text-dark';
                                        if($row['status'] == 'Lost') $status_badge = 'bg-secondary';
                                    }

                                    echo "<td><span class='badge $status_badge'>" . $status_text . "</span></td>";
                                    echo "<td>
                                            <div class='dropdown'>
                                                <button class='btn btn-light btn-sm border-0 action-menu-toggle' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                                                    <i class='bi bi-three-dots-vertical'></i>
                                                </button>
                                                <ul class='dropdown-menu dropdown-menu-end shadow-sm'>
                                                    <li>
                                                        <button type='button'
                                                                class='dropdown-item'
                                                                onclick='openEditItemModal(this)'
                                                                data-id='" . $row['id'] . "'
                                                                data-item=\"" . htmlspecialchars($row['item'], ENT_QUOTES) . "\"
                                                                data-description=\"" . htmlspecialchars($row['description'], ENT_QUOTES) . "\"
                                                                data-unit_measurement=\"" . htmlspecialchars($row['unit_measurement'], ENT_QUOTES) . "\"
                                                                data-unit_value='" . $row['unit_value'] . "'
                                                                data-balance_qty='" . $row['balance_qty'] . "'
                                                                data-item_type=\"" . htmlspecialchars($row['item_type'], ENT_QUOTES) . "\"
                                                                data-date_acquired='" . $row['date_acquired'] . "'>
                                                            <i class='bi bi-pencil me-2'></i>Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type='button' class='dropdown-item' onclick='openHistoryModal(" . $row['id'] . ", \"" . htmlspecialchars($row['item'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['item_type'], ENT_QUOTES) . "\")'>
                                                            <i class='bi bi-clock-history me-2'></i>History
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type='button' class='dropdown-item' onclick='openPrintModal(" . $row['id'] . ", \"" . htmlspecialchars($row['item'], ENT_QUOTES) . "\")'>
                                                            <i class='bi bi-printer me-2'></i>Print form
                                                        </button>
                                                    </li>
                                                    <li><hr class='dropdown-divider'></li>
                                                    <li>
                                                        <button type='button' class='dropdown-item text-danger' onclick='confirmDelete(" . $row['id'] . ")'>
                                                            <i class='bi bi-trash me-2'></i>Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 rounded-4 shadow-lg modal-elevated">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Add New Item</h5>
                        <div class="text-muted small">Create a new stock card with complete item details.</div>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="add_item.php">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-body pt-0 pb-4 px-4">
                    <div class="row g-4">
                        <div class="col-12 col-lg-5">
                            <label class="form-label fw-semibold">Item / Article <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg flex-column flex-sm-row">
                                <span class="input-group-text bg-white border-end-0 d-none d-sm-flex">
                                    <i class="bi bi-box-seam text-primary"></i>
                                </span>
                                <select class="form-select mb-2 mb-sm-0 border-start-0 flex-grow-1" id="item_select" name="existing_item_id">
                                    <option value="">Select an item from inventory</option>
                                    <?php if (!empty($items_for_select)): ?>
                                        <?php foreach ($items_for_select as $opt): ?>
                                            <option value="<?php echo (int)$opt['id']; ?>">
                                                <?php echo htmlspecialchars($opt['item']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <option value="__new__">+ Add new item…</option>
                                </select>
                            </div>
                            <input type="text" class="form-control mt-2 d-none" id="item" name="item" placeholder="Enter new item name">

                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold mb-0">Description <span class="text-danger">*</span></label>
                                </div>
                                <select class="form-select mb-2 d-none" id="description_select">
                                    <option value="">-- Select existing description --</option>
                                </select>
                                <textarea class="form-control" id="description" name="description" rows="2" required placeholder="Detailed description, specifications, or serial number"></textarea>
                            </div>
                        </div>

                        <div class="col-12 col-lg-7 ms-lg-auto">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="unit_measurement" class="form-label fw-semibold">Unit of Measurement</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="bi bi-tag"></i>
                                        </span>
                                        <input type="text" class="form-control" id="unit_measurement" name="unit_measurement" placeholder="e.g. pc, box, unit, ream">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="unit_value" class="form-label fw-semibold">Unit Value <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">₱</span>
                                        <input type="number" step="0.01" class="form-control" id="unit_value" name="unit_value" required placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="balance_qty" class="form-label fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="bi bi-stack"></i>
                                        </span>
                                        <input type="number" class="form-control" id="balance_qty" name="balance_qty" required min="0" value="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="item_type" class="form-label fw-semibold">Item Type <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="bi bi-diagram-3"></i>
                                        </span>
                                        <select class="form-select" id="item_type" name="item_type" required>
                                            <option value="Expendable">Expendable</option>
                                            <option value="Semi-Expendable">Semi-Expendable</option>
                                            <option value="Non-Expendable">Non-Expendable</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_acquired" class="form-label fw-semibold">Date Acquired</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="bi bi-calendar-event"></i>
                                        </span>
                                        <input type="date" class="form-control" id="date_acquired" name="date_acquired" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light border-0 me-2" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <span class="d-inline-flex align-items-center gap-1">
                            <i class="bi bi-save"></i>
                            <span>Save Item</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_item">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="edit_item" class="form-label">Item / Article <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_item" name="item" required>
                        </div>

                        <div class="col-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_unit_measurement" class="form-label">Unit of Measurement</label>
                            <input type="text" class="form-control" id="edit_unit_measurement" name="unit_measurement">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_unit_value" class="form-label">Unit Value <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="edit_unit_value" name="unit_value" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_balance_qty" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_balance_qty" name="balance_qty" required min="0">
                        </div>

                        <div class="col-md-6">
                            <label for="edit_item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_item_type" name="item_type" required>
                                <option value="Expendable">Expendable</option>
                                <option value="Semi-Expendable">Semi-Expendable</option>
                                <option value="Non-Expendable">Non-Expendable</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date_acquired" class="form-label">Date Acquired</label>
                            <input type="date" class="form-control" id="edit_date_acquired" name="date_acquired">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Item on Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Select the form layout to print for <strong id="printItemName"></strong>:</p>
                <input type="hidden" id="printItemId">
                <div class="d-grid gap-2">
                    <button onclick="printForm('RIS')" class="btn btn-outline-primary text-start">
                        <i class="bi bi-file-earmark-text me-2"></i> Requisition and Issue Slip (RIS)
                    </button>
                    <button onclick="printForm('ICS')" class="btn btn-outline-info text-start">
                        <i class="bi bi-file-earmark-person me-2"></i> Inventory Custodian Slip (ICS)
                    </button>
                    <button onclick="printForm('PAR')" class="btn btn-outline-success text-start">
                        <i class="bi bi-file-earmark-check me-2"></i> Property Acknowledgment Receipt (PAR)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Item History - <span id="historyItemName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historyEmptyState" class="text-center text-muted py-4 d-none">
                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                    <div>No history recorded for this item yet.</div>
                </div>
                <div id="historyTableWrapper" class="table-responsive d-none">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 20%;">Type</th>
                                <th style="width: 15%;">Quantity</th>
                                <th style="width: 15%;">Balance After</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.modal-elevated {
    transform: translateY(8px);
    transition: transform 0.18s ease-out, box-shadow 0.18s ease-out;
}
.modal.show .modal-elevated {
    transform: translateY(0);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
}
.modal-elevated .form-control:focus,
.modal-elevated .form-select:focus {
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.18);
}
</style>

<script>
const itemHistoryData = <?php echo json_encode($history_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const itemDescriptionMap = <?php echo json_encode($description_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

document.addEventListener('DOMContentLoaded', function () {
    var itemInput = document.getElementById('item');
    var unitInput = document.getElementById('unit_measurement');
    var itemSelect = document.getElementById('item_select');
    var descriptionSelect = document.getElementById('description_select');
    var descriptionTextarea = document.getElementById('description');
    var unitValueInput = document.getElementById('unit_value');
    var itemTypeSelect = document.getElementById('item_type');

    function loadDescriptionsForSelectedItem() {
        if (!descriptionSelect || !itemSelect) {
            return;
        }

        descriptionSelect.innerHTML = '';

        var placeholderOpt = document.createElement('option');
        placeholderOpt.value = '';
        placeholderOpt.textContent = '-- Select existing description --';
        descriptionSelect.appendChild(placeholderOpt);

        var selectedOption = itemSelect.options[itemSelect.selectedIndex];
        var key = selectedOption ? selectedOption.text : '';
        var list = (key && itemDescriptionMap && itemDescriptionMap[key]) ? itemDescriptionMap[key] : [];

        list.forEach(function (entry, index) {
            var opt = document.createElement('option');
            opt.value = String(index);
            opt.textContent = entry.description;
            descriptionSelect.appendChild(opt);
        });

        var customOpt = document.createElement('option');
        customOpt.value = '__custom__';
        customOpt.textContent = 'Custom description…';
        descriptionSelect.appendChild(customOpt);
    }

    if (itemSelect && itemInput) {
        function updateItemInputVisibility() {
            var isNew = itemSelect.value === '__new__';

            if (isNew) {
                itemInput.classList.remove('d-none');
                itemInput.disabled = false;

                if (descriptionSelect) {
                    descriptionSelect.classList.add('d-none');
                }
                if (descriptionTextarea) {
                    descriptionTextarea.classList.remove('d-none');
                }
            } else if (itemSelect.value) {
                itemInput.classList.add('d-none');
                itemInput.disabled = true;

                if (descriptionSelect) {
                    descriptionSelect.classList.remove('d-none');
                    loadDescriptionsForSelectedItem();
                }
                if (descriptionTextarea) {
                    descriptionTextarea.classList.add('d-none');
                }
            } else {
                itemInput.classList.add('d-none');
                itemInput.disabled = true;

                if (descriptionSelect) {
                    descriptionSelect.classList.add('d-none');
                }
                if (descriptionTextarea) {
                    descriptionTextarea.classList.remove('d-none');
                }
            }
        }

        itemSelect.addEventListener('change', updateItemInputVisibility);
        updateItemInputVisibility();
    }

    if (descriptionSelect && descriptionTextarea) {
        descriptionSelect.addEventListener('change', function () {
            var val = descriptionSelect.value;
            if (val === '__custom__') {
                descriptionTextarea.classList.remove('d-none');
                descriptionTextarea.value = '';
                descriptionTextarea.focus();
            } else if (val !== '') {
                var selectedOption = itemSelect.options[itemSelect.selectedIndex];
                var key = selectedOption ? selectedOption.text : '';
                var list = (key && itemDescriptionMap && itemDescriptionMap[key]) ? itemDescriptionMap[key] : [];
                var index = parseInt(val, 10);
                var entry = isNaN(index) ? null : list[index];

                if (entry) {
                    descriptionTextarea.value = entry.description || '';
                    descriptionTextarea.classList.add('d-none');

                    if (unitInput && entry.unit_measurement !== undefined && entry.unit_measurement !== null) {
                        unitInput.value = entry.unit_measurement;
                    }
                    if (unitValueInput && entry.unit_value !== undefined && entry.unit_value !== null) {
                        unitValueInput.value = entry.unit_value;
                    }
                    if (itemTypeSelect && entry.item_type) {
                        itemTypeSelect.value = entry.item_type;
                    }
                } else {
                    descriptionTextarea.value = '';
                    descriptionTextarea.classList.remove('d-none');
                }
            } else {
                descriptionTextarea.classList.add('d-none');
            }
        });
    }

    if (itemInput && unitInput) {
        var suggestTimeout = null;

        itemInput.addEventListener('input', function () {
            var value = itemInput.value.trim();
            if (suggestTimeout) {
                clearTimeout(suggestTimeout);
            }
            if (value.length < 3) {
                return;
            }
            suggestTimeout = setTimeout(function () {
                var params = new URLSearchParams();
                params.append('ajax', 'suggest_unit');
                params.append('item', value);

                fetch('inventory_list.php?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.unit) {
                        unitInput.value = data.unit;
                    }
                })
                .catch(function () {});
            }, 300);
        });
    }
});

// Type filter wired to DataTables column 6
document.addEventListener('DOMContentLoaded', function () {
    var typeFilter = document.getElementById('typeFilter');
    if (typeFilter) {
        typeFilter.addEventListener('change', function () {
            var table = $('.datatable').DataTable();
            var val = $.fn.dataTable.util.escapeRegex(this.value);
            table.column(6).search(val ? '^' + val + '$' : '', true, false).draw();
        });
    }
});

function openPrintModal(id, name) {
    document.getElementById('printItemId').value = id;
    document.getElementById('printItemName').innerText = name;
    var myModal = new bootstrap.Modal(document.getElementById('printModal'));
    myModal.show();
}

function printForm(type) {
    var id = document.getElementById('printItemId').value;
    window.open('print_report.php?source=item&id=' + id + '&type=' + type, '_blank');
}

function openHistoryModal(itemId, itemName, itemType) {
    var modalEl = document.getElementById('historyModal');
    var titleEl = document.getElementById('historyItemName');
    var bodyEl = document.getElementById('historyTableBody');
    var emptyEl = document.getElementById('historyEmptyState');
    var wrapperEl = document.getElementById('historyTableWrapper');

    if (!modalEl || !titleEl || !bodyEl || !emptyEl || !wrapperEl) {
        return;
    }

    titleEl.textContent = itemName;
    bodyEl.innerHTML = '';

    var history = (itemHistoryData && itemHistoryData[itemId]) ? itemHistoryData[itemId] : [];

    if (!history || history.length === 0) {
        emptyEl.classList.remove('d-none');
        wrapperEl.classList.add('d-none');
    } else {
        emptyEl.classList.add('d-none');
        wrapperEl.classList.remove('d-none');

        history.forEach(function(tx) {
            var tr = document.createElement('tr');

            var tdDate = document.createElement('td');
            tdDate.textContent = tx.transaction_date || '';

            var tdType = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'badge rounded-pill ';
            if (itemType === 'Expendable') {
                badge.className += 'bg-success';
            } else if (itemType === 'Semi-Expendable') {
                badge.className += 'bg-warning text-dark';
            } else if (itemType === 'Non-Expendable') {
                badge.className += 'bg-info text-dark';
            } else {
                badge.className += 'bg-secondary';
            }
            badge.textContent = itemType || '';
            tdType.appendChild(badge);

            var tdQty = document.createElement('td');
            tdQty.textContent = (tx.quantity !== undefined && tx.quantity !== null) ? tx.quantity : '';

            var tdBalance = document.createElement('td');
            tdBalance.textContent = (tx.balance_after !== undefined && tx.balance_after !== null) ? tx.balance_after : '';

            var tdRemarks = document.createElement('td');
            var small = document.createElement('small');
            small.className = 'text-muted';
            small.textContent = tx.remarks || '';
            tdRemarks.appendChild(small);

            tr.appendChild(tdDate);
            tr.appendChild(tdType);
            tr.appendChild(tdQty);
            tr.appendChild(tdBalance);
            tr.appendChild(tdRemarks);

            bodyEl.appendChild(tr);
        });
    }

    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete_item.php?id=' + id;
        }
    })
}

function openEditItemModal(button) {
    var id = button.getAttribute('data-id');
    var item = button.getAttribute('data-item') || '';
    var description = button.getAttribute('data-description') || '';
    var unitMeasurement = button.getAttribute('data-unit_measurement') || '';
    var unitValue = button.getAttribute('data-unit_value') || '';
    var balanceQty = button.getAttribute('data-balance_qty') || '';
    var itemType = button.getAttribute('data-item_type') || '';
    var dateAcquired = button.getAttribute('data-date_acquired') || '';

    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_item').value = item;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_unit_measurement').value = unitMeasurement;
    document.getElementById('edit_unit_value').value = unitValue;
    document.getElementById('edit_balance_qty').value = balanceQty;
    document.getElementById('edit_item_type').value = itemType;
    document.getElementById('edit_date_acquired').value = dateAcquired;

    var modal = new bootstrap.Modal(document.getElementById('editItemModal'));
    modal.show();
}
</script>

<?php renderFooter(); ?>
