<?php
/**
 * Email Queue Management Interface
 */

require_once "config/database.php";
require_once "includes/EmailQueueProcessor.php";

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$processor = new EmailQueueProcessor($db);

if ($_POST["action"] ?? "" == "process") {
    $processed = $processor->processQueue(20);
    $message = "تم معالجة $processed رسالة من القائمة";
}

// Get queue statistics
$stats = $db->query("
    SELECT status, COUNT(*) as count 
    FROM email_queue 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pending = $stats["pending"] ?? 0;
$sent = $stats["sent"] ?? 0;
$failed = $stats["failed"] ?? 0;
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة قائمة البريد الإلكتروني</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">إدارة قائمة البريد الإلكتروني</h1>
        
        <?php if (isset($message)): ?>
        <div class="bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-yellow-100 p-4 rounded-lg">
                <h3 class="font-bold">في الانتظار</h3>
                <p class="text-2xl"><?php echo $pending; ?></p>
            </div>
            <div class="bg-amber-100 p-4 rounded-lg">
                <h3 class="font-bold">تم الإرسال</h3>
                <p class="text-2xl"><?php echo $sent; ?></p>
            </div>
            <div class="bg-red-100 p-4 rounded-lg">
                <h3 class="font-bold">فشل الإرسال</h3>
                <p class="text-2xl"><?php echo $failed; ?></p>
            </div>
        </div>
        
        <form method="post" class="mb-4">
            <input type="hidden" name="action" value="process">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                معالجة قائمة البريد
            </button>
        </form>
        
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-bold mb-4">الرسائل الأخيرة</h2>
            <?php
            $recent = $db->query("
                SELECT * FROM email_queue 
                ORDER BY created_at DESC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-right p-2">المستقبل</th>
                        <th class="text-right p-2">الموضوع</th>
                        <th class="text-right p-2">الحالة</th>
                        <th class="text-right p-2">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $email): ?>
                    <tr class="border-b">
                        <td class="p-2"><?php echo htmlspecialchars($email["to_email"]); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars(substr($email["subject"], 0, 50)); ?></td>
                        <td class="p-2">
                            <span class="px-2 py-1 rounded text-xs 
                                <?php echo $email["status"] == "sent" ? "bg-amber-200 text-amber-800" : 
                                          ($email["status"] == "failed" ? "bg-red-200 text-red-800" : "bg-yellow-200 text-yellow-800"); ?>">
                                <?php echo $email["status"]; ?>
                            </span>
                        </td>
                        <td class="p-2"><?php echo date("Y-m-d H:i", strtotime($email["created_at"])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>