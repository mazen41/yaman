<?php
require_once 'config/database.php';

$fixes = [];
$errors = [];

// Check and add customer_group column
try {
    $check = $db->query("SHOW COLUMNS FROM `customers` LIKE 'customer_group'")->fetch();
    if (!$check) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `customer_group` varchar(100) DEFAULT NULL AFTER `city_name`");
        $fixes[] = "✅ Added column: customers.customer_group";
    } else {
        $fixes[] = "ℹ️ Column already exists: customers.customer_group";
    }
} catch (Exception $e) {
    $errors[] = "❌ customer_group: " . $e->getMessage();
}

// Check and add city_name column while we're at it
try {
    $check = $db->query("SHOW COLUMNS FROM `customers` LIKE 'city_name'")->fetch();
    if (!$check) {
        $db->exec("ALTER TABLE `customers` ADD COLUMN `city_name` varchar(100) DEFAULT NULL");
        $fixes[] = "✅ Added column: customers.city_name";
    } else {
        $fixes[] = "ℹ️ Column already exists: customers.city_name";
    }
} catch (Exception $e) {
    $errors[] = "❌ city_name: " . $e->getMessage();
}

// Show current customers table structure
try {
    $cols = $db->query("SHOW COLUMNS FROM `customers`")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'Field');
} catch (Exception $e) {
    $col_names = [];
    $errors[] = "❌ Could not read customers table: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fix customer_group</title>
    <style>
        body { font-family: monospace; padding: 30px; background: #f4f4f4; }
        .box { background: #fff; border-radius: 8px; padding: 20px; max-width: 700px; margin: auto; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        h2 { color: #1e293b; }
        .ok  { color: #059669; }
        .err { color: #dc2626; }
        .info{ color: #2563eb; }
        ul { line-height: 2; }
        .cols { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-top: 16px; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔧 Fix: customers.customer_group</h2>

    <ul>
        <?php foreach ($fixes as $f): ?>
            <li class="<?= str_starts_with($f,'✅') ? 'ok' : 'info' ?>"><?= $f ?></li>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <li class="err"><?= $e ?></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($col_names): ?>
    <div class="cols">
        <strong>customers table columns:</strong><br><br>
        <?= implode(', ', $col_names) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($errors)): ?>
        <p class="ok" style="margin-top:20px;font-size:16px;">
            ✅ Done! <a href="modules/reports/customer_summary.php">Go to Customer Summary →</a>
        </p>
    <?php else: ?>
        <p class="err" style="margin-top:20px;">Fix the errors above and refresh.</p>
    <?php endif; ?>
</div>
</body>
</html>
