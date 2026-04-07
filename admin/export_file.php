<?php
session_start();
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')){
    header("Location: ../login.php");
    exit();
}

$report_type = $_POST['export_type'] ?? '';
$items = $_POST['items'] ?? [];
$filename = strtoupper($report_type) . "_Report_" . date('Y-m-d') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Helper function to format numbers
function format_num($num, $decimals = 2) {
    return is_numeric($num) ? number_format((float)$num, $decimals) : $num;
}

if ($report_type === 'rpci') {
    $inventory_type = $_POST['inventory_type'] ?? 'Inventory Item';
    $as_of_date = $_POST['as_of_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $position_title = $_POST['position_title'] ?? '';
    $entity_name = $_POST['entity_name'] ?? 'CPD';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="7" style="text-align:center; font-weight:bold;">REPORT ON THE PHYSICAL COUNT OF INVENTORIES</th></tr>';
    echo '<tr><th colspan="7" style="text-align:center;">' . htmlspecialchars($inventory_type) . '</th></tr>';
    echo '<tr><th colspan="7" style="text-align:center;">As at ' . date('F d, Y', strtotime($as_of_date)) . '</th></tr>';
    echo '<tr><th colspan="7"></th></tr>';
    echo '<tr><th colspan="7">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th></tr>';
    echo '<tr><th colspan="7">For which ' . htmlspecialchars($accountable_officer) . ', ' . htmlspecialchars($position_title) . ', ' . htmlspecialchars($entity_name) . ' is accountable, having assumed such accountability on ' . date('F d, Y', strtotime($as_of_date)) . '.</th></tr>';
    echo '<tr><th colspan="7"></th></tr>';
    
    echo '<tr>
            <th>Article</th>
            <th>Description</th>
            <th>Stock Number</th>
            <th>Unit of Measure</th>
            <th>Unit Value</th>
            <th>Balance Per Card (Qty)</th>
            <th>On Hand Per Count (Qty)</th>
            <th>Shortage/Overage (Qty)</th>
            <th>Shortage/Overage (Value)</th>
            <th>Remarks</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($items as $item) {
        $shortage_qty = $item['shortage_qty'] ?? '';
        $shortage_val = $item['shortage_value'] ?? '';
        $overage_qty = $item['overage_qty'] ?? '';
        $overage_val = $item['overage_value'] ?? '';
        
        $so_qty = ($shortage_qty ? "($shortage_qty)" : "") . ($overage_qty ? $overage_qty : "");
        $so_val = ($shortage_val ? "($shortage_val)" : "") . ($overage_val ? $overage_val : "");
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['item'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['description'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['unit_value'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['balance_qty'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['physical_count'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($so_qty) . '</td>';
        echo '<td>' . htmlspecialchars($so_val) . '</td>';
        echo '<td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

} elseif ($report_type === 'rpcppe') {
    $inventory_type = $_POST['inventory_type'] ?? 'Property, Plant and Equipment';
    $as_of_date = $_POST['as_of_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $position_title = $_POST['position_title'] ?? '';
    $entity_name = $_POST['entity_name'] ?? 'CPD';

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="11" style="text-align:center; font-weight:bold;">REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT</th></tr>';
    echo '<tr><th colspan="11" style="text-align:center;">' . htmlspecialchars($inventory_type) . '</th></tr>';
    echo '<tr><th colspan="11" style="text-align:center;">As at ' . date('F d, Y', strtotime($as_of_date)) . '</th></tr>';
    echo '<tr><th colspan="11"></th></tr>';
    echo '<tr><th colspan="11">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th></tr>';
    echo '<tr><th colspan="11">For which ' . htmlspecialchars($accountable_officer) . ', ' . htmlspecialchars($position_title) . ', ' . htmlspecialchars($entity_name) . ' is accountable.</th></tr>';
    echo '<tr><th colspan="11"></th></tr>';
    
    echo '<tr>
            <th>Article</th>
            <th>Description</th>
            <th>Property Number</th>
            <th>Unit of Measure</th>
            <th>Unit Value</th>
            <th>Balance Per Card (Qty)</th>
            <th>On Hand Per Count (Qty)</th>
            <th>Shortage/Overage (Qty)</th>
            <th>Shortage/Overage (Value)</th>
            <th>Remarks</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($items as $item) {
        $shortage_qty = $item['shortage_qty'] ?? '';
        $shortage_val = $item['shortage_value'] ?? '';
        $overage_qty = $item['overage_qty'] ?? '';
        $overage_val = $item['overage_value'] ?? '';
        
        $so_qty = ($shortage_qty ? "($shortage_qty)" : "") . ($overage_qty ? $overage_qty : "");
        $so_val = ($shortage_val ? "($shortage_val)" : "") . ($overage_val ? $overage_val : "");
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['item'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['description'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>'; // Using stock_no as Property Number
        echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['unit_value'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['balance_qty'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['physical_count'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($so_qty) . '</td>';
        echo '<td>' . htmlspecialchars($so_val) . '</td>';
        echo '<td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

} elseif ($report_type === 'rsmi') {
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $serial_no = $_POST['serial_no'] ?? '';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="8" style="text-align:center; font-weight:bold;">REPORT OF SUPPLIES AND MATERIALS ISSUED</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center;">Period Covered: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center;">Serial No.: ' . htmlspecialchars($serial_no) . '</th></tr>';
    echo '<tr><th colspan="8"></th></tr>';
    
    echo '<tr>
            <th>RIS No.</th>
            <th>Responsibility Center Code</th>
            <th>Stock No.</th>
            <th>Item</th>
            <th>Unit</th>
            <th>Qty Issued</th>
            <th>Unit Cost</th>
            <th>Amount</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Sort items by RIS No?
    // They usually come sorted from print_file.php logic or DB
    
    $total_amount = 0;
    
    foreach ($items as $item) {
        $amount = (float)($item['amount'] ?? 0);
        $total_amount += $amount;
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['ris_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['responsibility_center_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['item'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['unit_measurement'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['quantity_issued'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['unit_value'] ?? '') . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '</tr>';
    }
    
    echo '<tr>
            <td colspan="7" style="text-align:right; font-weight:bold;">TOTAL</td>
            <td style="font-weight:bold;">' . format_num($total_amount) . '</td>
          </tr>';
    echo '</tbody></table>';
    
    // Add Recap
    echo '<br><table><tr><th colspan="3" style="font-weight:bold;">RECAPITULATION</th></tr>';
    echo '<tr><th>Stock No.</th><th>Qty</th><th>Unit Cost</th><th>Total Cost</th></tr>';
    
    $recap = [];
    foreach ($items as $item) {
        $sn = $item['stock_no'] ?? '';
        $uc = (float)($item['unit_value'] ?? 0);
        $qty = (float)($item['quantity_issued'] ?? 0);
        
        $key = $sn . '_' . $uc;
        if (!isset($recap[$key])) {
            $recap[$key] = [
                'stock_no' => $sn,
                'unit_cost' => $uc,
                'qty' => 0,
                'total' => 0
            ];
        }
        $recap[$key]['qty'] += $qty;
        $recap[$key]['total'] += ($qty * $uc);
    }
    
    ksort($recap);
    
    foreach ($recap as $r) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['stock_no']) . '</td>';
        echo '<td>' . htmlspecialchars($r['qty']) . '</td>';
        echo '<td>' . format_num($r['unit_cost']) . '</td>';
        echo '<td>' . format_num($r['total']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

} elseif ($report_type === 'ics') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $ics_no = $_POST['ics_no'] ?? '';
    $ics_date = $_POST['ics_date'] ?? date('Y-m-d');

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="7" style="text-align:center; font-weight:bold;">INVENTORY CUSTODIAN SLIP</th></tr>';
    echo '<tr><th colspan="7" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr><th colspan="3">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th>';
    echo '<th colspan="4">ICS No.: ' . htmlspecialchars($ics_no) . '</th></tr>';
    echo '<tr>
            <th rowspan="2">Quantity</th>
            <th rowspan="2">Unit</th>
            <th colspan="2">Amount</th>
            <th rowspan="2">Description</th>
            <th rowspan="2">Inventory Item No.</th>
            <th rowspan="2">Estimated Useful Life</th>
          </tr>';
    echo '<tr>
            <th>Unit Cost</th>
            <th>Total Cost</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;

        echo '<tr>';
        echo '<td>' . htmlspecialchars($qty) . '</td>';
        echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
        echo '<td>' . format_num($unit_value) . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '<td>' . htmlspecialchars(($item['item'] ?? '') . (isset($item['description']) ? ' - ' . $item['description'] : '')) . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'par') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $office = $_POST['office'] ?? '';
    $par_no = $_POST['par_no'] ?? '';
    $par_date = $_POST['par_date'] ?? date('Y-m-d');

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="6" style="text-align:center; font-weight:bold;">PROPERTY ACKNOWLEDGEMENT RECEIPT</th></tr>';
    echo '<tr><th colspan="6" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr><th colspan="2">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th>';
    echo '<th colspan="4">PAR No.: ' . htmlspecialchars($par_no) . '</th></tr>';
    echo '<tr>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Description</th>
            <th>Property Number</th>
            <th>Date Acquired</th>
            <th>Amount</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;
        $date_acquired = $item['date_acquired'] ?? '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($qty) . '</td>';
        echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(($item['item'] ?? '') . (isset($item['description']) ? ' - ' . $item['description'] : '')) . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'pc') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $property_desc = $_POST['property_desc'] ?? '';
    $property_number = $_POST['property_number'] ?? '';

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="8" style="text-align:center; font-weight:bold;">PROPERTY CARD</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr>
            <th colspan="4">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th>
            <th colspan="4">Property Number: ' . htmlspecialchars($property_number) . '</th>
          </tr>';
    echo '<tr><th colspan="8">Property, Plant and Equipment / Description: ' . htmlspecialchars($property_desc) . '</th></tr>';
    echo '<tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference/ PAR No.</th>
            <th>Receipt</th>
            <th colspan="2">Issue/Transfer/ Disposal</th>
            <th>Balance</th>
            <th rowspan="2">Amount</th>
            <th rowspan="2">Remarks</th>
          </tr>';
    echo '<tr>
            <th>Qty.</th>
            <th>Qty.</th>
            <th>Office/Officer</th>
            <th>Qty.</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;
        $date_acquired = $item['date_acquired'] ?? '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
        echo '<td></td>';
        echo '<td>' . htmlspecialchars($qty) . '</td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td>' . htmlspecialchars($qty) . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'ppelc') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $property_desc = $_POST['property_desc'] ?? '';
    $property_number = $_POST['property_number'] ?? '';

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="9" style="text-align:center; font-weight:bold;">PROPERTY, PLANT AND EQUIPMENT LEDGER CARD</th></tr>';
    echo '<tr><th colspan="9" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr>
            <th colspan="5">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th>
            <th colspan="4">Property Number: ' . htmlspecialchars($property_number) . '</th>
          </tr>';
    echo '<tr><th colspan="9">Property, Plant and Equipment / Description: ' . htmlspecialchars($property_desc) . '</th></tr>';
    echo '<tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference</th>
            <th colspan="3">Receipt</th>
            <th rowspan="2">Accumulated Depreciation</th>
            <th rowspan="2">Accumulated Impairment Losses</th>
            <th rowspan="2">Issues/Transfers/ Adjustment/s</th>
            <th rowspan="2">Adjusted Cost</th>
            <th colspan="2">Repair History</th>
          </tr>';
    echo '<tr>
            <th>Qty.</th>
            <th>Unit Cost</th>
            <th>Total Cost</th>
            <th>Nature of Repair</th>
            <th>Amount</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;
        $date_acquired = $item['date_acquired'] ?? '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
        echo '<td></td>';
        echo '<td>' . htmlspecialchars($qty) . '</td>';
        echo '<td>' . format_num($unit_value) . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'ptr') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="5" style="text-align:center; font-weight:bold;">PROPERTY TRANSFER REPORT</th></tr>';
    echo '<tr><th colspan="5" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr>
            <th colspan="3">Entity Name: ____________________________</th>
            <th colspan="2">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th>
          </tr>';
    echo '<tr>
            <th>Date Acquire</th>
            <th>Property No.</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Condition of PPE</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;
        $date_acquired = $item['date_acquired'] ?? '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(($item['item'] ?? '') . (isset($item['description']) ? ' - ' . $item['description'] : '')) . '</td>';
        echo '<td>' . format_num($amount) . '</td>';
        echo '<td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'rlsddp') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="7" style="text-align:center; font-weight:bold;">REPORT ON LOST/STOLEN/DAMAGED/DESTROYED PROPERTIES</th></tr>';
    echo '<tr><th colspan="7" style="text-align:center;">' . htmlspecialchars($entity_name) . '</th></tr>';
    echo '<tr><th colspan="4">Entity Name: ' . htmlspecialchars($entity_name) . '</th><th colspan="3">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th></tr>';
    echo '<tr>
            <th>Date</th>
            <th>Property No.</th>
            <th>Description</th>
            <th>Qty</th>
            <th>Unit Value</th>
            <th>Amount</th>
            <th>Cause/Remarks</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($items as $item) {
        $qty = isset($item['physical_count']) ? (float)$item['physical_count'] : 1;
        if ($qty <= 0) $qty = 1;
        $unit_value = isset($item['unit_value']) ? (float)$item['unit_value'] : 0;
        $amount = $qty * $unit_value;
        $date_acquired = $item['date_acquired'] ?? '';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($date_acquired) . '</td>';
        echo '<td>' . htmlspecialchars($item['stock_no'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(($item['item'] ?? '') . (isset($item['description']) ? ' - ' . $item['description'] : '')) . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($qty) . '</td>';
        echo '<td style="text-align:right;">' . format_num($unit_value) . '</td>';
        echo '<td style="text-align:right;">' . format_num($amount) . '</td>';
        echo '<td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'sc') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $reorder_point = $_POST['reorder_point'] ?? '';
    $item = $items[0] ?? [];

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="8" style="text-align:right; font-weight:normal;">GAM Appendix 58</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center; font-weight:bold;">Stock Card</th></tr>';
    echo '<tr><th colspan="8" style="text-align:center;">Form No. SC-FMD-FM075 | Version No. 06 | Effectivity Date: October 15, 2024</th></tr>';
    echo '<tr><th colspan="5">Entity Name: ' . htmlspecialchars($entity_name) . '</th><th colspan="3">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th></tr>';
    echo '<tr><th colspan="5">Item: ' . htmlspecialchars($item['item'] ?? '') . '</th><th colspan="3">Stock No.: ' . htmlspecialchars($item['stock_no'] ?? '') . '</th></tr>';
    echo '<tr><th colspan="5">Description: ' . htmlspecialchars($item['description'] ?? '') . '</th><th colspan="3">Re-order Point: ' . htmlspecialchars($reorder_point) . '</th></tr>';
    echo '<tr><th colspan="8">Unit of Measurement: ' . htmlspecialchars($item['unit'] ?? '') . '</th></tr>';
    echo '<tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference</th>
            <th>Receipt</th>
            <th colspan="2">Issue</th>
            <th>Balance</th>
            <th rowspan="2">No. of Days to Consume</th>
            <th rowspan="2"></th>
          </tr>';
    echo '<tr>
            <th>Qty.</th>
            <th>Qty.</th>
            <th>Office</th>
            <th>Qty.</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    for ($i = 0; $i < 20; $i++) {
        echo '<tr>';
        echo '<td></td><td></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} elseif ($report_type === 'slc') {
    $entity_name = $_POST['entity_name'] ?? 'COMMISSION ON POPULATION AND DEVELOPMENT - REGION VI';
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $reorder_point = $_POST['reorder_point'] ?? '';
    $item = $items[0] ?? [];

    echo '<table border="1">';
    echo '<thead>';
    echo '<tr><th colspan="12" style="text-align:right; font-weight:normal;">GAM Appendix 57</th></tr>';
    echo '<tr><th colspan="12" style="text-align:center; font-weight:bold;">Supplies Ledger Card</th></tr>';
    echo '<tr><th colspan="11" style="text-align:center;">Form No. SLC-FMD-FM074 | Version No. 06 | Effectivity Date: October 15, 2024</th></tr>';
    echo '<tr><th colspan="8">Entity Name: ' . htmlspecialchars($entity_name) . '</th><th colspan="4">Fund Cluster: ' . htmlspecialchars($fund_cluster) . '</th></tr>';
    echo '<tr><th colspan="6">Item: ' . htmlspecialchars($item['item'] ?? '') . '</th><th colspan="6">Item Code: ' . htmlspecialchars($item['stock_no'] ?? '') . '</th></tr>';
    echo '<tr><th colspan="6">Description: ' . htmlspecialchars($item['description'] ?? '') . '</th><th colspan="6">Re-order Point: ' . htmlspecialchars($reorder_point) . '</th></tr>';
    echo '<tr><th colspan="12">Unit of Measurement: ' . htmlspecialchars($item['unit'] ?? '') . '</th></tr>';
    echo '<tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference</th>
            <th colspan="3">Receipt</th>
            <th colspan="3">Issue</th>
            <th colspan="3">Balance</th>
            <th rowspan="2">No. of Days to Consume</th>
          </tr>';
    echo '<tr>
            <th>Qty.</th>
            <th>Unit Cost</th>
            <th>Total Cost</th>
            <th>Qty.</th>
            <th>Unit Cost</th>
            <th>Total Cost</th>
            <th>Qty.</th>
            <th>Unit Cost</th>
            <th>Total Cost</th>
          </tr>';
    echo '</thead>';
    echo '<tbody>';

    for ($i = 0; $i < 18; $i++) {
        echo '<tr>';
        echo '<td></td><td></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td style="text-align:center;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td style="text-align:right;"></td>';
        echo '<td></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

} else {
    echo "No Report Type Selected or Invalid Type.";
}
?>
