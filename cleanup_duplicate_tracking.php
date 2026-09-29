<?php
/**
 * Cleanup Script: Remove duplicate tracking numbers
 */
require_once 'config/database.php';

echo "<h2>Cleaning up duplicate tracking numbers</h2>";

try {
    // Find duplicates
    $duplicates_query = "SELECT basket_id, tracking_number, COUNT(*) as count
                        FROM basket_tracking
                        GROUP BY basket_id, tracking_number
                        HAVING count > 1";
    
    $duplicates = $db->query($duplicates_query)->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<p style='color: green;'>✅ No duplicate tracking numbers found!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Found " . count($duplicates) . " duplicate tracking numbers:</p>";
        echo "<pre>";
        print_r($duplicates);
        echo "</pre>";
        
        // Remove duplicates, keeping only the first one
        foreach ($duplicates as $dup) {
            $basket_id = $dup['basket_id'];
            $tracking_number = $dup['tracking_number'];
            
            // Get all IDs for this duplicate
            $ids_query = "SELECT id FROM basket_tracking 
                         WHERE basket_id = ? AND tracking_number = ?
                         ORDER BY created_at ASC";
            $stmt = $db->prepare($ids_query);
            $stmt->execute([$basket_id, $tracking_number]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Keep the first one, delete the rest
            $first_id = array_shift($ids);
            
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $delete_query = "DELETE FROM basket_tracking WHERE id IN ($placeholders)";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute($ids);
                
                echo "<p style='color: green;'>✅ Removed " . count($ids) . " duplicate(s) for tracking: $tracking_number (kept ID: $first_id)</p>";
            }
        }
        
        echo "<p style='color: green; font-weight: bold;'>✅ Cleanup completed!</p>";
    }
    
    // Show current tracking numbers
    echo "<h3>Current tracking numbers:</h3>";
    $current = $db->query("SELECT bt.*, pb.basket_code 
                          FROM basket_tracking bt
                          INNER JOIN purchase_baskets pb ON bt.basket_id = pb.id
                          ORDER BY bt.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($current);
    echo "</pre>";
    
    echo "<p><a href='modules/purchases/tracking.php'>Go to Tracking Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
