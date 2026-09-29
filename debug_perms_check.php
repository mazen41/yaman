<?php
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

$user_id = intval($_GET['user_id'] ?? 20);

echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h1>فحص صلاحيات المستخدم $user_id</h1>";

// Method 1: Direct query
echo "<h2>1. استعلام مباشر من user_permissions:</h2>";
$stmt = $db->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
$stmt->execute([$user_id]);
$direct = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($direct, true) . "</pre>";

// Method 2: With JOIN
echo "<h2>2. مع JOIN على permissions:</h2>";
$stmt = $db->prepare("
    SELECT up.*, p.permission_key, p.permission_type, p.module_name
    FROM user_permissions up 
    JOIN permissions p ON up.permission_id = p.id 
    WHERE up.user_id = ?
");
$stmt->execute([$user_id]);
$joined = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($joined, true) . "</pre>";

// Method 3: Build array like the page does
echo "<h2>3. بناء المصفوفة كما تفعل الصفحة:</h2>";
$user_permissions = [];
$stmt = $db->prepare("
    SELECT p.permission_key 
    FROM user_permissions up 
    JOIN permissions p ON up.permission_id = p.id 
    WHERE up.user_id = ?
");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $user_permissions[$row['permission_key']] = true;
}
echo "<pre>" . print_r($user_permissions, true) . "</pre>";

// Test hasUserPerm function
echo "<h2>4. اختبار دالة hasUserPerm:</h2>";
function hasUserPerm($key, $type) {
    global $user_permissions;
    $full_key = $key . '_' . $type;
    $result = isset($user_permissions[$full_key]);
    echo "<p>hasUserPerm('$key', '$type') = '$full_key' => " . ($result ? '✅ TRUE' : '❌ FALSE') . "</p>";
    return $result;
}

$modules = ['customers', 'orders', 'dashboard'];
$types = ['view', 'edit', 'add'];

foreach ($modules as $m) {
    echo "<h3>$m:</h3>";
    foreach ($types as $t) {
        hasUserPerm($m, $t);
    }
}

echo "</div>";
