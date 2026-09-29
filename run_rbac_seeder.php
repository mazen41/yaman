<?php
/**
 * Run RBAC Seeder - Web Interface
 * URL: https://taksoride.com/run_rbac_seeder.php
 */

session_start();

// Check if user is admin
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['is_admin'] != 1) {
    die("Access denied. Admin only.");
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>RBAC Seeder</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; }
        .btn { padding: 15px 30px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1d4ed8; }
        pre { background: #f9fafb; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 RBAC System Seeder</h1>
        <p>This will populate your database with:</p>
        <ul>
            <li>4 Roles (Super Admin, Manager, Employee, Accountant)</li>
            <li>69 Permissions (23 pages × 3 actions average)</li>
            <li>Default role-permission assignments</li>
        </ul>
        
        <?php if (!isset($_POST['run_seeder'])): ?>
            <form method="POST">
                <button type="submit" name="run_seeder" class="btn">Run Seeder Now</button>
            </form>
        <?php else: ?>
            <h2>Running Seeder...</h2>
            <pre><?php
            ob_start();
            include 'database/seeders/seed_rbac_system.php';
            $output = ob_get_clean();
            echo htmlspecialchars($output);
            ?></pre>
            
            <p><a href="modules/financial/employee_permissions_rbac.php">Go to Permission Management →</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
