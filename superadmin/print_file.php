<?php
session_start();
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')){
    header("Location: ../login.php");
    exit();
}

$report_type = $_POST['report_type'] ?? $_GET['rt'] ?? '';

// Form metadata
$form_no = $_POST['form_no'] ?? '';
$version_no = $_POST['version_no'] ?? '';
$effectivity_date = $_POST['effectivity_date'] ?? '';

// Default values to prevent undefined variable warnings
$as_of_date = date('Y-m-d');
$inventory_type = '';
$fund_cluster = '';
$accountable_officer = '';
$position_title = '';
$accountability_date = '';
$num_pages = '';
$pages_count_str = '';
$start_date = '';
$end_date = '';
$ris_series = '';
$entity_name = '';
$serial_no = '';
$report_date = '';
$selected_items = [];
$data = [];
$rpci_certified_correct_by_label = '';
$rpci_approved_by_label = '';
$rpci_verified_by_label = '';
$rpcppe_certified_correct_by_label = '';
$rpcppe_approved_by_label = '';
$rpcppe_verified_by_label = '';
$rsmi_certification_text = '';
$rsmi_supply_custodian_label = '';
$rsmi_accounting_staff_label = '';
$rpci_certified_title_officer_v = 'Administrative Officer V';
$rpci_certified_title_acting_officer_i = 'Acting Administrative Officer I';
$rpci_certified_title_accountant_ii = 'Accountant II';
$rpci_approved_position_title = 'Regional Director';
$rpci_verified_position_title = 'State Auditor III & OIC Audit Team Leader';

// PTR variables
$ptr_entity_name = '';
$ptr_fund_cluster = '';
$ptr_from_officer = '';
$ptr_to_officer = '';
$ptr_no = '';
$ptr_date = '';
$ptr_transfer_type = '';
$ptr_others_specify = '';
$ptr_reason = '';
$ptr_approved_by_name = '';
$ptr_approved_by_designation = '';
$ptr_released_by_name = '';
$ptr_released_by_designation = '';
$ptr_received_by_name = '';
$ptr_received_by_designation = '';

// RLSDDP variables
$rlsddp_entity_name = '';
$rlsddp_property_type = '';
$rlsddp_department = '';
$rlsddp_accountable_officer = '';
$rlsddp_office_address = '';
$rlsddp_position = '';
$rlsddp_tel_no = '';
$rlsddp_fund_cluster = '';
$rlsddp_date = '';
$rlsddp_no = '';
$rlsddp_pages = '';
$rlsddp_pc_no = '';
$rlsddp_par_no = '';
$rlsddp_nature = [];
$rlsddp_circumstances = '';
$rlsddp_verified_by = '';
$rlsddp_verified_date = '';
$rlsddp_id_type = '';
$rlsddp_id_no = '';
$rlsddp_id_issued_date = '';
$rlsddp_doc_no = '';
$rlsddp_page_no = '';
$rlsddp_book_no = '';
$rlsddp_series = '';
$rlsddp_notary = '';

// SC variables
$entity_name = '';
$reorder_point = '';
$inspected_by = '';
$verified_by = '';
$inspected_date = '';
$verified_date = '';
$sc_item_id = 0;
$sc_transactions = [];

// IIRUP variables
$iirup_entity_name = '';
$iirup_fund_cluster = '';
$iirup_location = '';
$iirup_inventory_no = '';
$iirup_report_date = '';
$iirup_requested_by_name = '';
$iirup_requested_by_designation = '';
$iirup_approved_by_name = '';
$iirup_approved_by_designation = '';
$iirup_inspection_officer_name = '';
$iirup_witness_name = '';

// PAR variables
$par_entity_name = '';
$par_no = '';
$par_received_by_name = '';
$par_received_by_position = '';
$par_issued_by_name = '';
$par_issued_by_position = '';

// PC variables
$pc_entity_name = '';
$pc_fund_cluster = '';
$pc_ppe = '';
$pc_property_number = '';
$pc_description = '';

// PPELC variables
$ppelc_entity_name = '';
$ppelc_fund_cluster = '';
$ppelc_ppe = '';
$ppelc_object_code = '';
$ppelc_estimated_life = '';
$ppelc_rate = '';
$ppelc_description = '';

