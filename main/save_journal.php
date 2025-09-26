<?php
require "db.php"; // connection
header("Content-Type: application/json");

try {
    $pdo->beginTransaction();

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) throw new Exception("No data received");

    $date = $data["date"] ?? null;
    $comment = trim($data["comment"] ?? "#");
    $lines = $data["lines"] ?? [];

    if (!$date || count($lines) < 2) {
        throw new Exception("Invalid entry. Date and at least 2 lines are required.");
    }

    // Mapping account names to types (optional for exact matches)
    $accountMapping = [
        // ASSETS
        'Cash' => 'Asset',
        'Cash on Hand' => 'Asset',
        'Petty Cash' => 'Asset',
        'Bank Account' => 'Asset',
        'Accounts Receivable' => 'Asset',
        'Inventory' => 'Asset',
        'Prepaid Expenses' => 'Asset',
        'Supplies' => 'Asset',
        'Office Supplies' => 'Asset',
        'Equipment' => 'Asset',
        'Computers' => 'Asset',
        'Laptops' => 'Asset',
        'Printers' => 'Asset',
        'Furniture' => 'Asset',
        'Vehicles' => 'Asset',
        'Land' => 'Asset',
        'Buildings' => 'Asset',
        'School Fees Receivable' => 'Asset',
        'Software Licenses' => 'Asset',
        'Prepaid Rent' => 'Asset',
        'Prepaid Insurance' => 'Asset',

        // LIABILITIES
        'Accounts Payable' => 'Liability',
        'Notes Payable' => 'Liability',
        'Salaries Payable' => 'Liability',
        'Wages Payable' => 'Liability',
        'Taxes Payable' => 'Liability',
        'Interest Payable' => 'Liability',
        'Unearned Revenue' => 'Liability',
        'Loans Payable' => 'Liability',
        'Credit Card Payable' => 'Liability',
        'Accrued Expenses' => 'Liability',
        'School Fees Collected in Advance' => 'Liability',

        // OWNER'S EQUITY
        'Owner Capital' => 'Equity',
        'Owner Drawings' => 'Equity',
        'Retained Earnings' => 'Equity',
        'Share Capital' => 'Equity',
        'Contributed Capital' => 'Equity',

        // REVENUE
        'Sales Revenue' => 'Revenue',
        'Service Revenue' => 'Revenue',
        'Interest Income' => 'Revenue',
        'Rent Income' => 'Revenue',
        'Commission Income' => 'Revenue',
        'School Fees Revenue' => 'Revenue',
        'Tuition Fees' => 'Revenue',
        'Lab Fees' => 'Revenue',
        'Library Fees' => 'Revenue',
        'Canteen Income' => 'Revenue',
        'Other Income' => 'Revenue',

        // EXPENSES
        'Rent Expense' => 'Expense',
        'Utilities Expense' => 'Expense',
        'Salaries Expense' => 'Expense',
        'Wages Expense' => 'Expense',
        'Depreciation Expense' => 'Expense',
        'Insurance Expense' => 'Expense',
        'Supplies Expense' => 'Expense',
        'Office Supplies Expense' => 'Expense',
        'Repairs and Maintenance' => 'Expense',
        'Advertising Expense' => 'Expense',
        'Printing Expense' => 'Expense',
        'Stationery Expense' => 'Expense',
        'Internet Expense' => 'Expense',
        'Telephone Expense' => 'Expense',
        'Travel Expense' => 'Expense',
        'Professional Fees' => 'Expense',
        'Books and Library Expense' => 'Expense',
        'Lab Materials Expense' => 'Expense',
        'Canteen Expense' => 'Expense',
        'Miscellaneous Expense' => 'Expense',
        'Cost of Goods Sold' => 'Expense',
    ];

    // Keyword-based type detection
    $accountKeywords = [
        'Asset' => ['cash','bank','inventory','prepaid','supplies','equipment','computer','laptop','printer','furniture','vehicle','land','building','software'],
        'Liability' => ['payable','loan','credit','accrued','unearned','tax','interest'],
        'Equity' => ['owner','capital','drawing','retained','share','contributed'],
        'Revenue' => ['revenue','income','fee','sales','service','tuition','lab','library','canteen','commission','rent','school fees'],
        'Expense' => ['expense','rent','utilities','salary','wages','depreciation','insurance','supplies','office','repairs','advertising','printing','stationery','internet','telephone','travel','professional','books','lab','canteen','miscellaneous','cost of goods sold']
    ];

    function detectAccountType($accountName, $keywords) {
        $nameLower = strtolower($accountName);
        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                if (strpos($nameLower, $word) !== false) {
                    return $type;
                }
            }
        }
        return 'Asset'; // fallback if nothing matches
    }

    // Prepare statements
    $findAccount = $pdo->prepare("SELECT id FROM accounts WHERE name = ?");
    $getExistingRef = $pdo->prepare("SELECT ref_no FROM journal_lines WHERE account_id = ? LIMIT 1");
    $refCheckStmt = $pdo->prepare("SELECT jl.ref_no, a.name FROM journal_lines jl JOIN accounts a ON jl.account_id = a.id WHERE jl.ref_no = ? LIMIT 1");
    $insertAccount = $pdo->prepare("INSERT INTO accounts (name, type) VALUES (?, ?)");

    $validatedLines = [];

    foreach ($lines as $line) {
        $accountName = trim($line["account_id"]);
        $ref = trim($line["ref"]);
        $debit = floatval($line["debit"] ?? 0);
        $credit = floatval($line["credit"] ?? 0);

        // Automatic type detection using mapping + keywords
        $type = $accountMapping[$accountName] ?? detectAccountType($accountName, $accountKeywords);

        // Check if account exists
        $findAccount->execute([$accountName]);
        $accountId = $findAccount->fetchColumn();

        if ($accountId) {
            // Existing account: must use the same ref
            $getExistingRef->execute([$accountId]);
            $existingRef = $getExistingRef->fetchColumn();
            if ($existingRef !== $ref) {
                throw new Exception("Account '$accountName' already exists with reference number '$existingRef'. You must use '$existingRef'.");
            }
        } else {
            // New account: ensure ref is not used by another account
            $refCheckStmt->execute([$ref]);
            $existingRefData = $refCheckStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingRefData) {
                throw new Exception("Reference number '$ref' is already used for account '{$existingRefData['name']}'. Please use a different ref number.");
            }

            // Create new account automatically
            $insertAccount->execute([$accountName, $type]);
            $accountId = $pdo->lastInsertId();
        }

        $validatedLines[] = [
            'account_name' => $accountName,
            'account_id' => $accountId,
            'ref' => $ref,
            'debit' => $debit,
            'credit' => $credit
        ];
    }

    // Create journal entry
    $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_date, comment, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$date, $comment]);
    $journalId = $pdo->lastInsertId();

    // Insert lines
    $lineStmt = $pdo->prepare("INSERT INTO journal_lines (journal_id, account_id, ref_no, debit, credit) VALUES (?, ?, ?, ?, ?)");
    foreach ($validatedLines as $vLine) {
        $lineStmt->execute([$journalId, $vLine['account_id'], $vLine['ref'], $vLine['debit'], $vLine['credit']]);
    }

    $pdo->commit();
    echo json_encode(["success" => true, "journal_id" => $journalId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
