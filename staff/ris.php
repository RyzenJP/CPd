<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "RIS  Requests";
require_once '../plugins/conn.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

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

$has_deleted_at = false;

if ($result = $conn->query("SELECT * FROM requests LIMIT 0")) {
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        if ($field->name === 'deleted_at') {
            $has_deleted_at = true;
            break;
        }
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'history_filter') {
    header('Content-Type: application/json');

    $period = isset($_GET['period']) ? trim($_GET['period']) : 'all';
    $item_type = isset($_GET['item_type']) ? trim($_GET['item_type']) : 'all';

    $allowed_periods = ['all', 'daily', 'weekly', 'range', 'yearly'];
    if (!in_array($period, $allowed_periods, true)) {
        $period = 'all';
    }

    $allowed_item_types = ['Expendable', 'Semi-Expendable', 'Non-Expendable'];
    if ($item_type !== 'all' && !in_array($item_type, $allowed_item_types, true)) {
        $item_type = 'all';
    }

    $filter_error = '';

    $normalizeDate = function ($value) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        return $value;
    };

    $start_date = null;
    $end_date = null;
    $period_label = '';
    $item_type_label = '';

    if ($period === 'daily') {
        $raw = isset($_GET['date_daily']) ? $_GET['date_daily'] : '';
        $start_date = $normalizeDate($raw);
        if (!$start_date) {
            $filter_error = 'Please select a valid date for the daily filter.';
        } else {
            $end_date = $start_date;
            $period_label = 'Daily: ' . date('M d, Y', strtotime($start_date));
        }
    } elseif ($period === 'weekly') {
        $raw_start = isset($_GET['week_start']) ? $_GET['week_start'] : '';
        $raw_end = isset($_GET['week_end']) ? $_GET['week_end'] : '';
        $start_date = $normalizeDate($raw_start);
        $end_date = $normalizeDate($raw_end);
        if (!$start_date || !$end_date) {
            $filter_error = 'Please select both start and end dates for the weekly filter.';
        } elseif ($start_date > $end_date) {
            $filter_error = 'The weekly start date cannot be after the end date.';
        } else {
            $period_label = 'Week: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
        }
    } elseif ($period === 'range') {
        $raw_from = isset($_GET['range_from']) ? $_GET['range_from'] : '';
        $raw_to = isset($_GET['range_to']) ? $_GET['range_to'] : '';
        $start_date = $normalizeDate($raw_from);
        $end_date = $normalizeDate($raw_to);
        if (!$start_date || !$end_date) {
            $filter_error = 'Please select both start and end dates for the date range filter.';
        } elseif ($start_date > $end_date) {
            $filter_error = 'The range start date cannot be after the end date.';
        } else {
            $period_label = 'Date range: ' . date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
        }
    } elseif ($period === 'yearly') {
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        if ($year < 2000 || $year > 2100) {
            $filter_error = 'Please select a valid year for the yearly filter.';
        } else {
            $start_date = $year . '-01-01';
            $end_date = $year . '-12-31';
            $period_label = 'Year: ' . $year;
        }
    }

    if ($item_type !== 'all') {
        $item_type_label = $item_type;
    }

    $rows_html = '';
    $records_count = 0;

    if ($filter_error === '') {
        $history_sql = "SELECT 
                            r.id AS request_id,
                            r.ris_no,
                            r.request_date,
                            r.approved_date,
                            r.created_at,
                            r.purpose,
                            r.status,
                            ri.quantity_requested,
                            i.item,
                            i.description,
                            i.unit_measurement,
                            i.item_type
                       FROM requests r
                       INNER JOIN request_items ri ON r.id = ri.request_id
                       INNER JOIN items i ON ri.item_id = i.id
                       WHERE r.requested_by = ?";

        $types = "s";
        $params = [$full_name];

        if ($has_deleted_at) {
            $history_sql .= " AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')";
        }

        $history_sql .= " AND r.status <> 'Pending'";

        if ($item_type !== 'all') {
            $history_sql .= " AND i.item_type = ?";
            $types .= "s";
            $params[] = $item_type;
        }

        if ($period !== 'all' && $start_date && $end_date) {
            $history_sql .= " AND r.request_date BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $history_sql .= " ORDER BY r.request_date DESC, r.ris_no, i.item";

        if ($stmt = $conn->prepare($history_sql)) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $status_styles = match($row['status']) {
                        'Pending' => 'bg-amber-50 text-amber-600 border-amber-100',
                        'Approved' => 'bg-blue-50 text-blue-600 border-blue-100',
                        'Issued' => 'bg-green-50 text-green-600 border-green-100',
                        'Rejected' => 'bg-red-50 text-red-600 border-red-100',
                        'Cancelled' => 'bg-gray-50 text-gray-600 border-gray-100',
                        default => 'bg-gray-50 text-gray-600 border-gray-100'
                    };

                    $rows_html .= '<tr class="hover:bg-gray-50/50 transition-colors border-b border-gray-50">';
                    $rows_html .= '<td class="px-6 py-4 whitespace-nowrap"><span class="text-sm font-bold text-primary">' . htmlspecialchars($row['ris_no']) . '</span></td>';
                    $rows_html .= '<td class="px-6 py-4 text-sm text-gray-800 font-medium">' . htmlspecialchars($row['item']) . '</td>';
                    $rows_html .= '<td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate">' . htmlspecialchars($row['description']) . '</td>';
                    $rows_html .= '<td class="px-6 py-4 text-sm text-gray-600">' . htmlspecialchars($row['unit_measurement']) . '</td>';
                    $rows_html .= '<td class="px-6 py-4 text-sm font-bold text-gray-800 text-right">' . (int)$row['quantity_requested'] . '</td>';
                    $rows_html .= '<td class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">' . htmlspecialchars($row['item_type']) . '</td>';
                    $displayDateTime = !empty($row['approved_date']) && $row['approved_date'] !== '0000-00-00 00:00:00'
                        ? $row['approved_date']
                        : $row['created_at'];
                    $rows_html .= '<td class="px-6 py-4 text-sm text-gray-500">' . date('M d, Y h:i A', strtotime($displayDateTime)) . '</td>';
                    $rows_html .= '<td class="px-6 py-4 whitespace-nowrap"><span class="px-3 py-1 rounded-full text-[11px] font-bold border ' . $status_styles . '">' . htmlspecialchars($row['status']) . '</span></td>';
                    $rows_html .= '<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">';
                    $rows_html .= '<button type="button" onclick="openViewModal(\'' . htmlspecialchars($row['ris_no']) . '\', \'' . date('M d, Y h:i A', strtotime($displayDateTime)) . '\', \'' . htmlspecialchars($row['status']) . '\', \'' . (int)$row['quantity_requested'] . '\', \'' . htmlspecialchars($row['purpose']) . '\')" class="text-blue-600 hover:text-blue-900 mx-1" title="View Details"><i class="bi bi-eye"></i></button>';
                    $rows_html .= '</td>';
                    $rows_html .= '</tr>';

                    $records_count++;
                }
            } else {
                $rows_html .= '<tr>';
                $rows_html .= '<td colspan="8" class="text-center text-muted py-4">';
                $rows_html .= 'No records found for the selected filters.';
                $rows_html .= '</td>';
                $rows_html .= '</tr>';
            }

            $stmt->close();
        }
    } else {
        $rows_html .= '<tr>';
        $rows_html .= '<td colspan="8" class="text-center text-danger py-4">';
        $rows_html .= htmlspecialchars($filter_error);
        $rows_html .= '</td>';
        $rows_html .= '</tr>';
    }

    echo json_encode([
        'html' => $rows_html,
        'records_count' => $records_count,
        'error' => $filter_error,
        'filters' => [
            'period' => $period,
            'item_type' => $item_type,
            'period_label' => $period_label,
            'item_type_label' => $item_type_label
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';

        if ($purpose === '') {
            $_SESSION['error'] = "Purpose is required.";
        } else {
            $ris_no = 'RIS-' . date('Ymd-His') . '-' . $user_id;
            $status = 'Pending';
            $now = date('Y-m-d H:i:s');
            $request_date = date('Y-m-d');

            $sql = "INSERT INTO requests (ris_no, requested_by, request_date, created_at, purpose, status) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssss", $ris_no, $full_name, $request_date, $now, $purpose, $status);
                if ($stmt->execute()) {
                    $stmt->close();
                    $_SESSION['success'] = "RIS created successfully.";
                    header("Location: ris.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to create RIS.";
                    $stmt->close();
                }
            } else {
                $_SESSION['error'] = "Failed to create RIS.";
            }
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';

        if ($id <= 0 || $purpose === '') {
            $_SESSION['error'] = "Invalid request to update.";
        } else {
            $sql = "UPDATE requests SET purpose = ? WHERE id = ? AND requested_by = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sis", $purpose, $id, $full_name);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $stmt->close();
                        $_SESSION['success'] = "RIS updated successfully.";
                        header("Location: ris.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "No changes were made.";
                    }
                } else {
                    $_SESSION['error'] = "Failed to update RIS.";
                }
                $stmt->close();
            } else {
                $error_msg = "Failed to update RIS.";
            }
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $_SESSION['error'] = "Invalid request to delete.";
        } else {
            if ($has_deleted_at) {
                $sql = "UPDATE requests SET status = 'Cancelled', deleted_at = NOW() WHERE id = ? AND requested_by = ?";
            } else {
                $sql = "UPDATE requests SET status = 'Cancelled' WHERE id = ? AND requested_by = ?";
            }

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("is", $id, $full_name);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $stmt->close();
                        $_SESSION['success'] = "RIS deleted successfully.";
                        header("Location: ris.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "Unable to delete RIS.";
                    }
                } else {
                    $_SESSION['error'] = "Failed to delete RIS.";
                }
                $stmt->close();
            } else {
                $error_msg = "Failed to delete RIS.";
            }
        }
    } elseif ($action === 'create_from_inventory') {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($item_id <= 0 || $quantity <= 0) {
            $_SESSION['error'] = "Invalid item request.";
        } else {
            $item_sql = "SELECT id, item, description, balance_qty FROM items WHERE id = ? AND status = 'Active' LIMIT 1";
            if ($stmt = $conn->prepare($item_sql)) {
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $item_result = $stmt->get_result();
                $item = $item_result ? $item_result->fetch_assoc() : null;
                $stmt->close();
            } else {
                $item = null;
            }

            if (!$item) {
                $_SESSION['error'] = "Selected item not found or inactive.";
            } elseif ($quantity > (int)$item['balance_qty']) {
                $_SESSION['error'] = "Requested quantity exceeds available stock.";
            } else {
                $ris_no = 'RIS-' . date('Ymd-His') . '-' . $user_id;
                $status = 'Pending';
                $now = date('Y-m-d H:i:s');
                $request_date = date('Y-m-d');
                $purpose = 'Request for ' . $item['item'] . ' (' . $quantity . ')';

                $conn->begin_transaction();
                try {
                    $sql = "INSERT INTO requests (ris_no, requested_by, request_date, created_at, purpose, status) VALUES (?, ?, ?, ?, ?, ?)";
                    if (!($stmt = $conn->prepare($sql))) {
                        throw new Exception("Failed to prepare RIS insert.");
                    }
                    $stmt->bind_param("ssssss", $ris_no, $full_name, $request_date, $now, $purpose, $status);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create RIS.");
                    }
                    $request_id = $stmt->insert_id;
                    $stmt->close();

                    $item_insert = "INSERT INTO request_items (request_id, item_id, quantity_requested, quantity_issued, remarks) VALUES (?, ?, ?, 0, '')";
                    if (!($stmt = $conn->prepare($item_insert))) {
                        throw new Exception("Failed to prepare request item insert.");
                    }
                    $stmt->bind_param("iii", $request_id, $item['id'], $quantity);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add item to RIS.");
                    }
                    $stmt->close();

                    $conn->commit();
                    $_SESSION['success'] = "Item request created successfully.";
                    header("Location: ris.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Failed to create item request.";
                }
            }
        }
    } elseif ($action === 'create_multi_from_inventory') {
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';

        // Support for parallel arrays (item_ids[] and quantities[])
        if (empty($items) && isset($_POST['item_ids']) && isset($_POST['quantities']) && is_array($_POST['item_ids']) && is_array($_POST['quantities'])) {
             $itemIds = $_POST['item_ids'];
             $quantities = $_POST['quantities'];
             for ($i = 0; $i < count($itemIds); $i++) {
                 if (isset($itemIds[$i]) && isset($quantities[$i])) {
                     $items[] = [
                         'id' => $itemIds[$i],
                         'quantity' => $quantities[$i]
                     ];
                 }
             }
        }

        $clean_items = [];

        foreach ($items as $itemData) {
            $itemId = isset($itemData['id']) ? (int)$itemData['id'] : 0;
            $qty = isset($itemData['quantity']) ? (int)$itemData['quantity'] : 0;
            if ($itemId > 0 && $qty > 0) {
                $clean_items[$itemId] = $qty;
            }
        }

        if (empty($clean_items)) {
            $_SESSION['error'] = "Please select at least one item with a valid quantity.";
        } else {
            $ids = array_keys($clean_items);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql = "SELECT id, item, balance_qty FROM items WHERE id IN ($placeholders) AND status = 'Active'";
            if ($stmt = $conn->prepare($sql)) {
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $result = $stmt->get_result();

                $dbItems = [];
                while ($row = $result->fetch_assoc()) {
                    $dbItems[(int)$row['id']] = $row;
                }
                $stmt->close();

                if (count($dbItems) !== count($clean_items)) {
                    $_SESSION['error'] = "One or more selected items are no longer available.";
                } else {
                    foreach ($clean_items as $itemId => $qty) {
                        $available = isset($dbItems[$itemId]) ? (int)$dbItems[$itemId]['balance_qty'] : 0;
                        if ($qty > $available) {
                            $_SESSION['error'] = "Requested quantity exceeds available stock for one of the selected items.";
                            break;
                        }
                    }
                }

                if ($error_msg === "") {
                    $ris_no = 'RIS-' . date('Ymd-His') . '-' . $user_id;
                    $status = 'Pending';
                    $now = date('Y-m-d H:i:s');
                    $request_date = date('Y-m-d');

                    if ($purpose === '') {
                        $first = reset($dbItems);
                        $label = $first ? $first['item'] : 'multiple items';
                        $purpose = 'Request for multiple items including ' . $label;
                    }

                    $conn->begin_transaction();
                    try {
                        $insert_ris = "INSERT INTO requests (ris_no, requested_by, request_date, created_at, purpose, status) VALUES (?, ?, ?, ?, ?, ?)";
                        if (!($stmt = $conn->prepare($insert_ris))) {
                            throw new Exception("Failed to prepare RIS insert.");
                        }
                        $stmt->bind_param("ssssss", $ris_no, $full_name, $request_date, $now, $purpose, $status);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create RIS.");
                        }
                        $request_id = $stmt->insert_id;
                        $stmt->close();

                        $insert_item = "INSERT INTO request_items (request_id, item_id, quantity_requested, quantity_issued, remarks) VALUES (?, ?, ?, 0, '')";
                        if (!($stmt = $conn->prepare($insert_item))) {
                            throw new Exception("Failed to prepare request item insert.");
                        }

                        foreach ($clean_items as $itemId => $qty) {
                            $stmt->bind_param("iii", $request_id, $itemId, $qty);
                            if (!$stmt->execute()) {
                                throw new Exception("Failed to add item to RIS.");
                            }
                        }
                        $stmt->close();

                        $conn->commit();
                        $_SESSION['success'] = "Multiple item requests created successfully.";
                        header("Location: ris.php");
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = "Failed to create item requests.";
                    }
                }
            } else {
                $_SESSION['error'] = "Failed to validate selected items.";
            }
        }
    }
}

