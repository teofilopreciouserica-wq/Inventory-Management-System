<?php

/**
 * 
 * @param mysqli 
 * @param string 
 * @return string
 */
function generateRefNumber(mysqli $conn, string $prefix): string {
    $date  = date('Ymd'); // e.g. 20250515
    $table = $prefix === 'SI' ? 'stock_in' : 'stock_out';

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM $table
        WHERE DATE(transaction_at) = CURDATE()
    ");
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $seq = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    return "{$prefix}-{$date}-{$seq}";
}