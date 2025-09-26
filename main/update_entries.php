<?php
header("Content-Type: application/json");
require "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$type    = $data["type"]    ?? null;
$value   = trim($data["value"] ?? "");
$lineId  = $data["lineId"]  ?? null;
$entryId = $data["entryId"] ?? null;

try {
    if ($type === "account") {
        // Check if account exists (case-insensitive, trimmed)
        $stmt = $pdo->prepare("
            SELECT id FROM accounts 
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        ");
        $stmt->execute([$value]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            // Create new account with default type (e.g., Asset)
            $stmt = $pdo->prepare("
                INSERT INTO accounts (name, type) 
                VALUES (?, 'Asset')
            ");
            $stmt->execute([$value]);

            $accountId = $pdo->lastInsertId();
        } else {
            $accountId = $account['id'];
        }

        // Update journal line with found/created account
        $stmt = $pdo->prepare("
            UPDATE journal_lines 
            SET account_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$accountId, $lineId]);

    } elseif ($type === "ref") {
        $stmt = $pdo->prepare("
            UPDATE journal_lines 
            SET ref_no = ? 
            WHERE id = ?
        ");
        $stmt->execute([$value, $lineId]);

    } elseif ($type === "debit") {
        $stmt = $pdo->prepare("
            UPDATE journal_lines 
            SET debit = ?, credit = 0 
            WHERE id = ?
        ");
        $stmt->execute([floatval($value), $lineId]);

    } elseif ($type === "credit") {
        $stmt = $pdo->prepare("
            UPDATE journal_lines 
            SET credit = ?, debit = 0 
            WHERE id = ?
        ");
        $stmt->execute([floatval($value), $lineId]);

    } elseif ($type === "comment") {
        $stmt = $pdo->prepare("
            UPDATE journal_entries 
            SET comment = ? 
            WHERE id = ?
        ");
        $stmt->execute([$value, $entryId]);

    } else {
        echo json_encode(["success" => false, "error" => "Invalid update type"]);
        exit;
    }

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}
