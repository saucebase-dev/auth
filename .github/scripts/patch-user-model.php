<?php

/**
 * Patches a model file to add trait imports and usages.
 *
 * Usage: php patch-user-model.php <file> <Namespace\Trait:TraitName> ...
 *
 * Example:
 *   php patch-user-model.php app/Models/User.php \
 *       'Modules\Auth\Traits\Sociable:Sociable' \
 *       'Modules\Billing\Traits\Billable:Billable'
 */

if ($argc < 3) {
    echo "Usage: php patch-user-model.php <file> <Namespace\\Trait:TraitName> ...\n";
    exit(1);
}

$file = $argv[1];
$code = file_get_contents($file);

if ($code === false) {
    echo "Error: Cannot read {$file}\n";
    exit(1);
}

$imports = [];
$traits = [];

for ($i = 2; $i < $argc; $i++) {
    [$fqcn, $name] = explode(':', $argv[$i]);
    $imports[] = "use {$fqcn};";
    $traits[] = "    use {$name};";
}

// Insert new imports after the last `use` import line before `class`
$classPos = preg_match('/^class\s/m', $code, $m, PREG_OFFSET_CAPTURE);
if (! $classPos) {
    echo "Error: No class keyword found in {$file}\n";
    exit(1);
}

// Find all use-import lines before the class keyword
preg_match_all('/^use\s+[A-Z][^;]+;\s*$/m', $code, $matches, PREG_OFFSET_CAPTURE);
$lastImportEnd = 0;
foreach ($matches[0] as $match) {
    $pos = $match[1] + strlen($match[0]);
    if ($match[1] < $m[0][1]) {
        $lastImportEnd = $pos;
    }
}

if ($lastImportEnd > 0) {
    $importBlock = "\n".implode("\n", $imports);
    $code = substr($code, 0, $lastImportEnd).$importBlock.substr($code, $lastImportEnd);
}

// Add trait usages: find the last `use TraitName;` or multi-line trait block ending with `;`
// inside the class body (after the opening `{`)
preg_match('/^class\s[^{]+\{/ms', $code, $classMatch, PREG_OFFSET_CAPTURE);
if (! $classMatch) {
    echo "Error: No class body found in {$file}\n";
    exit(1);
}

$classBodyStart = $classMatch[0][1] + strlen($classMatch[0][0]);

// Find all trait-use statements inside the class (lines starting with `use` followed by `;`)
// This handles both single-line `use Foo;` and multi-line `use Foo,\n    Bar;`
preg_match_all('/^\s+use\s+[^;]+;\s*$/ms', $code, $traitMatches, PREG_OFFSET_CAPTURE, $classBodyStart);

$lastTraitEnd = 0;
foreach ($traitMatches[0] as $match) {
    $end = $match[1] + strlen($match[0]);
    if ($end > $lastTraitEnd) {
        $lastTraitEnd = $end;
    }
}

if ($lastTraitEnd > 0) {
    $traitBlock = "\n".implode("\n", $traits);
    $code = substr($code, 0, $lastTraitEnd).$traitBlock.substr($code, $lastTraitEnd);
} else {
    // No existing traits found — insert right after class opening brace
    $traitBlock = "\n".implode("\n", $traits)."\n";
    $code = substr($code, 0, $classBodyStart).$traitBlock.substr($code, $classBodyStart);
}

file_put_contents($file, $code);
echo "Patched {$file} with: ".implode(', ', array_map(fn ($t) => trim($t), $traits))."\n";
