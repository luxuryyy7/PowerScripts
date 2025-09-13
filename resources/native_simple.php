<?php
// native_simple.php
// depend: PowerScripts
// uses: PHP

/**
 * Native simple script
 * - Use only native PHP
 */

$base = __DIR__;
$out = $base . DIRECTORY_SEPARATOR . "native_simple_output_" . time() . ".txt";

echo "[native_simple] Starting...\n";

$content = "Native simple executed at " . date("c") . " by PowerScripts\n";
if(@file_put_contents($out, $content) !== false){
    echo "[native_simple] Wrote output file: " . basename($out) . "\n";
} else {
    echo "[native_simple] Failed to write output file: " . $out . "\n";
}

echo "[native_simple] Done.\n";

return true;