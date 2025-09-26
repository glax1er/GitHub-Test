<?php
header("Content-Type: application/json");
require "db.php"; // this should define $pdo

$data = json_decode(file_get_contents("php://input"), true);
$entryId = $data['id'] ?? null;

if (!$entryId) {
    echo json_encode(['success' => false, 'error' => 'No entry ID provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM journal_lines WHERE journal_id = ?");
    $stmt->execute([$entryId]);

    $stmt = $pdo->prepare("DELETE FROM journal_entries WHERE id = ?");
    $stmt->execute([$entryId]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
