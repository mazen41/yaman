<?php
/**
 * System Rebranding Script
 * Changes brand name from "يمان" to "يمان"
 * Changes colors from green to gold (#C7A46D)
 */

// Configuration
$basePath = '/home/taksoride-admin/htdocs';
$excludeDirs = ['vendor', 'phpmailer', 'node_modules', '.git'];
$extensions = ['php', 'html', 'css', 'js'];

// Brand name changes
$brandChanges = [
    'يمان' => 'يمان',
    'yaman' => 'yaman',
    'Yaman' => 'Yaman',
    'YAMAN' => 'YAMAN'
];

// Color changes - Green to Gold
$colorChanges = [
    // Tailwind classes
    'bg-amber-50' => 'bg-amber-50',
    'bg-amber-100' => 'bg-amber-100',
    'bg-amber-200' => 'bg-amber-200',
    'bg-amber-300' => 'bg-amber-300',
    'bg-amber-400' => 'bg-amber-400',
    'bg-amber-500' => 'bg-amber-500',
    'bg-amber-600' => 'bg-amber-600',
    'bg-amber-700' => 'bg-amber-700',
    'bg-amber-800' => 'bg-amber-800',
    'bg-amber-900' => 'bg-amber-900',
    
    'text-amber-50' => 'text-amber-50',
    'text-amber-100' => 'text-amber-100',
    'text-amber-200' => 'text-amber-200',
    'text-amber-300' => 'text-amber-300',
    'text-amber-400' => 'text-amber-400',
    'text-amber-500' => 'text-amber-500',
    'text-amber-600' => 'text-amber-600',
    'text-amber-700' => 'text-amber-700',
    'text-amber-800' => 'text-amber-800',
    'text-amber-900' => 'text-amber-900',
    
    'border-amber-50' => 'border-amber-50',
    'border-amber-100' => 'border-amber-100',
    'border-amber-200' => 'border-amber-200',
    'border-amber-300' => 'border-amber-300',
    'border-amber-400' => 'border-amber-400',
    'border-amber-500' => 'border-amber-500',
    'border-amber-600' => 'border-amber-600',
    'border-amber-700' => 'border-amber-700',
    'border-amber-800' => 'border-amber-800',
    'border-amber-900' => 'border-amber-900',
    
    'hover:bg-amber-50' => 'hover:bg-amber-50',
    'hover:bg-amber-100' => 'hover:bg-amber-100',
    'hover:bg-amber-600' => 'hover:bg-amber-600',
    'hover:bg-amber-700' => 'hover:bg-amber-700',
    'hover:bg-amber-800' => 'hover:bg-amber-800',
    
    'hover:text-amber-600' => 'hover:text-amber-600',
    'hover:text-amber-700' => 'hover:text-amber-700',
    'hover:text-amber-800' => 'hover:text-amber-800',
    
    'from-amber-50' => 'from-amber-50',
    'from-amber-100' => 'from-amber-100',
    'from-amber-600' => 'from-amber-600',
    'from-amber-700' => 'from-amber-700',
    'from-amber-800' => 'from-amber-800',
    
    'to-amber-50' => 'to-amber-50',
    'to-amber-100' => 'to-amber-100',
    'to-amber-600' => 'to-amber-600',
    'to-amber-700' => 'to-amber-700',
    'to-amber-800' => 'to-amber-800',
    
    // Hex colors
    '#C7A46D' => '#C7A46D',
    '#C7A46D' => '#C7A46D',
    '#B8956A' => '#B8956A',
    '#A67C4A' => '#A67C4A',
    '#D4B87D' => '#D4B87D',
    '#E5D0A0' => '#E5D0A0',
    
    // RGB colors
    'rgb(199, 164, 109)' => 'rgb(199, 164, 109)',
    'rgb(199, 164, 109)' => 'rgb(199, 164, 109)',
    'rgb(184, 149, 106)' => 'rgb(184, 149, 106)',
    'rgb(212, 184, 125)' => 'rgb(212, 184, 125)',
];

function scanDirectory($dir, $excludeDirs, $extensions) {
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . '/' . $item;
        
        // Skip excluded directories
        $skip = false;
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($path, '/' . $excludeDir . '/') !== false || 
                strpos($path, '/' . $excludeDir) === strlen($path) - strlen($excludeDir) - 1) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        if (is_dir($path)) {
            $files = array_merge($files, scanDirectory($path, $excludeDirs, $extensions));
        } else {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array($ext, $extensions)) {
                $files[] = $path;
            }
        }
    }
    
    return $files;
}

function processFile($filePath, $brandChanges, $colorChanges) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changes = 0;
    
    // Apply brand name changes
    foreach ($brandChanges as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        $changes += $count;
    }
    
    // Apply color changes
    foreach ($colorChanges as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        $changes += $count;
    }
    
    // Only write if changes were made
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return $changes;
    }
    
    return 0;
}

// Main execution
echo "🎨 Starting System Rebranding...\n\n";
echo "Scanning files...\n";

$files = scanDirectory($basePath, $excludeDirs, $extensions);
$totalFiles = count($files);
$processedFiles = 0;
$totalChanges = 0;

echo "Found {$totalFiles} files to process\n\n";

foreach ($files as $file) {
    $changes = processFile($file, $brandChanges, $colorChanges);
    
    if ($changes > 0) {
        $processedFiles++;
        $totalChanges += $changes;
        $relativePath = str_replace($basePath, '', $file);
        echo "✅ {$relativePath} ({$changes} changes)\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════\n";
echo "🎉 Rebranding Complete!\n";
echo "═══════════════════════════════════════\n";
echo "Total files scanned: {$totalFiles}\n";
echo "Files modified: {$processedFiles}\n";
echo "Total changes made: {$totalChanges}\n";
echo "═══════════════════════════════════════\n";
?>
