<?php
require_once 'superadmin_sidebar.php';
ob_start();
$page_title = 'Transfers (PTR)';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
include '../plugins/conn.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

function validate_ymd(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }
    return $value;
}

function generate_next_ptr_no(mysqli $conn): string
{
    $year = (int)date('Y');
    $stmt = $conn->prepare("
        INSERT INTO ptr_sequences (year, last_seq)
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

function bind_params(mysqli_stmt $stmt, string $types, array $params): bool
{
    $args = [$types];
    foreach ($params as $i => $value) {
        $args[] = &$params[$i];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $args);
}

function upsert_ptr_document(mysqli $conn, string $ptr_no, array $header, array $selected_items, int $user_id): int
{
    if ($ptr_no === '' || empty($selected_items)) {
        return 0;
    }

    $selected_ids = [];
    foreach ($selected_items as $id => $d) {
        $selected_ids[] = (string)$id;
    }
    sort($selected_ids, SORT_NATURAL);
    $items_hash = sha1(implode(',', $selected_ids));

    $stmt_find = $conn->prepare("SELECT id FROM ptr_documents WHERE ptr_no = ? LIMIT 1");
    if (!$stmt_find) {
        return 0;
    }
    $stmt_find->bind_param("s", $ptr_no);
    $stmt_find->execute();
    $res = $stmt_find->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt_find->close();

    $doc_id = 0;
    if ($existing && isset($existing['id'])) {
        $doc_id = (int)$existing['id'];
    } else {
        $entity_name = substr(trim((string)($header['entity_name'] ?? '')), 0, 255);
        $fund_cluster = substr(trim((string)($header['fund_cluster'] ?? '')), 0, 100);
        $from_officer = substr(trim((string)($header['from_officer'] ?? '')), 0, 255);
        $to_officer = substr(trim((string)($header['to_officer'] ?? '')), 0, 255);
        $transfer_date = !empty($header['transfer_date']) ? date('Y-m-d', strtotime((string)$header['transfer_date'])) : null;
        $transfer_type = substr(trim((string)($header['transfer_type'] ?? '')), 0, 50);
        $others_specify = substr(trim((string)($header['others_specify'] ?? '')), 0, 255);
        $reason = (string)($header['reason'] ?? '');
        $approved_by_name = substr(trim((string)($header['approved_by_name'] ?? '')), 0, 255);
        $approved_by_designation = substr(trim((string)($header['approved_by_designation'] ?? '')), 0, 255);
        $released_by_name = substr(trim((string)($header['released_by_name'] ?? '')), 0, 255);
        $released_by_designation = substr(trim((string)($header['released_by_designation'] ?? '')), 0, 255);
        $received_by_name = substr(trim((string)($header['received_by_name'] ?? '')), 0, 255);
        $received_by_designation = substr(trim((string)($header['received_by_designation'] ?? '')), 0, 255);

        $stmt_ins = $conn->prepare("
            INSERT INTO ptr_documents
                (ptr_no, entity_name, fund_cluster, from_officer, to_officer, transfer_date, transfer_type, others_specify, reason,
                 approved_by_name, approved_by_designation, released_by_name, released_by_designation, received_by_name, received_by_designation,
                 items_hash, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt_ins) {
            return 0;
        }
        $stmt_ins->bind_param(
            "ssssssssssssssssi",
            $ptr_no,
            $entity_name,
            $fund_cluster,
            $from_officer,
            $to_officer,
            $transfer_date,
            $transfer_type,
            $others_specify,
            $reason,
            $approved_by_name,
            $approved_by_designation,
            $released_by_name,
            $released_by_designation,
            $received_by_name,
            $received_by_designation,
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

    $stmt_has = $conn->prepare("SELECT 1 FROM ptr_document_items WHERE ptr_document_id = ? LIMIT 1");
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
        INSERT INTO ptr_document_items
            (ptr_document_id, item_id, item_name, description, stock_no, unit, qty, unit_value, amount, date_acquired)
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
        $qty = isset($d['physical_count']) ? (float)$d['physical_count'] : 1.0;
        if ($qty <= 0) $qty = 1.0;
        $unit_value = isset($d['unit_value']) ? (float)$d['unit_value'] : 0.0;
        $amount = $qty * $unit_value;
        $date_acquired = null;
        if (!empty($d['date_acquired'])) {
            $date_acquired = date('Y-m-d', strtotime((string)$d['date_acquired']));
        }

        $stmt_item->bind_param("iissssddds", $doc_id, $id_int, $item_name, $description, $stock_no, $unit, $qty, $unit_value, $amount, $date_acquired);
        $stmt_item->execute();
    }

    $stmt_item->close();
    return $doc_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $entity_name = trim((string)($_POST['entity_name'] ?? ''));
    $fund_cluster = trim((string)($_POST['fund_cluster'] ?? ''));
    $from_officer = trim((string)($_POST['from_officer'] ?? ''));
    $to_officer = trim((string)($_POST['to_officer'] ?? ''));
    $ptr_no = trim((string)($_POST['ptr_no'] ?? ''));
    $transfer_date = validate_ymd((string)($_POST['transfer_date'] ?? ''));
    $transfer_type = trim((string)($_POST['transfer_type'] ?? ''));
    $others_specify = trim((string)($_POST['others_specify'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $approved_by_name = trim((string)($_POST['approved_by_name'] ?? ''));
    $approved_by_designation = trim((string)($_POST['approved_by_designation'] ?? ''));
    $released_by_name = trim((string)($_POST['released_by_name'] ?? ''));
    $released_by_designation = trim((string)($_POST['released_by_designation'] ?? ''));
    $received_by_name = trim((string)($_POST['received_by_name'] ?? ''));
    $received_by_designation = trim((string)($_POST['received_by_designation'] ?? ''));
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];

    if ($entity_name === '') {
        $error = 'Entity name is required.';
    } elseif ($from_officer === '' || $to_officer === '') {
        $error = 'From/To officer is required.';
    } elseif ($transfer_date === '') {
        $error = 'Transfer date is required.';
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
            $error = 'Please select at least one item to transfer.';
        } else {
            if ($ptr_no === '') {
                $ptr_no = generate_next_ptr_no($conn);
            }

            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $ids_for_query = array_keys($selected_ids);

            $selected_items = [];
            $stmt = $conn->prepare("
                SELECT id, item, description, stock_no, unit_measurement, unit_value, date_acquired
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
                    $id = (int)$row['id'];
                    $selected_items[$id] = [
                        'item' => (string)$row['item'],
                        'description' => (string)($row['description'] ?? ''),
                        'stock_no' => (string)($row['stock_no'] ?? ''),
                        'unit' => (string)($row['unit_measurement'] ?? ''),
                        'unit_value' => (float)($row['unit_value'] ?? 0),
                        'date_acquired' => (string)($row['date_acquired'] ?? ''),
                        'physical_count' => (float)$selected_ids[$id],
                    ];
                }
                $stmt->close();
            }

            if (count($selected_items) === 0) {
                $error = 'Selected items could not be loaded.';
            } else {
                $header = [
                    'entity_name' => $entity_name,
                    'fund_cluster' => $fund_cluster,
                    'from_officer' => $from_officer,
                    'to_officer' => $to_officer,
                    'transfer_date' => $transfer_date,
                    'transfer_type' => $transfer_type,
                    'others_specify' => $others_specify,
                    'reason' => $reason,
                    'approved_by_name' => $approved_by_name,
                    'approved_by_designation' => $approved_by_designation,
                    'released_by_name' => $released_by_name,
                    'released_by_designation' => $released_by_designation,
                    'received_by_name' => $received_by_name,
                    'received_by_designation' => $received_by_designation,
                ];

                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                $conn->begin_transaction();
                try {
                    $doc_id = upsert_ptr_document($conn, $ptr_no, $header, $selected_items, $uid);
                    if ($doc_id <= 0) {
                        throw new Exception('Failed to save PTR document.');
                    }
                    $conn->commit();
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: transfers.php?msg=saved&view=' . $doc_id);
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
if ($view_id > 0) {
    if ($stmt = $conn->prepare('SELECT * FROM ptr_documents WHERE id = ? LIMIT 1')) {
        $stmt->bind_param('i', $view_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $view_doc = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($view_doc) {
        if ($stmt = $conn->prepare('SELECT * FROM ptr_document_items WHERE ptr_document_id = ? ORDER BY item_name ASC')) {
            $stmt->bind_param('i', $view_id);
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
$history_res = $conn->query('SELECT id, ptr_no, entity_name, from_officer, to_officer, transfer_date, created_at FROM ptr_documents ORDER BY transfer_date DESC, id DESC LIMIT 50');
while ($history_res && $row = $history_res->fetch_assoc()) {
    $history[] = $row;
}

$tr_per_page = isset($_GET['tr_per_page']) ? (int)$_GET['tr_per_page'] : 10;
$tr_allowed = [10, 25, 50];
if (!in_array($tr_per_page, $tr_allowed, true)) {
    $tr_per_page = 10;
}
$tr_page = isset($_GET['tr_page']) ? max(1, (int)$_GET['tr_page']) : 1;
$tr_total = 0;
$tr_pages = 1;
$tr_offset = 0;

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
    $tr_total = (int)($row['total'] ?? 0);
    $stmt_count->close();
}
$tr_pages = $tr_total > 0 ? (int)ceil($tr_total / $tr_per_page) : 1;
if ($tr_page > $tr_pages) {
    $tr_page = $tr_pages;
}
$tr_offset = ($tr_page - 1) * $tr_per_page;
$tr_start = $tr_total > 0 ? ($tr_offset + 1) : 0;
$tr_end = $tr_total > 0 ? min($tr_offset + $tr_per_page, $tr_total) : 0;

$items = [];
$stmt_items = $conn->prepare(
    "SELECT id, stock_no, item, description, unit_measurement, unit_value, item_type
     FROM items
     WHERE status = 'Active' AND item_type IN ($types_placeholders)
     ORDER BY item ASC
     LIMIT ? OFFSET ?"
);
if ($stmt_items) {
    $params = array_merge($item_types, [$tr_per_page, $tr_offset]);
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
                <h4 class="mb-1 fw-bold text-primary">Transfers (PTR)</h4>
                <div class="text-muted small">Create and store Property Transfer Reports for PPE accountability movements.</div>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success">PTR document saved successfully.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($view_doc): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-primary">PTR No. <?php echo htmlspecialchars($view_doc['ptr_no'] ?? ''); ?></div>
                        <div class="text-muted small">
                            Transfer Date: <?php echo !empty($view_doc['transfer_date']) ? date('M d, Y', strtotime($view_doc['transfer_date'])) : '—'; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="transfers.php" class="btn btn-outline-secondary btn-sm">Back</a>
                        <form method="POST" action="print_file.php" target="_blank" class="m-0">
                            <input type="hidden" name="report_type" value="ptr">
                            <input type="hidden" name="ptr_entity_name" value="<?php echo htmlspecialchars($view_doc['entity_name'] ?? ''); ?>">
                            <input type="hidden" name="ptr_fund_cluster" value="<?php echo htmlspecialchars($view_doc['fund_cluster'] ?? ''); ?>">
                            <input type="hidden" name="ptr_from_officer" value="<?php echo htmlspecialchars($view_doc['from_officer'] ?? ''); ?>">
                            <input type="hidden" name="ptr_to_officer" value="<?php echo htmlspecialchars($view_doc['to_officer'] ?? ''); ?>">
                            <input type="hidden" name="ptr_no" value="<?php echo htmlspecialchars($view_doc['ptr_no'] ?? ''); ?>">
                            <input type="hidden" name="ptr_date" value="<?php echo htmlspecialchars($view_doc['transfer_date'] ?? ''); ?>">
                            <input type="hidden" name="ptr_transfer_type" value="<?php echo htmlspecialchars($view_doc['transfer_type'] ?? ''); ?>">
                            <input type="hidden" name="ptr_others_specify" value="<?php echo htmlspecialchars($view_doc['others_specify'] ?? ''); ?>">
                            <input type="hidden" name="ptr_reason" value="<?php echo htmlspecialchars($view_doc['reason'] ?? ''); ?>">
                            <input type="hidden" name="ptr_approved_by_name" value="<?php echo htmlspecialchars($view_doc['approved_by_name'] ?? ''); ?>">
                            <input type="hidden" name="ptr_approved_by_designation" value="<?php echo htmlspecialchars($view_doc['approved_by_designation'] ?? ''); ?>">
                            <input type="hidden" name="ptr_released_by_name" value="<?php echo htmlspecialchars($view_doc['released_by_name'] ?? ''); ?>">
                            <input type="hidden" name="ptr_released_by_designation" value="<?php echo htmlspecialchars($view_doc['released_by_designation'] ?? ''); ?>">
                            <input type="hidden" name="ptr_received_by_name" value="<?php echo htmlspecialchars($view_doc['received_by_name'] ?? ''); ?>">
                            <input type="hidden" name="ptr_received_by_designation" value="<?php echo htmlspecialchars($view_doc['received_by_designation'] ?? ''); ?>">
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
                                <i class="bi bi-printer me-2"></i>Print PTR
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Entity Name</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['entity_name'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Fund Cluster</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['fund_cluster'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Transfer Type</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['transfer_type'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">From Officer</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['from_officer'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">To Officer</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_doc['to_officer'] ?? ''); ?></div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Reason</div>
                            <div class="fw-semibold"><?php echo nl2br(htmlspecialchars($view_doc['reason'] ?? '')); ?></div>
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
                                        <td colspan="7" class="text-center text-muted py-4">No items found for this PTR.</td>
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
                        <button class="nav-link <?php echo $view_doc ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">New PTR</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_doc ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">History</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade <?php echo $view_doc ? '' : 'show active'; ?>" id="tab-new" role="tabpanel">
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <input type="hidden" name="tr_page" value="1">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Per Page</label>
                            <select name="tr_per_page" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($tr_allowed as $n): ?>
                                    <option value="<?php echo (int)$n; ?>" <?php echo $tr_per_page === (int)$n ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-10">
                            <div class="small text-muted">
                                Items shown: <span class="fw-semibold">Semi-Expendable, Non-Expendable</span> (Active only)
                                • Showing <?php echo (int)$tr_start; ?>-<?php echo (int)$tr_end; ?> of <?php echo (int)$tr_total; ?>
                            </div>
                        </div>
                    </form>

                    <form method="POST" id="ptrForm">
                        <input type="hidden" name="action" value="create">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Entity Name</label>
                                <input type="text" name="entity_name" class="form-control" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Fund Cluster</label>
                                <input type="text" name="fund_cluster" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">PTR No.</label>
                                <input type="text" name="ptr_no" class="form-control" placeholder="Leave blank to auto-generate">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">From Officer</label>
                                <input type="text" name="from_officer" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">To Officer</label>
                                <input type="text" name="to_officer" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Transfer Date</label>
                                <input type="date" name="transfer_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Transfer Type</label>
                                <select name="transfer_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Donation">Donation</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Relocate">Relocate</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Others (Specify)</label>
                                <input type="text" name="others_specify" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">Reason</label>
                                <textarea name="reason" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Approved By (Name)</label>
                                <input type="text" name="approved_by_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Approved By (Designation)</label>
                                <input type="text" name="approved_by_designation" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Released By (Name)</label>
                                <input type="text" name="released_by_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Released By (Designation)</label>
                                <input type="text" name="released_by_designation" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Received By (Name)</label>
                                <input type="text" name="received_by_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Received By (Designation)</label>
                                <input type="text" name="received_by_designation" class="form-control">
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
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $row): ?>
                                        <?php $id = (int)$row['id']; ?>
                                        <tr>
                                            <td class="text-center">
                                                <input class="form-check-input" type="checkbox" data-ptr="selected" data-item-id="<?php echo $id; ?>">
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars($row['stock_no'] ?? ''); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['unit_measurement'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['item_type'] ?? ''); ?></td>
                                            <td class="text-end small"><?php echo number_format((float)$row['unit_value'], 2); ?></td>
                                            <td class="text-end" style="max-width: 120px;">
                                                <input type="number" class="form-control form-control-sm text-end" value="1" min="1" step="1" data-ptr="qty" data-item-id="<?php echo $id; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($tr_total > 0): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-end mb-0">
                                    <?php
                                        $base = [
                                            'tr_per_page' => $tr_per_page,
                                        ];
                                        $prev = max(1, $tr_page - 1);
                                        $next = min($tr_pages, $tr_page + 1);
                                    ?>
                                    <li class="page-item <?php echo $tr_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="transfers.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['tr_page' => $prev]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $tr_pages; $p++): ?>
                                        <li class="page-item <?php echo $p === $tr_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="transfers.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['tr_page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $tr_page >= $tr_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="transfers.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['tr_page' => $next]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted">Selections are kept while you change pages.</div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save PTR
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade <?php echo $view_doc ? 'show active' : ''; ?>" id="tab-history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>PTR No</th>
                                    <th>Entity</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary"><?php echo htmlspecialchars($h['ptr_no'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['entity_name'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['from_officer'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['to_officer'] ?? ''); ?></td>
                                        <td class="small"><?php echo !empty($h['transfer_date']) ? date('M d, Y', strtotime($h['transfer_date'])) : '—'; ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="transfers.php?view=<?php echo (int)$h['id']; ?>">View</a>
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
                                        <td class="text-end text-muted py-4">No PTR documents recorded yet.</td>
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
        const form = document.getElementById('ptrForm');
        if (!form) return;

        const storageKey = 'ptr:selection';

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
            const selectedEls = form.querySelectorAll('[data-ptr="selected"][data-item-id]');
            selectedEls.forEach(function (el) {
                const itemId = String(el.getAttribute('data-item-id') || '');
                if (!itemId) return;
                const saved = state[itemId];
                if (saved && saved.selected) {
                    el.checked = true;
                }
            });

            const qtyEls = form.querySelectorAll('[data-ptr="qty"][data-item-id]');
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
            const kind = el.getAttribute('data-ptr');
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