$pending_items = [];
$history_items = [];

$base_items_sql = "SELECT 
                        r.id AS request_id,
                        r.ris_no,
                        r.request_date,
                        r.approved_date,
                        r.created_at,
                        r.purpose,
                        r.status,
                        ri.quantity_requested,
                        i.item,
                        i.description,
                        i.unit_measurement,
                        i.item_type
                   FROM requests r
                   INNER JOIN request_items ri ON r.id = ri.request_id
                   INNER JOIN items i ON ri.item_id = i.id
                   WHERE r.requested_by = ?";

if ($has_deleted_at) {
    $base_items_sql .= " AND (r.deleted_at IS NULL OR r.deleted_at = '0000-00-00 00:00:00')";
}

$pending_sql = $base_items_sql . " AND r.status = 'Pending' ORDER BY r.request_date DESC, r.ris_no, i.item";
$history_sql = $base_items_sql . " AND r.status <> 'Pending' ORDER BY r.request_date DESC, r.ris_no, i.item";

if ($stmt = $conn->prepare($pending_sql)) {
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_items[] = $row;
    }
    $stmt->close();
}

if ($stmt = $conn->prepare($history_sql)) {
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history_items[] = $row;
    }
    $stmt->close();
}

$pending_count = count($pending_items);
$approved_count = 0;
$issued_count = 0;
$rejected_count = 0;
$cancelled_count = 0;

