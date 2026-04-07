<?php

if (!function_exists('compute_beginning_balance_from_rows')) {
    function compute_beginning_balance_from_rows(array $rows, string $start_date): int
    {
        if ($start_date === '') {
            return 0;
        }

        $startDateOnly = substr($start_date, 0, 10);
        if ($startDateOnly === '') {
            return 0;
        }

        $total = 0;
        foreach ($rows as $row) {
            if (!isset($row['transaction_date'], $row['transaction_type'], $row['quantity'])) {
                continue;
            }
            $txDate = (string)$row['transaction_date'];
            $txType = strtolower((string)$row['transaction_type']);
            $qty = (int)$row['quantity'];

            $rowDateOnly = substr($txDate, 0, 10);
            if ($rowDateOnly === '' || $rowDateOnly >= $startDateOnly) {
                continue;
            }

            $isInbound = in_array($txType, ['acquisition', 'approved'], true);
            if ($isInbound) {
                $total += $qty;
            } else {
                $total -= $qty;
            }
        }

        return $total;
    }
}

if (!function_exists('calculate_beginning_balance')) {
    function calculate_beginning_balance(mysqli $conn, int $item_id, string $start_date): int
    {
        if ($item_id <= 0 || $start_date === '') {
            return 0;
        }

        $itemId = (int)$item_id;
        $startEsc = $conn->real_escape_string(substr($start_date, 0, 10));

        $sql = "
            SELECT t.transaction_date, t.transaction_type, t.quantity
            FROM inventory_transactions t
            WHERE t.item_id = {$itemId}
              AND DATE(t.transaction_date) < '{$startEsc}'
            ORDER BY t.transaction_date ASC
        ";

        $rows = [];
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($r = $result->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        return compute_beginning_balance_from_rows($rows, $startEsc);
    }
}
