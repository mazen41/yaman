<?php
/**
 * Migration: Add card_purchase_amount column to purchase_cards table
 * - card_purchase_amount: مبلغ شراء البطاقة (ما دفعته لشراء الكرت)
 */

require_once __DIR__ . '/config/database.php';

echo "<pre style='direction:rtl; font-family: Tahoma;'>";
echo "=== إضافة عمود مبلغ شراء البطاقة (card_purchase_amount) ===\n\n";

try {
    $check = $db->query("SHOW COLUMNS FROM purchase_cards LIKE 'card_purchase_amount'");

    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE purchase_cards ADD COLUMN card_purchase_amount DECIMAL(15,2) DEFAULT 0 AFTER card_name");
        echo "✅ تم إضافة العمود card_purchase_amount بنجاح\n";

        // تعبئة أولية: نفترض أن مبلغ شراء البطاقة يساوي الرصيد المتاح الحالي
        $db->exec("UPDATE purchase_cards SET card_purchase_amount = initial_balance WHERE card_purchase_amount = 0");
        echo "✅ تم تحديث البيانات الموجودة\n";
    } else {
        echo "⚠️ العمود card_purchase_amount موجود مسبقاً\n";
    }

    echo "\n=== اكتمل بنجاح ===\n";
    echo "\n⚠️ يرجى حذف هذا الملف بعد التشغيل لأسباب أمنية.\n";

} catch (PDOException $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
