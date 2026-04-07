<?php
require_once 'superadmin_sidebar.php';
ob_start();
$page_title = 'Lost Properties';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
include '../plugins/conn.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$view_no = isset($_GET['view']) ? trim((string)$_GET['view']) : '';

function validate_ymd(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }
    return $value;
}

function bind_params(mysqli_stmt $stmt, string $types, array $params): bool
{
    $args = [$types];
    foreach ($params as $i => $value) {
        $args[] = &$params[$i];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $args);
}

function ensure_rlsddp_tables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS rlsddp_sequences (
            year INT NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS rlsddp_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rlsddp_no VARCHAR(50) NOT NULL UNIQUE,
            entity_name VARCHAR(255) NOT NULL,
            property_type VARCHAR(100) NOT NULL,
            department VARCHAR(255) NOT NULL,
            accountable_officer VARCHAR(255) NOT NULL,
            office_address VARCHAR(255) NOT NULL,
            position_title VARCHAR(255) NOT NULL,
            tel_no VARCHAR(100) NOT NULL,
            fund_cluster VARCHAR(100) NOT NULL,
            report_date DATE NULL,
            pages VARCHAR(100) NOT NULL,
            pc_no VARCHAR(100) NOT NULL,
            par_no VARCHAR(100) NOT NULL,
            public_station VARCHAR(10) NOT NULL,
            nature_json TEXT NOT NULL,
            circumstances TEXT NOT NULL,
            verified_by VARCHAR(255) NOT NULL,
            verified_date DATE NULL,
            id_type VARCHAR(100) NOT NULL,
            id_no VARCHAR(100) NOT NULL,
            id_issued_date DATE NULL,
            doc_no VARCHAR(100) NOT NULL,
            page_no VARCHAR(50) NOT NULL,
            book_no VARCHAR(50) NOT NULL,
            series VARCHAR(50) NOT NULL,
            notary VARCHAR(255) NOT NULL,
            items_hash CHAR(40) NOT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS rlsddp_document_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rlsddp_document_id INT NOT NULL,
            item_id INT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            stock_no VARCHAR(100) NOT NULL,
            unit VARCHAR(50) NOT NULL,
            qty DECIMAL(12,2) NOT NULL,
            unit_value DECIMAL(14,2) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            date_acquired DATE NULL,
            INDEX idx_rlsddp_doc_id (rlsddp_document_id),
            CONSTRAINT fk_rlsddp_doc_items_doc FOREIGN KEY (rlsddp_document_id) REFERENCES rlsddp_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function generate_next_rlsddp_no(mysqli $conn): string
{
    ensure_rlsddp_tables($conn);
    $year = (int)date('Y');
    $stmt = $conn->prepare("
        INSERT INTO rlsddp_sequences (year, last_seq)
        VALUES (?, LAST_INSERT_ID(1))
        ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)
    ");
    if (!$stmt) {
        $token = '';
        try {
            $token = bin2hex(random_bytes(2));
        } catch (Throwable $e) {
            $token = dechex(mt_rand(0, 0xffff));
            $token = str_pad($token, 4, '0', STR_PAD_LEFT);
        }
        return $year . '-' . date('mdHis') . '-' . strtoupper($token);
    }
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $stmt->close();

    $seq = (int)mysqli_insert_id($conn);
    if ($seq <= 0) {
        $token = '';
        try {
            $token = bin2hex(random_bytes(2));
        } catch (Throwable $e) {
            $token = dechex(mt_rand(0, 0xffff));
            $token = str_pad($token, 4, '0', STR_PAD_LEFT);
        }
        return $year . '-' . date('mdHis') . '-' . strtoupper($token);
    }
    return $year . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function upsert_rlsddp_document(mysqli $conn, string $rlsddp_no, array $header, array $selected_items, int $user_id): int
{
    if ($rlsddp_no === '' || empty($selected_items)) {
        return 0;
    }
    ensure_rlsddp_tables($conn);

    $selected_ids = [];
    foreach ($selected_items as $id => $d) {
        $selected_ids[] = (string)$id;
    }
    sort($selected_ids, SORT_NATURAL);
    $items_hash = sha1(implode(',', $selected_ids));

    $stmt_find = $conn->prepare("SELECT id FROM rlsddp_documents WHERE rlsddp_no = ? LIMIT 1");
    if (!$stmt_find) {
        return 0;
    }
    $stmt_find->bind_param("s", $rlsddp_no);
    $stmt_find->execute();
    $res = $stmt_find->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt_find->close();

    $doc_id = 0;
    if ($existing && isset($existing['id'])) {
        $doc_id = (int)$existing['id'];
    } else {
        $entity_name = substr(trim((string)($header['entity_name'] ?? '')), 0, 255);
        $property_type = substr(trim((string)($header['property_type'] ?? '')), 0, 100);
        $department = substr(trim((string)($header['department'] ?? '')), 0, 255);
        $accountable_officer = substr(trim((string)($header['accountable_officer'] ?? '')), 0, 255);
        $office_address = substr(trim((string)($header['office_address'] ?? '')), 0, 255);
        $position_title = substr(trim((string)($header['position_title'] ?? '')), 0, 255);
        $tel_no = substr(trim((string)($header['tel_no'] ?? '')), 0, 100);
        $fund_cluster = substr(trim((string)($header['fund_cluster'] ?? '')), 0, 100);
        $report_date = !empty($header['report_date']) ? date('Y-m-d', strtotime((string)$header['report_date'])) : null;
        $pages = substr(trim((string)($header['pages'] ?? '')), 0, 100);
        $pc_no = substr(trim((string)($header['pc_no'] ?? '')), 0, 100);
        $par_no = substr(trim((string)($header['par_no'] ?? '')), 0, 100);
        $public_station = substr(trim((string)($header['public_station'] ?? 'No')), 0, 10);
        $nature_json = json_encode($header['nature'] ?? [], JSON_UNESCAPED_UNICODE);
        if ($nature_json === false) {
            $nature_json = '[]';
        }
        $circumstances = (string)($header['circumstances'] ?? '');
        $verified_by = substr(trim((string)($header['verified_by'] ?? '')), 0, 255);
        $verified_date = !empty($header['verified_date']) ? date('Y-m-d', strtotime((string)$header['verified_date'])) : null;
        $id_type = substr(trim((string)($header['id_type'] ?? '')), 0, 100);
        $id_no = substr(trim((string)($header['id_no'] ?? '')), 0, 100);
        $id_issued_date = !empty($header['id_issued_date']) ? date('Y-m-d', strtotime((string)$header['id_issued_date'])) : null;
        $doc_no = substr(trim((string)($header['doc_no'] ?? '')), 0, 100);
        $page_no = substr(trim((string)($header['page_no'] ?? '')), 0, 50);
        $book_no = substr(trim((string)($header['book_no'] ?? '')), 0, 50);
        $series = substr(trim((string)($header['series'] ?? '')), 0, 50);
        $notary = substr(trim((string)($header['notary'] ?? '')), 0, 255);

        $stmt_ins = $conn->prepare("
            INSERT INTO rlsddp_documents
                (rlsddp_no, entity_name, property_type, department, accountable_officer, office_address, position_title, tel_no, fund_cluster,
                 report_date, pages, pc_no, par_no, public_station, nature_json, circumstances, verified_by, verified_date,
                 id_type, id_no, id_issued_date, doc_no, page_no, book_no, series, notary, items_hash, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt_ins) {
            return 0;
        }
        $types = str_repeat('s', 27) . 'i';
        $stmt_ins->bind_param(
            $types,
            $rlsddp_no,
            $entity_name,
            $property_type,
            $department,
            $accountable_officer,
            $office_address,
            $position_title,
            $tel_no,
            $fund_cluster,
            $report_date,
            $pages,
            $pc_no,
            $par_no,
            $public_station,
            $nature_json,
            $circumstances,
            $verified_by,
            $verified_date,
            $id_type,
            $id_no,
            $id_issued_date,
            $doc_no,
            $page_no,
            $book_no,
            $series,
            $notary,
            $items_hash,
            $user_id
        );
        $stmt_ins->execute();
        $stmt_ins->close();
        $doc_id = (int)mysqli_insert_id($conn);
    }

    if ($doc_id <= 0) {
        return 0;
    }

    $stmt_has = $conn->prepare("SELECT 1 FROM rlsddp_document_items WHERE rlsddp_document_id = ? LIMIT 1");
    if (!$stmt_has) {
        return $doc_id;
    }
    $stmt_has->bind_param("i", $doc_id);
    $stmt_has->execute();
    $res_has = $stmt_has->get_result();
    $has_items = $res_has && $res_has->num_rows > 0;
    $stmt_has->close();
    if ($has_items) {
        return $doc_id;
    }

    $stmt_item = $conn->prepare("
        INSERT INTO rlsddp_document_items
            (rlsddp_document_id, item_id, item_name, description, stock_no, unit, qty, unit_value, amount, date_acquired)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_item) {
        return $doc_id;
    }

    foreach ($selected_items as $item_id => $d) {
        $id_int = is_numeric($item_id) ? (int)$item_id : null;
        $item_name = substr(trim((string)($d['item'] ?? '')), 0, 255);
        $description = (string)($d['description'] ?? '');
        $stock_no = substr(trim((string)($d['stock_no'] ?? '')), 0, 100);
        $unit = substr(trim((string)($d['unit'] ?? '')), 0, 50);
        $qty = isset($d['qty']) ? (float)$d['qty'] : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $unit_value = isset($d['unit_value']) ? (float)$d['unit_value'] : 0.0;
        $amount = $qty * $unit_value;
        $date_acquired = null;
        if (!empty($d['date_acquired'])) {
            $date_acquired = date('Y-m-d', strtotime((string)$d['date_acquired']));
        }
        $stmt_item->bind_param("iissssddds", $doc_id, $id_int, $item_name, $description, $stock_no, $unit, $qty, $unit_value, $amount, $date_acquired);
        $stmt_item->execute();

        if ($id_int !== null && $id_int > 0) {
            $stmt_bal = $conn->prepare("SELECT balance_qty FROM items WHERE id = ?");
            if ($stmt_bal) {
                $stmt_bal->bind_param("i", $id_int);
                $stmt_bal->execute();
                $res_bal = $stmt_bal->get_result();
                if ($res_bal && $row_bal = $res_bal->fetch_assoc()) {
                    $current_balance = (float)$row_bal['balance_qty'];
                    $new_balance = $current_balance - $qty;
                    if ($new_balance < 0) {
                        $new_balance = 0;
                    }
                    $stmt_upd = $conn->prepare("UPDATE items SET balance_qty = ? WHERE id = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("di", $new_balance, $id_int);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                    $stmt_tx = $conn->prepare("
                        INSERT INTO inventory_transactions 
                            (item_id, transaction_date, transaction_type, quantity, balance_after, remarks) 
                        VALUES (?, NOW(), ?, ?, ?, ?)
                    ");
                    if ($stmt_tx) {
                        $nature_list = $header['nature'] ?? [];
                        $nature_str = !empty($nature_list) ? implode(', ', $nature_list) : 'RLSDDP';
                        $remarks = 'RLSDDP No. ' . $rlsddp_no . ' (' . $nature_str . ')';
                        $tx_type = 'Stock Out - Disposal - ' . (isset($nature_list[0]) ? $nature_list[0] : 'RLSDDP');
                        
                        $tx_type = substr($tx_type, 0, 50);
                        $remarks = substr($remarks, 0, 255);
                        
                        $stmt_tx->bind_param("isdds", $id_int, $tx_type, $qty, $new_balance, $remarks);
                        $stmt_tx->execute();
                        $stmt_tx->close();
                    }
                }
                $stmt_bal->close();
            }
        }
    }

    $stmt_item->close();
    return $doc_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $rlsddp_no = trim((string)($_POST['rlsddp_no'] ?? ''));
    $entity_name = trim((string)($_POST['entity_name'] ?? ''));
    $property_type = trim((string)($_POST['property_type'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $accountable_officer = trim((string)($_POST['accountable_officer'] ?? ''));
    $office_address = trim((string)($_POST['office_address'] ?? ''));
    $position_title = trim((string)($_POST['position_title'] ?? ''));
    $tel_no = trim((string)($_POST['tel_no'] ?? ''));
    $fund_cluster = trim((string)($_POST['fund_cluster'] ?? ''));
    $report_date = validate_ymd((string)($_POST['report_date'] ?? date('Y-m-d')));
    $pages = trim((string)($_POST['pages'] ?? ''));
    $pc_no = trim((string)($_POST['pc_no'] ?? ''));
    $par_no = trim((string)($_POST['par_no'] ?? ''));
    $public_station = (string)($_POST['public_station'] ?? 'No');
    $nature = isset($_POST['nature']) && is_array($_POST['nature']) ? $_POST['nature'] : [];
    $circumstances = trim((string)($_POST['circumstances'] ?? ''));
    $verified_by = trim((string)($_POST['verified_by'] ?? ''));
    $verified_date = validate_ymd((string)($_POST['verified_date'] ?? ''));
    $id_type = trim((string)($_POST['id_type'] ?? ''));
    $id_no = trim((string)($_POST['id_no'] ?? ''));
    $id_issued_date = validate_ymd((string)($_POST['id_issued_date'] ?? ''));
    $doc_no = trim((string)($_POST['doc_no'] ?? ''));
    $page_no = trim((string)($_POST['page_no'] ?? ''));
    $book_no = trim((string)($_POST['book_no'] ?? ''));
    $series = trim((string)($_POST['series'] ?? ''));
    $notary = trim((string)($_POST['notary'] ?? ''));
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];

    $allowed_nature = ['Lost', 'Stolen', 'Damaged', 'Destroyed'];
    $nature = array_values(array_filter($nature, function ($v) use ($allowed_nature) {
        return in_array((string)$v, $allowed_nature, true);
    }));

    $public_station = $public_station === 'Yes' ? 'Yes' : 'No';

    if ($entity_name === '') {
        $error = 'Entity name is required.';
    } elseif ($property_type === '') {
        $error = 'Type of property is required.';
    } elseif ($department === '' || $accountable_officer === '') {
        $error = 'Department and accountable officer are required.';
    } elseif ($report_date === '') {
        $error = 'Report date is required.';
    } elseif ($pages === '') {
        $error = 'Number of pages is required.';
    } elseif (count($nature) === 0) {
        $error = 'Please select at least one nature of property (Lost/Stolen/Damaged/Destroyed).';
    } elseif ($circumstances === '') {
        $error = 'Circumstances is required.';
    } else {
        $selected_ids = [];
        foreach ($selected as $id => $payload) {
            $item_id = (int)$id;
            if ($item_id <= 0) {
                continue;
            }
            $qty_raw = $payload['qty'] ?? 1;
            $qty = (float)$qty_raw;
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $selected_ids[$item_id] = $qty;
        }

        if (empty($selected_ids)) {
            $error = 'Please select at least one item.';
        } else {
            if ($rlsddp_no === '') {
                $rlsddp_no = generate_next_rlsddp_no($conn);
            }

            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $ids_for_query = array_keys($selected_ids);

            $items_by_id = [];
            $stmt = $conn->prepare("
                SELECT id, item, description, stock_no, unit_measurement, unit_value, balance_qty, date_acquired
                FROM items
                WHERE id IN ($placeholders)
                LIMIT " . count($selected_ids) . "
            ");
            if ($stmt) {
                $params = $ids_for_query;
                bind_params($stmt, $types, $params);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($res && $row = $res->fetch_assoc()) {
                    $items_by_id[(int)$row['id']] = $row;
                }
                $stmt->close();
            }

            if (count($items_by_id) === 0) {
                $error = 'Selected items could not be loaded.';
            } else {
                foreach ($selected_ids as $item_id => $qty) {
                    $row = $items_by_id[$item_id] ?? null;
                    if (!$row) {
                        continue;
                    }
                    $available = (float)($row['balance_qty'] ?? 0);
                    if ($qty > $available && $available > 0) {
                        $error = 'Qty exceeds available for ' . (string)$row['item'] . ' (Available: ' . $available . ').';
                        break;
                    }
                }
            }

            if ($error === '') {
                $selected_items = [];
                foreach ($selected_ids as $item_id => $qty) {
                    $row = $items_by_id[$item_id] ?? null;
                    if (!$row) {
                        continue;
                    }
                    $selected_items[$item_id] = [
                        'item' => (string)$row['item'],
                        'description' => (string)($row['description'] ?? ''),
                        'stock_no' => (string)($row['stock_no'] ?? ''),
                        'unit' => (string)($row['unit_measurement'] ?? ''),
                        'unit_value' => (float)($row['unit_value'] ?? 0),
                        'date_acquired' => (string)($row['date_acquired'] ?? ''),
                        'qty' => (float)$qty,
                    ];
                }

                $header = [
                    'entity_name' => $entity_name,
                    'property_type' => $property_type,
                    'department' => $department,
                    'accountable_officer' => $accountable_officer,
                    'office_address' => $office_address,
                    'position_title' => $position_title,
                    'tel_no' => $tel_no,
                    'fund_cluster' => $fund_cluster,
                    'report_date' => $report_date,
                    'pages' => $pages,
                    'pc_no' => $pc_no,
                    'par_no' => $par_no,
                    'public_station' => $public_station,
                    'nature' => $nature,
                    'circumstances' => $circumstances,
                    'verified_by' => $verified_by,
                    'verified_date' => $verified_date,
                    'id_type' => $id_type,
                    'id_no' => $id_no,
                    'id_issued_date' => $id_issued_date,
                    'doc_no' => $doc_no,
                    'page_no' => $page_no,
                    'book_no' => $book_no,
                    'series' => $series,
                    'notary' => $notary,
                ];

                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                $conn->begin_transaction();
                try {
                    $doc_id = upsert_rlsddp_document($conn, $rlsddp_no, $header, $selected_items, $uid);
                    if ($doc_id <= 0) {
                        throw new Exception('Failed to save RLSDDP document.');
                    }
                    $conn->commit();
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: lost_properties.php?msg=saved&view=' . urlencode($rlsddp_no));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$view_doc = null;
$view_doc_items = [];
if ($view_no !== '') {
    ensure_rlsddp_tables($conn);
    if ($stmt = $conn->prepare("SELECT * FROM rlsddp_documents WHERE rlsddp_no = ? LIMIT 1")) {
        $stmt->bind_param("s", $view_no);
        $stmt->execute();
        $res = $stmt->get_result();
        $view_doc = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if ($view_doc && isset($view_doc['id'])) {
        $doc_id = (int)$view_doc['id'];
        if ($stmt = $conn->prepare("SELECT * FROM rlsddp_document_items WHERE rlsddp_document_id = ? ORDER BY item_name ASC")) {
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && $row = $res->fetch_assoc()) {
                $view_doc_items[] = $row;
            }
            $stmt->close();
        }
    }
}

$history = [];
ensure_rlsddp_tables($conn);
$history_res = $conn->query("SELECT rlsddp_no, accountable_officer, department, report_date, nature_json, created_at FROM rlsddp_documents ORDER BY report_date DESC, created_at DESC LIMIT 50");
while ($history_res && $row = $history_res->fetch_assoc()) {
    $history[] = $row;
}

$lp_per_page = isset($_GET['lp_per_page']) ? (int)$_GET['lp_per_page'] : 10;
$lp_allowed = [10, 25, 50];
if (!in_array($lp_per_page, $lp_allowed, true)) {
    $lp_per_page = 10;
}
$lp_page = isset($_GET['lp_page']) ? max(1, (int)$_GET['lp_page']) : 1;
$lp_total = 0;
$lp_pages = 1;
$lp_offset = 0;

$item_types = ['Semi-Expendable', 'Non-Expendable'];
$types_placeholders = implode(',', array_fill(0, count($item_types), '?'));
$type_bind = str_repeat('s', count($item_types));

$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM items WHERE status = 'Active' AND item_type IN ($types_placeholders)");
if ($stmt_count) {
    $params = $item_types;
    bind_params($stmt_count, $type_bind, $params);
    $stmt_count->execute();
    $res = $stmt_count->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $lp_total = (int)($row['total'] ?? 0);
    $stmt_count->close();
}

$lp_pages = $lp_total > 0 ? (int)ceil($lp_total / $lp_per_page) : 1;
if ($lp_page > $lp_pages) {
    $lp_page = $lp_pages;
}
$lp_offset = ($lp_page - 1) * $lp_per_page;
$lp_start = $lp_total > 0 ? ($lp_offset + 1) : 0;
$lp_end = $lp_total > 0 ? min($lp_offset + $lp_per_page, $lp_total) : 0;

$items = [];
$stmt_items = $conn->prepare(
    "SELECT id, stock_no, item, description, unit_measurement, unit_value, balance_qty, item_type, date_acquired
     FROM items
     WHERE status = 'Active' AND item_type IN ($types_placeholders)
     ORDER BY item ASC
     LIMIT ? OFFSET ?"
);
if ($stmt_items) {
    $params = array_merge($item_types, [$lp_per_page, $lp_offset]);
    bind_params($stmt_items, $type_bind . 'ii', $params);
    $stmt_items->execute();
    $res = $stmt_items->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1 fw-bold text-primary">Lost Properties (RLSDDP)</h4>
                <div class="text-muted small">Create and store a report of lost, stolen, damaged, or destroyed property for printing.</div>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success">RLSDDP document saved successfully.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($view_doc): ?>
            <?php
                $nature_list = [];
                if (!empty($view_doc['nature_json'])) {
                    $decoded = json_decode((string)$view_doc['nature_json'], true);
                    if (is_array($decoded)) {
                        $nature_list = $decoded;
                    }
                }
            ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-primary">RLSDDP No. <?php echo htmlspecialchars($view_doc['rlsddp_no'] ?? ''); ?></div>
                        <div class="text-muted small">
                            Date: <?php echo !empty($view_doc['report_date']) ? date('M d, Y', strtotime($view_doc['report_date'])) : '—'; ?>
                            • Nature: <?php echo htmlspecialchars(implode(', ', $nature_list)); ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="lost_properties.php" class="btn btn-outline-secondary btn-sm">Back</a>
                        <form method="POST" action="print_file.php" target="_blank" class="m-0">
                            <input type="hidden" name="report_type" value="rlsddp">
                            <input type="hidden" name="rlsddp_entity_name" value="<?php echo htmlspecialchars($view_doc['entity_name'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_property_type" value="<?php echo htmlspecialchars($view_doc['property_type'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_department" value="<?php echo htmlspecialchars($view_doc['department'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_accountable_officer" value="<?php echo htmlspecialchars($view_doc['accountable_officer'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_office_address" value="<?php echo htmlspecialchars($view_doc['office_address'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_position" value="<?php echo htmlspecialchars($view_doc['position_title'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_tel_no" value="<?php echo htmlspecialchars($view_doc['tel_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_fund_cluster" value="<?php echo htmlspecialchars($view_doc['fund_cluster'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_date" value="<?php echo htmlspecialchars($view_doc['report_date'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_no" value="<?php echo htmlspecialchars($view_doc['rlsddp_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_pages" value="<?php echo htmlspecialchars($view_doc['pages'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_pc_no" value="<?php echo htmlspecialchars($view_doc['pc_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_par_no" value="<?php echo htmlspecialchars($view_doc['par_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_public_station" value="<?php echo htmlspecialchars($view_doc['public_station'] ?? 'No'); ?>">
                            <?php foreach ($nature_list as $n): ?>
                                <input type="hidden" name="rlsddp_nature[]" value="<?php echo htmlspecialchars((string)$n); ?>">
                            <?php endforeach; ?>
                            <input type="hidden" name="rlsddp_circumstances" value="<?php echo htmlspecialchars($view_doc['circumstances'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_verified_by" value="<?php echo htmlspecialchars($view_doc['verified_by'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_verified_date" value="<?php echo htmlspecialchars($view_doc['verified_date'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_id_type" value="<?php echo htmlspecialchars($view_doc['id_type'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_id_no" value="<?php echo htmlspecialchars($view_doc['id_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_id_issued_date" value="<?php echo htmlspecialchars($view_doc['id_issued_date'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_doc_no" value="<?php echo htmlspecialchars($view_doc['doc_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_page_no" value="<?php echo htmlspecialchars($view_doc['page_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_book_no" value="<?php echo htmlspecialchars($view_doc['book_no'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_series" value="<?php echo htmlspecialchars($view_doc['series'] ?? ''); ?>">
                            <input type="hidden" name="rlsddp_notary" value="<?php echo htmlspecialchars($view_doc['notary'] ?? ''); ?>">
                            <?php foreach ($view_doc_items as $it): ?>
                                <?php $iid = (int)($it['item_id'] ?? 0); ?>
                                <input type="hidden" name="items[<?php echo $iid; ?>][selected]" value="1">
                                <input type="hidden" name="items[<?php echo $iid; ?>][item]" value="<?php echo htmlspecialchars($it['item_name'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][description]" value="<?php echo htmlspecialchars($it['description'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][stock_no]" value="<?php echo htmlspecialchars($it['stock_no'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][unit]" value="<?php echo htmlspecialchars($it['unit'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][unit_value]" value="<?php echo htmlspecialchars((string)($it['unit_value'] ?? '0')); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][physical_count]" value="<?php echo htmlspecialchars((string)($it['qty'] ?? '1')); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][date_acquired]" value="<?php echo htmlspecialchars((string)($it['date_acquired'] ?? '')); ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-printer me-2"></i>Print RLSDDP
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Accountable Officer</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['accountable_officer'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Department/Office</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['department'] ?? ''); ?></div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Circumstances</div>
                            <div class="fw-semibold"><?php echo nl2br(htmlspecialchars($view_doc['circumstances'] ?? '')); ?></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Stock No</th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-center">Unit</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view_doc_items as $it): ?>
                                    <tr>
                                        <td class="small"><?php echo htmlspecialchars($it['stock_no'] ?? ''); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($it['item_name'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($it['description'] ?? ''); ?></td>
                                        <td class="text-center small"><?php echo htmlspecialchars($it['unit'] ?? ''); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((float)($it['qty'] ?? 0), 2); ?></td>
                                        <td class="text-end small"><?php echo number_format((float)($it['unit_value'] ?? 0), 2); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format((float)($it['amount'] ?? 0), 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($view_doc_items) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No items found for this RLSDDP.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_doc ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">New RLSDDP</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_doc ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">History</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade <?php echo $view_doc ? '' : 'show active'; ?>" id="tab-new" role="tabpanel">
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <input type="hidden" name="lp_page" value="1">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Per Page</label>
                            <select name="lp_per_page" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($lp_allowed as $n): ?>
                                    <option value="<?php echo (int)$n; ?>" <?php echo $lp_per_page === (int)$n ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-10">
                            <div class="small text-muted">
                                Items shown: <span class="fw-semibold">Semi-Expendable, Non-Expendable</span> (Active only)
                                • Showing <?php echo (int)$lp_start; ?>-<?php echo (int)$lp_end; ?> of <?php echo (int)$lp_total; ?>
                            </div>
                        </div>
                    </form>

                    <form method="POST" id="rlsddpForm">
                        <input type="hidden" name="action" value="create">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">RLSDDP No.</label>
                                <input type="text" name="rlsddp_no" class="form-control" placeholder="Leave blank to auto-generate">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Date</label>
                                <input type="date" name="report_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Number of Pages</label>
                                <input type="text" name="pages" class="form-control" placeholder="e.g. One (1)" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Entity Name</label>
                                <input type="text" name="entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Type of Property</label>
                                <input type="text" name="property_type" class="form-control" placeholder="e.g. Equipment, Furniture" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Department/Office</label>
                                <input type="text" name="department" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Accountable Officer</label>
                                <input type="text" name="accountable_officer" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Office Address</label>
                                <input type="text" name="office_address" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Position Title</label>
                                <input type="text" name="position_title" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Tel No.</label>
                                <input type="text" name="tel_no" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Fund Cluster</label>
                                <input type="text" name="fund_cluster" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Public Station</label>
                                <select name="public_station" class="form-select">
                                    <option value="No" selected>No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">PC No.</label>
                                <input type="text" name="pc_no" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">PAR No.</label>
                                <input type="text" name="par_no" class="form-control" placeholder="Optional">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold text-muted">Nature of Property</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <label class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="nature[]" value="Lost">
                                        <span class="form-check-label">Lost</span>
                                    </label>
                                    <label class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="nature[]" value="Stolen">
                                        <span class="form-check-label">Stolen</span>
                                    </label>
                                    <label class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="nature[]" value="Damaged">
                                        <span class="form-check-label">Damaged</span>
                                    </label>
                                    <label class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="nature[]" value="Destroyed">
                                        <span class="form-check-label">Destroyed</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Circumstances</label>
                                <textarea name="circumstances" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Verified By</label>
                                <input type="text" name="verified_by" class="form-control" placeholder="Immediate supervisor">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Verification Date</label>
                                <input type="date" name="verified_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Government Issued ID</label>
                                <input type="text" name="id_type" class="form-control" placeholder="e.g. Passport, Driver's License">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">ID No.</label>
                                <input type="text" name="id_no" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Date Issued</label>
                                <input type="date" name="id_issued_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Doc No.</label>
                                <input type="text" name="doc_no" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Page No.</label>
                                <input type="text" name="page_no" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Book No.</label>
                                <input type="text" name="book_no" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Series of</label>
                                <input type="text" name="series" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Notary Public</label>
                                <input type="text" name="notary" class="form-control">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 48px;" class="text-center">Pick</th>
                                        <th>Stock No</th>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-center">Type</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Available</th>
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $row): ?>
                                        <?php $id = (int)$row['id']; ?>
                                        <tr>
                                            <td class="text-center">
                                                <input class="form-check-input" type="checkbox" data-lp="selected" data-item-id="<?php echo $id; ?>">
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars($row['stock_no'] ?? ''); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['unit_measurement'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['item_type'] ?? ''); ?></td>
                                            <td class="text-end small"><?php echo number_format((float)$row['unit_value'], 2); ?></td>
                                            <td class="text-end fw-semibold"><?php echo number_format((int)($row['balance_qty'] ?? 0)); ?></td>
                                            <td class="text-end" style="max-width: 120px;">
                                                <input type="number" class="form-control form-control-sm text-end" value="1" min="1" step="1" data-lp="qty" data-item-id="<?php echo $id; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($lp_total > 0): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-end mb-0">
                                    <?php
                                        $base = [
                                            'lp_per_page' => $lp_per_page,
                                        ];
                                        $prev = max(1, $lp_page - 1);
                                        $next = min($lp_pages, $lp_page + 1);
                                    ?>
                                    <li class="page-item <?php echo $lp_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="lost_properties.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['lp_page' => $prev]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $lp_pages; $p++): ?>
                                        <li class="page-item <?php echo $p === $lp_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="lost_properties.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['lp_page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $lp_page >= $lp_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="lost_properties.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['lp_page' => $next]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted">Selections are kept while you change pages.</div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save RLSDDP
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade <?php echo $view_doc ? 'show active' : ''; ?>" id="tab-history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>RLSDDP No</th>
                                    <th>Accountable Officer</th>
                                    <th>Department</th>
                                    <th>Date</th>
                                    <th>Nature</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <?php
                                        $n = [];
                                        if (!empty($h['nature_json'])) {
                                            $d = json_decode((string)$h['nature_json'], true);
                                            if (is_array($d)) {
                                                $n = $d;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-primary"><?php echo htmlspecialchars($h['rlsddp_no'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['accountable_officer'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['department'] ?? ''); ?></td>
                                        <td class="small"><?php echo !empty($h['report_date']) ? date('M d, Y', strtotime($h['report_date'])) : '—'; ?></td>
                                        <td class="small"><?php echo htmlspecialchars(implode(', ', $n)); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="lost_properties.php?view=<?php echo urlencode((string)($h['rlsddp_no'] ?? '')); ?>">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($history) === 0): ?>
                                    <tr>
                                        <td class="text-muted"></td>
                                        <td class="text-muted"></td>
                                        <td class="text-muted"></td>
                                        <td class="text-muted"></td>
                                        <td class="text-muted"></td>
                                        <td class="text-end text-muted py-4">No RLSDDP documents recorded yet.</td>
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
    (function () {
        const form = document.getElementById('rlsddpForm');
        if (!form) return;

        const storageKey = 'rlsddp:selection';

        const readState = function () {
            try {
                const raw = sessionStorage.getItem(storageKey);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        };

        const writeState = function (state) {
            try {
                sessionStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) {
            }
        };

        const state = readState();

        const applyToVisible = function () {
            const selectedEls = form.querySelectorAll('[data-lp="selected"][data-item-id]');
            selectedEls.forEach(function (el) {
                const itemId = String(el.getAttribute('data-item-id') || '');
                if (!itemId) return;
                const saved = state[itemId];
                if (saved && saved.selected) {
                    el.checked = true;
                }
            });

            const qtyEls = form.querySelectorAll('[data-lp="qty"][data-item-id]');
            qtyEls.forEach(function (el) {
                const itemId = String(el.getAttribute('data-item-id') || '');
                if (!itemId) return;
                const saved = state[itemId];
                if (saved && saved.qty !== undefined && saved.qty !== null && saved.qty !== '') {
                    el.value = saved.qty;
                }
            });
        };

        const setSelected = function (itemId, isSelected) {
            if (!state[itemId] || typeof state[itemId] !== 'object') {
                state[itemId] = {};
            }
            state[itemId].selected = !!isSelected;
            writeState(state);
        };

        const setQty = function (itemId, qty) {
            if (!state[itemId] || typeof state[itemId] !== 'object') {
                state[itemId] = {};
            }
            state[itemId].qty = qty;
            writeState(state);
        };

        applyToVisible();

        form.addEventListener('change', function (ev) {
            const el = ev.target;
            if (!el || !el.getAttribute) return;
            const kind = el.getAttribute('data-lp');
            const itemId = String(el.getAttribute('data-item-id') || '');
            if (!kind || !itemId) return;

            if (kind === 'selected') {
                setSelected(itemId, el.checked);
            }
            if (kind === 'qty') {
                setQty(itemId, el.value);
            }
        });

        form.addEventListener('submit', function () {
            const existing = form.querySelectorAll('input[name^="selected["]');
            existing.forEach(function (n) {
                n.remove();
            });

            Object.keys(state).forEach(function (itemId) {
                const rec = state[itemId];
                if (!rec || !rec.selected) return;
                const qty = rec.qty !== undefined && rec.qty !== null && String(rec.qty).trim() !== '' ? String(rec.qty) : '1';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected[' + itemId + '][qty]';
                input.value = qty;
                form.appendChild(input);
            });
        });
    })();
</script>

<?php renderFooter(); ?>

