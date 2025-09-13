<?php
// native_complex.php
// depend: PowerScripts
// uses: PHP

/**
 * Native complex script: "Script Archive & Analyzer"
 *
 * Only use native PHP
 */

$started = microtime(true);
$base = __DIR__;
$logsDir = $base . DIRECTORY_SEPARATOR . "logs";
$outDir = $base . DIRECTORY_SEPARATOR . "native_complex_output";
$archiveDir = $base . DIRECTORY_SEPARATOR . "archived";
@mkdir($logsDir, 0777, true);
@mkdir($outDir, 0777, true);
@mkdir($archiveDir, 0777, true);

echo "[native_complex] Starting archive & analyzer at " . date("c") . "\n";

function sr_read($path){
    if(!is_file($path) || !is_readable($path)) return false;
    return @file_get_contents($path);
}

function sr_parse_line($line){
    $res = array('time' => time(), 'level' => 'INFO', 'message' => trim($line));

    if(preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)){
        $res['time'] = strtotime($m[1]);
    } elseif(preg_match('/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $m2)){
        $res['time'] = strtotime($m2[1]);
    }

    if(preg_match('/\b(ERROR|WARN|WARNING|CRITICAL|FATAL)\b/i', $line, $lvl)){
        $res['level'] = strtoupper($lvl[1]);
        if($res['level'] === 'WARNING') $res['level'] = 'WARN';
    } elseif(preg_match('/\b(INFO|DEBUG|NOTICE)\b/i', $line, $lvl2)){
        $res['level'] = strtoupper($lvl2[1]);
    }

    if(preg_match('/\] ?(.*)$/', $line, $mm)){
        $res['message'] = trim($mm[1]);
    }

    return $res;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($logsDir, FilesystemIterator::SKIP_DOTS));
$files = array();
foreach($it as $f){
    if($f->isFile()){
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if(in_array($ext, array('log','txt','out'))){
            $files[] = $f->getPathname();
        }
    }
}

echo "[native_complex] Found " . count($files) . " log file(s).\n";

$stats = array(
    'total_lines' => 0,
    'by_level' => array(),
    'by_day' => array()
);

$samples = array();
$maxSamples = 2000;

foreach($files as $file){
    $content = sr_read($file);
    if($content === false) {
        echo "[native_complex] Cannot read file: {$file}\n";
        continue;
    }
    $lines = preg_split('/\r\n|\r|\n/', $content);
    foreach($lines as $ln){
        $ln = trim($ln);
        if($ln === '') continue;
        $stats['total_lines']++;
        $p = sr_parse_line($ln);
        if(!isset($stats['by_level'][$p['level']])) $stats['by_level'][$p['level']] = 0;
        $stats['by_level'][$p['level']]++;

        $day = date('Y-m-d', $p['time']);
        if(!isset($stats['by_day'][$day])) $stats['by_day'][$day] = array('count' => 0, 'by_level' => array());
        $stats['by_day'][$day]['count']++;
        if(!isset($stats['by_day'][$day]['by_level'][$p['level']])) $stats['by_day'][$day]['by_level'][$p['level']] = 0;
        $stats['by_day'][$day]['by_level'][$p['level']]++;

        if(count($samples) < $maxSamples){
            $samples[] = array('time' => date('c', $p['time']), 'level' => $p['level'], 'message' => $p['message'], 'file' => basename($file));
        }
    }
}

$ts = time();
$report = array(
    'generated' => date('c', $ts),
    'files_scanned' => count($files),
    'stats' => $stats,
    'samples_count' => count($samples)
);

$reportFile = $outDir . DIRECTORY_SEPARATOR . "report_{$ts}.json";
$csvFile = $outDir . DIRECTORY_SEPARATOR . "summary_{$ts}.csv";
$samplesFile = $outDir . DIRECTORY_SEPARATOR . "samples_{$ts}.json";

file_put_contents($reportFile, json_encode($report, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0));
file_put_contents($samplesFile, json_encode($samples, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0));

$fh = @fopen($csvFile, 'w');
if($fh){
    fputcsv($fh, array('level', 'count'));
    foreach($stats['by_level'] as $lvl => $count){
        fputcsv($fh, array($lvl, $count));
    }
    fclose($fh);
}

echo "[native_complex] Wrote report JSON: " . basename($reportFile) . "\n";
echo "[native_complex] Wrote CSV summary: " . basename($csvFile) . "\n";
echo "[native_complex] Wrote samples JSON: " . basename($samplesFile) . "\n";

$gzFile = $reportFile . ".gz";
$gz = @gzencode(@file_get_contents($reportFile));
if($gz !== false){
    file_put_contents($gzFile, $gz);
    echo "[native_complex] Wrote gzipped report: " . basename($gzFile) . "\n";
} else {
    echo "[native_complex] gzencode not available or failed.\n";
}

$rotated = 0;
$now = time();
$days = 7;
foreach($files as $file){
    $mtime = @filemtime($file);
    if($mtime !== false && ($now - $mtime) > ($days * 24 * 3600)){
        $dest = $archiveDir . DIRECTORY_SEPARATOR . basename($file) . '.' . $mtime;
        if(@rename($file, $dest)){
            $rotated++;
        } else {
            if(@copy($file, $dest)){
                @unlink($file);
                $rotated++;
            }
        }
    }
}
echo "[native_complex] Rotated {$rotated} old log file(s) to archived/\n";

$elapsed = round(microtime(true) - $started, 3);
echo "[native_complex] Done in {$elapsed}s. Lines processed: " . $stats['total_lines'] . "\n";
echo "[native_complex] Output folder: " . basename($outDir) . "\n";

return true;