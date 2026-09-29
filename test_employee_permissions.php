<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing employee_permissions.php</h2><pre>";

try {
    session_start();
    
    // Set a test user session
    $_SESSION['user_id'] = 1;
    
    echo "1. Including database...\n";
    require_once 'config/database.php';
    echo "   ✓ Database connected\n\n";
    
    echo "2. Checking if file exists...\n";
    $file = 'modules/financial/employee-permissions.php';
    if (file_exists($file)) {
        echo "   ✓ File exists\n\n";
    } else {
        echo "   ✗ File NOT found: $file\n\n";
    }
    
    echo "3. Trying to include the file...\n";
    ob_start();
    include $file;
    $output = ob_get_clean();
    
    echo "   ✓ File included successfully\n\n";
    echo "4. Output:\n";
    echo $output;
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}

echo "</pre>";
?>
