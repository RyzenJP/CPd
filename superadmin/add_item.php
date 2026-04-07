<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../plugins/conn.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $item = isset($_POST['item']) ? trim($_POST['item']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';
    $existing_item_id = isset($_POST['existing_item_id']) ? trim($_POST['existing_item_id']) : '';

    // If user selected an existing item, use its name when item text field is empty
    if (($item === '' || $item === null) && $existing_item_id !== '' && $existing_item_id !== '__new__') {
        $stmt_item = $conn->prepare("SELECT item FROM items WHERE id = ? LIMIT 1");
        if ($stmt_item) {
            $id_int = (int)$existing_item_id;
            $stmt_item->bind_param("i", $id_int);
            $stmt_item->execute();
            $stmt_item->bind_result($existing_item_name);
            if ($stmt_item->fetch()) {
                $item = trim((string)$existing_item_name);
            }
            $stmt_item->close();
        }
    }

    $unit_measurement = isset($_POST['unit_measurement']) ? trim($_POST['unit_measurement']) : '';
    $unit_value = isset($_POST['unit_value']) ? $_POST['unit_value'] : 0;
    $balance_qty = isset($_POST['balance_qty']) ? (int)$_POST['balance_qty'] : 0;
    $date_acquired = isset($_POST['date_acquired']) ? $_POST['date_acquired'] : null;

    if ($item === '' || $description === '' || $unit_measurement === '' || $item_type === '') {
        $_SESSION['error'] = 'Please fill in all required fields.';
        session_write_close();
        header('Location: inventory_list.php');
        exit();
    }

    // Try to find an exact existing item with same Item/Article, Description, Unit, Unit Value, and Type
    $stmt_dup = $conn->prepare("SELECT id, balance_qty FROM items WHERE item = ? AND unit_measurement = ? AND item_type = ? AND description = ? AND unit_value = ? LIMIT 1");
    if ($stmt_dup) {
        $unit_value_float = (float)$unit_value;
        $stmt_dup->bind_param("ssssd", $item, $unit_measurement, $item_type, $description, $unit_value_float);
        $stmt_dup->execute();
        $stmt_dup->bind_result($existing_id, $existing_balance);

        if ($stmt_dup->fetch()) {
            $stmt_dup->close();

            $new_balance = (int)$existing_balance + $balance_qty;

            $update_stmt = $conn->prepare("UPDATE items SET balance_qty = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ii", $new_balance, $existing_id);
                $update_stmt->execute();
                $update_stmt->close();
            }

            if ($balance_qty > 0) {
                $tx_stmt = $conn->prepare("INSERT INTO inventory_transactions (item_id, transaction_date, transaction_type, quantity, balance_after, remarks) VALUES (?, NOW(), 'Acquisition', ?, ?, ?)");
                if ($tx_stmt) {
                    $remarks = 'Additional stock added via Add Item';
                    $added_int = $balance_qty;
                    $balance_int = $new_balance;
                    $tx_stmt->bind_param("iiis", $existing_id, $added_int, $balance_int, $remarks);
                    $tx_stmt->execute();
                    $tx_stmt->close();
                }
            }

            $_SESSION['success'] = 'Item quantity updated successfully.';
            session_write_close();
            header('Location: inventory_list.php');
            exit();
        }
        $stmt_dup->close();
    }

    $prefix = '';
    switch ($item_type) {
        case 'Expendable':
            $prefix = 'XP-';
            break;
        case 'Non-Expendable':
            $prefix = 'NXP-';
            break;
        case 'Semi-Expendable':
            $prefix = 'SXP-';
            break;
        default:
            $prefix = 'UNK-';
            break;
    }

    $stmt_check = $conn->prepare("SELECT stock_no FROM items WHERE stock_no LIKE ? ORDER BY LENGTH(stock_no) DESC, stock_no DESC LIMIT 1");
    if ($stmt_check) {
        $like_pattern = $prefix . '%';
        $stmt_check->bind_param("s", $like_pattern);
        $stmt_check->execute();
        $stmt_check->bind_result($last_stock);

        $next_num = 1;
        if ($stmt_check->fetch()) {
            $num_part = substr($last_stock, strlen($prefix));
            if (is_numeric($num_part)) {
                $next_num = intval($num_part) + 1;
            }
        }
        $stmt_check->close();
    } else {
        $next_num = 1;
    }

    $stock_no = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO items (item, description, stock_no, unit_measurement, unit_value, balance_qty, item_type, date_acquired) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssdiss", $item, $description, $stock_no, $unit_measurement, $unit_value, $balance_qty, $item_type, $date_acquired);

        if ($stmt->execute()) {
            $new_item_id = $stmt->insert_id;

            if ($new_item_id && $balance_qty > 0) {
                $tx_stmt = $conn->prepare("INSERT INTO inventory_transactions (item_id, transaction_date, transaction_type, quantity, balance_after, remarks) VALUES (?, NOW(), 'Acquisition', ?, ?, ?)");
                if ($tx_stmt) {
                    $remarks = 'Initial stock on item creation';
                    $qty_int = (int)$balance_qty;
                    $balance_int = (int)$balance_qty;
                    $tx_stmt->bind_param("iiis", $new_item_id, $qty_int, $balance_int, $remarks);
                    $tx_stmt->execute();
                    $tx_stmt->close();
                }
            }

            $_SESSION['success'] = 'Item added successfully.';
            session_write_close();
            header('Location: inventory_list.php');
            exit();
        }
    }

    $_SESSION['error'] = 'Failed to add item. Please try again.';
    session_write_close();
    header('Location: inventory_list.php');
    exit();
}

header('Location: inventory_list.php');
exit();