function generate_next_par_no(mysqli $conn): string {
    $conn->query("
        CREATE TABLE IF NOT EXISTS par_sequences (
            year INT NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        )
    ");

    $year = (int)date('Y');
    $stmt = $conn->prepare("
        INSERT INTO par_sequences (year, last_seq)
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
    $stmt->bind_param("i", $year);
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

function generate_next_ptr_no(mysqli $conn): string {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ptr_sequences (
            year INT NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        )
    ");

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
    $stmt->bind_param("i", $year);
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

function generate_next_rlsddp_no(mysqli $conn): string {
    $conn->query("
        CREATE TABLE IF NOT EXISTS rlsddp_sequences (
            year INT NOT NULL PRIMARY KEY,
            last_seq INT NOT NULL
        )
    ");

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
    $stmt->bind_param("i", $year);
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

function ensure_par_storage_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS par_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            par_no VARCHAR(50) NOT NULL UNIQUE,
            entity_name VARCHAR(255) NOT NULL,
            received_by_name VARCHAR(255) NOT NULL,
            received_by_position VARCHAR(255) NOT NULL,
            issued_by_name VARCHAR(255) NOT NULL,
            issued_by_position VARCHAR(255) NOT NULL,
            items_hash CHAR(40) NOT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS par_document_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            par_document_id INT NOT NULL,
            item_id INT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            stock_no VARCHAR(100) NOT NULL,
            unit VARCHAR(50) NOT NULL,
            qty DECIMAL(12,2) NOT NULL,
            unit_value DECIMAL(14,2) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            date_acquired DATE NULL,
            INDEX idx_par_doc_id (par_document_id),
            CONSTRAINT fk_par_doc_items_doc FOREIGN KEY (par_document_id) REFERENCES par_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function store_par_document(mysqli $conn, string $par_no, array $header, array $selected_items, int $user_id): void {
    if ($par_no === '' || empty($selected_items)) {
        return;
    }

    ensure_par_storage_tables($conn);

    $selected_ids = [];
    foreach ($selected_items as $id => $d) {
        $selected_ids[] = (string)$id;
    }
    sort($selected_ids, SORT_NATURAL);
    $items_hash = sha1(implode(',', $selected_ids));

    $stmt_find = $conn->prepare("SELECT id FROM par_documents WHERE par_no = ? LIMIT 1");
    if (!$stmt_find) {
        return;
    }
    $stmt_find->bind_param("s", $par_no);
    $stmt_find->execute();
    $res = $stmt_find->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt_find->close();

    $doc_id = 0;
    if ($existing && isset($existing['id'])) {
        $doc_id = (int)$existing['id'];
    } else {
        $entity_name = substr(trim((string)($header['entity_name'] ?? '')), 0, 255);
        $received_by_name = substr(trim((string)($header['received_by_name'] ?? '')), 0, 255);
        $received_by_position = substr(trim((string)($header['received_by_position'] ?? '')), 0, 255);
        $issued_by_name = substr(trim((string)($header['issued_by_name'] ?? '')), 0, 255);
        $issued_by_position = substr(trim((string)($header['issued_by_position'] ?? '')), 0, 255);

        $stmt_ins = $conn->prepare("
            INSERT INTO par_documents
                (par_no, entity_name, received_by_name, received_by_position, issued_by_name, issued_by_position, items_hash, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt_ins) {
            return;
        }
        $stmt_ins->bind_param(
            "sssssssi",
            $par_no,
            $entity_name,
            $received_by_name,
            $received_by_position,
            $issued_by_name,
            $issued_by_position,
            $items_hash,
            $user_id
        );
        $stmt_ins->execute();
        $stmt_ins->close();
        $doc_id = (int)mysqli_insert_id($conn);
    }

    if ($doc_id <= 0) {
        return;
    }

    $stmt_has = $conn->prepare("SELECT 1 FROM par_document_items WHERE par_document_id = ? LIMIT 1");
    if (!$stmt_has) {
        return;
    }
    $stmt_has->bind_param("i", $doc_id);
    $stmt_has->execute();
    $res_has = $stmt_has->get_result();
    $has_items = $res_has && $res_has->num_rows > 0;
    $stmt_has->close();
    if ($has_items) {
        return;
    }

    $stmt_item = $conn->prepare("
        INSERT INTO par_document_items
            (par_document_id, item_id, item_name, description, stock_no, unit, qty, unit_value, amount, date_acquired)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_item) {
        return;
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

        // Decrease inventory stock when PAR is issued
        if ($id_int !== null) {
            $stmt_update_balance = $conn->prepare("UPDATE items SET balance_qty = balance_qty - ? WHERE id = ?");
            if ($stmt_update_balance) {
                $stmt_update_balance->bind_param("di", $qty, $id_int);
                $stmt_update_balance->execute();
                $stmt_update_balance->close();
            }

            // Log transaction in inventory_transactions
            $remarks_text = "PAR No. " . $par_no;
            $stmt_insert_tx = $conn->prepare("
                INSERT INTO inventory_transactions (item_id, transaction_date, transaction_type, quantity, balance_after, remarks)
                SELECT ?, NOW(), 'Issued - PAR', ?, balance_qty, ?
                FROM items WHERE id = ?
            ");
            if ($stmt_insert_tx) {
                $stmt_insert_tx->bind_param("idsi", $id_int, $qty, $remarks_text, $id_int);
                $stmt_insert_tx->execute();
                $stmt_insert_tx->close();
            }
        }
    }

    $stmt_item->close();
}

function ensure_ptr_storage_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ptr_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ptr_no VARCHAR(50) NOT NULL UNIQUE,
            entity_name VARCHAR(255) NOT NULL,
            fund_cluster VARCHAR(100) NOT NULL,
            from_officer VARCHAR(255) NOT NULL,
            to_officer VARCHAR(255) NOT NULL,
            transfer_date DATE NULL,
            transfer_type VARCHAR(50) NOT NULL,
            others_specify VARCHAR(255) NOT NULL,
            reason TEXT NOT NULL,
            approved_by_name VARCHAR(255) NOT NULL,
            approved_by_designation VARCHAR(255) NOT NULL,
            released_by_name VARCHAR(255) NOT NULL,
            released_by_designation VARCHAR(255) NOT NULL,
            received_by_name VARCHAR(255) NOT NULL,
            received_by_designation VARCHAR(255) NOT NULL,
            items_hash CHAR(40) NOT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS ptr_document_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ptr_document_id INT NOT NULL,
            item_id INT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            stock_no VARCHAR(100) NOT NULL,
            unit VARCHAR(50) NOT NULL,
            qty DECIMAL(12,2) NOT NULL,
            unit_value DECIMAL(14,2) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            date_acquired DATE NULL,
            INDEX idx_ptr_doc_id (ptr_document_id),
            CONSTRAINT fk_ptr_doc_items_doc FOREIGN KEY (ptr_document_id) REFERENCES ptr_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function store_ptr_document(mysqli $conn, string $ptr_no, array $header, array $selected_items, int $user_id): void {
    if ($ptr_no === '' || empty($selected_items)) {
        return;
    }

    ensure_ptr_storage_tables($conn);

    $selected_ids = [];
    foreach ($selected_items as $id => $d) {
        $selected_ids[] = (string)$id;
    }
    sort($selected_ids, SORT_NATURAL);
    $items_hash = sha1(implode(',', $selected_ids));

    $stmt_find = $conn->prepare("SELECT id FROM ptr_documents WHERE ptr_no = ? LIMIT 1");
    if (!$stmt_find) {
        return;
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
            return;
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
        return;
    }

    $stmt_has = $conn->prepare("SELECT 1 FROM ptr_document_items WHERE ptr_document_id = ? LIMIT 1");
    if (!$stmt_has) {
        return;
    }
    $stmt_has->bind_param("i", $doc_id);
    $stmt_has->execute();
    $res_has = $stmt_has->get_result();
    $has_items = $res_has && $res_has->num_rows > 0;
    $stmt_has->close();
    if ($has_items) {
        return;
    }

    $stmt_item = $conn->prepare("
        INSERT INTO ptr_document_items
            (ptr_document_id, item_id, item_name, description, stock_no, unit, qty, unit_value, amount, date_acquired)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_item) {
        return;
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
}

function ensure_rlsddp_storage_tables(mysqli $conn): void {
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

function store_rlsddp_document(mysqli $conn, string $rlsddp_no, array $header, array $selected_items, int $user_id): void {
    if ($rlsddp_no === '' || empty($selected_items)) {
        return;
    }

    ensure_rlsddp_storage_tables($conn);

    $selected_ids = [];
    foreach ($selected_items as $id => $d) {
        $selected_ids[] = (string)$id;
    }
    sort($selected_ids, SORT_NATURAL);
    $items_hash = sha1(implode(',', $selected_ids));

    $stmt_find = $conn->prepare("SELECT id FROM rlsddp_documents WHERE rlsddp_no = ? LIMIT 1");
    if (!$stmt_find) {
        return;
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
        if ($nature_json === false) { $nature_json = '[]'; }
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
            return;
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
        return;
    }

    $stmt_has = $conn->prepare("SELECT 1 FROM rlsddp_document_items WHERE rlsddp_document_id = ? LIMIT 1");
    if (!$stmt_has) {
        return;
    }
    $stmt_has->bind_param("i", $doc_id);
    $stmt_has->execute();
    $res_has = $stmt_has->get_result();
    $has_items = $res_has && $res_has->num_rows > 0;
    $stmt_has->close();
    if ($has_items) {
        return;
    }

    $stmt_item = $conn->prepare("
        INSERT INTO rlsddp_document_items
            (rlsddp_document_id, item_id, item_name, description, stock_no, unit, qty, unit_value, amount, date_acquired)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_item) {
        return;
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
}

function log_report_audit($conn, $user_id, $report_type, $action, $details) {
    if (!$conn instanceof mysqli) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS report_audit_trail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_type VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($conn->error) {
        return;
    }

    $stmt = $conn->prepare("INSERT INTO report_audit_trail (user_id, report_type, action, details, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $report_type, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Logic based on report type
if ($report_type === 'rpci') {
    // ... existing code ...
    $inventory_type = $_POST['inventory_type'] ?? 'Inventory Item';
    $as_of_date = $_POST['as_of_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $items = $_POST['items'] ?? [];

    // Debug: Uncomment to see raw date value
    // echo "<div class='no-print alert alert-info'>DEBUG: Raw as_of_date from form: " . htmlspecialchars($as_of_date) . "</div>";

    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $position_title = $_POST['position_title'] ?? '';
    $accountability_date = $_POST['accountability_date'] ?? '';
    $num_pages = $_POST['num_pages'] ?? 'Two (2)';

    if ($accountable_officer === '') { $accountable_officer = 'LORILYN M. MONTILIJAO'; }
    if ($position_title === '') { $position_title = 'Acting Administrative Officer I'; }
    if ($accountability_date === '') { $accountability_date = '2013-06-17'; }

    $rpci_certified_title_officer_v = trim($_POST['rpci_certified_title_officer_v'] ?? $rpci_certified_title_officer_v);
    if ($rpci_certified_title_officer_v === '') { $rpci_certified_title_officer_v = 'Administrative Officer V'; }
    $rpci_certified_title_acting_officer_i = trim($_POST['rpci_certified_title_acting_officer_i'] ?? $rpci_certified_title_acting_officer_i);
    if ($rpci_certified_title_acting_officer_i === '') { $rpci_certified_title_acting_officer_i = 'Acting Administrative Officer I'; }
    $rpci_certified_title_accountant_ii = trim($_POST['rpci_certified_title_accountant_ii'] ?? $rpci_certified_title_accountant_ii);
    if ($rpci_certified_title_accountant_ii === '') { $rpci_certified_title_accountant_ii = 'Accountant II'; }
    $rpci_approved_position_title = trim($_POST['rpci_approved_position_title'] ?? $rpci_approved_position_title);
    if ($rpci_approved_position_title === '') { $rpci_approved_position_title = 'Regional Director'; }
    $rpci_verified_position_title = trim($_POST['rpci_verified_position_title'] ?? $rpci_verified_position_title);
    if ($rpci_verified_position_title === '') { $rpci_verified_position_title = 'State Auditor III & OIC Audit Team Leader'; }

    // Names for certification section
    $rpci_certified_name_officer_v = trim($_POST['rpci_certified_name_officer_v'] ?? 'MIMIA C. GUMBAN');
    $rpci_certified_name_acting_officer_i = trim($_POST['rpci_certified_name_acting_officer_i'] ?? 'MARIE JEANNE A. JAGONIO');
    $rpci_certified_name_accountant_ii = trim($_POST['rpci_certified_name_accountant_ii'] ?? 'RACHELLE E. LABORDO');
    $rpci_approved_name = trim($_POST['rpci_approved_name'] ?? 'HAROLD ALFRED P. MARSHALL');
    $rpci_verified_name = trim($_POST['rpci_verified_name'] ?? 'MS. SIMONETTE D. CATALUÑA');

    $rpci_certified_correct_by_label = trim($_POST['rpci_certified_correct_by_label'] ?? '');
    if ($rpci_certified_correct_by_label === '') { $rpci_certified_correct_by_label = 'Inventory Committee Chair and Members'; }
    $rpci_approved_by_label = trim($_POST['rpci_approved_by_label'] ?? '');
    if ($rpci_approved_by_label === '') { $rpci_approved_by_label = 'Head of Agency/Entity or Authorized Representative'; }
    $rpci_verified_by_label = trim($_POST['rpci_verified_by_label'] ?? '');
    if ($rpci_verified_by_label === '') { $rpci_verified_by_label = 'Commission on Audit Representative'; }

    // Filter only selected items
    $selected_items = array_filter($items, function($item) {
        return isset($item['selected']);
    });

} elseif ($report_type === 'rpcppe') {
    $inventory_type = $_POST['inventory_type'] ?? 'Property, Plant and Equipment';
    $as_of_date = $_POST['as_of_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $items = $_POST['items'] ?? [];
    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $position_title = $_POST['position_title'] ?? '';
    $accountability_date = $_POST['accountability_date'] ?? '';
    $pages_count_str = $_POST['pages_count_str'] ?? 'Six (6)';

    if ($accountable_officer === '') { $accountable_officer = 'LORILYN M. MONTILIJAO'; }
    if ($position_title === '') { $position_title = 'Administrative Officer I'; }
    if ($accountability_date === '') { $accountability_date = '2023-08-30'; }
    if ($pages_count_str === '') { $pages_count_str = 'Six (6)'; }

    $rpcppe_certified_correct_by_label = trim($_POST['rpcppe_certified_correct_by_label'] ?? '');
    if ($rpcppe_certified_correct_by_label === '') { $rpcppe_certified_correct_by_label = 'Inventory Committee Chair and Members'; }
    $rpcppe_approved_by_label = trim($_POST['rpcppe_approved_by_label'] ?? '');
    if ($rpcppe_approved_by_label === '') { $rpcppe_approved_by_label = 'Head of Agency/Entity or Authorized Representative'; }
    $rpcppe_verified_by_label = trim($_POST['rpcppe_verified_by_label'] ?? '');
    if ($rpcppe_verified_by_label === '') { $rpcppe_verified_by_label = 'Commission on Audit Representative'; }

    // Names for certification section
    $rpcppe_certified_name_1 = trim($_POST['rpcppe_certified_name_1'] ?? 'MIMIA C. GUMBAN');
    $rpcppe_certified_name_2 = trim($_POST['rpcppe_certified_name_2'] ?? 'MARIE JEANNE A. JAGONIO');
    $rpcppe_certified_name_3 = trim($_POST['rpcppe_certified_name_3'] ?? 'RACHELLE E. LABORDO');
    $rpcppe_approved_name = trim($_POST['rpcppe_approved_name'] ?? 'HAROLD ALFRED P. MARSHALL');
    $rpcppe_verified_name = trim($_POST['rpcppe_verified_name'] ?? 'MS. SIMONETTE D. CATALUÑA');

    // Titles for certification section
    $rpcppe_certified_title_1 = trim($_POST['rpcppe_certified_title_1'] ?? 'Administrative Officer V');
    $rpcppe_certified_title_2 = trim($_POST['rpcppe_certified_title_2'] ?? 'Acting Administrative Officer I');
    $rpcppe_certified_title_3 = trim($_POST['rpcppe_certified_title_3'] ?? 'Accountant II');
    $rpcppe_approved_title = trim($_POST['rpcppe_approved_title'] ?? 'Regional Director');
    $rpcppe_verified_title = trim($_POST['rpcppe_verified_title'] ?? 'State Auditor III & OIC Audit Team Leader');

    // Filter only selected items
    $selected_items = array_filter($items, function($item) {
        return isset($item['selected']);
    });

} elseif ($report_type === 'rsmi') {
    include '../plugins/conn.php';

    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $ris_series = $_POST['ris_series'] ?? '';
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '01';
    $serial_no = $_POST['serial_no'] ?? date('Y') . '-001';
    $report_date = $_POST['report_date'] ?? $end_date;

    $rsmi_certification_text = trim($_POST['rsmi_certification_text'] ?? '');
    if ($rsmi_certification_text === '') { $rsmi_certification_text = 'I hereby certify to the correctness of the above information.'; }
    $rsmi_supply_custodian_label = trim($_POST['rsmi_supply_custodian_label'] ?? '');
    if ($rsmi_supply_custodian_label === '') { $rsmi_supply_custodian_label = 'Signature over Printed Name of Supply and/or Property Custodian'; }
    $rsmi_accounting_staff_label = trim($_POST['rsmi_accounting_staff_label'] ?? '');
    if ($rsmi_accounting_staff_label === '') { $rsmi_accounting_staff_label = 'Signature over Printed Name of Designated Accounting Staff'; }

    // Escape dates to prevent SQL injection
    $start_date = $conn->real_escape_string($start_date);
    $end_date = $conn->real_escape_string($end_date);

    if (isset($_POST['items']) && is_array($_POST['items'])) {
        // Use submitted items (filtered by selection)
        foreach ($_POST['items'] as $item) {
            if (isset($item['selected'])) {
                $data[] = [
                    'request_date' => $item['date'],
                    'ris_no' => $item['ris_no'],
                    'stock_no' => $item['stock_no'],
                    'item' => $item['item'],
                    'unit_measurement' => $item['unit'],
                    'quantity_issued' => $item['qty'],
                    'unit_value' => $item['unit_value'],
                    'amount' => $item['amount']
                ];
            }
        }

        // Sort by Date then RIS No
        usort($data, function($a, $b) {
            if ($a['request_date'] == $b['request_date']) {
                return strnatcmp($a['ris_no'], $b['ris_no']);
            }
            return strtotime($a['request_date']) - strtotime($b['request_date']);
        });

    } else {
        // Fallback to SQL if no items submitted
        $sql = "SELECT
                    DATE(IFNULL(r.approved_date, r.created_at)) AS request_date,
                    r.ris_no as ris_no,
                    i.stock_no,
                    i.item,
                    i.unit_measurement,
                    COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) AS quantity_issued,
                    i.unit_value,
                    (COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) * i.unit_value) as amount
                FROM request_items ri
                JOIN requests r ON ri.request_id = r.id
                JOIN items i ON ri.item_id = i.id
                WHERE r.status IN ('Issued', 'Approved')
                AND DATE(IFNULL(r.approved_date, r.created_at)) BETWEEN '$start_date' AND '$end_date'
                ORDER BY request_date ASC, r.id ASC";

        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }
} elseif ($report_type === 'ris') {
    include '../plugins/conn.php';

    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    if ($request_id <= 0) {
        echo "Invalid RIS request.";
        exit();
    }

    $stmt_req = $conn->prepare("SELECT * FROM requests WHERE id = ?");
    if (!$stmt_req) {
        echo "Unable to load RIS data.";
        exit();
    }
    $stmt_req->bind_param("i", $request_id);
    $stmt_req->execute();
    $request_res = $stmt_req->get_result();
    $request_row = $request_res->fetch_assoc();
    $stmt_req->close();

    if (!$request_row) {
        echo "RIS record not found.";
        exit();
    }

    $entity_name = trim($_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI');
    if ($entity_name === '') {
        $entity_name = 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    }
    $fund_cluster = trim($_POST['fund_cluster'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $responsibility_center = trim($_POST['responsibility_center'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $ris_no = trim($_POST['ris_no'] ?? $request_row['ris_no']);
    $purpose = trim($_POST['purpose'] ?? $request_row['purpose']);
    $requested_by = trim($_POST['requested_by'] ?? $request_row['requested_by']);
    $approved_by = trim($_POST['approved_by'] ?? ($request_row['approved_by'] ?? ''));
    $issued_by = trim($_POST['issued_by'] ?? '');
    $received_by = trim($_POST['received_by'] ?? $request_row['requested_by']);

    $entity_name = substr($entity_name, 0, 255);
    $fund_cluster = substr($fund_cluster, 0, 100);
    $division = substr($division, 0, 150);
    $responsibility_center = substr($responsibility_center, 0, 100);
    $office = substr($office, 0, 150);
    $ris_no = substr($ris_no, 0, 50);
    $purpose = substr($purpose, 0, 500);
    $requested_by = substr($requested_by, 0, 255);
    $approved_by = substr($approved_by, 0, 255);
    $issued_by = substr($issued_by, 0, 255);
    $received_by = substr($received_by, 0, 255);

    $ris_items = [];
    $stmt_items = $conn->prepare("
        SELECT ri.quantity_requested, ri.quantity_issued, ri.remarks,
               i.item, i.description, i.unit_measurement, i.stock_no, i.balance_qty
        FROM request_items ri
        JOIN items i ON ri.item_id = i.id
        WHERE ri.request_id = ?
    ");
    if ($stmt_items) {
        $stmt_items->bind_param("i", $request_id);
        $stmt_items->execute();
        $items_res = $stmt_items->get_result();
        while ($row = $items_res->fetch_assoc()) {
            $ris_items[] = $row;
        }
        $stmt_items->close();
    }

    if (isset($_SESSION['user_id'])) {
        $header_data = [
            'request_id' => $request_id,
            'entity_name' => $entity_name,
            'fund_cluster' => $fund_cluster,
            'division' => $division,
            'responsibility_center' => $responsibility_center,
            'office' => $office,
            'ris_no' => $ris_no,
            'purpose' => $purpose,
            'requested_by' => $requested_by,
            'approved_by' => $approved_by,
            'issued_by' => $issued_by,
            'received_by' => $received_by
        ];
        $details = json_encode($header_data, JSON_UNESCAPED_UNICODE);
        log_report_audit($conn, (int)$_SESSION['user_id'], 'ris', 'generate', $details);
    }
} elseif ($report_type === 'ics') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $ics_no = $_POST['ics_no'] ?? '';
    $ics_received_from_name = $_POST['ics_received_from_name'] ?? '';
    $ics_received_from_position = $_POST['ics_received_from_position'] ?? '';
    $ics_received_by_name = $_POST['ics_received_by_name'] ?? '';
    $ics_received_by_position = $_POST['ics_received_by_position'] ?? '';
    $ics_approved_name = $_POST['ics_approved_name'] ?? '';
    $ics_approved_position = $_POST['ics_approved_position'] ?? '';
    $ics_reason = $_POST['ics_reason'] ?? '';
    $items = $_POST['items'] ?? [];
    $selected_items = array_filter($items, function($item) {
        return isset($item['selected']);
    });
} elseif ($report_type === 'iirup' || $report_type === 'par' || $report_type === 'pc' || $report_type === 'ppelc' || $report_type === 'ptr' || $report_type === 'rlsddp' || $report_type === 'sc' || $report_type === 'slc' || $report_type === 'issued_items' || $report_type === 'inventory_transactions') {
    $items = $_POST['items'] ?? [];

    if ($report_type === 'iirup') {
        $iirup_entity_name = $_POST['iirup_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $iirup_fund_cluster = $_POST['iirup_fund_cluster'] ?? '';
        $iirup_location = $_POST['iirup_location'] ?? '';
        $iirup_inventory_no = $_POST['iirup_inventory_no'] ?? '';
        $iirup_report_date = $_POST['iirup_report_date'] ?? date('Y-m-d');
        $iirup_requested_by_name = $_POST['iirup_requested_by_name'] ?? '';
        $iirup_requested_by_designation = $_POST['iirup_requested_by_designation'] ?? '';
        $iirup_approved_by_name = $_POST['iirup_approved_by_name'] ?? '';
        $iirup_approved_by_designation = $_POST['iirup_approved_by_designation'] ?? '';
        $iirup_inspection_officer_name = $_POST['iirup_inspection_officer_name'] ?? '';
        $iirup_witness_name = $_POST['iirup_witness_name'] ?? '';
    } elseif ($report_type === 'par') {
        include '../plugins/conn.php';

        $par_entity_name = $_POST['par_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $par_no = trim($_POST['par_no'] ?? '');

        $par_force_new = isset($_POST['par_no_force_new']) && (string)$_POST['par_no_force_new'] === '1';
        $par_selected_ids = [];
        foreach ($items as $id => $d) {
            if (isset($d['selected'])) {
                $par_selected_ids[] = (string)$id;
            }
        }
        sort($par_selected_ids, SORT_NATURAL);
        $par_doc_key = 'par:' . sha1(implode(',', $par_selected_ids));
        if (!isset($_SESSION['par_doc_numbers']) || !is_array($_SESSION['par_doc_numbers'])) {
            $_SESSION['par_doc_numbers'] = [];
        }

        if ($par_no !== '') {
            $_SESSION['par_doc_numbers'][$par_doc_key] = $par_no;
        } elseif (!$par_force_new && isset($_SESSION['par_doc_numbers'][$par_doc_key])) {
            $par_no = (string)$_SESSION['par_doc_numbers'][$par_doc_key];
        } else {
            $par_no = generate_next_par_no($conn);
            $_SESSION['par_doc_numbers'][$par_doc_key] = $par_no;
        }

        $par_received_by_name = $_POST['par_received_by_name'] ?? '';
        $par_received_by_position = $_POST['par_received_by_position'] ?? '';
        $par_issued_by_name = $_POST['par_issued_by_name'] ?? '';
        $par_issued_by_position = $_POST['par_issued_by_position'] ?? '';
    } elseif ($report_type === 'pc') {
        $pc_entity_name = $_POST['pc_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $pc_fund_cluster = $_POST['pc_fund_cluster'] ?? '';
        $pc_ppe = $_POST['pc_ppe'] ?? '';
        $pc_property_number = $_POST['pc_property_number'] ?? '';
        $pc_description = $_POST['pc_description'] ?? '';
    } elseif ($report_type === 'ppelc') {
        $ppelc_entity_name = $_POST['ppelc_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $ppelc_fund_cluster = $_POST['ppelc_fund_cluster'] ?? '';
        $ppelc_ppe = $_POST['ppelc_ppe'] ?? '';
        $ppelc_object_code = $_POST['ppelc_object_code'] ?? '';
        $ppelc_estimated_life = $_POST['ppelc_estimated_life'] ?? '';
        $ppelc_rate = $_POST['ppelc_rate'] ?? '';
        $ppelc_description = $_POST['ppelc_description'] ?? '';
    } elseif ($report_type === 'ptr') {
        include '../plugins/conn.php';

        $ptr_entity_name = $_POST['ptr_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $ptr_fund_cluster = $_POST['ptr_fund_cluster'] ?? '';
        $ptr_from_officer = $_POST['ptr_from_officer'] ?? '';
        $ptr_to_officer = $_POST['ptr_to_officer'] ?? '';
        $ptr_no = trim($_POST['ptr_no'] ?? '');

        $ptr_force_new = isset($_POST['ptr_no_force_new']) && (string)$_POST['ptr_no_force_new'] === '1';
        $ptr_selected_ids = [];
        foreach ($items as $id => $d) {
            if (isset($d['selected'])) {
                $ptr_selected_ids[] = (string)$id;
            }
        }
        sort($ptr_selected_ids, SORT_NATURAL);
        $ptr_doc_key = 'ptr:' . sha1(implode(',', $ptr_selected_ids));
        if (!isset($_SESSION['ptr_doc_numbers']) || !is_array($_SESSION['ptr_doc_numbers'])) {
            $_SESSION['ptr_doc_numbers'] = [];
        }

        if ($ptr_no !== '') {
            $_SESSION['ptr_doc_numbers'][$ptr_doc_key] = $ptr_no;
        } elseif (!$ptr_force_new && isset($_SESSION['ptr_doc_numbers'][$ptr_doc_key])) {
            $ptr_no = (string)$_SESSION['ptr_doc_numbers'][$ptr_doc_key];
        } else {
            $ptr_no = generate_next_ptr_no($conn);
            $_SESSION['ptr_doc_numbers'][$ptr_doc_key] = $ptr_no;
        }

        $ptr_date = $_POST['ptr_date'] ?? date('Y-m-d');
        $ptr_transfer_type = $_POST['ptr_transfer_type'] ?? '';
        $ptr_others_specify = $_POST['ptr_others_specify'] ?? '';
        $ptr_reason = $_POST['ptr_reason'] ?? '';
        $ptr_approved_by_name = $_POST['ptr_approved_by_name'] ?? '';
        $ptr_approved_by_designation = $_POST['ptr_approved_by_designation'] ?? '';
        $ptr_released_by_name = $_POST['ptr_released_by_name'] ?? '';
        $ptr_released_by_designation = $_POST['ptr_released_by_designation'] ?? '';
        $ptr_received_by_name = $_POST['ptr_received_by_name'] ?? '';
        $ptr_received_by_designation = $_POST['ptr_received_by_designation'] ?? '';
    } elseif ($report_type === 'rlsddp') {
        include '../plugins/conn.php';

        $rlsddp_entity_name = $_POST['rlsddp_entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $rlsddp_property_type = trim($_POST['rlsddp_property_type'] ?? '');
        $rlsddp_department = trim($_POST['rlsddp_department'] ?? '');
        $rlsddp_accountable_officer = trim($_POST['rlsddp_accountable_officer'] ?? '');
        $rlsddp_office_address = trim($_POST['rlsddp_office_address'] ?? '');
        $rlsddp_position = trim($_POST['rlsddp_position'] ?? '');
        $rlsddp_tel_no = trim($_POST['rlsddp_tel_no'] ?? '');
        $rlsddp_fund_cluster = trim($_POST['rlsddp_fund_cluster'] ?? '');
        $rlsddp_date = $_POST['rlsddp_date'] ?? date('Y-m-d');
        $rlsddp_no = trim($_POST['rlsddp_no'] ?? '');
        $rlsddp_pages = trim($_POST['rlsddp_pages'] ?? '');
        $rlsddp_pc_no = trim($_POST['rlsddp_pc_no'] ?? '');
        $rlsddp_par_no = trim($_POST['rlsddp_par_no'] ?? '');
        $rlsddp_public_station = $_POST['rlsddp_public_station'] ?? 'No';
        $rlsddp_nature = $_POST['rlsddp_nature'] ?? [];
        $rlsddp_circumstances = trim($_POST['rlsddp_circumstances'] ?? '');
        $rlsddp_verified_by = trim($_POST['rlsddp_verified_by'] ?? '');
        $rlsddp_verified_date = trim($_POST['rlsddp_verified_date'] ?? '');
        $rlsddp_id_type = trim($_POST['rlsddp_id_type'] ?? '');
        $rlsddp_id_no = trim($_POST['rlsddp_id_no'] ?? '');
        $rlsddp_id_issued_date = $_POST['rlsddp_id_issued_date'] ?? '';
        $rlsddp_doc_no = trim($_POST['rlsddp_doc_no'] ?? '');
        $rlsddp_page_no = trim($_POST['rlsddp_page_no'] ?? '');
        $rlsddp_book_no = trim($_POST['rlsddp_book_no'] ?? '');
        $rlsddp_series = trim($_POST['rlsddp_series'] ?? '');
        $rlsddp_notary = trim($_POST['rlsddp_notary'] ?? '');

        $rlsddp_selected_ids = [];
        foreach ($items as $id => $d) {
            if (isset($d['selected'])) {
                $rlsddp_selected_ids[] = (string)$id;
            }
        }
        sort($rlsddp_selected_ids, SORT_NATURAL);
        $rlsddp_items_hash = sha1(implode(',', $rlsddp_selected_ids));

        $rlsddp_force_new = isset($_POST['rlsddp_no_force_new']) && (string)$_POST['rlsddp_no_force_new'] === '1';
        $rlsddp_doc_key = 'rlsddp:' . $rlsddp_items_hash;
        if (!isset($_SESSION['rlsddp_doc_numbers']) || !is_array($_SESSION['rlsddp_doc_numbers'])) {
            $_SESSION['rlsddp_doc_numbers'] = [];
        }

        if ($rlsddp_no !== '') {
            $_SESSION['rlsddp_doc_numbers'][$rlsddp_doc_key] = $rlsddp_no;
        } elseif (!$rlsddp_force_new && isset($_SESSION['rlsddp_doc_numbers'][$rlsddp_doc_key])) {
            $rlsddp_no = (string)$_SESSION['rlsddp_doc_numbers'][$rlsddp_doc_key];
        } else {
            $rlsddp_no = generate_next_rlsddp_no($conn);
            $_SESSION['rlsddp_doc_numbers'][$rlsddp_doc_key] = $rlsddp_no;
        }

        if ($rlsddp_pc_no === '' && count($rlsddp_selected_ids) === 1) {
            $only_id = $rlsddp_selected_ids[0];
            $one = $items[$only_id] ?? ($items[(int)$only_id] ?? null);
            if (is_array($one)) {
                $rlsddp_pc_no = trim((string)($one['stock_no'] ?? ''));
            }
        }

        if ($rlsddp_par_no === '' && isset($conn) && $conn instanceof mysqli) {
            $stmt_par = $conn->prepare("SELECT par_no FROM par_documents WHERE items_hash = ? ORDER BY created_at DESC LIMIT 1");
            if ($stmt_par) {
                $stmt_par->bind_param("s", $rlsddp_items_hash);
                $stmt_par->execute();
                $res_par = $stmt_par->get_result();
                if ($res_par && ($row_par = $res_par->fetch_assoc())) {
                    $rlsddp_par_no = (string)($row_par['par_no'] ?? '');
                }
                $stmt_par->close();
            }

            if ($rlsddp_par_no === '' && count($rlsddp_selected_ids) === 1) {
                $only_id = (int)$rlsddp_selected_ids[0];
                $stmt_par2 = $conn->prepare("
                    SELECT d.par_no
                    FROM par_documents d
                    JOIN par_document_items di ON di.par_document_id = d.id
                    WHERE di.item_id = ?
                    ORDER BY d.created_at DESC
                    LIMIT 1
                ");
                if ($stmt_par2) {
                    $stmt_par2->bind_param("i", $only_id);
                    $stmt_par2->execute();
                    $res_par2 = $stmt_par2->get_result();
                    if ($res_par2 && ($row_par2 = $res_par2->fetch_assoc())) {
                        $rlsddp_par_no = (string)($row_par2['par_no'] ?? '');
                    }
                    $stmt_par2->close();
                }
            }
        }
    } elseif ($report_type === 'issued_items') {
        include '../plugins/conn.php';
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        $tx_type = $_GET['tx_type'] ?? '';

        $start_date_esc = $conn->real_escape_string($start_date);
        $end_date_esc = $conn->real_escape_string($end_date);

        $sql = "SELECT
            r.request_date,
            r.ris_no,
            i.stock_no,
            i.item,
            i.unit_measurement,
            COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) AS quantity_issued,
            i.unit_value,
            (COALESCE(NULLIF(ri.quantity_issued, 0), ri.quantity_requested) * i.unit_value) AS amount
        FROM request_items ri
        JOIN requests r ON ri.request_id = r.id
        JOIN items i ON ri.item_id = i.id
        WHERE r.status IN ('Issued','Approved')
        AND r.request_date BETWEEN '$start_date_esc' AND '$end_date_esc'";

        if (!empty($tx_type)) {
            $tx_type_esc = $conn->real_escape_string($tx_type);
            $sql .= " AND i.item_type = '$tx_type_esc'";
        }

        $result = $conn->query($sql);
        $issued_items_data = [];
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $issued_items_data[] = $row;
            }
        }
    } elseif ($report_type === 'inventory_transactions') {
        include '../plugins/conn.php';
        require_once '../inventory_balance.php';
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date   = $_GET['end_date']   ?? date('Y-m-d');
        $tx_type    = $_GET['tx_type']    ?? '';

        $start_date_esc = $conn->real_escape_string($start_date);
        $end_date_esc   = $conn->real_escape_string($end_date);

        $sql = "SELECT
            t.transaction_date,
            t.transaction_type,
            t.quantity,
            t.balance_after,
            t.remarks,
            i.id AS item_id,
            i.item,
            i.description,
            i.item_type,
            i.stock_no,
            i.unit_measurement
        FROM inventory_transactions t
        JOIN items i ON t.item_id = i.id
        WHERE DATE(t.transaction_date) BETWEEN '$start_date_esc' AND '$end_date_esc'";

        if (!empty($tx_type)) {
            $tx_type_esc = $conn->real_escape_string($tx_type);
            $sql .= " AND i.item_type = '$tx_type_esc'";
        }

        // Sort like inventory card: grouped by item/description
        $sql .= " ORDER BY i.item ASC, i.description ASC, t.transaction_date ASC";

        // Beginning balance note: first transaction in the period per (item_id + description)
        $inventory_tx_beginning_balances = [];
        $begin_sql = "SELECT
                i.id AS item_id,
                i.description,
                MIN(t.transaction_date) AS first_tx_date
            FROM inventory_transactions t
            JOIN items i ON t.item_id = i.id
            WHERE DATE(t.transaction_date) BETWEEN '$start_date_esc' AND '$end_date_esc'";
        if (!empty($tx_type)) {
            $begin_sql .= " AND i.item_type = '$tx_type_esc'";
        }
        $begin_sql .= " GROUP BY i.id, i.description";
        $begin_result = $conn->query($begin_sql);
        if ($begin_result && $begin_result->num_rows > 0) {
            while ($row_begin = $begin_result->fetch_assoc()) {
                if (!empty($row_begin['first_tx_date'])) {
                    $key = $row_begin['item_id'] . '||' . $row_begin['description'];
                    $inventory_tx_beginning_balances[$key] = [
                        'first_tx_date' => $row_begin['first_tx_date'],
                        'begin_qty' => calculate_beginning_balance($conn, (int)$row_begin['item_id'], $start_date)
                    ];
                }
            }
        }

        $result = $conn->query($sql);
        $inventory_tx_data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $inventory_tx_data[] = $row;
            }
        }
    } elseif ($report_type === 'sc') {
        $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $fund_cluster = $_POST['fund_cluster'] ?? '';
        $reorder_point = $_POST['reorder_point'] ?? '';
        $inspected_by = $_POST['inspected_by'] ?? '';
        $verified_by = $_POST['verified_by'] ?? '';
        $inspected_date = $_POST['inspected_date'] ?? '';
        $verified_date = $_POST['verified_date'] ?? '';
    } elseif ($report_type === 'slc') {
        $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
        $fund_cluster = $_POST['fund_cluster'] ?? '';
        $reorder_point = $_POST['reorder_point'] ?? '';
    }

    $selected_items = array_filter($items, function($item) {
        return isset($item['selected']);
    });

    if (($report_type === 'par' || $report_type === 'ptr' || $report_type === 'rlsddp') && isset($conn) && $conn instanceof mysqli) {
        $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        if ($report_type === 'par') {
            $header = [
                'entity_name' => $par_entity_name,
                'received_by_name' => $par_received_by_name,
                'received_by_position' => $par_received_by_position,
                'issued_by_name' => $par_issued_by_name,
                'issued_by_position' => $par_issued_by_position,
            ];
            store_par_document($conn, (string)$par_no, $header, $selected_items, $uid);
        } elseif ($report_type === 'ptr') {
            $header = [
                'entity_name' => $ptr_entity_name,
                'fund_cluster' => $ptr_fund_cluster,
                'from_officer' => $ptr_from_officer,
                'to_officer' => $ptr_to_officer,
                'transfer_date' => $ptr_date,
                'transfer_type' => $ptr_transfer_type,
                'others_specify' => $ptr_others_specify,
                'reason' => $ptr_reason,
                'approved_by_name' => $ptr_approved_by_name,
                'approved_by_designation' => $ptr_approved_by_designation,
                'released_by_name' => $ptr_released_by_name,
                'released_by_designation' => $ptr_released_by_designation,
                'received_by_name' => $ptr_received_by_name,
                'received_by_designation' => $ptr_received_by_designation,
            ];
            store_ptr_document($conn, (string)$ptr_no, $header, $selected_items, $uid);
        } elseif ($report_type === 'rlsddp') {
            $header = [
                'entity_name' => $rlsddp_entity_name,
                'property_type' => $rlsddp_property_type,
                'department' => $rlsddp_department,
                'accountable_officer' => $rlsddp_accountable_officer,
                'office_address' => $rlsddp_office_address,
                'position_title' => $rlsddp_position,
                'tel_no' => $rlsddp_tel_no,
                'fund_cluster' => $rlsddp_fund_cluster,
                'report_date' => $rlsddp_date,
                'pages' => $rlsddp_pages,
                'pc_no' => $rlsddp_pc_no,
                'par_no' => $rlsddp_par_no,
                'public_station' => $rlsddp_public_station,
                'nature' => $rlsddp_nature,
                'circumstances' => $rlsddp_circumstances,
                'verified_by' => $rlsddp_verified_by,
                'verified_date' => $rlsddp_verified_date,
                'id_type' => $rlsddp_id_type,
                'id_no' => $rlsddp_id_no,
                'id_issued_date' => $rlsddp_id_issued_date,
                'doc_no' => $rlsddp_doc_no,
                'page_no' => $rlsddp_page_no,
                'book_no' => $rlsddp_book_no,
                'series' => $rlsddp_series,
                'notary' => $rlsddp_notary,
            ];
            store_rlsddp_document($conn, (string)$rlsddp_no, $header, $selected_items, $uid);
        }
    }

    if (($report_type === 'sc' || $report_type === 'slc') && !empty($selected_items)) {
        include '../plugins/conn.php';

        if (function_exists('array_key_first')) {
            $sc_item_id = (int)array_key_first($selected_items);
        } else {
            reset($selected_items);
            $sc_item_id = (int)key($selected_items);
        }
        if ($sc_item_id > 0 && isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("
                SELECT transaction_date, transaction_type, quantity, balance_after, remarks
                FROM inventory_transactions
                WHERE item_id = ?
                ORDER BY transaction_date ASC
            ");
            if ($stmt) {
                $stmt->bind_param("i", $sc_item_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $sc_transactions[] = $row;
                }
                $stmt->close();
            }
        }
    }
} else {
    echo "Invalid Report Type";
    exit();
}

// Helper for title
$page_title = '';
if ($report_type === 'rpci') $page_title = "RPCI Report - $as_of_date";
elseif ($report_type === 'rpcppe') $page_title = "RPCPPE Report - $as_of_date";
elseif ($report_type === 'rsmi') $page_title = "RSMI Report";
elseif ($report_type === 'ris') $page_title = "RIS Report - " . htmlspecialchars($ris_no);
elseif ($report_type === 'sc') $page_title = "Stock Card";
elseif ($report_type === 'slc') $page_title = "Supplies Ledger Card";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11px;
        }
        .report-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-title {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
        }
        .table th, .table td {
            border: 1px solid black !important;
            padding: 4px 6px;
            vertical-align: middle;
        }
        .table thead th {
            text-align: center;
            background-color: <?php echo ($report_type === 'rpcppe') ? '#fff' : '#f0f0f0'; ?> !important;
            font-weight: bold;
            <?php if($report_type === 'rpcppe') echo 'text-transform: uppercase;'; ?>
        }
        @media print {
            @page {
                size: <?php echo (in_array($report_type, ['ptr','rlsddp'])) ? 'A4' : 'landscape'; ?>;
                margin: 0.5in;
            }
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; }
        }
        <?php if($report_type === 'rsmi'): ?>
        .header-text { text-align: center; font-weight: bold; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-white p-4">

    <div class="container-fluid">
        <div class="no-print d-print-none mb-3 d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>

            <form action="export_file.php" method="POST" target="_blank" class="d-inline">
                <input type="hidden" name="export_type" value="<?php echo htmlspecialchars($report_type); ?>">

                <?php if ($report_type === 'rpci'): ?>
                    <input type="hidden" name="inventory_type" value="<?php echo htmlspecialchars($inventory_type); ?>">
                    <input type="hidden" name="as_of_date" value="<?php echo htmlspecialchars($as_of_date); ?>">
                    <input type="hidden" name="fund_cluster" value="<?php echo htmlspecialchars($fund_cluster); ?>">
                    <input type="hidden" name="accountable_officer" value="<?php echo htmlspecialchars($accountable_officer); ?>">
                    <input type="hidden" name="position_title" value="<?php echo htmlspecialchars($position_title); ?>">
                    <input type="hidden" name="accountability_date" value="<?php echo htmlspecialchars($accountability_date); ?>">
                    <input type="hidden" name="num_pages" value="<?php echo htmlspecialchars($num_pages); ?>">
                    <?php foreach ($selected_items as $id => $d): ?>
                        <input type="hidden" name="items[<?php echo $id; ?>][selected]" value="1">
                        <input type="hidden" name="items[<?php echo $id; ?>][item]" value="<?php echo htmlspecialchars($d['item']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][description]" value="<?php echo htmlspecialchars($d['description']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][stock_no]" value="<?php echo htmlspecialchars($d['stock_no']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit]" value="<?php echo htmlspecialchars($d['unit']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit_value]" value="<?php echo htmlspecialchars($d['unit_value']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][balance_qty]" value="<?php echo htmlspecialchars($d['balance_qty']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][physical_count]" value="<?php echo htmlspecialchars($d['physical_count']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][remarks]" value="<?php echo htmlspecialchars($d['remarks'] ?? ''); ?>">
                    <?php endforeach; ?>

                <?php elseif ($report_type === 'rpcppe'): ?>
                    <input type="hidden" name="inventory_type" value="<?php echo htmlspecialchars($inventory_type); ?>">
                    <input type="hidden" name="as_of_date" value="<?php echo htmlspecialchars($as_of_date); ?>">
                    <input type="hidden" name="fund_cluster" value="<?php echo htmlspecialchars($fund_cluster); ?>">
                    <input type="hidden" name="accountable_officer" value="<?php echo htmlspecialchars($accountable_officer); ?>">
                    <input type="hidden" name="position_title" value="<?php echo htmlspecialchars($position_title); ?>">
                    <input type="hidden" name="accountability_date" value="<?php echo htmlspecialchars($accountability_date); ?>">
                    <?php foreach ($selected_items as $id => $d): ?>
                        <input type="hidden" name="items[<?php echo $id; ?>][selected]" value="1">
                        <input type="hidden" name="items[<?php echo $id; ?>][item]" value="<?php echo htmlspecialchars($d['item']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][description]" value="<?php echo htmlspecialchars($d['description']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][stock_no]" value="<?php echo htmlspecialchars($d['stock_no']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit]" value="<?php echo htmlspecialchars($d['unit']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit_value]" value="<?php echo htmlspecialchars($d['unit_value']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][balance_qty]" value="<?php echo htmlspecialchars($d['balance_qty']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][physical_count]" value="<?php echo htmlspecialchars($d['physical_count']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][remarks]" value="<?php echo htmlspecialchars($d['remarks'] ?? ''); ?>">
                    <?php endforeach; ?>

                <?php elseif ($report_type === 'rsmi'): ?>
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <input type="hidden" name="ris_series" value="<?php echo htmlspecialchars($ris_series); ?>">
                    <input type="hidden" name="entity_name" value="<?php echo htmlspecialchars($entity_name); ?>">
                    <input type="hidden" name="fund_cluster" value="<?php echo htmlspecialchars($fund_cluster); ?>">
                    <input type="hidden" name="serial_no" value="<?php echo htmlspecialchars($serial_no); ?>">
                    <input type="hidden" name="report_date" value="<?php echo htmlspecialchars($report_date); ?>">
                    <?php if(!empty($data)): foreach($data as $idx => $row): ?>
                        <input type="hidden" name="items[<?php echo $idx; ?>][ris_no]" value="<?php echo htmlspecialchars($row['ris_no']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][stock_no]" value="<?php echo htmlspecialchars($row['stock_no']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][item]" value="<?php echo htmlspecialchars($row['item']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][unit]" value="<?php echo htmlspecialchars($row['unit_measurement']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][qty]" value="<?php echo htmlspecialchars($row['quantity_issued']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][unit_value]" value="<?php echo htmlspecialchars($row['unit_value']); ?>">
                        <input type="hidden" name="items[<?php echo $idx; ?>][amount]" value="<?php echo htmlspecialchars($row['amount']); ?>">
                    <?php endforeach; endif; ?>
                <?php elseif ($report_type === 'ics' || $report_type === 'iirup' || $report_type === 'par' || $report_type === 'pc' || $report_type === 'ppelc' || $report_type === 'ptr' || $report_type === 'sc' || $report_type === 'slc'): ?>
                    <?php foreach ($selected_items as $id => $d): ?>
                        <input type="hidden" name="items[<?php echo $id; ?>][selected]" value="1">
                        <input type="hidden" name="items[<?php echo $id; ?>][item]" value="<?php echo htmlspecialchars($d['item']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][description]" value="<?php echo htmlspecialchars($d['description']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][stock_no]" value="<?php echo htmlspecialchars($d['stock_no']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit]" value="<?php echo htmlspecialchars($d['unit']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][unit_value]" value="<?php echo htmlspecialchars($d['unit_value']); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][physical_count]" value="<?php echo htmlspecialchars($d['physical_count'] ?? 1); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][date_acquired]" value="<?php echo htmlspecialchars($d['date_acquired'] ?? ''); ?>">
                        <input type="hidden" name="items[<?php echo $id; ?>][remarks]" value="<?php echo htmlspecialchars($d['remarks'] ?? ''); ?>">
                    <?php endforeach; ?>
                <?php endif; ?>

                <button type="submit" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
            </form>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>

        <!-- RPCI Content -->
        <?php if ($report_type === 'rpci'): ?>
        <div style="font-family: Arial, sans-serif;">
            <div class="text-end" style="font-size: 10px; margin-bottom: 2px;">GAM Appendix 66</div>
            <table class="table table-bordered w-100 border-dark mb-2" style="margin-bottom: 20px;">
                <tr>
                    <td rowspan="2" width="12%" class="text-center align-middle p-2">
                        <img src="../assets/img/logo no bg.png" alt="Logo" style="max-height: 60px;">
                    </td>
                    <td class="align-middle p-2" style="border-bottom: none;">
                        <div class="fw-bold" style="font-size: 18px;">Report on the Physical Count of Inventories</div>
                    </td>
                </tr>
                <tr>
                    <td class="p-0">
                        <table class="table table-bordered m-0 border-0 w-100" style="font-size: 10px;">
                            <tr class="text-center">
                                <td class="border-0 border-end border-dark" style="width: 33.33%;">Form No. <?php echo htmlspecialchars($form_no ?: 'RPCI-FMD-FM083'); ?></td>
                                <td class="border-0 border-end border-dark" style="width: 33.33%;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></td>
                                <td class="border-0" style="width: 33.33%;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="text-center mb-4" style="font-size: 11px;">
                <div style="margin-bottom: 15px;">
                    <div style="border-bottom: 1px solid #000; width: 400px; margin: 0 auto; min-height: 20px; font-weight: bold;">
                        <?php echo htmlspecialchars($inventory_type); ?>
                    </div>
                    <div style="font-size: 10px;">(Type of Inventory Item)</div>
                </div>
                <div>
                    As at <span style="border-bottom: 1px solid #000; min-width: 250px; display: inline-block; font-weight: bold;"><?php echo htmlspecialchars(date('F d, Y', strtotime($as_of_date))); ?></span>
                </div>
            </div>

            <div class="mb-3" style="font-size: 12px;">
                <strong>Fund Cluster :</strong> <span style="border-bottom: 1px solid #000; min-width: 200px; display: inline-block; padding: 0 5px;"><?php echo htmlspecialchars($fund_cluster); ?></span>
            </div>

            <div class="mb-4" style="font-size: 12px; line-height: 2.2;">
                <span>For which</span>
                <div style="display: inline-block; text-align: center; margin: 0 5px; vertical-align: top;">
                    <span style="border-bottom: 1px solid #000; min-width: 220px; display: block; padding: 0 10px; font-weight: bold;"><?php echo htmlspecialchars($accountable_officer); ?></span>
                    <span style="font-size: 10px; display: block; line-height: 1;">(Name of Accountable Officer)</span>
                </div>
                <span>,</span>
                <div style="display: inline-block; text-align: center; margin: 0 5px; vertical-align: top;">
                    <span style="border-bottom: 1px solid #000; min-width: 180px; display: block; padding: 0 10px; font-weight: bold;"><?php echo htmlspecialchars($position_title); ?></span>
                    <span style="font-size: 10px; display: block; line-height: 1;">(Official Designation)</span>
                </div>
                <span>,</span>
                <div style="display: inline-block; text-align: center; margin: 0 5px; vertical-align: top;">
                    <span style="border-bottom: 1px solid #000; min-width: 260px; display: block; padding: 0 10px; font-weight: bold;">COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</span>
                    <span style="font-size: 10px; display: block; line-height: 1;">(Entity Name)</span>
                </div>
                <span>is accountable, having assumed such accountability on</span>
                <div style="display: inline-block; text-align: center; margin: 0 5px; vertical-align: top;">
                    <span style="border-bottom: 1px solid #000; min-width: 180px; display: block; padding: 0 10px; font-weight: bold;"><?php echo htmlspecialchars(date('F d, Y', strtotime($accountability_date))); ?></span>
                    <span style="font-size: 10px; display: block; line-height: 1;">(Date of Assumption)</span>
                </div>
                <span>.</span>
            </div>

            <table class="table table-bordered table-sm w-100 border-dark" style="font-size: 11px;">
                <thead>
                    <tr class="text-center align-middle">
                        <th rowspan="2" style="width: 10%;">Article</th>
                        <th rowspan="2" style="width: 25%;">Description</th>
                        <th rowspan="2" style="width: 7%;">Stock<br>Number</th>
                        <th rowspan="2" style="width: 7%;">Unit of<br>Measure</th>
                        <th rowspan="2" style="width: 7%;">Unit<br>Value</th>
                        <th rowspan="2" style="width: 9%;">Balance Per<br>Card<br><span style="font-size: 9px; font-weight: normal;">(Quantity)</span></th>
                        <th rowspan="2" style="width: 9%;">On Hand Per<br>Count<br><span style="font-size: 9px; font-weight: normal;">(Quantity)</span></th>
                        <th colspan="2" style="width: 16%;">Shortage/Overage</th>
                        <th rowspan="2" style="width: 10%;">Remarks</th>
                    </tr>
                    <tr class="text-center align-middle">
                        <th style="width: 8%;">Quantity</th>
                        <th style="width: 8%;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $min_rows = 15;
                    $rows_rendered = 0;
                    if (!empty($selected_items)) {
                        foreach ($selected_items as $id => $d) {
                            $balance_qty = (float)($d['balance_qty'] ?? 0);
                            $physical_qty = (float)($d['physical_count'] ?? 0);
                            $unit_value = (float)($d['unit_value'] ?? 0);
                            $diff_qty = $physical_qty - $balance_qty;
                            $diff_val = $diff_qty * $unit_value;
                            $rows_rendered++;
                            ?>
                            <tr class="align-middle" style="min-height: 30px;">
                                <td class="fw-bold p-1"><?php echo htmlspecialchars($d['item'] ?? ''); ?></td>
                                <td class="p-1"><?php echo htmlspecialchars($d['description'] ?? ''); ?></td>
                                <td class="text-center p-1"><?php echo htmlspecialchars($d['stock_no'] ?? ''); ?></td>
                                <td class="text-center p-1"><?php echo htmlspecialchars($d['unit'] ?? ''); ?></td>
                                <td class="text-end p-1"><?php echo $unit_value ? number_format($unit_value, 2) : ''; ?></td>
                                <td class="text-center p-1"><?php echo $balance_qty !== 0.0 ? rtrim(rtrim(number_format($balance_qty, 2, '.', ''), '0'), '.') : ''; ?></td>
                                <td class="text-center p-1"><?php echo $physical_qty !== 0.0 ? rtrim(rtrim(number_format($physical_qty, 2, '.', ''), '0'), '.') : ''; ?></td>
                                <td class="text-center p-1"><?php echo $diff_qty !== 0.0 ? rtrim(rtrim(number_format($diff_qty, 2, '.', ''), '0'), '.') : ''; ?></td>
                                <td class="text-end p-1"><?php echo $diff_qty !== 0.0 ? number_format($diff_val, 2) : ''; ?></td>
                                <td class="p-1"><?php echo htmlspecialchars($d['remarks'] ?? ''); ?></td>
                            </tr>
                            <?php
                        }
                    }
                    for ($i = $rows_rendered; $i < $min_rows; $i++) {
                        echo '<tr style="height: 30px;"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <table class="table table-bordered w-100 border-dark mt-4" style="font-size: 11px;">
                <tr>
                    <td style="width: 33.33%; height: 150px; vertical-align: top; padding: 10px;">
                        <div style="margin-bottom: 40px;">Certified Correct by:</div>
                        <div class="text-center">
                            <div style="border-bottom: 1px solid #000; margin: 0 10px; min-height: 20px;">
                                <?php echo htmlspecialchars($rpci_certified_name_officer_v); ?><br>
                                <?php echo htmlspecialchars($rpci_certified_name_acting_officer_i); ?><br>
                                <?php echo htmlspecialchars($rpci_certified_name_accountant_ii); ?>
                            </div>
                            <div class="mt-2" style="font-size: 10px;">Signature over Printed Name of<br>Inventory Committee Chair and Members</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; height: 150px; vertical-align: top; padding: 10px;">
                        <div style="margin-bottom: 40px;">Approved by:</div>
                        <div class="text-center">
                            <div style="border-bottom: 1px solid #000; margin: 0 10px; min-height: 20px; font-weight: bold;">
                                <?php echo htmlspecialchars($rpci_approved_name); ?>
                            </div>
                            <div class="mt-2" style="font-size: 10px;">Signature over Printed Name of Head of<br>Agency/Entity or Authorized Representative</div>
                        </div>
                    </td>
                    <td style="width: 33.33%; height: 150px; vertical-align: top; padding: 10px;">
                        <div style="margin-bottom: 40px;">Verified by:</div>
                        <div class="text-center">
                            <div style="border-bottom: 1px solid #000; margin: 0 10px; min-height: 20px; font-weight: bold;">
                                <?php echo htmlspecialchars($rpci_verified_name); ?>
                            </div>
                            <div class="mt-2" style="font-size: 10px;">Signature over Printed Name of COA<br>Representative</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php elseif ($report_type === 'ris'): ?>
        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Requisition and Issue Slip</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'RIS-FMD-FM080'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2" style="width: 55%;">
                    <div><strong>Entity Name:</strong> <?php echo htmlspecialchars($entity_name); ?></div>
                    <div><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($fund_cluster); ?></div>
                </td>
                <td class="p-2" style="width: 45%;">
                    <div><strong>Division:</strong> <?php echo htmlspecialchars($division); ?></div>
                    <div><strong>Office:</strong> <?php echo htmlspecialchars($office); ?></div>
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <div><strong>Responsibility Center Code:</strong> <?php echo htmlspecialchars($responsibility_center); ?></div>
                </td>
                <td class="p-2">
                    <div class="d-flex justify-content-between">
                        <div><strong>RIS No.:</strong> <?php echo htmlspecialchars($ris_no); ?></div>
                        <div><strong>Date:</strong> <?php echo date('F d, Y', strtotime($request_row['request_date'])); ?></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Purpose:</strong>
                    <span><?php echo htmlspecialchars($purpose); ?></span>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th rowspan="2" class="text-center align-middle" style="width: 6%;">Stock No.</th>
                    <th rowspan="2" class="text-center align-middle" style="width: 35%;">Item Description</th>
                    <th rowspan="2" class="text-center align-middle" style="width: 6%;">Unit</th>
                    <th colspan="2" class="text-center align-middle" style="width: 18%;">Quantity</th>
                    <th rowspan="2" class="text-center align-middle" style="width: 12%;">Balance on Hand</th>
                    <th rowspan="2" class="text-center align-middle" style="width: 23%;">Remarks</th>
                </tr>
                <tr>
                    <th class="text-center align-middle" style="width: 9%;">Requested</th>
                    <th class="text-center align-middle" style="width: 9%;">Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $max_rows = 15;
                $current_rows = 0;
                if (empty($ris_items)) {
                    echo "<tr><td colspan='7' class='text-center py-3'>No items found for this RIS.</td></tr>";
                } else {
                    foreach ($ris_items as $row) {
                        $current_rows++;
                        $qty_req = isset($row['quantity_requested']) ? (float)$row['quantity_requested'] : 0;
                        $qty_issued = isset($row['quantity_issued']) && $row['quantity_issued'] > 0
                            ? (float)$row['quantity_issued']
                            : $qty_req;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($row['stock_no']); ?></td>
                            <td>
                                <?php
                                $desc = isset($row['description']) && $row['description'] !== ''
                                    ? $row['item'] . ', ' . $row['description']
                                    : $row['item'];
                                echo htmlspecialchars($desc);
                                ?>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                            <td class="text-center"><?php echo $qty_req > 0 ? $qty_req : ''; ?></td>
                            <td class="text-center"><?php echo $qty_issued > 0 ? $qty_issued : ''; ?></td>
                            <td class="text-center"><?php echo isset($row['balance_qty']) ? htmlspecialchars($row['balance_qty']) : ''; ?></td>
                            <td><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
                        </tr>
                        <?php
                    }
                }

                if ($current_rows < $max_rows) {
                    for ($i = $current_rows; $i < $max_rows; $i++) {
                        echo "<tr>";
                        echo "<td>&nbsp;</td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "<td></td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <table class="table table-bordered w-100 mb-0 border-dark">
            <tr>
                <td class="align-top p-2" style="width: 25%;">
                    <div class="fw-bold mb-2">Requested by:</div>
                    <div style="min-height: 40px;"></div>
                    <div class="border-top pt-1 text-center"><?php echo htmlspecialchars($requested_by); ?></div>
                    <div class="small text-center">Printed Name</div>
                </td>
                <td class="align-top p-2" style="width: 25%;">
                    <div class="fw-bold mb-2">Approved by:</div>
                    <div style="min-height: 40px;"></div>
                    <div class="border-top pt-1 text-center"><?php echo htmlspecialchars($approved_by); ?></div>
                    <div class="small text-center">Printed Name</div>
                </td>
                <td class="align-top p-2" style="width: 25%;">
                    <div class="fw-bold mb-2">Issued by:</div>
                    <div style="min-height: 40px;"></div>
                    <div class="border-top pt-1 text-center"><?php echo htmlspecialchars($issued_by); ?></div>
                    <div class="small text-center">Printed Name</div>
                </td>
                <td class="align-top p-2" style="width: 25%;">
                    <div class="fw-bold mb-2">Received by:</div>
                    <div style="min-height: 40px;"></div>
                    <div class="border-top pt-1 text-center"><?php echo htmlspecialchars($received_by); ?></div>
                    <div class="small text-center">Printed Name</div>
                </td>
            </tr>
        </table>

        <!-- ICS Content -->
        <?php elseif ($report_type === 'ics'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Inventory Custodian Slip</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'ICS-FMD-FM081'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <div><strong>Entity Name:</strong> <?php echo htmlspecialchars($entity_name); ?></div>
                    <div><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($fund_cluster); ?></div>
                </td>
                <td class="p-2" style="width: 40%;">
                    <div><strong>ICS No.:</strong> <?php echo htmlspecialchars($ics_no); ?></div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 10%;">Quantity</th>
                    <th rowspan="2" style="width: 8%;">Unit</th>
                    <th colspan="2" style="width: 16%;">Amount</th>
                    <th rowspan="2" style="width: 36%;">Description</th>
                    <th rowspan="2" style="width: 15%;">Inventory Item No.</th>
                    <th rowspan="2" style="width: 15%;">Estimated Useful Life</th>
                </tr>
                <tr>
                    <th style="width: 8%;">Unit Cost</th>
                    <th style="width: 8%;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='7' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $amount = $qty * $unit_value;
                        ?>
                        <tr>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-center ics-qty" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo $qty; ?>"></td>
                            <td class="text-center" style="font-size: 11px;"><?php echo htmlspecialchars($d['unit']); ?></td>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end ics-unit-cost" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo $unit_value; ?>"></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm text-end ics-total-cost" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo number_format($amount, 2); ?>" readonly></td>
                            <td style="font-size: 11px;"><?php echo htmlspecialchars($d['item']); ?> - <?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-center" style="font-size: 11px;"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm text-center ics-useful-life" data-id="<?php echo $id; ?>" name="ics_useful_life_<?php echo $id; ?>" style="font-size: 11px;" value="" placeholder="e.g. 5 years"></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <script>
            // ICS Auto-Calculations
            function calculateICS(itemId) {
                const qtyElem = document.querySelector(`.ics-qty[data-id="${itemId}"]`);
                const unitCostElem = document.querySelector(`.ics-unit-cost[data-id="${itemId}"]`);
                const totalCostElem = document.querySelector(`.ics-total-cost[data-id="${itemId}"]`);

                if (!qtyElem || !unitCostElem || !totalCostElem) return;

                const qty = parseFloat(qtyElem.value) || 0;
                const unitCost = parseFloat(unitCostElem.value) || 0;
                const totalCost = qty * unitCost;

                totalCostElem.value = totalCost.toFixed(2);
            }

            // Initialize ICS event listeners
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.ics-qty, .ics-unit-cost').forEach(function(elem) {
                    elem.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-id');
                        calculateICS(itemId);
                    });
                    elem.addEventListener('input', function() {
                        const itemId = this.getAttribute('data-id');
                        calculateICS(itemId);
                    });
                });
            });
        </script>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="height: 90px;">
                    <strong>Reason for Transfer:</strong>
                    <div class="mt-2" style="line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($ics_reason ?? '')); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="text-center align-middle p-2" style="width: 33%;">Approved by:</td>
                <td class="text-center align-middle p-2" style="width: 33%;">Released/Issued by:</td>
                <td class="text-center align-middle p-2" style="width: 34%;">Received by:</td>
            </tr>
            <tr>
                <td class="p-2 text-center" style="height: 100px; vertical-align: bottom;">
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 0 auto 5px;"></div>
                    <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($ics_approved_name ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;"></div>
                    <div style="font-size: 10px; margin: 5px 0;"><?php echo htmlspecialchars($ics_approved_position ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto 0;"></div>
                    <div style="font-size: 10px; margin-top: 5px;">Date</div>
                </td>
                <td class="p-2 text-center" style="height: 100px; vertical-align: bottom;">
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 0 auto 5px;"></div>
                    <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($ics_received_from_name ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;"></div>
                    <div style="font-size: 10px; margin: 5px 0;"><?php echo htmlspecialchars($ics_received_from_position ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto 0;"></div>
                    <div style="font-size: 10px; margin-top: 5px;">Date</div>
                </td>
                <td class="p-2 text-center" style="height: 100px; vertical-align: bottom;">
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 0 auto 5px;"></div>
                    <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($ics_received_by_name ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;"></div>
                    <div style="font-size: 10px; margin: 5px 0;"><?php echo htmlspecialchars($ics_received_by_position ?? ''); ?></div>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto 0;"></div>
                    <div style="font-size: 10px; margin-top: 5px;">Date</div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3 align-middle" style="width: 50%;">
                    <div class="mb-2"><strong>Received by:</strong></div>
                    <div class="text-center" style="margin-top: 20px;">
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 0 auto 5px;"></div>
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($ics_received_by_name ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 5px auto;"></div>
                        <div style="font-size: 10px;"><?php echo htmlspecialchars($ics_received_by_position ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 5px auto 0;"></div>
                        <div style="font-size: 10px; margin-top: 5px;">Date</div>
                    </div>
                </td>
                <td class="p-3 align-middle" style="width: 50%;">
                    <div class="mb-2"><strong>Issued by:</strong></div>
                    <div class="text-center" style="margin-top: 20px;">
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 0 auto 5px;"></div>
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($ics_received_from_name ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 5px auto;"></div>
                        <div style="font-size: 10px;"><?php echo htmlspecialchars($ics_received_from_position ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 70%; margin: 5px auto 0;"></div>
                        <div style="font-size: 10px; margin-top: 5px;">Date</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- IIRUP Content -->
        <?php elseif ($report_type === 'iirup'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Inventory and Inspection Report of Unserviceable Property</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'IIRUP-FMD-FM0XX'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <div><strong>Entity Name:</strong> <?php echo htmlspecialchars($iirup_entity_name); ?></div>
                    <div><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($iirup_fund_cluster); ?></div>
                    <div><strong>Location:</strong> <?php echo htmlspecialchars($iirup_location); ?></div>
                </td>
                <td class="p-2" style="width: 40%;">
                    <div><strong>Inventory No.:</strong> <?php echo htmlspecialchars($iirup_inventory_no); ?></div>
                    <div><strong>Date:</strong> <?php echo htmlspecialchars($iirup_report_date); ?></div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 7%;">Date Acquired</th>
                    <th rowspan="2" style="width: 15%;">Particulars / Articles</th>
                    <th rowspan="2" style="width: 9%;">Property No.</th>
                    <th colspan="6" class="text-center" style="width: 40%;">INVENTORY</th>
                    <th rowspan="2" style="width: 7%;">Remarks</th>
                    <th colspan="5" class="text-center" style="width: 15%;">INSPECTION and DISPOSAL</th>
                    <th colspan="3" class="text-center" style="width: 17%;">RECORD OF SALES</th>
                </tr>
                <tr>
                    <th style="width: 4%;">Qty</th>
                    <th style="width: 6%;">Unit Cost</th>
                    <th style="width: 6%;">Total Cost</th>
                    <th style="width: 8%;">Accumulated Depreciation</th>
                    <th style="width: 8%;">Accumulated Impairment Losses</th>
                    <th style="width: 8%;">Carrying Amount</th>
                    <th style="width: 3%;">Sale</th>
                    <th style="width: 3%;">Transfer</th>
                    <th style="width: 4%;">Destruction</th>
                    <th style="width: 5%;">Others (Specify)</th>
                    <th style="width: 5%;">Total</th>
                    <th style="width: 5%;">Appraised Value</th>
                    <th style="width: 4%;">OR No.</th>
                    <th style="width: 5%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='18' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $total_cost = $qty * $unit_value;
                        $date_acquired = isset($d['date_acquired']) && $d['date_acquired'] ? date('Y-m-d', strtotime($d['date_acquired'])) : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($date_acquired); ?></td>
                            <td><?php echo htmlspecialchars($d['item']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-end"><?php echo number_format($unit_value, 2); ?></td>
                            <td class="text-end"><?php echo number_format($total_cost, 2); ?></td>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end iirup-accumulated-depreciation" data-id="<?php echo $id; ?>" name="iirup_accumulated_depreciation_<?php echo $id; ?>" style="font-size: 11px;" value="0.00" placeholder="0.00"></td>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end iirup-accumulated-impairment" data-id="<?php echo $id; ?>" name="iirup_accumulated_impairment_<?php echo $id; ?>" style="font-size: 11px;" value="0.00" placeholder="0.00"></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm text-end iirup-carrying-amount" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="0.00" readonly></td>
                            <input type="hidden" class="iirup-total-cost" data-id="<?php echo $id; ?>" value="<?php echo $total_cost; ?>">
                            <td class="p-1"><input type="text" class="form-control form-control-sm" name="iirup_remarks_<?php echo $id; ?>" style="font-size: 11px;" value=""></td>
                            <td class="text-center p-1"><input type="checkbox" class="form-check-input" name="iirup_disposal_sale_<?php echo $id; ?>"></td>
                            <td class="text-center p-1"><input type="checkbox" class="form-check-input" name="iirup_disposal_transfer_<?php echo $id; ?>"></td>
                            <td class="text-center p-1"><input type="checkbox" class="form-check-input" name="iirup_disposal_destruction_<?php echo $id; ?>"></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm" name="iirup_disposal_others_<?php echo $id; ?>" style="font-size: 11px;" placeholder="Specify"></td>
                            <td class="text-end p-1"><input type="text" class="form-control form-control-sm text-end" name="iirup_sales_total_<?php echo $id; ?>" style="font-size: 11px;" value=""></td>
                            <td class="text-end p-1"><input type="text" class="form-control form-control-sm text-end" name="iirup_appraised_value_<?php echo $id; ?>" style="font-size: 11px;" value=""></td>
                            <td class="text-center p-1"><input type="text" class="form-control form-control-sm" name="iirup_or_no_<?php echo $id; ?>" style="font-size: 11px;" value=""></td>
                            <td class="text-end p-1"><input type="text" class="form-control form-control-sm text-end" name="iirup_amount_<?php echo $id; ?>" style="font-size: 11px;" value=""></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3 align-top" style="width: 46%;">
                    <p class="mb-5 text-justify" style="text-indent: 40px;">
                        I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
                    </p>
                    <div class="d-flex justify-content-between mt-4">
                        <div class="text-center" style="width: 48%;">
                            <div class="mb-2">Requested by:</div>
                            <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($iirup_requested_by_name ?? ''); ?></div>
                            <div style="border-bottom: 1px solid #000; width: 100%; margin: 5px auto;"></div>
                            <div class="small">(Signature over Printed Name of Accountable Officer)</div>
                            <div style="font-size: 10px; margin: 5px 0;"><?php echo htmlspecialchars($iirup_requested_by_designation ?? ''); ?></div>
                            <div style="border-bottom: 1px solid #000; width: 100%; margin: 5px auto 0;"></div>
                            <div class="small">(Designation of Accountable Officer)</div>
                        </div>
                        <div class="text-center" style="width: 48%;">
                            <div class="mb-2">Approved by:</div>
                            <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($iirup_approved_by_name ?? ''); ?></div>
                            <div style="border-bottom: 1px solid #000; width: 100%; margin: 5px auto;"></div>
                            <div class="small">(Signature over Printed Name of Authorized Official)</div>
                            <div style="font-size: 10px; margin: 5px 0;"><?php echo htmlspecialchars($iirup_approved_by_designation ?? ''); ?></div>
                            <div style="border-bottom: 1px solid #000; width: 100%; margin: 5px auto 0;"></div>
                            <div class="small">(Designation of Authorized Official)</div>
                        </div>
                    </div>
                </td>
                <td class="p-3 align-top" style="width: 54%;">
                    <div class="d-flex justify-content-between">
                        <div style="width: 48%;">
                            <p class="mb-5 text-justify" style="text-indent: 40px;">
                                I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.
                            </p>
                            <div class="text-center mt-4">
                                <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($iirup_inspection_officer_name ?? ''); ?></div>
                                <div style="border-bottom: 1px solid #000; width: 90%; margin: 5px auto;"></div>
                                <div class="small">(Signature over Printed Name of Inspection Officer)</div>
                            </div>
                        </div>
                        <div style="width: 48%;">
                            <p class="mb-5 text-justify" style="text-indent: 40px;">
                                I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this ____ day of _____________, _____.
                            </p>
                            <div class="text-center mt-4">
                                <div style="font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($iirup_witness_name ?? ''); ?></div>
                                <div style="border-bottom: 1px solid #000; width: 90%; margin: 5px auto;"></div>
                                <div class="small">(Signature over Printed Name of Witness)</div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <script>
            // IIRUP Carrying Amount Calculation: Carrying Amount = Total Cost - Accumulated Depreciation - Accumulated Impairment Losses
            function calculateIirupCarryingAmount(itemId) {
                const totalCostElem = document.querySelector(`.iirup-total-cost[data-id="${itemId}"]`);
                const accumDeprecElem = document.querySelector(`.iirup-accumulated-depreciation[data-id="${itemId}"]`);
                const accumImpairmentElem = document.querySelector(`.iirup-accumulated-impairment[data-id="${itemId}"]`);
                const carryingAmountElem = document.querySelector(`.iirup-carrying-amount[data-id="${itemId}"]`);

                if (!totalCostElem || !accumDeprecElem || !accumImpairmentElem || !carryingAmountElem) return;

                const totalCost = parseFloat(totalCostElem.value) || 0;
                const accumDepreciation = parseFloat(accumDeprecElem.value) || 0;
                const accumImpairment = parseFloat(accumImpairmentElem.value) || 0;

                // Carrying Amount = Total Cost - Accumulated Depreciation - Accumulated Impairment Losses
                const carryingAmount = totalCost - accumDepreciation - accumImpairment;
                carryingAmountElem.value = carryingAmount.toFixed(2);
            }

            // RECORD OF SALES - Amount equals Appraised Value
            function syncAppraisedValueToAmount(itemId) {
                const appraisedValueElem = document.querySelector(`input[name="iirup_appraised_value_${itemId}"]`);
                const amountElem = document.querySelector(`input[name="iirup_amount_${itemId}"]`);

                if (appraisedValueElem && amountElem) {
                    const appraisedValue = parseFloat(appraisedValueElem.value) || 0;
                    amountElem.value = appraisedValue.toFixed(2);
                }
            }

            // Initialize event listeners for IIRUP fields
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.iirup-accumulated-depreciation, .iirup-accumulated-impairment').forEach(function(elem) {
                    elem.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-id');
                        calculateIirupCarryingAmount(itemId);
                    });
                    elem.addEventListener('input', function() {
                        const itemId = this.getAttribute('data-id');
                        calculateIirupCarryingAmount(itemId);
                    });
                });

                // Sync Appraised Value to Amount in RECORD OF SALES
                document.querySelectorAll('input[name^="iirup_appraised_value_"]').forEach(function(elem) {
                    elem.addEventListener('change', function() {
                        const itemId = this.name.replace('iirup_appraised_value_', '');
                        syncAppraisedValueToAmount(itemId);
                    });
                    elem.addEventListener('input', function() {
                        const itemId = this.name.replace('iirup_appraised_value_', '');
                        syncAppraisedValueToAmount(itemId);
                    });
                });
            });
        </script>

        <!-- PAR Content -->
        <?php elseif ($report_type === 'par'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Property Acknowledgement Receipt</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'PAR-FMD-FM0XX'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> <?php echo htmlspecialchars($par_entity_name); ?>
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>PAR No.:</strong> <?php echo htmlspecialchars($par_no); ?>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 10%;">Unit</th>
                    <th style="width: 36%;">Description</th>
                    <th style="width: 14%;">Property Number</th>
                    <th style="width: 15%;">Date Acquired</th>
                    <th style="width: 15%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='6' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $amount = $qty * $unit_value;
                        $date_acquired = isset($d['date_acquired']) && $d['date_acquired'] ? date('Y-m-d', strtotime($d['date_acquired'])) : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['unit']); ?></td>
                            <td><?php echo htmlspecialchars($d['item']); ?> - <?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($date_acquired); ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3 align-top" style="width: 50%;">
                    <div class="mb-2">Received by:</div>
                    <div class="text-center" style="margin-top: 20px;">
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($par_received_by_name ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;"></div>
                        <div class="small">Signature over Printed Name of End User</div>
                        <div style="font-size: 10px; margin-top: 5px;"><?php echo htmlspecialchars($par_received_by_position ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto 0;"></div>
                        <div class="small">Position/Office</div>
                    </div>
                </td>
                <td class="p-3 align-top" style="width: 50%;">
                    <div class="mb-2">Issued by:</div>
                    <div class="text-center" style="margin-top: 20px;">
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($par_issued_by_name ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;"></div>
                        <div class="small">Signature over Printed Name of Supply and/or Property Custodian</div>
                        <div style="font-size: 10px; margin-top: 5px;"><?php echo htmlspecialchars($par_issued_by_position ?? ''); ?></div>
                        <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto 0;"></div>
                        <div class="small">Position/Office</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- PC Content (Property Card) -->
        <?php elseif ($report_type === 'pc'): ?>
        <?php
        $pc_items = array_filter($selected_items, function($d) {
            return strtolower((string)($d['item_type'] ?? '')) !== 'expendable';
        });

        if (empty($pc_items)) {
            echo "<div class='text-center py-4'>No semi-expendable or non-expendable items selected.</div>";
        } else {
            $pc_total = count($pc_items);
            $pc_index = 0;
            foreach ($pc_items as $id => $d) {
                $pc_index++;

                $property_number = trim((string)($d['stock_no'] ?? ''));
                if ($property_number === '') {
                    $property_number = trim((string)$pc_property_number);
                }

                $desc_parts = [];
                if (!empty($d['item'])) $desc_parts[] = (string)$d['item'];
                if (!empty($d['description'])) $desc_parts[] = (string)$d['description'];
                $description = trim(implode(' - ', $desc_parts));
                if ($description === '') {
                    $description = trim((string)$pc_description);
                }
                ?>

                <table class="table table-bordered w-100 mb-4 border-dark">
                    <tr>
                        <td width="20%" class="text-center align-middle p-2">
                            <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                        </td>
                        <td class="text-center align-middle p-2">
                            <h5 class="fw-bold mb-0 text-uppercase">Property Card</h5>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="p-0">
                            <div class="d-flex w-100">
                                <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'PC-FMD-FM0XX'); ?></div>
                                <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                                <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="table table-bordered w-100 mb-3 border-dark">
                    <tr>
                        <td class="p-2" style="width: 60%;">
                            <strong>Entity Name :</strong> <?php echo htmlspecialchars($pc_entity_name); ?>
                        </td>
                        <td class="p-2" style="width: 40%;">
                            <strong>Fund Cluster:</strong> <?php echo htmlspecialchars($pc_fund_cluster); ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2">
                            <strong>Property, Plant and Equipment :</strong> <?php echo htmlspecialchars($pc_ppe); ?>
                        </td>
                        <td class="p-2">
                            <strong>Property Number:</strong> <?php echo htmlspecialchars($property_number); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="p-2">
                            <strong>Description :</strong> <?php echo htmlspecialchars($description); ?>
                        </td>
                    </tr>
                </table>

                <table class="table table-bordered table-sm w-100 mb-3">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 10%;">Date</th>
                            <th rowspan="2" style="width: 14%;">Reference/ PAR No.</th>
                            <th style="width: 10%;">Receipt</th>
                            <th colspan="2" style="width: 26%;">Issue/Transfer/ Disposal</th>
                            <th style="width: 10%;">Balance</th>
                            <th rowspan="2" style="width: 10%;">Amount</th>
                            <th rowspan="2" style="width: 10%;">Remarks</th>
                        </tr>
                        <tr>
                            <th>Qty.</th>
                            <th>Qty.</th>
                            <th>Office/Officer</th>
                            <th>Qty.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $qty = isset($d['physical_count']) ? (float)$d['physical_count'] : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = (float)($d['unit_value'] ?? 0);
                        $amount = $qty * $unit_value;
                        $date_acquired = isset($d['date_acquired']) && $d['date_acquired'] ? date('Y-m-d', strtotime($d['date_acquired'])) : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($date_acquired); ?></td>
                            <td class="text-center"></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-center"></td>
                            <td></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($pc_index < $pc_total): ?>
                    <div style="page-break-after: always;"></div>
                <?php endif; ?>

                <?php
            }
        }
        ?>

        <!-- PPELC Content -->
        <?php elseif ($report_type === 'ppelc'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Property, Plant and Equipment Ledger Card</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'PPELC-FMD-FM0XX'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> <?php echo htmlspecialchars($ppelc_entity_name); ?>
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>Fund Cluster :</strong> <?php echo htmlspecialchars($ppelc_fund_cluster); ?>
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <strong>Property, Plant and Equipment:</strong> <?php echo htmlspecialchars($ppelc_ppe); ?>
                </td>
                <td class="p-2">
                    <div>Object Account Code: <?php echo htmlspecialchars($ppelc_object_code); ?></div>
                    <div>Estimated Useful Life: <?php echo htmlspecialchars($ppelc_estimated_life); ?></div>
                    <div>Rate of Depreciation: <?php echo htmlspecialchars($ppelc_rate); ?></div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Description:</strong> <?php echo htmlspecialchars($ppelc_description); ?>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 7%;">Date</th>
                    <th rowspan="2" style="width: 10%;">Reference</th>
                    <th colspan="3" style="width: 21%;">Receipt</th>
                    <th rowspan="2" style="width: 10%;">Accumulated Depreciation</th>
                    <th rowspan="2" style="width: 10%;">Accumulated Impairment Losses</th>
                    <th rowspan="2" style="width: 10%;">Issues/Transfers/ Adjustment/s</th>
                    <th rowspan="2" style="width: 9%;">Adjusted Cost</th>
                    <th colspan="2" style="width: 23%;">Repair History</th>
                </tr>
                <tr>
                    <th style="width: 7%;">Qty.</th>
                    <th style="width: 7%;">Unit Cost</th>
                    <th style="width: 7%;">Total Cost</th>
                    <th style="width: 11%;">Nature of Repair</th>
                    <th style="width: 12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='11' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $amount = $qty * $unit_value;
                        $date_acquired = isset($d['date_acquired']) && $d['date_acquired'] ? date('Y-m-d', strtotime($d['date_acquired'])) : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($date_acquired); ?></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm" name="ppelc_reference_<?php echo $id; ?>" style="font-size: 11px;" value="" placeholder="Reference"></td>
                            <td class="text-center p-1"><input type="number" step="0.01" class="form-control form-control-sm text-center ppelc-qty" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo $qty; ?>"></td>
                            <td class="text-end p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end ppelc-unit-cost" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo $unit_value; ?>"></td>
                            <td class="text-end p-1"><input type="text" class="form-control form-control-sm text-end ppelc-total-cost" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo number_format($amount, 2); ?>" readonly></td>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end ppelc-accum-depreciation" data-id="<?php echo $id; ?>" name="ppelc_accum_depreciation_<?php echo $id; ?>" style="font-size: 11px;" value="0.00" placeholder="0.00"></td>
                            <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end ppelc-accum-impairment" data-id="<?php echo $id; ?>" name="ppelc_accum_impairment_<?php echo $id; ?>" style="font-size: 11px;" value="0.00" placeholder="0.00"></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm" name="ppelc_adjustment_<?php echo $id; ?>" style="font-size: 11px;" value="" placeholder="Issues/Transfers"></td>
                            <td class="text-end p-1"><input type="text" class="form-control form-control-sm text-end ppelc-adjusted-cost" data-id="<?php echo $id; ?>" style="font-size: 11px;" value="<?php echo number_format($amount, 2); ?>" readonly></td>
                            <td class="p-1"><input type="text" class="form-control form-control-sm" name="ppelc_repair_nature_<?php echo $id; ?>" style="font-size: 11px;" value="" placeholder="Repair type"></td>
                            <td class="text-end p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end" name="ppelc_repair_amount_<?php echo $id; ?>" style="font-size: 11px;" value="0.00"></td>
                            <input type="hidden" class="ppelc-total-cost-hidden" data-id="<?php echo $id; ?>" value="<?php echo $amount; ?>">
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <script>
            // PPELC Automatic Calculations
            function calculatePPELC(itemId) {
                const qtyElem = document.querySelector(`.ppelc-qty[data-id="${itemId}"]`);
                const unitCostElem = document.querySelector(`.ppelc-unit-cost[data-id="${itemId}"]`);
                const totalCostElem = document.querySelector(`.ppelc-total-cost[data-id="${itemId}"]`);
                const accumDeprecElem = document.querySelector(`.ppelc-accum-depreciation[data-id="${itemId}"]`);
                const accumImpairmentElem = document.querySelector(`.ppelc-accum-impairment[data-id="${itemId}"]`);
                const adjustedCostElem = document.querySelector(`.ppelc-adjusted-cost[data-id="${itemId}"]`);

                if (!qtyElem || !unitCostElem || !totalCostElem) return;

                // Calculate Total Cost = Qty × Unit Cost
                const qty = parseFloat(qtyElem.value) || 0;
                const unitCost = parseFloat(unitCostElem.value) || 0;
                const totalCost = qty * unitCost;
                totalCostElem.value = totalCost.toFixed(2);

                // Calculate Adjusted Cost = Total Cost - Accumulated Depreciation - Accumulated Impairment
                const accumDepreciation = parseFloat(accumDeprecElem?.value) || 0;
                const accumImpairment = parseFloat(accumImpairmentElem?.value) || 0;
                const adjustedCost = totalCost - accumDepreciation - accumImpairment;

                if (adjustedCostElem) {
                    adjustedCostElem.value = adjustedCost.toFixed(2);
                }
            }

            // Initialize PPELC event listeners
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.ppelc-qty, .ppelc-unit-cost').forEach(function(elem) {
                    elem.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-id');
                        calculatePPELC(itemId);
                    });
                    elem.addEventListener('input', function() {
                        const itemId = this.getAttribute('data-id');
                        calculatePPELC(itemId);
                    });
                });

                document.querySelectorAll('.ppelc-accum-depreciation, .ppelc-accum-impairment').forEach(function(elem) {
                    elem.addEventListener('change', function() {
                        const itemId = this.getAttribute('data-id');
                        calculatePPELC(itemId);
                    });
                    elem.addEventListener('input', function() {
                        const itemId = this.getAttribute('data-id');
                        calculatePPELC(itemId);
                    });
                });
            });
        </script>

        <!-- PTR Content (Property Transfer Report) -->
        <?php elseif ($report_type === 'ptr'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Property Transfer Report</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'PTR-FMD-FM0XX'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> <?php echo htmlspecialchars($ptr_entity_name); ?>
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>Fund Cluster :</strong> <?php echo htmlspecialchars($ptr_fund_cluster); ?>
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <div>From Accountable Officer/Agency/Fund Cluster : <?php echo htmlspecialchars($ptr_from_officer); ?></div>
                    <div>To Accountable Officer/Agency/Fund Cluster : <?php echo htmlspecialchars($ptr_to_officer); ?></div>
                </td>
                <td class="p-2">
                    <div>PTR No. : <?php echo htmlspecialchars($ptr_no !== '' ? $ptr_no : '—'); ?></div>
                    <div>Date : <?php echo htmlspecialchars($ptr_date); ?></div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Transfer Type: (check only one)</strong>
                    <div class="mt-1">
                        <span class="me-3"><?php echo ($ptr_transfer_type === 'Donation') ? '&#9745;' : '&#9633;'; ?> Donation</span>
                        <span class="me-3"><?php echo ($ptr_transfer_type === 'Reassignment') ? '&#9745;' : '&#9633;'; ?> Reassignment</span>
                        <span class="me-3"><?php echo ($ptr_transfer_type === 'Relocate') ? '&#9745;' : '&#9633;'; ?> Relocate</span>
                        <span class="me-3"><?php echo ($ptr_transfer_type === 'Others') ? '&#9745;' : '&#9633;'; ?> Others (Specify) <?php echo htmlspecialchars($ptr_others_specify); ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-3">
            <thead>
                <tr>
                    <th style="width: 18%;">Date Acquire</th>
                    <th style="width: 18%;">Property No.</th>
                    <th style="width: 34%;">Description</th>
                    <th style="width: 15%;">Amount</th>
                    <th style="width: 15%;">Condition of PPE</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='5' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $amount = $qty * $unit_value;
                        $date_acquired = isset($d['date_acquired']) && $d['date_acquired'] ? date('Y-m-d', strtotime($d['date_acquired'])) : '';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($date_acquired); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td><?php echo htmlspecialchars($d['item']); ?> - <?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                            <td><?php echo htmlspecialchars($d['remarks'] ?? ''); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="height: 90px;">
                    <strong>Reason for Transfer:</strong>
                    <div class="mt-2" style="line-height: 1.6;">
                        <?php echo htmlspecialchars($ptr_reason); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="text-center align-middle p-2" style="width: 33%;">Approved by:</td>
                <td class="text-center align-middle p-2" style="width: 33%;">Released/Issued by:</td>
                <td class="text-center align-middle p-2" style="width: 34%;">Received by:</td>
            </tr>
            <tr>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_approved_by_name); ?></span><br>
                    Designation : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_approved_by_designation); ?></span><br>
                    Date : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_date); ?></span>
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_released_by_name); ?></span><br>
                    Designation : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_released_by_designation); ?></span><br>
                    Date : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_date); ?></span>
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_received_by_name); ?></span><br>
                    Designation : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_received_by_designation); ?></span><br>
                    Date : <span style="text-decoration: underline;"><?php echo htmlspecialchars($ptr_date); ?></span>
                </td>
            </tr>
        </table>

        <!-- RLSDDP Content -->
        <?php elseif ($report_type === 'rlsddp'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Report of Lost, Stolen, Damaged or Destroyed Property</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'RLSDDP-FMD-FM082'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> <?php echo htmlspecialchars($rlsddp_entity_name); ?></td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> <?php echo htmlspecialchars($rlsddp_fund_cluster); ?></td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 30%;">Department/Office : <?php echo htmlspecialchars($rlsddp_department); ?></td>
                <td class="p-2" style="width: 30%;">Accountable Officer : <?php echo htmlspecialchars($rlsddp_accountable_officer); ?></td>
                <td class="p-2" style="width: 40%;">RLSDDP No. : <?php echo htmlspecialchars($rlsddp_no); ?></td>
            </tr>
            <tr>
                <td class="p-2">Position Title : <?php echo htmlspecialchars($rlsddp_position); ?></td>
                <td class="p-2">Office Address : <?php echo htmlspecialchars($rlsddp_office_address); ?></td>
                <td class="p-2">Date : <?php echo htmlspecialchars($rlsddp_date); ?></td>
            </tr>
            <tr>
                <td class="p-2">Tel No. : <?php echo htmlspecialchars($rlsddp_tel_no); ?></td>
                <td class="p-2">Public Station : <?php echo ($rlsddp_public_station === 'Yes') ? '[&#9745;] Yes &nbsp; [&#9633;] No' : '[&#9633;] Yes &nbsp; [&#9745;] No'; ?></td>
                <td class="p-2">PC No. : <?php echo htmlspecialchars($rlsddp_pc_no); ?> &nbsp;&nbsp; PAR No. : <?php echo htmlspecialchars($rlsddp_par_no); ?></td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2">
                    <strong>Nature of Property: (check applicable box)</strong>
                    <div class="mt-1">
                        <?php echo (is_array($rlsddp_nature) && in_array('Lost', $rlsddp_nature)) ? '[&#9745;] Lost' : '[&#9633;] Lost'; ?> &nbsp;&nbsp;
                        <?php echo (is_array($rlsddp_nature) && in_array('Stolen', $rlsddp_nature)) ? '[&#9745;] Stolen' : '[&#9633;] Stolen'; ?> &nbsp;&nbsp;
                        <?php echo (is_array($rlsddp_nature) && in_array('Damaged', $rlsddp_nature)) ? '[&#9745;] Damaged' : '[&#9633;] Damaged'; ?> &nbsp;&nbsp;
                        <?php echo (is_array($rlsddp_nature) && in_array('Destroyed', $rlsddp_nature)) ? '[&#9745;] Destroyed' : '[&#9633;] Destroyed'; ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100 mb-2">
            <thead>
                <tr>
                    <th style="width: 20%;">Property No.</th>
                    <th style="width: 60%;">Description</th>
                    <th style="width: 20%;">Acquisition Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($selected_items)) {
                    echo "<tr><td colspan='3' class='text-center py-3'>No item selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $qty = isset($d['physical_count']) ? floatval($d['physical_count']) : 1;
                        if ($qty <= 0) $qty = 1;
                        $unit_value = floatval($d['unit_value']);
                        $amount = $qty * $unit_value;
                        ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td><?php echo htmlspecialchars($d['item']); ?> - <?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2">
                    <strong>Circumstances:</strong>
                    <div class="mt-2" style="line-height: 1.6; min-height: 60px;">
                        <?php echo nl2br(htmlspecialchars($rlsddp_circumstances)); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 50%;">
                    I hereby certify that the facts and circumstances stated above are true and correct.
                </td>
                <td class="p-2" style="width: 50%;">
                    Verified by:
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    Signature over Printed Name of the Accountable Officer<br><br>
                    Date: <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_date); ?></span>
                </td>
                <td class="p-2">
                    Signature over Printed Name of the Immediate Supervisor<br><br>
                    Date: <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_verified_date); ?></span>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2" style="width: 50%;">
                    Government Issued ID : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_id_type); ?></span><br>
                    ID No. : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_id_no); ?></span><br>
                    Date Issued : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_id_issued_date); ?></span>
                </td>
                <td class="p-2" style="width: 50%;"></td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2">
                    SUBSCRIBED AND SWORN to before me this <span style="text-decoration: underline;"><?php echo date('j', strtotime($rlsddp_date)); ?></span> day of <span style="text-decoration: underline;"><?php echo date('F', strtotime($rlsddp_date)); ?></span>, affiant exhibiting the above government issued identification card.
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    Doc No. : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_doc_no); ?></span><br>
                    Page No. : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_page_no); ?></span><br>
                    Book No. : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_book_no); ?></span><br>
                    Series of : <span style="text-decoration: underline;"><?php echo htmlspecialchars($rlsddp_series); ?></span>
                </td>
                <td class="p-2 text-center" style="width: 40%;">
                    <div style="border-bottom: 1px solid black; height: 20px; margin-bottom: 6px;"><?php echo htmlspecialchars($rlsddp_notary); ?></div>
                    Notary Public
                </td>
            </tr>
        </table>

        <!-- Issued Items Content -->
        <?php elseif ($report_type === 'issued_items'): ?>
        <div class="mb-3 d-flex align-items-center">
            <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 60px; margin-right: 15px;">
            <div>
                <h5 class="fw-bold mb-0">Commission on Population and Development - Negros Island Region</h5>
                <div class="small">Inventory transactions report</div>
                <div class="small">Period: <?php echo htmlspecialchars($start_date); ?> - <?php echo htmlspecialchars($end_date); ?></div>
                <div class="small">Item type: <?php echo htmlspecialchars($tx_type); ?></div>
            </div>
        </div>

        <table class="table table-bordered table-sm w-100">
            <thead class="table-light">
                <tr>
                    <th>Date Issued</th>
                    <th>RIS No.</th>
                    <th>Stock No.</th>
                    <th>Item</th>
                    <th>Unit</th>
                    <th class="text-center">Qty Issued</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($issued_items_data)): ?>
                    <tr><td colspan="8" class="text-center py-3">No records found.</td></tr>
                <?php else: ?>
                    <?php
                    $total_qty = 0;
                    $total_amt = 0.0;
                    foreach($issued_items_data as $row):
                        $qty = intval($row['quantity_issued']);
                        $amt = floatval($row['amount']);
                        $total_qty += $qty;
                        $total_amt += $amt;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['request_date']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['ris_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['item']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                        <td class="text-center"><?php echo $qty; ?></td>
                        <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($amt, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold">
                        <td colspan="5" class="text-end">TOTAL</td>
                        <td class="text-center"><?php echo number_format($total_qty); ?></td>
                        <td></td>
                        <td class="text-end"><?php echo number_format($total_amt, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Inventory Transactions Content -->
        <?php elseif ($report_type === 'inventory_transactions'): ?>
        <div class="mb-3 d-flex align-items-center">
            <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 60px; margin-right: 15px;">
            <div>
                <h5 class="fw-bold mb-0">Commission on Population and Development - Negros Island Region</h5>
                <div class="small">Inventory transactions report</div>
                <div class="small">
                    Period: <?php echo date('M d, Y', strtotime($start_date)); ?> &ndash; <?php echo date('M d, Y', strtotime($end_date)); ?>
                </div>
                <div class="small">
                    Item type: <?php echo !empty($tx_type) ? htmlspecialchars($tx_type) : 'All types'; ?>
                </div>
            </div>
        </div>

        <table class="table table-bordered table-sm w-100" style="font-size:12px;">
            <thead class="table-light">
                <tr>
                    <th style="width:10%">Item</th>
                    <th style="width:14%">Description</th>
                    <th style="width:16%">Date</th>
                    <th style="width:12%">Item Type</th>
                    <th class="text-end" style="width:8%">In-Stocks</th>
                    <th class="text-end" style="width:10%">Quantity Add</th>
                    <th class="text-end" style="width:10%">End Balance</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory_tx_data)): ?>
                    <tr><td colspan="8" class="text-center py-3 text-muted">No records found.</td></tr>
                <?php else:
                    $lastItem = null;
                    foreach ($inventory_tx_data as $tx):
                        $isNewItem = $lastItem !== $tx['item'];
                        $qty           = intval($tx['quantity']);
                        $end_bal       = intval($tx['balance_after']);
                        $tx_type_lower = strtolower($tx['transaction_type']);
                        $inbound_types = ['acquisition', 'approved', 'in'];
                        $instocks_before = in_array($tx_type_lower, $inbound_types, true)
                            ? $end_bal - $qty
                            : $end_bal + $qty;

                        $remarks_text = $tx['remarks'] ?? $tx['transaction_type'];
                        $key = ($tx['item_id'] ?? '') . '||' . ($tx['description'] ?? '');
                        if (isset($inventory_tx_beginning_balances[$key]) && $inventory_tx_beginning_balances[$key]['first_tx_date'] === $tx['transaction_date']) {
                            $begin_date_label = date('M d, Y', strtotime($start_date));
                            $remarks_text = 'Beginning balance as of ' . $begin_date_label . ' are ' . $instocks_before . ' in-stocks';
                        }

                        // End Balance colour: orange if low (< 15), red if 0
                        $bal_style = '';
                        if ($end_bal == 0)      $bal_style = 'color:#dc3545;font-weight:bold;';
                        elseif ($end_bal < 15)  $bal_style = 'color:#fd7e14;font-weight:bold;';
                ?>
                <tr>
                    <td style="font-weight:bold; color:#1a237e;">
                        <?php echo $isNewItem ? htmlspecialchars($tx['item']) : ''; ?>
                    </td>
                    <td class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($tx['description']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($tx['transaction_date'])); ?><br>
                        <small class="text-muted"><?php echo date('h:i A', strtotime($tx['transaction_date'])); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($tx['item_type']); ?></td>
                    <td class="text-end"><?php echo $instocks_before; ?></td>
                    <td class="text-end fw-bold text-primary"><?php echo $qty; ?></td>
                    <td class="text-end" style="<?php echo $bal_style; ?>"><?php echo $end_bal; ?></td>
                    <td style="font-size:11px;"><?php echo htmlspecialchars($remarks_text); ?></td>
                </tr>
                <?php
                    $lastItem = $tx['item'];
                    endforeach;
                endif; ?>
            </tbody>
        </table>

        <!-- SC Content -->
        <?php elseif ($report_type === 'sc'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td colspan="2" class="text-end border-0 p-1" style="border: none !important; font-size: 11px;">GAM Appendix 58</td>
            </tr>
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Stock Card</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'SC-FMD-FM075'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> <?php echo htmlspecialchars($entity_name); ?></td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> <?php echo htmlspecialchars($fund_cluster); ?></td>
            </tr>
            <?php
                $one = reset($selected_items);
                $sc_item = $one ?: [];
            ?>
            <tr>
                <td class="p-2"><strong>Item :</strong> <?php echo htmlspecialchars($sc_item['item'] ?? ''); ?></td>
                <td class="p-2"><strong>Stock No. :</strong> <?php echo htmlspecialchars($sc_item['stock_no'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="p-2"><strong>Description :</strong> <?php echo htmlspecialchars($sc_item['description'] ?? ''); ?></td>
                <td class="p-2"><strong>Re-order Point :</strong> <?php echo htmlspecialchars($reorder_point); ?></td>
            </tr>
            <tr>
                <td class="p-2"><strong>Unit of Measurement :</strong> <?php echo htmlspecialchars($sc_item['unit'] ?? ''); ?></td>
                <td class="p-2"></td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 10%;">Date</th>
                    <th rowspan="2" style="width: 12%;">Reference</th>
                    <th colspan="1" class="text-center" style="width: 10%;">Receipt</th>
                    <th colspan="2" class="text-center" style="width: 22%;">Issue</th>
                    <th colspan="1" class="text-center" style="width: 10%;">Balance</th>
                    <th rowspan="2" class="text-center" style="width: 16%;">No. of Days to Consume</th>
                </tr>
                <tr>
                    <th class="text-center">Qty.</th>
                    <th class="text-center">Qty.</th>
                    <th class="text-center">Office</th>
                    <th class="text-center">Qty.</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $min_rows = 20;
                $rows = max($min_rows, count($sc_transactions));
                $inbound_needles = ['acquisition', 'receipt', 'stock in', 'add', 'in'];
                $outbound_needles = ['issue', 'issued', 'stock out', 'deduct', 'out', 'transfer'];

                for ($i = 0; $i < $rows; $i++):
                    $tx = $sc_transactions[$i] ?? null;
                    $txTypeLower = $tx ? strtolower(trim((string)($tx['transaction_type'] ?? ''))) : '';
                    $qty = $tx ? (int)($tx['quantity'] ?? 0) : 0;
                    $balance = $tx ? (int)($tx['balance_after'] ?? 0) : '';
                    $remarks = $tx ? trim((string)($tx['remarks'] ?? '')) : '';
                    if ($tx && $remarks === '') {
                        $remarks = (string)($tx['transaction_type'] ?? '');
                    }

                    // Reference: show RIS number only (no names/offices)
                    $reference_display = $remarks;
                    if ($remarks !== '' && preg_match('/\bRIS-[A-Za-z0-9-]+\b/', $remarks, $m)) {
                        $reference_display = $m[0];
                    }
                    $office_display = '';

                    $isInbound = false;
                    $isOutbound = false;
                    if ($tx) {
                        foreach ($inbound_needles as $needle) {
                            if ($txTypeLower === $needle || strpos($txTypeLower, $needle) !== false) {
                                $isInbound = true;
                                break;
                            }
                        }
                        foreach ($outbound_needles as $needle) {
                            if ($txTypeLower === $needle || strpos($txTypeLower, $needle) !== false) {
                                $isOutbound = true;
                                break;
                            }
                        }

                        // If type isn't recognized but a RIS reference exists, treat as stock-out
                        if (!$isInbound && !$isOutbound && stripos($remarks, 'RIS') !== false) {
                            $isOutbound = true;
                        }
                    }
                ?>
                <tr>
                    <td><?php echo $tx ? date('m/d/Y', strtotime($tx['transaction_date'])) : ''; ?></td>
                    <td><?php echo $tx ? htmlspecialchars($reference_display) : ''; ?></td>
                    <td class="text-center"><?php echo $isInbound ? $qty : ''; ?></td>
                    <td class="text-center"><?php echo $isOutbound ? ('-' . $qty) : ''; ?></td>
                    <td><?php echo $isOutbound ? htmlspecialchars($office_display) : ''; ?></td>
                    <td class="text-center"><?php echo $tx ? $balance : ''; ?></td>
                    <td class="text-center">
                        <input type="text" value="" style="width:100%; border:0; outline:none; background:transparent; text-align:center;">
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="mt-3">
            <table class="table table-bordered w-100 border-dark">
                <tr>
                    <td class="p-2" style="width: 50%;">
                        <div class="mb-1">Inspected by: <span class="text-decoration-underline"><?php echo htmlspecialchars($inspected_by); ?></span></div>
                        <div class="mb-1">Date: <span class="text-decoration-underline"><?php echo htmlspecialchars($inspected_date); ?></span></div>
                    </td>
                    <td class="p-2" style="width: 50%;">
                        <div class="mb-1">Verified by: <span class="text-decoration-underline"><?php echo htmlspecialchars($verified_by); ?></span></div>
                        <div class="mb-1">Date: <span class="text-decoration-underline"><?php echo htmlspecialchars($verified_date); ?></span></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- SLC Content -->
        <?php elseif ($report_type === 'slc'): ?>
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td colspan="2" class="text-end border-0 p-1" style="border: none !important; font-size: 11px;">GAM Appendix 57</td>
            </tr>
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Supplies Ledger Card</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'SLC-FMD-FM074'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> <?php echo htmlspecialchars($entity_name); ?></td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> <?php echo htmlspecialchars($fund_cluster); ?></td>
            </tr>
            <?php $one = reset($selected_items); $slc = $one ?: []; ?>
            <tr>
                <td class="p-2"><strong>Item :</strong> <?php echo htmlspecialchars($slc['item'] ?? ''); ?></td>
                <td class="p-2"><strong>Item Code :</strong> <?php echo htmlspecialchars($slc['stock_no'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="p-2"><strong>Description :</strong> <?php echo htmlspecialchars($slc['description'] ?? ''); ?></td>
                <td class="p-2"><strong>Re-order Point :</strong> <?php echo htmlspecialchars($reorder_point); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="p-2"><strong>Unit of Measurement :</strong> <?php echo htmlspecialchars($slc['unit'] ?? ''); ?></td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 8%;">Date</th>
                    <th rowspan="2" style="width: 10%;">Reference</th>
                    <th colspan="3" class="text-center" style="width: 21%;">Receipt</th>
                    <th colspan="3" class="text-center" style="width: 21%;">Issue</th>
                    <th colspan="3" class="text-center" style="width: 21%;">Balance</th>
                    <th rowspan="2" style="width: 8%;">Days to Consume</th>
                </tr>
                <tr>
                    <th class="text-center">Qty.</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Cost</th>
                    <th class="text-center">Qty.</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Cost</th>
                    <th class="text-center">Qty.</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $min_rows = 18;
                $rows = max($min_rows, count($sc_transactions));
                $inbound_needles = ['acquisition', 'receipt', 'stock in', 'add', 'in', 'approved'];
                $outbound_needles = ['issue', 'issued', 'stock out', 'deduct', 'out', 'transfer'];
                $unit_value = (float)($slc['unit_value'] ?? 0);
                $last_issue_date = null;

                for ($i = 0; $i < $rows; $i++):
                    $tx = $sc_transactions[$i] ?? null;
                    $date_display = $tx ? date('m/d/Y', strtotime((string)$tx['transaction_date'])) : '';
                    $reference_display = $tx ? (string)($tx['remarks'] ?? $tx['transaction_type'] ?? '') : '';
                    $tx_text = $tx ? strtolower(trim((string)($tx['transaction_type'] ?? '') . ' ' . (string)($tx['remarks'] ?? ''))) : '';
                    $qty = $tx ? (float)($tx['quantity'] ?? 0) : 0.0;
                    $balance = $tx ? (float)($tx['balance_after'] ?? 0) : null;

                    $isInbound = false;
                    foreach ($inbound_needles as $needle) {
                        if ($tx_text !== '' && strpos($tx_text, $needle) !== false) { $isInbound = true; break; }
                    }
                    $isOutbound = false;
                    foreach ($outbound_needles as $needle) {
                        if ($tx_text !== '' && strpos($tx_text, $needle) !== false) { $isOutbound = true; break; }
                    }

                    $receipt_qty = $isInbound ? $qty : null;
                    $issue_qty = $isOutbound ? $qty : null;
                    $receipt_total = $isInbound ? $qty * $unit_value : null;
                    $issue_total = $isOutbound ? $qty * $unit_value : null;

                    $days_consume = '';
                    if ($isOutbound && $tx) {
                        $current_date = date('Y-m-d', strtotime((string)$tx['transaction_date']));
                        if ($last_issue_date) {
                            $days_consume = (string)max(0, (int)((strtotime($current_date) - strtotime($last_issue_date)) / 86400));
                        }
                        $last_issue_date = $current_date;
                    }
                ?>
                <tr>
                    <td class="text-center"><?php echo htmlspecialchars($date_display); ?></td>
                    <td><?php echo htmlspecialchars($reference_display); ?></td>
                    <td class="text-center"><?php echo $receipt_qty !== null ? rtrim(rtrim(number_format($receipt_qty, 2, '.', ''), '0'), '.') : ''; ?></td>
                    <td class="text-end"><?php echo $receipt_qty !== null ? number_format($unit_value, 2) : ''; ?></td>
                    <td class="text-end"><?php echo $receipt_total !== null ? number_format($receipt_total, 2) : ''; ?></td>
                    <td class="text-center"><?php echo $issue_qty !== null ? rtrim(rtrim(number_format($issue_qty, 2, '.', ''), '0'), '.') : ''; ?></td>
                    <td class="text-end"><?php echo $issue_qty !== null ? number_format($unit_value, 2) : ''; ?></td>
                    <td class="text-end"><?php echo $issue_total !== null ? number_format($issue_total, 2) : ''; ?></td>
                    <td class="text-center"><?php echo $tx ? rtrim(rtrim(number_format((float)$balance, 2, '.', ''), '0'), '.') : ''; ?></td>
                    <td class="text-end"><?php echo $tx ? number_format($unit_value, 2) : ''; ?></td>
                    <td class="text-end"><?php echo $tx ? number_format(((float)$balance) * $unit_value, 2) : ''; ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($days_consume); ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- RPCPPE Content -->
        <?php elseif ($report_type === 'rpcppe'): ?>
        <table class="table table-bordered w-100 mb-0 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/logo no bg.png" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h4 class="fw-bold mb-0 text-uppercase">Report on the Physical Count of Property, Plant and Equipment</h4>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'RPCPPE-FMD-FM091'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="text-center fw-bold mt-2"><?php echo htmlspecialchars($inventory_type); ?></div>
        <div class="text-center small fst-italic mb-1">(Type of Property, Plant and Equipment)</div>
        <div class="text-center mb-2" style="text-decoration: underline;">As at <?php echo date('F d, Y', strtotime($as_of_date)); ?></div>

        <div class="mb-1"><strong>Fund Cluster :</strong> <span style="text-decoration: underline;"><?php echo htmlspecialchars($fund_cluster); ?></span></div>
        <div class="mb-3">
            For which <span style="text-decoration: underline;"><?php echo htmlspecialchars($accountable_officer); ?></span> , <span style="text-decoration: underline;"><?php echo htmlspecialchars($position_title); ?></span> , <span style="text-decoration: underline;">COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</span> is accountable, having assumed such accountability on <span style="text-decoration: underline;"><?php echo date('F d, Y', strtotime($accountability_date)); ?></span> .
        </div>

        <table id="rpcppeTable" class="table table-bordered table-sm w-100 border-dark">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 10%;">ARTICLE</th>
                    <th rowspan="2" style="width: 25%;">DESCRIPTION</th>
                    <th rowspan="2">PROPERTY NUMBER</th>
                    <th rowspan="2">UNIT OF MEASURE</th>
                    <th rowspan="2">UNIT VALUE</th>
                    <th rowspan="1">QUANTITY per<br>PROPERTY CARD</th>
                    <th rowspan="1">QUANTITY per<br>PHYSICAL COUNT</th>
                    <th colspan="2">SHORTAGE/OVERAGE</th>
                    <th rowspan="2">REMARKS</th>
                </tr>
                <tr>
                    <th>(Quantity)</th>
                    <th>(Quantity)</th>
                    <th>Quantity</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_balance_qty = 0;
                $total_physical_qty = 0;

                if (empty($selected_items)) {
                    echo "<tr><td colspan='10' class='text-center py-3'>No items selected.</td></tr>";
                } else {
                    foreach ($selected_items as $id => $d) {
                        $balance_qty = floatval($d['balance_qty']);
                        $physical_qty = floatval($d['physical_count']);
                        $unit_value = floatval($d['unit_value']);

                        $diff_qty = $physical_qty - $balance_qty;
                        $diff_val = $diff_qty * $unit_value;

                        $total_balance_qty += $balance_qty;
                        $total_physical_qty += $physical_qty;

                        $shortage_overage_qty = ($diff_qty !== 0.0) ? $diff_qty : '';
                        $shortage_overage_val = ($diff_qty !== 0.0) ? number_format($diff_val, 2) : '';

                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($d['item']); ?></td>
                            <td><?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['unit']); ?></td>
                            <td class="text-end"><?php echo number_format($unit_value, 2); ?></td>

                            <td class="text-center"><?php echo $balance_qty; ?></td>
                            <td class="text-center fw-bold"><?php echo $physical_qty; ?></td>

                            <td class="text-center"><?php echo $shortage_overage_qty; ?></td>
                            <td class="text-end"><?php echo $shortage_overage_val; ?></td>

                            <td><?php echo htmlspecialchars($d['remarks'] ?? ''); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <div class="mt-0">
            <table class="table table-bordered w-100 border-dark mb-0">
                <tr>
                    <td class="p-2" style="width: 33%;">
                        <div class="fw-bold mb-5">Certified Correct by:</div>
                        <div class="border-top mb-1" style="margin-top: 40px;"></div>
                        <div class="small text-center">Signature over Printed Name of<br>Inventory Committee Chair and<br>Members</div>
                    </td>
                    <td class="p-2" style="width: 34%;">
                        <div class="fw-bold mb-5">Approved by:</div>
                        <div class="border-top mb-1" style="margin-top: 40px;"></div>
                        <div class="small text-center">Signature over Printed Name of Head of<br>Agency/Entity or Authorized<br>Representative</div>
                    </td>
                    <td class="p-2" style="width: 33%;">
                        <div class="fw-bold mb-5">Verified by:</div>
                        <div class="border-top mb-1" style="margin-top: 40px;"></div>
                        <div class="small text-center">Signature over Printed Name of COA<br>Representative</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- RSMI Content -->
        <?php elseif ($report_type === 'rsmi'): ?>
        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td width="20%" class="text-center align-middle p-2">
                    <img src="../assets/img/473022962_535116549689015_1606086345882705132_n.jpg" alt="CPD Logo" style="max-height: 70px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h5 class="fw-bold mb-0 text-uppercase">Report of Supplies and Materials Issued</h5>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. <?php echo htmlspecialchars($form_no ?: 'RSMI-FMD-FM081'); ?></div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. <?php echo htmlspecialchars($version_no ?: '06'); ?></div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: <?php echo htmlspecialchars($effectivity_date ?: 'October 15, 2024'); ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2">
                    <div><strong>Entity Name:</strong> <?php echo htmlspecialchars($entity_name); ?></div>
                    <div><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($fund_cluster); ?></div>
                </td>
                <td class="p-2" style="width: 40%;">
                    <div class="d-flex justify-content-between">
                        <div><strong>Serial No.:</strong> <?php echo htmlspecialchars($serial_no); ?></div>
                        <div><strong>Date:</strong> <?php echo date('F d, Y', strtotime($report_date)); ?></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">To be filled up by the Supply and/or Property Division/Unit</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">To be filled up by the Accounting Division/Unit</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered table-sm w-100">
            <thead>
                <tr>
                    <th>RIS No.</th>
                    <th>Responsibility Center Code</th>
                    <th>Stock No.</th>
                    <th>Item</th>
                    <th>Unit</th>
                    <th class="text-center">Quantity Issued</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $total_qty = 0;
                    $total_amt = 0.0;
                    $recap_stock = [];
                    $recap_unitcost = [];
                ?>
                <?php if(empty($data)): ?>
                    <tr><td colspan="8" class="text-center py-3">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach($data as $row): ?>
                    <tr>
                        <td class="text-center"><?php echo $row['ris_no']; ?></td>
                        <td></td> <!-- Responsibility Center Code placeholder -->
                        <td><?php echo htmlspecialchars($row['stock_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['item']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['unit_measurement']); ?></td>
                        <td class="text-center"><?php echo $row['quantity_issued']; ?></td>
                        <td class="text-end"><?php echo number_format($row['unit_value'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
                    </tr>
                    <?php
                        $qty = intval($row['quantity_issued']);
                        $amt = floatval($row['amount']);
                        $total_qty += $qty;
                        $total_amt += $amt;

                        $sn = $row['stock_no'];
                        $uc = number_format(floatval($row['unit_value']), 2, '.', '');

                        if (!isset($recap_stock[$sn])) $recap_stock[$sn] = 0;
                        $recap_stock[$sn] += $qty;

                        if (!isset($recap_unitcost[$uc])) $recap_unitcost[$uc] = 0.0;
                        $recap_unitcost[$uc] += $amt;
                    ?>
                    <?php endforeach; ?>
                <?php
                    // Fill empty rows to maintain form height
                    // Reduced min_rows from 25 to 15 to prevent unnecessary page breaks
                    $min_rows = 15;
                    $current_rows = count($data);
                    if ($current_rows < $min_rows) {
                        for ($i = 0; $i < ($min_rows - $current_rows); $i++) {
                            echo "<tr>";
                            echo "<td>&nbsp;</td>"; // RIS No
                            echo "<td></td>";       // Resp Center
                            echo "<td></td>";       // Stock No
                            echo "<td></td>";       // Item
                            echo "<td></td>";       // Unit
                            echo "<td></td>";       // Qty
                            echo "<td></td>";       // Cost
                            echo "<td></td>";       // Amount
                            echo "</tr>";
                        }
                    }
                ?>
            <?php endif; ?>
            <tr>
                <td colspan="5" class="text-end fw-bold">TOTAL</td>
                <td class="text-center fw-bold"><?php echo number_format($total_qty); ?></td>
                <td></td>
                <td class="text-end fw-bold"><?php echo number_format($total_amt, 2); ?></td>
            </tr>
            <tr>
                <td colspan="8" class="p-0">
                    <table class="table table-bordered w-100 mb-0 border-0">
                        <tr>
                            <td class="align-top p-2 border-0 border-end" style="width: 50%;">
                                <div class="fw-bold mb-2">Recapitulation:</div>
                                <table class="table table-bordered mb-0 w-100">
                                    <thead>
                                        <tr>
                                            <th>Stock No.</th>
                                            <th class="text-center">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($recap_stock)): foreach($recap_stock as $sn => $qty): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sn); ?></td>
                                                <td class="text-center"><?php echo number_format($qty); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        <tr class="fw-bold">
                                            <td>TOTAL</td>
                                            <td class="text-center"><?php echo number_format($total_qty); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                            <td class="align-top p-2 border-0" style="width: 50%;">
                                <div class="fw-bold mb-2">Recapitulation:</div>
                                <table class="table table-bordered mb-0 w-100">
                                    <thead>
                                        <tr>
                                            <th>Unit Cost</th>
                                            <th class="text-end">Total Cost</th>
                                            <th>UACS Object Code</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($recap_unitcost)): foreach($recap_unitcost as $uc => $amt): ?>
                                            <tr>
                                                <td class="text-end"><?php echo number_format($uc, 2); ?></td>
                                                <td class="text-end"><?php echo number_format($amt, 2); ?></td>
                                                <td></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        <tr class="fw-bold">
                                            <td></td>
                                            <td class="text-end"><?php echo number_format($total_amt, 2); ?></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="mt-3">
        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3">
                    <div class="mb-3"><?php echo nl2br(htmlspecialchars($rsmi_certification_text)); ?></div>
                    <div class="row">
                        <div class="col-6 text-center">
                            <div class="small mb-1" style="min-height: 20px;"><?php echo htmlspecialchars($rsmi_supply_custodian_label); ?></div>
                            <div class="border-top mb-1"></div>
                            <div class="small text-muted">Supply and/or Property Custodian</div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="small mb-1" style="min-height: 20px;"><?php echo htmlspecialchars($rsmi_accounting_staff_label); ?></div>
                            <div class="border-top mb-1"></div>
                            <div class="small text-muted">Designated Accounting Staff</div>
                            <div class="small mt-2">Date</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    </div>
</body>
</html>
