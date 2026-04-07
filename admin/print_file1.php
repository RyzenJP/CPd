<?php
session_start();
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')){
    header("Location: ../login.php");
    exit();
}

$report_type = $_POST['report_type'] ?? '';

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
    $inventory_type = $_POST['inventory_type'] ?? 'Inventory Item';
    $as_of_date = $_POST['as_of_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $items = $_POST['items'] ?? [];
    
    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $position_title = $_POST['position_title'] ?? '';
    $accountability_date = $_POST['accountability_date'] ?? '';
    $num_pages = $_POST['num_pages'] ?? 'Two (2)';
    
    if ($accountable_officer === '') { $accountable_officer = 'LORILYN M. MONTILIJAO'; }
    if ($position_title === '') { $position_title = 'Acting Administrative Officer I'; }
    if ($accountability_date === '') { $accountability_date = '2013-06-17'; }
    
    $rpci_certified_officer_1 = trim($_POST['rpci_certified_officer_1'] ?? 'MIMIA C. GUMBAN');
    $rpci_certified_officer_2 = trim($_POST['rpci_certified_officer_2'] ?? 'MARIE JEANNE A. JAGONIO');
    $rpci_certified_officer_3 = trim($_POST['rpci_certified_officer_3'] ?? 'RACHELLE E. LABORDO');
    $rpci_approved_officer = trim($_POST['rpci_approved_officer'] ?? 'HAROLD ALFRED P. MARSHALL');
    $rpci_verified_officer = trim($_POST['rpci_verified_officer'] ?? 'MS. SIMONETTE D. CATALUÑA');
    
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
                    r.request_date,
                    r.ris_no as ris_no,
                    i.stock_no,
                    i.item,
                    i.unit_measurement,
                    ri.quantity_requested as quantity_issued,
                    i.unit_value,
                    (ri.quantity_requested * i.unit_value) as amount
                FROM request_items ri
                JOIN requests r ON ri.request_id = r.id
                JOIN items i ON ri.item_id = i.id
                WHERE r.status IN ('Issued', 'Approved')
                AND r.request_date BETWEEN '$start_date' AND '$end_date'
                ORDER BY r.request_date ASC, r.id ASC";

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
    $ics_approved_name = $_POST['ics_approved_name'] ?? '';
    $ics_approved_position = $_POST['ics_approved_position'] ?? '';
    $ics_received_from_name = $_POST['ics_received_from_name'] ?? '';
    $ics_received_from_position = $_POST['ics_received_from_position'] ?? '';
    $ics_received_by_name = $_POST['ics_received_by_name'] ?? '';
    $ics_received_by_position = $_POST['ics_received_by_position'] ?? '';
    $ics_reason = $_POST['ics_reason'] ?? '';
    
    $items = $_POST['items'] ?? [];
    $selected_items = array_filter($items, function($item) {
        return isset($item['selected']);
    });
} elseif ($report_type === 'iirup' || $report_type === 'par' || $report_type === 'pc' || $report_type === 'ppelc' || $report_type === 'ptr' || $report_type === 'rlsddp' || $report_type === 'sc' || $report_type === 'slc') {
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
elseif ($report_type === 'ics') $page_title = "Inventory Custodian Slip";
elseif ($report_type === 'iirup') $page_title = "Inventory Inspection Report of Unserviceable Property";
elseif ($report_type === 'par') $page_title = "Property Acknowledgment Receipt";
elseif ($report_type === 'pc') $page_title = "Property Card";
elseif ($report_type === 'ppelc') $page_title = "PPE Ledger Card";
elseif ($report_type === 'ptr') $page_title = "Property Transfer Report";
elseif ($report_type === 'rlsddp') $page_title = "Record of Lost/Stolen/Damaged/Destroyed Property";
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
                    <input type="hidden" name="rpci_certified_officer_1" value="<?php echo htmlspecialchars($rpci_certified_officer_1); ?>">
                    <input type="hidden" name="rpci_certified_officer_2" value="<?php echo htmlspecialchars($rpci_certified_officer_2); ?>">
                    <input type="hidden" name="rpci_certified_officer_3" value="<?php echo htmlspecialchars($rpci_certified_officer_3); ?>">
                    <input type="hidden" name="rpci_approved_officer" value="<?php echo htmlspecialchars($rpci_approved_officer); ?>">
                    <input type="hidden" name="rpci_verified_officer" value="<?php echo htmlspecialchars($rpci_verified_officer); ?>">
                    <input type="hidden" name="rpci_certified_title_officer_v" value="<?php echo htmlspecialchars($rpci_certified_title_officer_v); ?>">
                    <input type="hidden" name="rpci_certified_title_acting_officer_i" value="<?php echo htmlspecialchars($rpci_certified_title_acting_officer_i); ?>">
                    <input type="hidden" name="rpci_certified_title_accountant_ii" value="<?php echo htmlspecialchars($rpci_certified_title_accountant_ii); ?>">
                    <input type="hidden" name="rpci_approved_position_title" value="<?php echo htmlspecialchars($rpci_approved_position_title); ?>">
                    <input type="hidden" name="rpci_verified_position_title" value="<?php echo htmlspecialchars($rpci_verified_position_title); ?>">
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
                    <input type="hidden" name="pages_count_str" value="<?php echo htmlspecialchars($pages_count_str); ?>">
                    <input type="hidden" name="rpcppe_certified_correct_by_label" value="<?php echo htmlspecialchars($rpcppe_certified_correct_by_label); ?>">
                    <input type="hidden" name="rpcppe_approved_by_label" value="<?php echo htmlspecialchars($rpcppe_approved_by_label); ?>">
                    <input type="hidden" name="rpcppe_verified_by_label" value="<?php echo htmlspecialchars($rpcppe_verified_by_label); ?>">
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
        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td width="25%" class="text-center align-middle p-2">
                    <img src="../assets/img/logo no bg.png" alt="CPD Logo" style="max-height: 80px;">
                </td>
                <td class="text-center align-middle p-2">
                    <h4 class="fw-bold mb-0 text-uppercase">Report on the Physical Count of Inventories</h4>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-0">
                    <div class="d-flex w-100">
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. RPCI-FMD-FM083</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">GAM-Appendix 66</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: July 10, 2023</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="text-center p-2">
                    <div class="fw-bold">Office Supplies Inventory</div>
                    <div class="small fst-italic">(Type of <?php echo htmlspecialchars($inventory_type); ?>)</div>
                </td>
            </tr>
            <tr>
                <td class="text-center p-2">As at <?php echo date('F d, Y', strtotime($as_of_date)); ?></td>
            </tr>
        </table>
        <p class="mb-1"><strong>Fund Cluster :</strong> <?php echo htmlspecialchars($fund_cluster); ?></p>
        <p class="mb-3">
            For which <?php echo htmlspecialchars($accountable_officer); ?>, <?php echo htmlspecialchars($position_title); ?> , COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI  is accountable, having assumed such accountability on <?php echo date('F d, Y', strtotime($accountability_date)); ?>.
        </p>

        <table id="rpciTable" class="table table-bordered table-sm w-100">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 10%;">Article</th>
                    <th rowspan="2" style="width: 20%;">Description</th>
                    <th rowspan="2">Stock<br>Number</th>
                    <th rowspan="2">Unit of<br>Measure</th>
                    <th rowspan="2">Unit<br>Value</th>
                    <th rowspan="1">Balance Per Card</th>
                    <th rowspan="2">Value</th>
                    <th rowspan="1">On Hand Per Count</th>
                    <th colspan="2">Shortage/Overage</th>
                    <th rowspan="2">Remarks</th>
                </tr>
                <tr>
                    <th>(Quantity)</th>
                    <!-- Value rowspan=2 -->
                    <th>(Quantity)</th>
                    <th>Quantity</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_balance_qty = 0;
                $total_balance_val = 0;
                $total_physical_qty = 0;
                $total_diff_val = 0;

                if (empty($selected_items)) {
                    echo "<tr><td colspan='11' class='text-center py-3'>No items selected.</td></tr>";
                } else {
                    echo "<tr><td class='fw-bold' colspan='11'>OFFICE SUPPLY INVENTORY</td></tr>";
                    foreach ($selected_items as $id => $d) {
                        $balance_qty = floatval($d['balance_qty']);
                        $physical_qty = floatval($d['physical_count']);
                        $unit_value = floatval($d['unit_value']);
                        
                        $balance_val = $balance_qty * $unit_value;
                        
                        $diff_qty = $physical_qty - $balance_qty;
                        $diff_val = $diff_qty * $unit_value;

                        $total_balance_qty += $balance_qty;
                        $total_balance_val += $balance_val;
                        $total_physical_qty += $physical_qty;
                        $total_diff_val += $diff_val;

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
                            <td class="text-end"><?php echo number_format($balance_val, 2); ?></td>
                            
                            <td class="text-center fw-bold"><?php echo $physical_qty; ?></td>
                            
                            <td class="text-center"><?php echo $shortage_overage_qty; ?></td>
                            <td class="text-end"><?php echo $shortage_overage_val; ?></td>
                            
                            <td><?php echo htmlspecialchars($d['remarks'] ?? ''); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
                <tr class="fw-bold bg-light">
                    <td colspan="5">TOTAL OFFICE SUPPLIES INVENTORY</td>
                    <td class="text-center"><?php echo number_format($total_balance_qty); ?></td>
                    <td class="text-end"><?php echo number_format($total_balance_val, 2); ?></td>
                    <td class="text-center"><?php echo number_format($total_physical_qty); ?></td>
                    <td class="text-center"></td>
                    <td class="text-end"></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4">
            <table class="table table-bordered w-100 border-dark">
                <tr>
                    <td class="text-center fw-bold">CERTIFICATION</td>
                </tr>
                <tr>
                    <td class="p-3">
                        <p class="mb-2 text-center">WE, THE UNDERSIGNED, DO HEREBY CERTIFY THAT THE INVENTORY OF THE COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI AT PAVIA, ILOILO - REPORT ON PHYSICAL COUNT OF OFFICE SUPPLIES INVENTORY contained in the form prescribed by the New Government Accounting System is true and correct per verification from Stock Cards and other documents, consisting of <?php echo htmlspecialchars($num_pages); ?> pages including this page as of <?php echo date('F d, Y', strtotime($as_of_date)); ?></p>
                        <p class="mb-3 text-center">It is further certified that the actual physical count was made by the Chairman and all members of the Property Inventory Committee and witnessed by a Representative of the Commission on Audit.</p>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="fw-bold mb-2">Certified Correct by:</div>
                            </div>
                        </div>
                        <div class="row text-center mb-4">
                            <div class="col-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($rpci_certified_officer_1); ?></div>
                                <div class="small"><?php echo htmlspecialchars($rpci_certified_title_officer_v); ?></div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($rpci_certified_officer_2); ?></div>
                                <div class="small"><?php echo htmlspecialchars($rpci_certified_title_acting_officer_i); ?></div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($rpci_certified_officer_3); ?></div>
                                <div class="small"><?php echo htmlspecialchars($rpci_certified_title_accountant_ii); ?></div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-6">
                                <div class="fw-bold mb-2">Approved by:</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($rpci_approved_officer); ?></div>
                                <div class="small"><?php echo htmlspecialchars($rpci_approved_position_title); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold mb-2">Verified by:</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($rpci_verified_officer); ?></div>
                                <div class="small"><?php echo htmlspecialchars($rpci_verified_position_title); ?></div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- RIS Content -->
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. RIS-FMD-FM080</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. ICS-FMD-FM081</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <div><strong>Entity Name:</strong> COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</div>
                    <div><strong>Fund Cluster:</strong> ____________________________</div>
                </td>
                <td class="p-2" style="width: 40%;">
                    <div><strong>ICS No.:</strong> ____________________________</div>
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
                        ________________________________________________<br>
                        ________________________________________________<br>
                        ________________________________________________<br>
                        ________________________________________________
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
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3 align-middle" style="width: 50%;">
                    <div class="mb-3"><strong>Received by:</strong></div>
                    <div class="text-center" style="margin-top: 30px;">
                        <div style="border-top: 1px solid #000; width: 70%; margin: 0 auto 2px auto;"></div>
                        <div>Signature Over Printed Name</div>
                        <div style="border-top: 1px solid #000; width: 70%; margin: 10px auto 2px auto;"></div>
                        <div>Position/Office</div>
                        <div style="border-top: 1px solid #000; width: 70%; margin: 10px auto 2px auto;"></div>
                        <div>Date</div>
                    </div>
                </td>
                <td class="p-3 align-middle" style="width: 50%;">
                    <div class="mb-3"><strong>Issued by:</strong></div>
                    <div class="text-center" style="margin-top: 30px;">
                        <div style="border-top: 1px solid #000; width: 70%; margin: 0 auto 2px auto;"></div>
                        <div>Signature Over Printed Name</div>
                        <div style="border-top: 1px solid #000; width: 70%; margin: 10px auto 2px auto;"></div>
                        <div>Position/Office</div>
                        <div style="border-top: 1px solid #000; width: 70%; margin: 10px auto 2px auto;"></div>
                        <div>Date</div>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. IIRUP-FMD-FM0XX</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
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

        <script>
        // IIRUP Auto-Calculation Functions
        function calculateIirupCarryingAmount(itemId) {
            const totalCostInput = document.querySelector(`.iirup-total-cost[data-id="${itemId}"]`);
            const depreciationInput = document.querySelector(`.iirup-accumulated-depreciation[data-id="${itemId}"]`);
            const impairmentInput = document.querySelector(`.iirup-accumulated-impairment[data-id="${itemId}"]`);
            const carryingAmountInput = document.querySelector(`.iirup-carrying-amount[data-id="${itemId}"]`);
            
            if (totalCostInput && depreciationInput && impairmentInput && carryingAmountInput) {
                const totalCost = parseFloat(totalCostInput.value) || 0;
                const depreciation = parseFloat(depreciationInput.value) || 0;
                const impairment = parseFloat(impairmentInput.value) || 0;
                const carryingAmount = totalCost - depreciation - impairment;
                carryingAmountInput.value = isNaN(carryingAmount) ? '0.00' : carryingAmount.toFixed(2);
            }
        }

        function syncAppraisedValueToAmount(itemId) {
            const appraisedValueInput = document.querySelector(`input[name="iirup_appraised_value_${itemId}"]`);
            const amountInput = document.querySelector(`input[name="iirup_amount_${itemId}"]`);
            
            if (appraisedValueInput && amountInput) {
                amountInput.value = appraisedValueInput.value;
            }
        }

        // Attach event listeners to IIRUP inputs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.iirup-accumulated-depreciation').forEach(input => {
                input.addEventListener('change', function() {
                    calculateIirupCarryingAmount(this.getAttribute('data-id'));
                });
                input.addEventListener('keyup', function() {
                    calculateIirupCarryingAmount(this.getAttribute('data-id'));
                });
            });

            document.querySelectorAll('.iirup-accumulated-impairment').forEach(input => {
                input.addEventListener('change', function() {
                    calculateIirupCarryingAmount(this.getAttribute('data-id'));
                });
                input.addEventListener('keyup', function() {
                    calculateIirupCarryingAmount(this.getAttribute('data-id'));
                });
            });

            document.querySelectorAll('input[name*="iirup_appraised_value_"]').forEach(input => {
                const itemId = input.name.replace('iirup_appraised_value_', '');
                input.addEventListener('change', function() {
                    syncAppraisedValueToAmount(itemId);
                });
                input.addEventListener('keyup', function() {
                    syncAppraisedValueToAmount(itemId);
                });
            });
        });
        </script>

        <table class="table table-bordered w-100 border-dark">
            <tr>
                <td class="p-3 align-top" style="width: 46%;">
                    <p class="mb-5 text-justify" style="text-indent: 40px;">
                        I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
                    </p>
                    <div class="d-flex justify-content-between mt-4">
                        <div class="text-center" style="width: 48%;">
                            <div class="mb-2">Requested by:</div>
                            <div style="border-top: 1px solid #000; width: 100%; margin: 30px auto 2px auto;"></div>
                            <div class="small"><?php echo htmlspecialchars($iirup_requested_by_name); ?></div>
                            <div style="border-top: 1px solid #000; width: 100%; margin: 10px auto 2px auto;"></div>
                            <div class="small"><?php echo htmlspecialchars($iirup_requested_by_designation); ?></div>
                        </div>
                        <div class="text-center" style="width: 48%;">
                            <div class="mb-2">Approved by:</div>
                            <div style="border-top: 1px solid #000; width: 100%; margin: 30px auto 2px auto;"></div>
                            <div class="small"><?php echo htmlspecialchars($iirup_approved_by_name); ?></div>
                            <div style="border-top: 1px solid #000; width: 100%; margin: 10px auto 2px auto;"></div>
                            <div class="small"><?php echo htmlspecialchars($iirup_approved_by_designation); ?></div>
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
                                <div style="border-top: 1px solid #000; width: 90%; margin: 30px auto 2px auto;"></div>
                                <div class="small"><?php echo htmlspecialchars($iirup_inspection_officer_name); ?></div>
                            </div>
                        </div>
                        <div style="width: 48%;">
                            <p class="mb-5 text-justify" style="text-indent: 40px;">
                                I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this ____ day of _____________, _____.
                            </p>
                            <div class="text-center mt-4">
                                <div style="border-top: 1px solid #000; width: 90%; margin: 30px auto 2px auto;"></div>
                                <div class="small"><?php echo htmlspecialchars($iirup_witness_name); ?></div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. PAR-FMD-FM0XX</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> ____________________________
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>PAR No.:</strong> __________________
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
                    <div class="text-center" style="margin-top: 30px;">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto 2px auto;"></div>
                        <div class="small">Signature over Printed Name of End User</div>
                        <div style="border-top: 1px solid #000; width: 80%; margin: 10px auto 2px auto;"></div>
                        <div class="small">Position/Office</div>
                        <div style="border-top: 1px solid #000; width: 80%; margin: 10px auto 2px auto;"></div>
                        <div class="small">Date</div>
                    </div>
                </td>
                <td class="p-3 align-top" style="width: 50%;">
                    <div class="mb-2">Issued by:</div>
                    <div class="text-center" style="margin-top: 30px;">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto 2px auto;"></div>
                        <div class="small">Signature over Printed Name of Supply and/or Property Custodian</div>
                        <div style="border-top: 1px solid #000; width: 80%; margin: 10px auto 2px auto;"></div>
                        <div class="small">Position/Office</div>
                        <div style="border-top: 1px solid #000; width: 80%; margin: 10px auto 2px auto;"></div>
                        <div class="small">Date</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- PC Content (Property Card) -->
        <?php elseif ($report_type === 'pc'): ?>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. PC-FMD-FM0XX</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>Fund Cluster:</strong> __________________
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <strong>Property, Plant and Equipment :</strong>
                </td>
                <td class="p-2">
                    <strong>Property Number:</strong> ________
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Description :</strong>
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
                if (empty($selected_items)) {
                    echo "<tr><td colspan='8' class='text-center py-3'>No item selected.</td></tr>";
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
                            <td class="text-center"></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-center"></td>
                            <td></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                            <td></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. PPELC-FMD-FM0XX</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> ____________________________
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>Fund Cluster :</strong> ______________________
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <strong>Property, Plant and Equipment:</strong>
                </td>
                <td class="p-2">
                    <div>Object Account Code: __________________</div>
                    <div>Estimated Useful Life: __________________</div>
                    <div>Rate of Depreciation: __________________</div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Description:</strong>
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
                            <td class="text-center"></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-end"><?php echo number_format($unit_value, 2); ?></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                            <td class="text-end"></td>
                            <td class="text-end"></td>
                            <td class="text-end"></td>
                            <td class="text-end"><?php echo number_format($amount, 2); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. PTR-FMD-FM0XX</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    <strong>Entity Name :</strong> ____________________________
                </td>
                <td class="p-2" style="width: 40%;">
                    <strong>Fund Cluster :</strong> ____________
                </td>
            </tr>
            <tr>
                <td class="p-2">
                    <div>From Accountable Officer/Agency/Fund Cluster : ____________________________</div>
                    <div>To Accountable Officer/Agency/Fund Cluster : ____________________________</div>
                </td>
                <td class="p-2">
                    <div>PTR No. : ____________</div>
                    <div>Date : ____________</div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="p-2">
                    <strong>Transfer Type: (check only one)</strong>
                    <div class="mt-1">
                        <span class="me-3">&#9633; Donation</span>
                        <span class="me-3">&#9633; Reassignment</span>
                        <span class="me-3">&#9633; Relocate</span>
                        <span class="me-3">&#9633; Others (Specify) ____________</span>
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
                            <td><?php echo htmlspecialchars($d['item']); ?> - <?php echo htmlspecialchars($d['description']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($d['stock_no']); ?></td>
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
                        ________________________________________________<br>
                        ________________________________________________<br>
                        ________________________________________________<br>
                        ________________________________________________
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
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
                </td>
                <td class="p-2">
                    Signature : _______________________________<br>
                    Printed Name : ____________________________<br>
                    Designation : _____________________________<br>
                    Date : _________________________________
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. RLSDDP-FMD-FM082</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> ____________________________</td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> ____________</td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 30%;">Department/Office : __________________</td>
                <td class="p-2" style="width: 30%;">Accountable Officer : ________________</td>
                <td class="p-2" style="width: 40%;">RLSDDP No. : ____________</td>
            </tr>
            <tr>
                <td class="p-2">Position Title : _______________________</td>
                <td class="p-2">Office Address : _______________________</td>
                <td class="p-2">Date : ____________</td>
            </tr>
            <tr>
                <td class="p-2">Tel No. : ____________</td>
                <td class="p-2">Public Station : [  ] Yes &nbsp; [  ] No</td>
                <td class="p-2">PC No. : ____________ &nbsp;&nbsp; PAR No. : ____________</td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2">
                    <strong>Nature of Property: (check applicable box)</strong>
                    <div class="mt-1">
                        [  ] Lost &nbsp;&nbsp; [  ] Stolen &nbsp;&nbsp; [  ] Damaged &nbsp;&nbsp; [  ] Destroyed
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
                    <div class="mt-2" style="line-height: 1.6;">
                        ________________________________________________<br>
                        ________________________________________________<br>
                        ________________________________________________
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
                    Date: ____________________
                </td>
                <td class="p-2">
                    Signature over Printed Name of the Immediate Supervisor<br><br>
                    Date: ____________________
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2" style="width: 50%;">
                    Government Issued ID : ________________<br>
                    ID No. : ________________<br>
                    Date Issued : ________________
                </td>
                <td class="p-2" style="width: 50%;"></td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-2 border-dark">
            <tr>
                <td class="p-2">
                    SUBSCRIBED AND SWORN to before me this _____ day of __________, affiant exhibiting the above government issued identification card.
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-4 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;">
                    Doc No. : __________<br>
                    Page No. : __________<br>
                    Book No. : __________<br>
                    Series of : __________
                </td>
                <td class="p-2 text-center" style="width: 40%;">
                    <div style="border-bottom: 1px solid black; height: 20px; margin-bottom: 6px;"></div>
                    Notary Public
                </td>
            </tr>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. SC-FMD-FM075</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> ____________</td>
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
                <td class="p-2"><strong>Re-order Point :</strong> ____________</td>
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
                $rows = 20;
                for ($i = 0; $i < $rows; $i++):
                ?>
                <tr>
                    <td></td>
                    <td></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="mt-3">
            <table class="table table-bordered w-100 border-dark">
                <tr>
                    <td class="p-2" style="width: 50%;">Inspected by: ____________________________<br> Date: ____________</td>
                    <td class="p-2" style="width: 50%;">Verified by: _____________________________<br> Date: ____________</td>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. SLC-FMD-FM074</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="table table-bordered w-100 mb-3 border-dark">
            <tr>
                <td class="p-2" style="width: 60%;"><strong>Entity Name :</strong> COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</td>
                <td class="p-2" style="width: 40%;"><strong>Fund Cluster :</strong> ____________</td>
            </tr>
            <?php $one = reset($selected_items); $slc = $one ?: []; ?>
            <tr>
                <td class="p-2"><strong>Item :</strong> <?php echo htmlspecialchars($slc['item'] ?? ''); ?></td>
                <td class="p-2"><strong>Item Code :</strong> <?php echo htmlspecialchars($slc['stock_no'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="p-2"><strong>Description :</strong> <?php echo htmlspecialchars($slc['description'] ?? ''); ?></td>
                <td class="p-2"><strong>Re-order Point :</strong> </td>
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
                    // Initialize running balance
                    $runningQty = 0;
                    $runningUnitCost = $slc['unit_value'] ?? 0;
                    
                    for ($i = 0; $i < 18; $i++): 
                        $rowId = "slc_row_" . $i;
                ?>
                <tr>
                    <td class="p-1"><input type="date" class="form-control form-control-sm slc-date" data-row="<?php echo $i; ?>" style="font-size: 11px;"></td>
                    <td class="p-1"><input type="text" class="form-control form-control-sm slc-reference" data-row="<?php echo $i; ?>" style="font-size: 11px;" placeholder="Reference"></td>
                    
                    <!-- RECEIPT -->
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-center slc-receipt-qty" data-row="<?php echo $i; ?>" style="font-size: 11px;" value="0" placeholder="0"></td>
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end slc-receipt-unitcost" data-row="<?php echo $i; ?>" style="font-size: 11px;" value="0" placeholder="0.00"></td>
                    <td class="p-1"><input type="text" class="form-control form-control-sm text-end slc-receipt-total" data-row="<?php echo $i; ?>" style="font-size: 11px;" readonly placeholder="0.00"></td>
                    
                    <!-- ISSUE -->
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-center slc-issue-qty" data-row="<?php echo $i; ?>" style="font-size: 11px;" value="0" placeholder="0"></td>
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end slc-issue-unitcost" data-row="<?php echo $i; ?>" style="font-size: 11px;" value="0" placeholder="0.00"></td>
                    <td class="p-1"><input type="text" class="form-control form-control-sm text-end slc-issue-total" data-row="<?php echo $i; ?>" style="font-size: 11px;" readonly placeholder="0.00"></td>
                    
                    <!-- BALANCE -->
                    <td class="p-1"><input type="text" class="form-control form-control-sm text-center slc-balance-qty" data-row="<?php echo $i; ?>" style="font-size: 11px;" readonly placeholder="0"></td>
                    <td class="p-1"><input type="text" class="form-control form-control-sm text-end slc-balance-unitcost" data-row="<?php echo $i; ?>" style="font-size: 11px;" readonly placeholder="0.00"></td>
                    <td class="p-1"><input type="text" class="form-control form-control-sm text-end slc-balance-total" data-row="<?php echo $i; ?>" style="font-size: 11px;" readonly placeholder="0.00"></td>
                    
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-center slc-days-consume" data-row="<?php echo $i; ?>" style="font-size: 11px;" value="0" placeholder="0"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <script>
            // SLC Automatic Calculations
            function calculateSLCRow(rowIndex) {
                const receiptQty = parseFloat(document.querySelector(`.slc-receipt-qty[data-row="${rowIndex}"]`)?.value) || 0;
                const receiptUnitCost = parseFloat(document.querySelector(`.slc-receipt-unitcost[data-row="${rowIndex}"]`)?.value) || 0;
                const issueQty = parseFloat(document.querySelector(`.slc-issue-qty[data-row="${rowIndex}"]`)?.value) || 0;
                const issueUnitCost = parseFloat(document.querySelector(`.slc-issue-unitcost[data-row="${rowIndex}"]`)?.value) || 0;

                // Calculate Receipt Total Cost
                const receiptTotal = receiptQty * receiptUnitCost;
                document.querySelector(`.slc-receipt-total[data-row="${rowIndex}"]`).value = receiptTotal.toFixed(2);

                // Calculate Issue Total Cost
                const issueTotal = issueQty * issueUnitCost;
                document.querySelector(`.slc-issue-total[data-row="${rowIndex}"]`).value = issueTotal.toFixed(2);

                // Calculate Balance from previous row
                let previousBalanceQty = 0;
                let previousBalanceUnitCost = 0;
                let previousBalanceTotal = 0;

                if (rowIndex > 0) {
                    previousBalanceQty = parseFloat(document.querySelector(`.slc-balance-qty[data-row="${rowIndex - 1}"]`)?.value) || 0;
                    previousBalanceUnitCost = parseFloat(document.querySelector(`.slc-balance-unitcost[data-row="${rowIndex - 1}"]`)?.value) || 0;
                    previousBalanceTotal = parseFloat(document.querySelector(`.slc-balance-total[data-row="${rowIndex - 1}"]`)?.value) || 0;
                } else {
                    // First row uses opening balance (0 or from item balance_qty)
                    previousBalanceQty = 0;
                    previousBalanceUnitCost = <?php echo $runningUnitCost; ?>;
                    previousBalanceTotal = 0;
                }

                // Balance Qty = Previous Balance + Receipt - Issue
                const balanceQty = previousBalanceQty + receiptQty - issueQty;
                document.querySelector(`.slc-balance-qty[data-row="${rowIndex}"]`).value = balanceQty.toFixed(2);

                // Balance Total = Previous + Receipt Total - Issue Total
                const balanceTotal = previousBalanceTotal + receiptTotal - issueTotal;
                document.querySelector(`.slc-balance-total[data-row="${rowIndex}"]`).value = balanceTotal.toFixed(2);

                // Balance Unit Cost = Balance Total / Balance Qty (if qty > 0)
                const balanceUnitCost = balanceQty > 0 ? balanceTotal / balanceQty : previousBalanceUnitCost;
                document.querySelector(`.slc-balance-unitcost[data-row="${rowIndex}"]`).value = balanceUnitCost.toFixed(2);

                // Trigger calculation for next row (if exists)
                if (rowIndex < 17) {
                    calculateSLCRow(rowIndex + 1);
                }
            }

            // Initialize SLC event listeners
            document.addEventListener('DOMContentLoaded', function() {
                for (let i = 0; i < 18; i++) {
                    const inputs = document.querySelectorAll(`.slc-receipt-qty[data-row="${i}"], .slc-receipt-unitcost[data-row="${i}"], .slc-issue-qty[data-row="${i}"], .slc-issue-unitcost[data-row="${i}"]`);
                    inputs.forEach(function(input) {
                        input.addEventListener('change', function() {
                            calculateSLCRow(i);
                        });
                        input.addEventListener('input', function() {
                            calculateSLCRow(i);
                        });
                    });
                }
            });
        </script>

        <!-- RPCPPE Content -->
        <?php elseif ($report_type === 'rpcppe'): ?>
        <table class="table table-bordered w-100 mb-0 border-dark">
            <tr>
                <td colspan="3" class="text-end border-0 p-1" style="border: none !important; font-size: 11px;">GAM Appendix 73</td>
            </tr>
            <tr>
                <td colspan="3" class="text-center align-middle p-2 border-0" style="border-top: 1px solid black !important; border-bottom: 1px solid black !important; border-left: 1px solid black !important; border-right: 1px solid black !important;">
                    <h4 class="fw-bold mb-0 text-uppercase">Report on the Physical Count of Property, Plant and Equipment</h4>
                </td>
            </tr>
            <tr>
                <td class="p-1 text-start" style="width: 33%;">Form No. RPCPPE-FMD-FM091</td>
                <td class="p-1 text-center" style="width: 33%;">Version No. 06</td>
                <td class="p-1 text-end" style="width: 33%;">Effectivity Date: October 15, 2024</td>
            </tr>
        </table>

        <div class="text-center fw-bold mt-2"><?php echo htmlspecialchars($inventory_type); ?></div>
        <div class="text-center small fst-italic mb-2">(Type of Property, Plant and Equipment)</div>
        <div class="text-center mb-2">As at <?php echo date('F d, Y', strtotime($as_of_date)); ?></div>
        
        <div class="mb-1"><strong>Fund Cluster :</strong> <span style="text-decoration: underline;"><?php echo htmlspecialchars($fund_cluster); ?></span></div>
        <div class="mb-3">
            For which <strong><?php echo htmlspecialchars($accountable_officer); ?></strong> , <strong><?php echo htmlspecialchars($position_title); ?></strong> , <strong>COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI</strong> is accountable, having assumed such accountability on <strong><?php echo date('F d, Y', strtotime($accountability_date)); ?></strong> .
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
                    <td class="text-center fw-bold p-1">CERTIFICATION</td>
                </tr>
                <tr>
                    <td class="p-3">
                        <p class="mb-2 text-justify" style="text-indent: 50px;">
                            WE, THE UNDERSIGNED, DO HEREBY CERTIFY THAT THE INVENTORY OF THE COMMISSION ON POPULATION AND DEVELOPMENT REGION VI AT PAVIA, ILOILO - REPORT ON PHYSICAL COUNT OF PROPERTY PLANT AND EQUIPMENT contained in the form prescribed by the New Government Accounting System is true and correct per verification from Stock Cards and other documents, consisting of <?php echo htmlspecialchars($pages_count_str); ?> pages including this page as of <?php echo date('F d, Y', strtotime($as_of_date)); ?>.
                        </p>
                        <p class="mb-4 text-justify" style="text-indent: 50px;">
                            It is further certified that the actual physical count was made by the Chairman and all members of the Property Inventory Committee and witnessed by a Representative of the Commission on Audit.
                        </p>
                        
                        <div class="row text-center">
                            <div class="col-4 mb-4">
                                <div class="fw-bold mb-2">Certified Correct by:</div>
                                <div class="border-top mt-4 pt-1"></div>
                                <div class="small mt-1"><?php echo htmlspecialchars($rpcppe_certified_correct_by_label); ?></div>
                            </div>
                            <div class="col-4 mb-4">
                                <div class="fw-bold mb-2">Approved by:</div>
                                <div class="border-top mt-4 pt-1"></div>
                                <div class="small mt-1"><?php echo htmlspecialchars($rpcppe_approved_by_label); ?></div>
                            </div>
                            <div class="col-4 mb-4">
                                <div class="fw-bold mb-2">Verified by:</div>
                                <div class="border-top mt-4 pt-1"></div>
                                <div class="small mt-1"><?php echo htmlspecialchars($rpcppe_verified_by_label); ?></div>
                            </div>
                        </div>
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
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Form No. RSMI-FMD-FM081</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Version No. 06</div>
                        <div class="border-end border-dark flex-fill text-center p-1" style="font-size: 11px;">Effectivity Date: October 15, 2024</div>
                        <div class="flex-fill text-center p-1" style="font-size: 11px;">GAM Appendix 64</div>
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
                            <div class="border-top mb-1" style="margin-top: 40px;"></div>
                            <div class="small"><?php echo htmlspecialchars($rsmi_supply_custodian_label); ?></div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="border-top mb-1" style="margin-top: 40px;"></div>
                            <div class="small"><?php echo htmlspecialchars($rsmi_accounting_staff_label); ?></div>
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
