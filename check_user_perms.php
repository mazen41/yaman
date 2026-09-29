<?php
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h2>فحص صلاحيات المستخدمين</h2>";

// Get user 9 permissions
$stmt = $db->prepare("
    SELECT up.user_id, u.full_name, p.permission_key 
    FROM user_permissions up 
    JOIN permissions p ON up.permission_id = p.id 
    JOIN users u ON up.user_id = u.id
    ORDER BY up.user_id
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($results);
echo "</pre>";

// Check if customers_view exists
echo "<h3>هل صلاحية customers_view موجودة؟</h3>";
$check = $db->query("SELECT * FROM permissions WHERE permission_key = 'customers_view'")->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($check);
echo "</pre>";

// Show all permissions
echo "<h3>كل الصلاحيات في الجدول:</h3>";
$all = $db->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($all);
echo "</pre>";
