<?php
require_once 'superadmin_sidebar.php';
ob_start();
$page_title = 'Physical Count';
renderHeader($page_title);
renderSidebar();
include 'superadmin_navbar.php';
include '../plugins/conn.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$selected_count_type = isset($_GET['count_type']) && in_array($_GET['count_type'], ['RPCI', 'RPCPPE'], true) ? $_GET['count_type'] : 'RPCI';
$selected_date_as_of = isset($_GET['date_as_of']) ? trim((string)$_GET['date_as_of']) : date('Y-m-d');
$selected_date_as_of = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date_as_of) ? $selected_date_as_of : date('Y-m-d');

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

$view_count = null;
$view_items = [];

if ($view_id > 0) {
    if ($stmt = $conn->prepare('SELECT id, date_as_of, count_type, certified_by, approved_by, remarks, created_at FROM physical_counts WHERE id = ? LIMIT 1')) {
        $stmt->bind_param('i', $view_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $view_count = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if ($view_count) {
        $sql_items = 'SELECT pci.item_id, pci.balance_per_card, pci.on_hand_per_count, pci.remarks AS count_remarks, i.stock_no, i.item, i.description, i.unit_measurement, i.unit_value, i.item_type
                      FROM physical_count_items pci
                      INNER JOIN items i ON pci.item_id = i.id
                      WHERE pci.physical_count_id = ?
                      ORDER BY i.item ASC';
        if ($stmt = $conn->prepare($sql_items)) {
            $stmt->bind_param('i', $view_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && $row = $res->fetch_assoc()) {
                $view_items[] = $row;
            }
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $date_as_of = isset($_POST['date_as_of']) ? trim((string)$_POST['date_as_of']) : '';
    $count_type = isset($_POST['count_type']) ? trim((string)$_POST['count_type']) : '';
    $certified_by = isset($_POST['certified_by']) ? trim((string)$_POST['certified_by']) : '';
    $approved_by = isset($_POST['approved_by']) ? trim((string)$_POST['approved_by']) : '';
    $remarks = isset($_POST['remarks']) ? trim((string)$_POST['remarks']) : '';
    $items_input = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_as_of)) {
        $error = 'Please provide a valid date (YYYY-MM-DD).';
    } elseif (!in_array($count_type, ['RPCI', 'RPCPPE'], true)) {
        $error = 'Invalid count type.';
    } elseif (empty($items_input)) {
        $error = 'No items were submitted for counting.';
    } else {
        $item_ids = [];
        foreach ($items_input as $item_id => $payload) {
            $id = (int)$item_id;
            if ($id > 0) {
                $item_ids[$id] = true;
            }
        }
        $item_ids = array_keys($item_ids);

        if (empty($item_ids)) {
            $error = 'No valid items were submitted for counting.';
        } else {
            $conn->begin_transaction();
            try {
                $pc_id = 0;
                if ($stmt = $conn->prepare('INSERT INTO physical_counts (date_as_of, count_type, certified_by, approved_by, remarks) VALUES (?, ?, ?, ?, ?)')) {
                    $stmt->bind_param('sssss', $date_as_of, $count_type, $certified_by, $approved_by, $remarks);
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to create physical count header.');
                    }
                    $pc_id = (int)$conn->insert_id;
                    $stmt->close();
                } else {
                    throw new Exception('Failed to prepare physical count header.');
                }

                $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                $types = str_repeat('i', count($item_ids));
                $sql_balances = "SELECT id, balance_qty FROM items WHERE id IN ($placeholders)";
                $stmt_bal = $conn->prepare($sql_balances);
                if (!$stmt_bal) {
                    throw new Exception('Failed to prepare item lookup.');
                }
                $stmt_bal->bind_param($types, ...$item_ids);
                if (!$stmt_bal->execute()) {
                    throw new Exception('Failed to load item balances.');
                }
                $res_bal = $stmt_bal->get_result();
                $balances = [];
                while ($res_bal && $r = $res_bal->fetch_assoc()) {
                    $balances[(int)$r['id']] = (int)$r['balance_qty'];
                }
                $stmt_bal->close();

                $stmt_item = $conn->prepare('INSERT INTO physical_count_items (physical_count_id, item_id, balance_per_card, on_hand_per_count, remarks) VALUES (?, ?, ?, ?, ?)');
                if (!$stmt_item) {
                    throw new Exception('Failed to prepare physical count items.');
                }

                foreach ($item_ids as $id) {
                    if (!array_key_exists($id, $balances)) {
                        continue;
                    }
                    $balance_per_card = (int)$balances[$id];
                    $raw_on_hand = $items_input[$id]['on_hand'] ?? $balance_per_card;
                    $on_hand = (int)max(0, (int)$raw_on_hand);
                    $item_remarks = isset($items_input[$id]['remarks']) ? trim((string)$items_input[$id]['remarks']) : '';
                    $stmt_item->bind_param('iiiis', $pc_id, $id, $balance_per_card, $on_hand, $item_remarks);
                    if (!$stmt_item->execute()) {
                        throw new Exception('Failed to save physical count item.');
                    }
                }
                $stmt_item->close();

                $conn->commit();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: physical_count.php?msg=saved&view=' . $pc_id);
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$history = [];
$history_sql = 'SELECT id, date_as_of, count_type, certified_by, approved_by, created_at FROM physical_counts ORDER BY date_as_of DESC, id DESC LIMIT 50';
$history_res = $conn->query($history_sql);
while ($history_res && $row = $history_res->fetch_assoc()) {
    $history[] = $row;
}

function bind_params(mysqli_stmt $stmt, string $types, array $params): bool
{
    $args = [$types];
    foreach ($params as $i => $value) {
        $args[] = &$params[$i];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $args);
}

$item_types = $selected_count_type === 'RPCI'
    ? ['Expendable']
    : ['Semi-Expendable', 'Non-Expendable'];

$pc_per_page = isset($_GET['pc_per_page']) ? (int)$_GET['pc_per_page'] : 10;
$pc_allowed_per_page = [10, 25, 50];
if (!in_array($pc_per_page, $pc_allowed_per_page, true)) {
    $pc_per_page = 10;
}
$pc_page = isset($_GET['pc_page']) ? max(1, (int)$_GET['pc_page']) : 1;
$pc_total = 0;
$pc_pages = 1;
$pc_offset = 0;

$types_placeholders = implode(',', array_fill(0, count($item_types), '?'));
$type_bind = str_repeat('s', count($item_types));

$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM items WHERE status = 'Active' AND item_type IN ($types_placeholders)");
if ($stmt_count) {
    $params = $item_types;
    bind_params($stmt_count, $type_bind, $params);
    $stmt_count->execute();
    $res = $stmt_count->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $pc_total = (int)($row['total'] ?? 0);
    $stmt_count->close();
}

$pc_pages = $pc_total > 0 ? (int)ceil($pc_total / $pc_per_page) : 1;
if ($pc_page > $pc_pages) {
    $pc_page = $pc_pages;
}
$pc_offset = ($pc_page - 1) * $pc_per_page;
$pc_start = $pc_total > 0 ? ($pc_offset + 1) : 0;
$pc_end = $pc_total > 0 ? min($pc_offset + $pc_per_page, $pc_total) : 0;

$items = [];
$stmt_items = $conn->prepare(
    "SELECT id, stock_no, item, description, unit_measurement, unit_value, balance_qty, item_type
     FROM items
     WHERE status = 'Active' AND item_type IN ($types_placeholders)
     ORDER BY item ASC
     LIMIT ? OFFSET ?"
);
if ($stmt_items) {
    $params = array_merge($item_types, [$pc_per_page, $pc_offset]);
    bind_params($stmt_items, $type_bind . 'ii', $params);
    $stmt_items->execute();
    $res = $stmt_items->get_result();
    while ($res && $row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();
}

$all_item_defaults = [];
$stmt_all = $conn->prepare("SELECT id, balance_qty FROM items WHERE status = 'Active' AND item_type IN ($types_placeholders) ORDER BY item ASC");
if ($stmt_all) {
    $params = $item_types;
    bind_params($stmt_all, $type_bind, $params);
    $stmt_all->execute();
    $res = $stmt_all->get_result();
    while ($res && $r = $res->fetch_assoc()) {
        $all_item_defaults[] = [
            'id' => (int)$r['id'],
            'balance' => (int)$r['balance_qty'],
        ];
    }
    $stmt_all->close();
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1 fw-bold text-primary">Physical Count</h4>
                <div class="text-muted small">Create a physical count sheet and store the results for reporting.</div>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success">Physical count saved successfully.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($view_count): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-primary">View Physical Count</div>
                        <div class="text-muted small">
                            <?php echo htmlspecialchars($view_count['count_type']); ?> • As of <?php echo date('M d, Y', strtotime($view_count['date_as_of'])); ?>
                        </div>
                    </div>
                    <a href="physical_count.php" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="small text-muted">Certified By</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_count['certified_by'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Approved By</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($view_count['approved_by'] ?? ''); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Remarks</div>
                            <div class="fw-semibold"><?php echo nl2br(htmlspecialchars($view_count['remarks'] ?? '')); ?></div>
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
                                    <th class="text-end">Balance (Card)</th>
                                    <th class="text-end">On Hand (Count)</th>
                                    <th class="text-end">Short/Over</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view_items as $it): ?>
                                    <?php
                                        $bal = (int)$it['balance_per_card'];
                                        $onhand = (int)$it['on_hand_per_count'];
                                        $diff = $onhand - $bal;
                                    ?>
                                    <tr>
                                        <td class="small"><?php echo htmlspecialchars($it['stock_no'] ?? ''); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($it['item']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($it['description'] ?? ''); ?></td>
                                        <td class="text-center small"><?php echo htmlspecialchars($it['unit_measurement'] ?? ''); ?></td>
                                        <td class="text-center small"><?php echo htmlspecialchars($it['item_type'] ?? ''); ?></td>
                                        <td class="text-end small"><?php echo number_format((float)$it['unit_value'], 2); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format($bal); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format($onhand); ?></td>
                                        <td class="text-end fw-bold <?php echo $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : 'text-muted'); ?>">
                                            <?php echo $diff > 0 ? '+' . $diff : (string)$diff; ?>
                                        </td>
                                        <td class="small"><?php echo htmlspecialchars($it['count_remarks'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($view_items) === 0): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No items found for this physical count.</td>
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
                        <button class="nav-link <?php echo $view_count ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">New Physical Count</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $view_count ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">History</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade <?php echo $view_count ? '' : 'show active'; ?>" id="tab-new" role="tabpanel">
                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <input type="hidden" name="pc_page" value="1">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Count Type</label>
                            <select name="count_type" class="form-select" onchange="this.form.submit()">
                                <option value="RPCI" <?php echo $selected_count_type === 'RPCI' ? 'selected' : ''; ?>>RPCI (Inventories)</option>
                                <option value="RPCPPE" <?php echo $selected_count_type === 'RPCPPE' ? 'selected' : ''; ?>>RPCPPE (PPE)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Date As Of</label>
                            <input type="date" name="date_as_of" class="form-control" value="<?php echo htmlspecialchars($selected_date_as_of); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Per Page</label>
                            <select name="pc_per_page" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($pc_allowed_per_page as $n): ?>
                                    <option value="<?php echo (int)$n; ?>" <?php echo $pc_per_page === (int)$n ? 'selected' : ''; ?>><?php echo (int)$n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">
                                Items shown:
                                <span class="fw-semibold"><?php echo htmlspecialchars(implode(', ', $item_types)); ?></span>
                                (Active only) • Showing <?php echo (int)$pc_start; ?>-<?php echo (int)$pc_end; ?> of <?php echo (int)$pc_total; ?>
                            </div>
                        </div>
                    </form>

                    <form method="POST" id="physicalCountForm">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="count_type" value="<?php echo htmlspecialchars($selected_count_type); ?>">
                        <input type="hidden" name="date_as_of" value="<?php echo htmlspecialchars($selected_date_as_of); ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Certified By</label>
                                <input type="text" name="certified_by" class="form-control" placeholder="Name / Position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Approved By</label>
                                <input type="text" name="approved_by" class="form-control" placeholder="Name / Position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional notes for this count">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Stock No</th>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-center">Type</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Balance (Card)</th>
                                        <th class="text-end">On Hand (Count)</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $row): ?>
                                        <?php $id = (int)$row['id']; ?>
                                        <tr>
                                            <td class="small"><?php echo htmlspecialchars($row['stock_no'] ?? ''); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['item']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['unit_measurement'] ?? ''); ?></td>
                                            <td class="text-center small"><?php echo htmlspecialchars($row['item_type'] ?? ''); ?></td>
                                            <td class="text-end small"><?php echo number_format((float)$row['unit_value'], 2); ?></td>
                                            <td class="text-end fw-semibold"><?php echo number_format((int)$row['balance_qty']); ?></td>
                                            <td class="text-end" style="max-width: 140px;">
                                                <input type="number" name="items[<?php echo $id; ?>][on_hand]" class="form-control form-control-sm text-end" value="<?php echo (int)$row['balance_qty']; ?>" min="0" step="1" data-pc-item="on_hand" data-item-id="<?php echo $id; ?>">
                                            </td>
                                            <td style="min-width: 180px;">
                                                <input type="text" name="items[<?php echo $id; ?>][remarks]" class="form-control form-control-sm" placeholder="Optional" data-pc-item="remarks" data-item-id="<?php echo $id; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($pc_total > 0): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-end mb-0">
                                    <?php
                                        $base = [
                                            'count_type' => $selected_count_type,
                                            'date_as_of' => $selected_date_as_of,
                                            'pc_per_page' => $pc_per_page,
                                        ];
                                        $prev = max(1, $pc_page - 1);
                                        $next = min($pc_pages, $pc_page + 1);
                                    ?>
                                    <li class="page-item <?php echo $pc_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="physical_count.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['pc_page' => $prev]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $pc_pages; $p++): ?>
                                        <li class="page-item <?php echo $p === $pc_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="physical_count.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['pc_page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $pc_page >= $pc_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="physical_count.php?<?php echo htmlspecialchars(http_build_query(array_merge($base, ['pc_page' => $next]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Physical Count
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade <?php echo $view_count ? 'show active' : ''; ?>" id="tab-history" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>As Of</th>
                                    <th>Type</th>
                                    <th>Certified By</th>
                                    <th>Approved By</th>
                                    <th>Created</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo (int)$h['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($h['date_as_of'])); ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($h['count_type']); ?></span></td>
                                        <td class="small"><?php echo htmlspecialchars($h['certified_by'] ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($h['approved_by'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($h['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" href="physical_count.php?view=<?php echo (int)$h['id']; ?>">
                                                View
                                            </a>
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
                                        <td class="text-muted"></td>
                                        <td class="text-end text-muted py-4">No physical counts recorded yet.</td>
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
        const form = document.getElementById('physicalCountForm');
        if (!form) return;
        const countType = <?php echo json_encode($selected_count_type, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const dateAsOf = <?php echo json_encode($selected_date_as_of, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const storageKey = 'pc:' + countType + ':' + dateAsOf;
        const defaults = <?php echo json_encode($all_item_defaults, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

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

        const applyToInputs = function () {
            const inputs = form.querySelectorAll('[data-pc-item][data-item-id]');
            inputs.forEach(function (el) {
                const itemId = String(el.getAttribute('data-item-id') || '');
                const field = String(el.getAttribute('data-pc-item') || '');
                if (!itemId || !field) return;
                const saved = state[itemId];
                if (!saved || typeof saved !== 'object') return;
                if (Object.prototype.hasOwnProperty.call(saved, field)) {
                    el.value = saved[field];
                }
            });
        };

        const captureInput = function (el) {
            const itemId = String(el.getAttribute('data-item-id') || '');
            const field = String(el.getAttribute('data-pc-item') || '');
            if (!itemId || !field) return;
            if (!state[itemId] || typeof state[itemId] !== 'object') {
                state[itemId] = {};
            }
            state[itemId][field] = el.value;
            writeState(state);
        };

        applyToInputs();

        form.addEventListener('input', function (ev) {
            const el = ev.target;
            if (!el || !el.getAttribute) return;
            if (!el.getAttribute('data-pc-item')) return;
            captureInput(el);
        });

        form.addEventListener('submit', function () {
            const inputs = form.querySelectorAll('[data-pc-item][data-item-id]');
            inputs.forEach(function (el) {
                captureInput(el);
            });

            defaults.forEach(function (d) {
                const itemId = String(d.id);
                const balance = Number(d.balance || 0);
                const saved = state[itemId] || {};

                const onHandValue = saved.on_hand !== undefined && saved.on_hand !== null && saved.on_hand !== ''
                    ? String(saved.on_hand)
                    : String(balance);
                const remarksValue = saved.remarks !== undefined && saved.remarks !== null
                    ? String(saved.remarks)
                    : '';

                const onHand = document.createElement('input');
                onHand.type = 'hidden';
                onHand.name = 'items[' + itemId + '][on_hand]';
                onHand.value = onHandValue;
                form.appendChild(onHand);

                const remarks = document.createElement('input');
                remarks.type = 'hidden';
                remarks.name = 'items[' + itemId + '][remarks]';
                remarks.value = remarksValue;
                form.appendChild(remarks);
            });
        });
    })();
</script>

<?php renderFooter(); ?>
