<?php
require "db.php"; // connection

header("Content-Type: application/json");

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("No data received");
    }

    $date = $data["date"] ?? null;
    $comment = $data["comment"] ?? "#";
    $lines = $data["lines"] ?? [];

    if (!$date || count($lines) < 2) {
        throw new Exception("Invalid entry. Date and at least 2 lines are required.");
    }

    // Insert into journal_entries
    $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_date, comment, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$date, $comment]);
    $journalId = $pdo->lastInsertId();

    // Prepare statements
    $lineStmt = $pdo->prepare("INSERT INTO journal_lines (journal_id, account_id, ref_no, debit, credit) VALUES (?, ?, ?, ?, ?)");
    $findAccount = $pdo->prepare("SELECT id FROM accounts WHERE name = ?");
    $insertAccount = $pdo->prepare("INSERT INTO accounts (name, type) VALUES (?, 'Asset')"); // default to Asset

    foreach ($lines as $line) {
        $accountName = trim($line["account_id"]); // actually the typed account name
        $ref = $line["ref"];
        $debit = $line["debit"];
        $credit = $line["credit"];

        // Find or create account
        $findAccount->execute([$accountName]);
        $accountId = $findAccount->fetchColumn();

        if (!$accountId) {
            $insertAccount->execute([$accountName]);
            $accountId = $pdo->lastInsertId();
        }

        // Insert line
        $lineStmt->execute([$journalId, $accountId, $ref, $debit, $credit]);
    }

    echo json_encode(["success" => true, "journal_id" => $journalId]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
