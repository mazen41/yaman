<?php
echo "<h1>Applying Final Fix for View.php</h1>";
echo "<pre>";

// Read the current view.php file
$view_file = 'modules/orders/view.php';
$content = file_get_contents($view_file);

if (!$content) {
    die("Could not read view.php file\n");
}

echo "Applying comprehensive fixes to view.php...\n";

// Replace all direct array access with null coalescing operator
$replacements = [
    // Basic field access with fallbacks
    "htmlspecialchars(\$order['customer_name'])" => "htmlspecialchars(\$order['customer_name'] ?? 'غير معروف')",
    "htmlspecialchars(\$order['order_number'])" => "htmlspecialchars(\$order['order_number'] ?? 'غير معروف')",
    "\$order['status']" => "(\$order['status'] ?? 'غير معروف')",
    "\$order['customer_id']" => "(\$order['customer_id'] ?? 0)",
    
    // Numeric fields
    "number_format(\$order['total_amount'], 2)" => "number_format(\$order['total_amount'] ?? 0, 2)",
    "number_format(\$order['subtotal_amount'], 2)" => "number_format(\$order['subtotal_amount'] ?? 0, 2)",
    "number_format(\$order['discount_amount'], 2)" => "number_format(\$order['discount_amount'] ?? 0, 2)",
    "number_format(\$order['discount_value'], 2)" => "number_format(\$order['discount_value'] ?? 0, 2)",
    "number_format(\$order['shipping_cost'], 2)" => "number_format(\$order['shipping_cost'] ?? 0, 2)",
    "number_format(\$order['final_amount'], 2)" => "number_format(\$order['final_amount'] ?? 0, 2)",
    
    // Date fields
    "date('Y-m-d', strtotime(\$order['created_at']))" => "date('Y-m-d', strtotime(\$order['created_at'] ?? 'now'))",
    "date('Y-m-d H:i', strtotime(\$order['approved_at']))" => "date('Y-m-d H:i', strtotime(\$order['approved_at'] ?? 'now'))",
    
    // Conditional checks
    "!empty(\$order['discount_type'])" => "!empty(\$order['discount_type'] ?? '')",
    "\$order['discount_type'] == 'percentage'" => "(\$order['discount_type'] ?? '') == 'percentage'",
    "\$order['requires_approval']" => "(\$order['requires_approval'] ?? 0)",
    "\$order['approved_by']" => "(\$order['approved_by'] ?? null)",
    
    // Text fields that might be missing
    "htmlspecialchars(\$order['approved_by_name'])" => "htmlspecialchars(\$order['approved_by_name'] ?? 'غير معروف')",
    "nl2br(htmlspecialchars(\$order['notes']))" => "nl2br(htmlspecialchars(\$order['notes'] ?? ''))",
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Write the updated content back
if (file_put_contents($view_file, $content)) {
    echo "✅ Successfully updated view.php with null coalescing operators\n";
} else {
    echo "❌ Failed to update view.php\n";
}

echo "\nFixes applied:\n";
foreach ($replacements as $search => $replace) {
    echo "- $search -> $replace\n";
}

echo "</pre>";
echo "<p>The view.php file has been updated with comprehensive null coalescing operators to prevent undefined array key warnings.</p>";
echo "<p><a href='modules/orders/view.php?id=1'>Test View Page</a></p>";
?>
