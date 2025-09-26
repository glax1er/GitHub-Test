<?php
require "db.php"; // PDO connection

// Define custom type order
$typeOrder = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

// Fetch all accounts sorted by type then name
$accountsStmt = $pdo->query("
    SELECT id, name, type 
    FROM accounts 
    ORDER BY FIELD(type, 'Asset', 'Liability', 'Equity', 'Revenue', 'Expense'), name
");
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="ivan.jpg">
<title>Ledger</title>
<style>
body { font-family: Arial; margin: 20px; background: #f4f6f9; }
h1 { text-align: center; margin-bottom: 20px; }
h2 { margin-top: 40px; }
table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 30px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
th { background: #007bff; color: white; }
td:first-child { text-align: left; }
tr:nth-child(even) { background: #f9f9f9; }
.balance { font-weight: bold; }
.balance-positive { color: green; }
.balance-negative { color: red; }
.total-row { font-weight: bold; background: #eee; }

button {
    padding: 6px 10px;
    margin: 5px 0;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s ease-in-out;
}

button:hover {
    background: #0056b3;
}

button:active {
    transform: translateY(1px);
}

.remove-btn, .delete-entry-btn {
    background: #dc3545;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.remove-btn:hover, .delete-entry-btn:hover {
    background: #a71d2a;
}
</style>
</head>
<body>
<h1>Ledger</h1>
<button type="button" onclick="window.location.href='index.php'">
    Go back to Journal Entries
</button>

<?php foreach ($accounts as $acc): ?>
    <h2><?= htmlspecialchars($acc['name']) ?> (<?= $acc['type'] ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Ref No</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->prepare("
            SELECT jl.debit, jl.credit, jl.ref_no, je.entry_date
            FROM journal_lines jl
            JOIN journal_entries je ON jl.journal_id = je.id
            WHERE jl.account_id = ?
            ORDER BY je.entry_date, jl.id
        ");
        $stmt->execute([$acc['id']]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $runningBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line):
            $totalDebit += $line['debit'];
            $totalCredit += $line['credit'];

            if (in_array($acc['type'], ['Asset', 'Expense'])) {
                $runningBalance += $line['debit'] - $line['credit'];
            } else {
                $runningBalance += $line['credit'] - $line['debit'];
            }

            $displayBalance = abs($runningBalance); // ALWAYS positive
        ?>
            <tr>
                <td><?= $line['entry_date'] ?></td>
                <td><?= htmlspecialchars($line['ref_no']) ?></td>
                <td><?= number_format($line['debit'], 2) ?></td>
                <td><?= number_format($line['credit'], 2) ?></td>
                <td class="balance <?= $runningBalance >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                    <?= number_format($displayBalance, 2) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2">Total</td>
            <td><?= number_format($totalDebit, 2) ?></td>
            <td><?= number_format($totalCredit, 2) ?></td>
            <td></td>
        </tr>
        <tr class="total-row">
            <td colspan="4">Ending Balance</td>
            <td class="balance <?= $runningBalance >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                <?= number_format(abs($runningBalance), 2) ?>
            </td>
        </tr>
        </tbody>
    </table>
<?php endforeach; ?>

</body>
</html>
