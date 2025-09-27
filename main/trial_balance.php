<?php
require_once __DIR__ . "/db.php";  // this defines $pdo

header('Content-Type: application/json');

try {
    $sql = "
        SELECT 
            a.name AS account_title,
            a.type AS account_type,
            IFNULL(SUM(jl.debit), 0) AS total_debit,
            IFNULL(SUM(jl.credit), 0) AS total_credit
        FROM accounts a
        LEFT JOIN journal_lines jl ON a.id = jl.account_id
        GROUP BY a.id, a.name, a.type
        ORDER BY a.type, a.name
    ";

    $stmt = $pdo->query($sql);
    $trial_balance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_debits = 0;
    $total_credits = 0;

    foreach ($trial_balance as $row) {
        $total_debits += $row['total_debit'];
        $total_credits += $row['total_credit'];
    }

    echo json_encode([
        "accounts" => $trial_balance,
        "totals" => [
            "debits" => $total_debits,
            "credits" => $total_credits
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
