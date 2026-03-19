<?php
/**
 * cleanup_broken_tools.php
 *
 * Removes auto-generated tool files that have PHP syntax errors or nested
 * class declarations (caused by the self-contained PHP generation experiment).
 *
 * Run from chatbotapp root: php cleanup_broken_tools.php
 */

$toolsPath = __DIR__ . '/app/Ai/Tools';
$removed   = 0;
$kept      = 0;

foreach (glob($toolsPath . '/*Tool.php') as $file) {
    $content  = file_get_contents($file);
    $basename = basename($file);

    // Check for nested class declarations (LLM bug from self-contained generation)
    if (preg_match('/^\s*class\s+\w+/m', $content) > 1 ||
        substr_count($content, 'class ') > 1) {
        echo "  REMOVING (nested class): {$basename}\n";
        unlink($file);
        $removed++;
        continue;
    }

    // Check for self-contained generation markers that may have broken PHP
    if (str_contains($content, 'Self-contained: runs entirely in PHP')) {
        // These may have LLM-generated PHP bodies — verify with PHP lint
        $output = [];
        $code   = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $code);
        if ($code !== 0) {
            echo "  REMOVING (PHP syntax error): {$basename}\n";
            echo "    " . implode("\n    ", $output) . "\n";
            unlink($file);
            $removed++;
            continue;
        }
    }

    echo "  OK: {$basename}\n";
    $kept++;
}

echo "\nRemoved: {$removed} | Kept: {$kept}\n";

if ($removed > 0) {
    echo "Running composer dump-autoload...\n";
    exec('composer dump-autoload --quiet 2>&1', $out, $code);
    echo $code === 0 ? "Done.\n" : implode("\n", $out) . "\n";
}