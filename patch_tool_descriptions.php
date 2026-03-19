<?php
/**
 * patch_tool_descriptions.php
 *
 * Run this ONCE from your Laravel project root to update all existing Tool
 * description() strings with enriched keyword text.
 *
 * Usage (from chatbotapp root):
 *   php patch_tool_descriptions.php
 *
 * What it does:
 *   - Reads every *Tool.php in app/Ai/Tools/
 *   - Finds the description() return string
 *   - Appends domain keyword expansions based on what's already there
 *   - Writes the file back
 *   - Runs composer dump-autoload when done
 */

$toolsPath = __DIR__ . '/app/Ai/Tools';

// Domain synonym map — same logic as tool_generator.py
$synonyms = [
    'arithmetic'   => 'add subtract multiply divide plus minus times calculate compute',
    'math'         => 'arithmetic calculate compute evaluate number formula',
    'calculator'   => 'calculate math arithmetic compute add subtract multiply divide',
    'calculate'    => 'compute evaluate solve work out arithmetic math',
    'trigonometry' => 'trig sin cos tan sine cosine tangent angle degrees radians',
    'trig'         => 'trigonometry sine cosine tangent sin cos tan angle',
    'sine'         => 'sin trigonometry trig angle math calculate',
    'cosine'       => 'cos trigonometry trig angle math calculate',
    'tangent'      => 'tan trigonometry trig angle math calculate',
    'log'          => 'logarithm ln log10 math calculate',
    'logarithm'    => 'log ln natural math calculate',
    'pi'           => '3.14159 circle radius circumference math',
    'factorial'    => 'n! permutation combination math calculate',
    'fibonacci'    => 'sequence series number math generate',
    'prime'        => 'prime number divisible math find',
    'random'       => 'generate number list produce',
    'statistics'   => 'mean average median mode sum calculate',
    'percentage'   => 'percent ratio proportion calculate',
    'generate'     => 'create make build produce',
    'create'       => 'generate make build produce',
    'make'         => 'create generate build produce',
    'find'         => 'search get fetch retrieve locate',
    'search'       => 'find get fetch retrieve lookup',
    'plan'         => 'schedule organise organize create build',
    'schedule'     => 'plan organise organize create',
    'list'         => 'show display enumerate get find',
    'calculate'    => 'compute evaluate solve work out',
    'compute'      => 'calculate evaluate solve',
    'workout'      => 'exercise fitness training gym routine plan',
    'exercise'     => 'workout fitness training gym activity',
    'fitness'      => 'workout exercise health gym training',
    'routine'      => 'plan schedule workout exercise daily',
    'meal'         => 'food diet nutrition eating plan recipe',
    'recipe'       => 'meal food cooking dish ingredient prepare',
    'diet'         => 'nutrition meal food eating health',
    'guide'        => 'tutorial learn how-to beginner introduction',
    'tutorial'     => 'guide learn beginner introduction',
    'beginner'     => 'introduction basic starter learn guide',
    'trip'         => 'travel journey itinerary vacation',
    'itinerary'    => 'trip travel schedule plan journey',
    'hotel'        => 'accommodation stay lodging room',
    'attraction'   => 'landmark sight tour destination visit',
    'time'         => 'clock hour minute current now',
    'date'         => 'calendar day month year today current',
    'weather'      => 'forecast temperature climate conditions',
    'number'       => 'integer value digit numeric',
    'convert'      => 'transform change translate',
    'count'        => 'tally total number sum',
    'extract'      => 'parse read retrieve get',
    'run'          => 'execute perform do',
];

$files = glob($toolsPath . '/*Tool.php');
$patched = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);

    // Find the description() return string
    if (!preg_match("/return\s+'([^']+)';/", $content, $matches)) {
        echo "  SKIP (no description found): " . basename($file) . "\n";
        continue;
    }

    $currentDesc = $matches[1];
    $descLower   = strtolower($currentDesc);

    // Collect synonym expansions for words already in the description
    $additions = [];
    foreach ($synonyms as $word => $expansion) {
        if (str_contains($descLower, $word) && !str_contains($descLower, $expansion)) {
            $additions[] = $expansion;
        }
    }

    if (empty($additions)) {
        echo "  OK (already rich): " . basename($file) . "\n";
        continue;
    }

    $enriched    = $currentDesc . ' ' . implode(' ', array_unique($additions));
    $enriched    = str_replace("'", "\\'", $enriched); // escape for PHP string
    $newContent  = preg_replace(
        "/return\s+'[^']+';/",
        "return '{$enriched}';",
        $content,
        1
    );

    file_put_contents($file, $newContent);
    echo "  PATCHED: " . basename($file) . "\n";
    $patched++;
}

echo "\nDone. Patched {$patched} of " . count($files) . " tool files.\n";

// Run composer dump-autoload
echo "Running composer dump-autoload...\n";
exec('composer dump-autoload --quiet 2>&1', $output, $code);
echo $code === 0 ? "Done.\n" : "Warning: " . implode("\n", $output) . "\n";