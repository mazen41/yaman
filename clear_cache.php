<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared\n";
} else {
    echo "❌ OPcache not available\n";
}
