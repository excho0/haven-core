<?php

// ---------- 🎨 Styling Helpers ----------
function color($text, $code) { return "\033[" . $code . "m" . $text . "\033[0m"; }
function success($msg) { return color("✅ $msg", "32"); }
function error($msg)   { return color("❌ $msg", "31"); }
function info($msg)    { return color("💡 $msg", "36"); }
function bold($msg)    { return color($msg, "1"); }

function separator($label = null) {
    $line = str_repeat("─", 40);
    return $label ? bold("\n$line $label $line\n") : bold("\n$line\n");
}

function ask($question, $default = null) {
    $prompt = $default ? " [$default]" : '';
    echo info($question) . $prompt . ": ";
    $input = trim(readline());
    return $input ?: $default;
}

// ---------- 📁 File Logic ----------
function getCssFiles() {
    return array_filter(glob(__DIR__ . '/*.css'), fn($f) => !preg_match('/\.min\.css$/', $f));
}

function chooseFiles($files) {
    echo separator("Select CSS Files (comma-separated indexes)");
    foreach ($files as $index => $file) {
        echo "  " . color("[$index]", "33") . " " . basename($file) . "\n";
    }
    $input = ask("Enter numbers (e.g., 0,2,3)");
    $indices = array_map('trim', explode(',', $input));
    $selected = array_filter($indices, fn($i) => isset($files[(int)$i]));
    return array_map(fn($i) => $files[(int)$i], $selected);
}

// ---------- 🧼 Minification ----------
function minifyCss($css, $style = 'compact') {
    $css = preg_replace('/\/\*[^!][\s\S]*?\*\//', '', $css);
    $css = preg_replace([
        '/\s*([{}:;,])\s*/',
        '/\s+/',
        '/;}/',
    ], ['$1', ' ', '}'], $css);
    $css = trim($css);

    return $style === 'compact'
        ? str_replace(["\n", "\r"], '', $css)
        : str_replace('}', "}\n", $css);
}

// ---------- 🔁 Watch Mode ----------
function watchFile($path, $callback) {
    echo info("🔁 Watching " . basename($path) . " for changes...\n");
    $lastModified = filemtime($path);
    while (true) {
        clearstatcache();
        $current = filemtime($path);
        if ($current !== $lastModified) {
            $lastModified = $current;
            $callback();
        }
        sleep(1);
    }
}

// ---------- 🚀 Main Flow ----------
echo bold("\n💎 CSS Minifier CLI") . "\n";

$cssFiles = getCssFiles();
if (empty($cssFiles)) {
    echo error("No .css files found in this folder (excluding .min.css).\n");
    exit(1);
}

$inputFiles = chooseFiles($cssFiles);
if (empty($inputFiles)) {
    echo error("No valid selections.\n");
    exit(1);
}

$minStyleInput = ask("Choose minify style: (1) One Line / (2) Readable", '1');
$style = $minStyleInput === '2' ? 'readable' : 'compact';

echo separator("Ready to Minify");

foreach ($inputFiles as $inputFile) {
    $outputFile = preg_replace('/\.css$/', '.min.css', $inputFile);

    echo "📄 Input: " . basename($inputFile) . "\n";
    echo "💾 Output: " . basename($outputFile) . "\n";

    $css = file_get_contents($inputFile);
    $minified = minifyCss($css, $style);
    if (file_put_contents($outputFile, $minified)) {
        echo success("Saved: ") . basename($outputFile) . "\n";
    } else {
        echo error("Failed to write output file.\n");
    }
}

$watch = ask("🔄 Enable watch mode for all selected files? (y/n)", 'n');
if (strtolower($watch) === 'y') {
    if (!function_exists('pcntl_fork')) {
        echo error("Watch mode requires the PCNTL extension (Unix systems only).\n");
        exit(1);
    }

    foreach ($inputFiles as $inputFile) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            echo error("❌ Failed to fork process.\n");
            exit(1);
        } elseif ($pid === 0) {
            $outputFile = preg_replace('/\.css$/', '.min.css', $inputFile);
            watchFile($inputFile, function() use ($inputFile, $outputFile, $style) {
                $css = file_get_contents($inputFile);
                $minified = minifyCss($css, $style);
                if (file_put_contents($outputFile, $minified)) {
                    echo success("Updated: ") . basename($outputFile) . " @ " . date('H:i:s') . "\n";
                } else {
                    echo error("❌ Failed to write during watch for ") . basename($inputFile) . "\n";
                }
            });
            exit; // child process ends here
        }
    }

    while (pcntl_wait($status) > 0); // parent waits
} else {
    echo info("👋 Done. Thanks for using the CLI.\n");
}