foreach ($pending_items as $row) {
    if ($row['status'] === 'Pending') {
        $pending_count++;
    }
}

foreach ($history_items as $row) {
    if ($row['status'] === 'Approved') {
        $approved_count++;
    } elseif ($row['status'] === 'Issued') {
        $issued_count++;
    } elseif ($row['status'] === 'Rejected') {
        $rejected_count++;
    } elseif ($row['status'] === 'Cancelled') {
        $cancelled_count++;
    }
}

require_once 'staff_sidebar.php';
require_once 'staff_navbar.php';
?>

<div class="lg:ml-[260px] pt-20 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <!-- Pending -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 transition-all duration-300 hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Pending</p>
                        <h3 class="text-xl font-bold text-primary"><?php echo (int)$pending_count; ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-primary/5 text-primary flex items-center justify-center">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
            <!-- Approved -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 transition-all duration-300 hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Approved</p>
                        <h3 class="text-xl font-bold text-blue-600"><?php echo (int)$approved_count; ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
            <!-- Issued -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 transition-all duration-300 hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Issued</p>
                        <h3 class="text-xl font-bold text-green-600"><?php echo (int)$issued_count; ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
            <!-- Rejected/Cancelled -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-5 transition-all duration-300 hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Rejected</p>
                        <h3 class="text-xl font-bold text-red-600"><?php echo (int)($rejected_count + $cancelled_count); ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>



        <!-- Pending Items Section -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="px-6 py-5 border-b border-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-xl bg-primary/5 text-primary flex items-center justify-center mr-4">
                        <i class="bi bi-box-arrow-in-down text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Item Request</h3>
                        <p class="text-xs text-gray-500">All items you have requested and are still pending.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-primary/5 text-primary text-[11px] font-bold rounded-full">
                        Pending: <?php echo (int)count($pending_items); ?>
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap w-32">RIS No.</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-40">Item</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-20">Unit</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-20">Qty</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Type</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap w-24">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (count($pending_items) === 0): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="bi bi-inbox text-4xl text-gray-200 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-medium">No pending RIS found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $seen_pending_requests = []; ?>
                            <?php foreach ($pending_items as $row): ?>
                                <?php
                                $status_styles = 'bg-amber-50 text-amber-600 border-amber-100';
                                $req_id = (int)$row['request_id'];
                                $row_id_attr = '';
                                if ($req_id > 0 && !in_array($req_id, $seen_pending_requests, true)) {
                                    $seen_pending_requests[] = $req_id;
                                    $row_id_attr = 'id="ris-' . $req_id . '"';
                                }
                                ?>
                                <tr <?php echo $row_id_attr; ?> class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($row['ris_no']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($row['item']); ?></td>
                                    <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-800 text-right"><?php echo (int)$row['quantity_requested']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded uppercase"><?php echo htmlspecialchars($row['item_type']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-800"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                            <span class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?php echo $status_styles; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History Records Section -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-50 bg-white">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-xl bg-gray-50 text-gray-500 flex items-center justify-center mr-4">
                            <i class="bi bi-clipboard-check text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Requisition and Issue Records</h3>
                            <p class="text-xs text-gray-500">History of approved, issued, rejected, and cancelled requests.</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-gray-50 text-gray-500 text-[11px] font-bold rounded-full">
                        Records: <span id="historyRecordsCount"><?php echo (int)count($history_items); ?></span>
                    </span>
                </div>
            </div>
            
            <div class="p-6 bg-gray-50/30 border-b border-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Period</label>
                        <select id="periodFilter" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                            <option value="all">All periods</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="range">Date range</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="md:col-span-6">
                        <div id="periodInputsContainer">
                            <div id="periodDailyGroup" class="hidden">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Select Date</label>
                                <input type="date" id="periodDailyDate" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                            </div>
                            <div id="periodWeeklyGroup" class="hidden">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Week Range</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="date" id="periodWeekStart" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                                    <input type="date" id="periodWeekEnd" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                                </div>
                            </div>
                            <div id="periodRangeGroup" class="hidden">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Date Range</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="date" id="periodRangeFrom" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                                    <input type="date" id="periodRangeTo" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                                </div>
                            </div>
                            <div id="periodYearlyGroup" class="hidden">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Select Year</label>
                                <select id="periodYearSelect" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                                    <?php
                                    $currentYear = (int)date('Y');
                                    for ($y = $currentYear + 5; $y >= $currentYear - 5; $y--) {
                                        echo '<option value="' . $y . '">' . $y . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Item Type</label>
                        <select id="itemTypeFilter" class="w-full bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                            <option value="all">All item types</option>
                            <option value="Expendable">Expendable</option>
                            <option value="Semi-Expendable">Semi-Expendable</option>
                            <option value="Non-Expendable">Non-Expendable</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center mt-4 gap-2 flex-wrap">
                    <div id="filterError" class="text-xs font-bold text-secondary"></div>
                    <div id="activeFiltersSummary" class="text-xs font-medium text-gray-400 ml-auto"></div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50">
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap w-32">RIS No.</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-40">Item</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-20">Unit</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-20">Qty</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Type</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap w-24">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Status</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-50">
                        <?php if (count($history_items) === 0): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="bi bi-inbox text-4xl text-gray-200 mb-2"></i>
                                        <p class="text-sm text-gray-500 font-medium">No approved or rejected RIS found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $seen_history_requests = []; ?>
                            <?php foreach ($history_items as $row): ?>
                                <?php
                                $status_styles = match($row['status']) {
                                    'Approved' => 'bg-blue-50 text-blue-600 border-blue-100',
                                    'Issued' => 'bg-green-50 text-green-600 border-green-100',
                                    'Rejected' => 'bg-red-50 text-red-600 border-red-100',
                                    'Cancelled' => 'bg-gray-50 text-gray-600 border-gray-100',
                                    default => 'bg-gray-50 text-gray-600 border-gray-100'
                                };
                                $req_id = (int)$row['request_id'];
                                $row_id_attr = '';
                                if ($req_id > 0 && !in_array($req_id, $seen_history_requests, true)) {
                                    $seen_history_requests[] = $req_id;
                                    $row_id_attr = 'id="ris-' . $req_id . '"';
                                }
                                ?>
                                <tr <?php echo $row_id_attr; ?> class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($row['ris_no']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($row['item']); ?></td>
                                    <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-800 text-right"><?php echo (int)$row['quantity_requested']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-bold rounded uppercase"><?php echo htmlspecialchars($row['item_type']); ?></span>
                                    </td>
                                    <?php
                                    $displayDateTime = !empty($row['approved_date']) && $row['approved_date'] !== '0000-00-00 00:00:00'
                                        ? $row['approved_date']
                                        : $row['created_at'];
                                    ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-800"><?php echo date('M d, Y', strtotime($displayDateTime)); ?></span>
                                            <span class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($displayDateTime)); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?php echo $status_styles; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="risModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('risModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="bg-gray-50/50 border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-primary" id="modal-title">New RIS</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('risModal')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="px-6 py-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Requested By</label>
                                <input type="text" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-medium text-gray-500 outline-none" value="<?php echo htmlspecialchars($full_name); ?>" readonly>
                            </div>
                            <div>
                                <label for="purpose" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Purpose <span class="text-secondary">*</span></label>
                                <textarea name="purpose" id="purpose" class="w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none resize-none" rows="4" required placeholder="State the purpose of this request..."></textarea>
                            </div>
                            <div class="p-4 bg-primary/5 rounded-2xl border border-primary/10">
                                <div class="flex">
                                    <i class="bi bi-info-circle text-primary mt-0.5 mr-3"></i>
                                    <p class="text-xs text-primary/80 leading-relaxed font-medium">
                                        After creating this RIS, an administrator can review, approve, and issue the requested items.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50/50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="px-6 py-2.5 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors focus:outline-none" onclick="closeModal('risModal')">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20 focus:outline-none">
                            Create RIS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="editRisModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('editRisModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editRisId">
                    <div class="bg-gray-50/50 border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-primary">Edit RIS</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('editRisModal')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="px-6 py-6">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">RIS No.</label>
                                <input type="text" id="editRisNo" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-medium text-gray-500 outline-none" readonly>
                            </div>
                            <div>
                                <label for="editPurpose" class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Purpose <span class="text-secondary">*</span></label>
                                <textarea name="purpose" id="editPurpose" class="w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-primary/5 focus:border-primary transition-all outline-none resize-none" rows="4" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50/50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="px-6 py-2.5 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors focus:outline-none" onclick="closeModal('editRisModal')">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20 focus:outline-none">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="viewRisModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closeModal('viewRisModal')"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                <div class="bg-gray-50/50 border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-primary">RIS Details</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors focus:outline-none" onclick="closeModal('viewRisModal')">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="px-6 py-6">
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">RIS No.</label>
                                <p id="viewRisNo" class="text-sm font-bold text-primary"></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Date</label>
                                <p id="viewRisDate" class="text-sm font-medium text-gray-700"></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Status</label>
                                <span id="viewRisStatus" class="inline-block px-3 py-1 rounded-full text-[11px] font-bold border"></span>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Total Items</label>
                                <p id="viewRisTotal" class="text-sm font-bold text-gray-700"></p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Purpose</label>
                            <p id="viewRisPurpose" class="text-sm text-gray-600 bg-gray-50 rounded-2xl p-4 border border-gray-100 italic"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50/50 border-t border-gray-100 px-6 py-4">
                    <button type="button" class="w-full px-6 py-2.5 bg-gray-100 text-gray-600 text-xs font-bold rounded-xl hover:bg-gray-200 transition-all duration-200 focus:outline-none" onclick="closeModal('viewRisModal')">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteRisForm" method="post" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteRisId">
</form>

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

function openEditModal(id, ris, purpose) {
    document.getElementById('editRisId').value = id;
    document.getElementById('editRisNo').value = ris;
    document.getElementById('editPurpose').value = purpose;
    openModal('editRisModal');
}

function openViewModal(ris, date, status, total, purpose) {
    var risEl = document.getElementById('viewRisNo');
    var dateEl = document.getElementById('viewRisDate');
    var statusEl = document.getElementById('viewRisStatus');
    var totalEl = document.getElementById('viewRisTotal');
    var purposeEl = document.getElementById('viewRisPurpose');

    if(risEl) risEl.textContent = ris || '---';
    if(dateEl) dateEl.textContent = date || '---';
    if(totalEl) totalEl.textContent = total || '0';
    if(purposeEl) purposeEl.textContent = purpose || 'No purpose stated.';
    
    if(statusEl) {
        statusEl.textContent = status || '---';
        statusEl.className = 'inline-block px-3 py-1 rounded-full text-[11px] font-bold border ';
        var styles = 'bg-gray-50 text-gray-600 border-gray-100';
        if (status === 'Pending') styles = 'bg-amber-50 text-amber-600 border-amber-100';
        else if (status === 'Approved') styles = 'bg-blue-50 text-blue-600 border-blue-100';
        else if (status === 'Issued') styles = 'bg-green-50 text-green-600 border-green-100';
        else if (status === 'Rejected') styles = 'bg-red-50 text-red-600 border-red-100';
        else if (status === 'Cancelled') styles = 'bg-gray-50 text-gray-600 border-gray-100';
        statusEl.className += styles;
    }
    openModal('viewRisModal');
}

document.addEventListener('DOMContentLoaded', function () {
    var periodFilter = document.getElementById('periodFilter');
    var itemTypeFilter = document.getElementById('itemTypeFilter');
    var periodDailyGroup = document.getElementById('periodDailyGroup');
    var periodWeeklyGroup = document.getElementById('periodWeeklyGroup');
    var periodRangeGroup = document.getElementById('periodRangeGroup');
    var periodYearlyGroup = document.getElementById('periodYearlyGroup');
    var periodDailyDate = document.getElementById('periodDailyDate');
    var periodWeekStart = document.getElementById('periodWeekStart');
    var periodWeekEnd = document.getElementById('periodWeekEnd');
    var periodRangeFrom = document.getElementById('periodRangeFrom');
    var periodRangeTo = document.getElementById('periodRangeTo');
    var periodYearSelect = document.getElementById('periodYearSelect');
    var historyTableBody = document.getElementById('historyTableBody');
    var historyRecordsCount = document.getElementById('historyRecordsCount');
    var filterError = document.getElementById('filterError');
    var activeFiltersSummary = document.getElementById('activeFiltersSummary');

    function updatePeriodInputsVisibility() {
        if (periodDailyGroup) periodDailyGroup.classList.add('hidden');
        if (periodWeeklyGroup) periodWeeklyGroup.classList.add('hidden');
        if (periodRangeGroup) periodRangeGroup.classList.add('hidden');
        if (periodYearlyGroup) periodYearlyGroup.classList.add('hidden');

        if (!periodFilter) return;
        var value = periodFilter.value;
        if (value === 'daily' && periodDailyGroup) {
            periodDailyGroup.classList.remove('hidden');
        } else if (value === 'weekly' && periodWeeklyGroup) {
            periodWeeklyGroup.classList.remove('hidden');
        } else if (value === 'range' && periodRangeGroup) {
            periodRangeGroup.classList.remove('hidden');
        } else if (value === 'yearly' && periodYearlyGroup) {
            periodYearlyGroup.classList.remove('hidden');
        }

        if (filterError) {
            filterError.textContent = '';
        }
    }

    function validateFilters() {
        if (!periodFilter || !filterError) {
            return true;
        }
        filterError.textContent = '';
        var value = periodFilter.value;

        if (value === 'daily') {
            if (!periodDailyDate || !periodDailyDate.value) {
                filterError.textContent = 'Please select a date.';
                return false;
            }
        } else if (value === 'weekly') {
            if (!periodWeekStart || !periodWeekEnd || !periodWeekStart.value || !periodWeekEnd.value) {
                filterError.textContent = 'Please select both dates.';
                return false;
            }
            if (periodWeekStart.value > periodWeekEnd.value) {
                filterError.textContent = 'Start date cannot be after end date.';
                return false;
            }
        } else if (value === 'range') {
            if (!periodRangeFrom || !periodRangeTo || !periodRangeFrom.value || !periodRangeTo.value) {
                filterError.textContent = 'Please select both dates.';
                return false;
            }
            if (periodRangeFrom.value > periodRangeTo.value) {
                filterError.textContent = 'Start date cannot be after end date.';
                return false;
            }
        } else if (value === 'yearly') {
            if (!periodYearSelect || !periodYearSelect.value) {
                filterError.textContent = 'Please select a year.';
                return false;
            }
        }

        return true;
    }

    function buildFilterParams() {
        var params = new URLSearchParams();
        params.append('ajax', 'history_filter');
        var periodValue = periodFilter ? periodFilter.value : 'all';
        params.append('period', periodValue || 'all');

        if (itemTypeFilter && itemTypeFilter.value && itemTypeFilter.value !== 'all') {
            params.append('item_type', itemTypeFilter.value);
        }

        if (periodValue === 'daily' && periodDailyDate && periodDailyDate.value) {
            params.append('date_daily', periodDailyDate.value);
        } else if (periodValue === 'weekly') {
            if (periodWeekStart && periodWeekStart.value) {
                params.append('week_start', periodWeekStart.value);
            }
            if (periodWeekEnd && periodWeekEnd.value) {
                params.append('week_end', periodWeekEnd.value);
            }
        } else if (periodValue === 'range') {
            if (periodRangeFrom && periodRangeFrom.value) {
                params.append('range_from', periodRangeFrom.value);
            }
            if (periodRangeTo && periodRangeTo.value) {
                params.append('range_to', periodRangeTo.value);
            }
        } else if (periodValue === 'yearly' && periodYearSelect && periodYearSelect.value) {
            params.append('year', periodYearSelect.value);
        }

        return params;
    }

    function applyFilters() {
        if (!historyTableBody) {
            return;
        }
        if (!validateFilters()) {
            return;
        }

        var params = buildFilterParams();

        if (activeFiltersSummary) {
            activeFiltersSummary.textContent = 'Loading records...';
        }

        fetch('ris.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (typeof data.html === 'string') {
                    historyTableBody.innerHTML = data.html;
                }
                if (filterError) {
                    filterError.textContent = data.error || '';
                }
                if (historyRecordsCount && typeof data.records_count === 'number') {
                    historyRecordsCount.textContent = data.records_count;
                }
                if (activeFiltersSummary) {
                    var text = '';
                    if (data.filters) {
                        var parts = [];
                        if (data.filters.period_label) {
                            parts.push(data.filters.period_label);
                        }
                        if (data.filters.item_type_label) {
                            parts.push(data.filters.item_type_label);
                        }
                        if (parts.length > 0) {
                            text = 'Filters: ' + parts.join(' • ');
                        }
                    }
                    activeFiltersSummary.textContent = text;
                }
            })
            .catch(function () {
                if (filterError) {
                    filterError.textContent = 'Unable to load filtered records.';
                }
                if (activeFiltersSummary) {
                    activeFiltersSummary.textContent = '';
                }
            });
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

    var debouncedApplyFilters = debounce(applyFilters, 300);

    if (periodFilter) {
        periodFilter.addEventListener('change', function () {
            updatePeriodInputsVisibility();

            if (periodFilter.value === 'daily' && periodDailyDate && !periodDailyDate.value) {
                var now = new Date();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                periodDailyDate.value = now.getFullYear() + '-' + month + '-' + day;
            }

            debouncedApplyFilters();
        });
    }

    if (itemTypeFilter) {
        itemTypeFilter.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    if (periodDailyDate) {
        periodDailyDate.addEventListener('change', function () {
            debouncedApplyFilters();
        });
        periodDailyDate.addEventListener('input', function () {
            debouncedApplyFilters();
        });
    }

    if (periodWeekStart) {
        periodWeekStart.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    if (periodWeekEnd) {
        periodWeekEnd.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    if (periodRangeFrom) {
        periodRangeFrom.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    if (periodRangeTo) {
        periodRangeTo.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    if (periodYearSelect) {
        periodYearSelect.addEventListener('change', function () {
            debouncedApplyFilters();
        });
    }

    updatePeriodInputsVisibility();
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete RIS?',
        text: 'This will mark the RIS as cancelled.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1a237e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        background: '#ffffff',
        customClass: {
            popup: 'rounded-3xl border-0 shadow-2xl',
            confirmButton: 'px-6 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-light transition-all duration-200 shadow-lg shadow-primary/20',
            cancelButton: 'px-6 py-2.5 text-xs font-bold text-gray-500 hover:text-gray-700 transition-colors'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.isConfirmed) {
            var form = document.getElementById('deleteRisForm');
            document.getElementById('deleteRisId').value = id;
            form.submit();
        }
    });
}
</script>

</body>
</html>
