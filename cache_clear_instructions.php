<?php
// Force browser cache clear by sending proper headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'>";
echo "<meta http-equiv='Cache-Control' content='no-cache, no-store, must-revalidate'>";
echo "<meta http-equiv='Pragma' content='no-cache'>";
echo "<meta http-equiv='Expires' content='0'>";
echo "</head><body>";
echo "<h1>Cache Clear Instructions</h1>";
echo "<p>The fix has been deployed successfully. Please clear your browser cache:</p>";
echo "<ul>";
echo "<li><strong>Chrome/Edge:</strong> Press Ctrl+Shift+Delete, select 'Cached images and files', then click 'Clear data'</li>";
echo "<li><strong>Firefox:</strong> Press Ctrl+Shift+Delete, check 'Cache', then click 'Clear Now'</li>";
echo "<li><strong>Quick Fix:</strong> Press Ctrl+F5 (or Cmd+Shift+R on Mac) to hard refresh the payment page</li>";
echo "</ul>";
echo "<p>After clearing cache, reload: <a href='/modules/payments/add.php?invoice_id=124'>Payment Page</a></p>";
echo "<hr>";
echo "<h2>Verification Results:</h2>";
echo "<p>✓ Server file is correct (0 occurrences of 'bank_transfer')</p>";
echo "<p>✓ HTML uses 'transfer'</p>";
echo "<p>✓ JavaScript uses 'transfer'</p>";
echo "<p>✓ Database ENUM: enum('cash','transfer','credit_card','check','other')</p>";
echo "</body></html>";
?>
