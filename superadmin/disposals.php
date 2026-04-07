<?php
require_once 'superadmin_sidebar.php';
ob_start();
$page_title = 'Disposals (IIRUP)';
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

function generate_next_iirup_no(mysqli $conn): string
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS iirup_sequences (
            year INT NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        )
    ");

    $year = (int)date('Y');
    $stmt = $conn->prepare("
        INSERT INTO iirup_sequences (year, last_seq)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $iirup_no = trim((string)($_POST['iirup_no'] ?? ''));
    $disposal_mode = trim((string)($_POST['disposal_mode'] ?? ''));
    $date_disposed = validate_ymd((string)($_POST['date_disposed'] ?? ''));
    $appraised_value = (float)($_POST['appraised_value'] ?? 0);
    $or_no = trim((string)($_POST['or_no'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];

    $allowed_modes = ['Sale', 'Destruction', 'Transfer', 'Donation'];

    if (!in_array($disposal_mode, $allowed_modes, true)) {
        $error = 'Please select a valid disposal mode.';
    } elseif ($date_disposed === '') {
        $error = 'Please provide a valid disposal date.';
    } else {
        $selected_ids = [];
        foreach ($selected as $id => $payload) {
            $item_id = (int)$id;
            if ($item_id <= 0) {
                continue;
            }
            $qty_raw = $payload['qty'] ?? 1;
            $qty = (int)$qty_raw;
            if ($qty <= 0) {
                $qty = 1;
            }
            $selected_ids[$item_id] = $qty;
        }

        if (empty($selected_ids)) {
            $error = 'Please select at least one item for disposal.';
        } else {
            if ($iirup_no === '') {
                $iirup_no = generate_next_iirup_no($conn);
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
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

                $conn->begin_transaction();
                try {
                    $stmt_get_balance = $conn->prepare("SELECT balance_qty FROM items WHERE id = ? LIMIT 1");
                    $stmt_update_balance = $conn->prepare("UPDATE items SET balance_qty = balance_qty - ? WHERE id = ?");
                    $stmt_insert_disposal = $conn->prepare("INSERT INTO disposals (item_id, quantity, iirup_no, disposal_mode, date_disposed, appraised_value, or_no, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_insert_tx = $conn->prepare("INSERT INTO inventory_transactions (item_id, transaction_date, transaction_type, quantity, balance_after, remarks) VALUES (?, NOW(), 'Disposal', ?, ?, ?)");

                    if (!$stmt_get_balance || !$stmt_update_balance || !$stmt_insert_disposal || !$stmt_insert_tx) {
                        throw new Exception('Failed to prepare disposal statements.');
                    }

                    foreach ($selected_ids as $item_id => $qty) {
                        $stmt_get_balance->bind_param("i", $item_id);
                        $stmt_get_balance->execute();
                        $res_bal = $stmt_get_balance->get_result();
                        $row_bal = $res_bal ? $res_bal->fetch_assoc() : null;
                        $current_balance = (int)($row_bal['balance_qty'] ?? 0);
                        if ($qty > $current_balance) {
                            throw new Exception('Insufficient stock for item ID ' . $item_id . ' (Available: ' . $current_balance . ', Disposing: ' . $qty . ').');
                        }

                        $stmt_update_balance->bind_param("ii", $qty, $item_id);
                        if (!$stmt_update_balance->execute()) {
                            throw new Exception('Failed to update stock for item ID ' . $item_id . '.');
                        }

                        $new_balance = $current_balance - $qty;

                        $stmt_insert_disposal->bind_param(
                            "iisssdssis",
                            $item_id,
                            $qty,
                            $iirup_no,
                            $disposal_mode,
                            $date_disposed,
                            $appraised_value,
                            $or_no,
                            $remarks,
                            $uid
                        );
                        if (!$stmt_insert_disposal->execute()) {
                            throw new Exception('Failed to insert disposal record for item ID ' . $item_id . '.');
                        }

                        $tx_remarks = $iirup_no !== '' ? ('IIRUP ' . $iirup_no . ' - ' . $disposal_mode) : ('Disposal - ' . $disposal_mode);
                        $stmt_insert_tx->bind_param("iiis", $item_id, $qty, $new_balance, $tx_remarks);
                        if (!$stmt_insert_tx->execute()) {
                            throw new Exception('Failed to log inventory transaction for item ID ' . $item_id . '.');
                        }
                    }

                    $stmt_get_balance->close();
                    $stmt_update_balance->close();
                    $stmt_insert_disposal->close();
                    $stmt_insert_tx->close();

                    $conn->commit();
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: disposals.php?msg=saved&view=' . urlencode($iirup_no));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$view_rows = [];
$view_summary = null;
if ($view_no !== '') {
    if ($stmt = $conn->prepare("
        SELECT d.id, d.item_id, d.quantity, d.iirup_no, d.disposal_mode, d.date_disposed, d.appraised_value, d.or_no, d.remarks, d.created_at,
               i.stock_no, i.item, i.description, i.unit_measurement, i.unit_value, i.item_type
        FROM disposals d
        INNER JOIN items i ON d.item_id = i.id
        WHERE d.iirup_no = ?
        ORDER BY d.id ASC
    ")) {
        $stmt->bind_param("s", $view_no);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $view_rows[] = $row;
        }
        $stmt->close();
    }

    if (count($view_rows) > 0) {
        $first = $view_rows[0];
        $total_amount = 0.0;
        foreach ($view_rows as $r) {
            $total_amount += ((float)$r['unit_value']) * ((int)$r['quantity']);
        }
        $view_summary = [
            'iirup_no' => (string)($first['iirup_no'] ?? ''),
            'disposal_mode' => (string)($first['disposal_mode'] ?? ''),
            'date_disposed' => (string)($first['date_disposed'] ?? ''),
            'appraised_value' => (string)($first['appraised_value'] ?? ''),
            'or_no' => (string)($first['or_no'] ?? ''),
            'remarks' => (string)($first['remarks'] ?? ''),
            'total_amount' => $total_amount,
        ];
    }
}

$history = [];
$history_res = $conn->query("
    SELECT iirup_no,
           MAX(date_disposed) AS date_disposed,
           MAX(disposal_mode) AS disposal_mode,
           COUNT(*) AS items_count,
           SUM(quantity) AS total_qty,
           MAX(created_at) AS created_at
    FROM disposals
    WHERE iirup_no IS NOT NULL AND iirup_no <> ''
    GROUP BY iirup_no
    ORDER BY date_disposed DESC, created_at DESC
    LIMIT 50
");
while ($history_res && $row = $history_res->fetch_assoc()) {
    $history[] = $row;
}

$ds_per_page = isset($_GET['ds_per_page']) ? (int)$_GET['ds_per_page'] : 10;
$ds_allowed = [10, 25, 50];
if (!in_array($ds_per_page, $ds_allowed, true)) {
    $ds_per_page = 10;
}
$ds_page = isset($_GET['ds_page']) ? max(1, (int)$_GET['ds_page']) : 1;
$ds_total = 0;
$ds_pages = 1;
$ds_offset = 0;

$item_types = ['Semi-Expendable', 'Non-Expendable'];
$types_placeholders = implode(',', array_fill(0, count($item_types), '?'));
$type_bind = str_repeat('s', count($item_types));

$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM items WHERE status = 'Active' AND balance_qty > 0 AND item_type IN ($types_placeholders)");
if ($stmt_count) {
    $params = $item_types;
    bind_params($stmt_count, $type_bind, $params);
    $stmt_count->execute();
    $res = $stmt_count->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $ds_total = (int)($row['total'] ?? 0);
    $stmt_count->close();
}

$ds_pages = $ds_total > 0 ? (int)ceil($ds_total / $ds_per_page) : 1;
if ($ds_page > $ds_pages) {
    $ds_page = $ds_pages;
}
$ds_offset = ($ds_page - 1) * $ds_per_page;
$ds_start = $ds_total > 0 ? ($ds_offset + 1) : 0;
$ds_end = $ds_total > 0 ? min($ds_offset + $ds_per_page, $ds_total) : 0;

$items = [];
$stmt_items = $conn->prepare(
    "SELECT id, stock_no, item, description, unit_measurement, unit_value, balance_qty, item_type, date_acquired
     FROM items
     WHERE status = 'Active' AND balance_qty > 0 AND item_type IN ($types_placeholders)
     ORDER BY item ASC
     LIMIT ? OFFSET ?"
);
if ($stmt_items) {
    $params = array_merge($item_types, [$ds_per_page, $ds_offset]);
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
                <h4 class="mb-1 fw-bold text-primary">Disposals (IIRUP)</h4>
                <div class="text-muted small">Record disposed items and keep an audit trail for stock reductions.</div>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success">Disposal saved successfully.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($view_summary): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-primary">IIRUP No. <?php echo htmlspecialchars($view_summary['iirup_no']); ?></div>
                        <div class="text-muted small">
                            Mode: <?php echo htmlspecialchars($view_summary['disposal_mode']); ?> • Date: <?php echo date('M d, Y', strtotime($view_summary['date_disposed'])); ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="disposals.php" class="btn btn-outline-secondary btn-sm">Back</a>
                        <form method="POST" action="print_file.php" target="_blank" class="m-0">
                            <input type="hidden" name="report_type" value="iirup">
                            <input type="hidden" name="iirup_entity_name" value="COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI">
                            <input type="hidden" name="iirup_fund_cluster" value="">
                            <input type="hidden" name="iirup_location" value="">
                            <input type="hidden" name="iirup_inventory_no" value="<?php echo htmlspecialchars($view_summary['iirup_no']); ?>">
                            <input type="hidden" name="iirup_report_date" value="<?php echo htmlspecialchars($view_summary['date_disposed']); ?>">
                            <input type="hidden" name="iirup_requested_by_name" value="">
                            <input type="hidden" name="iirup_requested_by_designation" value="">
                            <input type="hidden" name="iirup_approved_by_name" value="">
                            <input type="hidden" name="iirup_approved_by_designation" value="">
                            <input type="hidden" name="iirup_inspection_officer_name" value="">
                            <input type="hidden" name="iirup_witness_name" value="">
                            <?php foreach ($view_rows as $r): ?>
                                <?php $iid = (int)$r['item_id']; ?>
                                <input type="hidden" name="items[<?php echo $iid; ?>][selected]" value="1">
                                <input type="hidden" name="items[<?php echo $iid; ?>][item]" value="<?php echo htmlspecialchars($r['item'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][description]" value="<?php echo htmlspecialchars($r['description'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][stock_no]" value="<?php echo htmlspecialchars($r['stock_no'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][unit]" value="<?php echo htmlspecialchars($r['unit_measurement'] ?? ''); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][unit_value]" value="<?php echo htmlspecialchars((string)($r['unit_value'] ?? '0')); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][physical_count]" value="<?php echo htmlspecialchars((string)($r['quantity'] ?? '1')); ?>">
                                <input type="hidden" name="items[<?php echo $iid; ?>][date_acquired]" value="<?php echo htmlspecialchars((string)($r['date_acquired'] ?? '')); ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-printer me-2"></i>Print IIRUP
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="small text-muted">Appraised Value</div>
                            <div class="fw-semibold"><?php echo number_format((float)$view_summary['appraised_value'], 2); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">OR No.</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_summary['or_no']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Remarks</div>
                            <div class="fw-semibold"><?php echo nl2br(htmlspecialchars($view_summary['remarks'])); ?></div>
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
                                    <th class="text-center">Type</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view_rows as $r): ?>
                                    <?php $amount = ((float)$r['unit_value']) * ((int)$r['quantity']); ?>
                                    <tr>
                                        <td class="small"><?php echo htmlspecialchars($r['stock_no'] ?? ''); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($r['item'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                                        <td class="text-center small"><?php echo htmlspecialchars($r['unit_measurement'] ?? ''); ?></td>
                                        <td class="text-center small"><?php echo htmlspecialchars($r['item_type'] ?? ''); ?></td>
                                        <td class="text-end small"><?php echo number_format((float)$r['unit_value'], 2); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((int)$r['quantity']); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($amount, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($view_rows) === 0): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No disposal records found for this IIRUP number.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (count($view_rows) > 0): ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="7" class="text-end">Total</th>
                                        <th class="text-end"><?php echo number_format((float)$view_summary['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_summary ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">New Disposal</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_summary ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">History</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade <?php echo $view_summary ? '' : 'show active'; ?>" id="tab-new" role="tabpanel">
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <input type="hidden" name="ds_page" value="1">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Per Page</label>
                            <select name="ds_per_page" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($ds_allowed as $n): ?>
                                    <option value="<?php echo (int)$n; ?>" <?php echo $ds_per_page === (int)$n ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-10">
                            <div class="small text-muted">
                                Items shown: <span class="fw-semibold">Semi-Expendable, Non-Expendable</span> (Active only, balance &gt; 0)
                                • Showing <?php echo (int)$ds_start; ?>-<?php echo (int)$ds_end; ?> of <?php echo (int)$ds_total; ?>
                            </div>
                        </div>
                    </form>

                    <form method="POST" id="disposalForm">
                        <input type="hidden" name="action" value="create">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">IIRUP No.</label>
                                <input type="text" name="iirup_no" class="form-control" placeholder="Leave blank to auto-generate">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Disposal Mode</label>
                                <select name="disposal_mode" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Sale">Sale</option>
                                    <option value="Destruction">Destruction</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Donation">Donation</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Date Disposed</label>
                                <input type="date" name="date_disposed" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Appraised Value</label>
                                <input type="number" name="appraised_value" class="form-control" value="0" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">OR No.</label>
                                <input type="text" name="or_no" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Remarks</label>
                                <input type="text" name="remarks" class="form-control">
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
                                                <input class="form-check-input" type="checkbox" data-ds="selected" data-item-id="<?php echo $id; ?>">
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars($row['stock_no'] ?? ''); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['unit_measurement'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['item_type'] ?? ''); ?></td>
                                            <td class="text-end small"><?php echo number_format((float)$row['unit_value'], 2); ?></td>
                                            <td class="text-end fw-semibold"><?php echo number_format((int)$row['balance_qty']); ?></td>
                                            <td class="text-end" style="max-width: 120px;">
                                                <input type="number" class="form-control form-control-sm text-end" value="1" min="1" step="1" data-ds="qty" data-item-id="<?php echo $id; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($ds_total > 0): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-end mb-0">
                                    <?php
                                        $base = [
                                            'ds_per_page' => $ds_per_page,
                                        ];
                                        $prev = max(1, $ds_page - 1);
                                        $next = min($ds_pages, $ds_page + 1);
                                    ?>
                                    <li class="page-item <?php echo $ds_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="disposals.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['ds_page' => $prev]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $ds_pages; $p++): ?>
                                        <li class="page-item <?php echo $p === $ds_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="disposals.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['ds_page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $ds_page >= $ds_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="disposals.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['ds_page' => $next]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted">Selections are kept while you change pages.</div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Disposal
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade <?php echo $view_summary ? 'show active' : ''; ?>" id="tab-history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>IIRUP No</th>
                                    <th>Date</th>
                                    <th>Mode</th>
                                    <th class="text-end">Items</th>
                                    <th class="text-end">Total Qty</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary"><?php echo htmlspecialchars($h['iirup_no'] ?? ''); ?></td>
                                        <td class="small"><?php echo !empty($h['date_disposed']) ? date('M d, Y', strtotime($h['date_disposed'])) : '—'; ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['disposal_mode'] ?? ''); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((int)($h['items_count'] ?? 0)); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((int)($h['total_qty'] ?? 0)); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="disposals.php?view=<?php echo urlencode((string)($h['iirup_no'] ?? '')); ?>">View</a>
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
                                        <td class="text-end text-muted py-4">No disposal records recorded yet.</td>
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
        const form = document.getElementById('disposalForm');
        if (!form) return;

        const storageKey = 'ds:selection';

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
            const selectedEls = form.querySelectorAll('[data-ds="selected"][data-item-id]');
            selectedEls.forEach(function (el) {
                const itemId = String(el.getAttribute('data-item-id') || '');
                if (!itemId) return;
                const saved = state[itemId];
                if (saved && saved.selected) {
                    el.checked = true;
                }
            });

            const qtyEls = form.querySelectorAll('[data-ds="qty"][data-item-id]');
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
            const kind = el.getAttribute('data-ds');
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

