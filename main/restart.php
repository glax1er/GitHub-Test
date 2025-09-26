<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "accounting";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Delete from child first (journal_lines), then journal_entries, then accounts
    $pdo->exec("DELETE FROM journal_lines");
    $pdo->exec("DELETE FROM journal_entries");
    $pdo->exec("DELETE FROM accounts");

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
