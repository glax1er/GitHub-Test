<?php
require "db.php";

header("Content-Type: application/json");

try {
    $stmt = $pdo->query("
        SELECT 
            je.id AS journal_id,
            je.entry_date,
            je.comment,
            jl.id AS line_id,
            jl.account_id,
            a.name AS account_name,
            jl.ref_no,
            jl.debit,
            jl.credit
        FROM journal_entries je
        JOIN journal_lines jl ON je.id = jl.journal_id
        JOIN accounts a ON jl.account_id = a.id
        ORDER BY je.entry_date DESC, je.id, jl.id
    ");

    $entries = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $jid = $row["journal_id"];

        if (!isset($entries[$jid])) {
            $entries[$jid] = [
                "id" => $jid,
                "date" => $row["entry_date"],
                "comment" => $row["comment"],   
                "lines" => []
            ];
        }

        $entries[$jid]["lines"][] = [
            "line_id"     => $row["line_id"],
            "account_id"  => $row["account_id"],
            "account_name"=> $row["account_name"], // ✅ show account name
            "ref"         => $row["ref_no"],
            "debit"       => (float)$row["debit"],
            "credit"      => (float)$row["credit"]
        ];
    }

    echo json_encode(array_values($entries));

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
