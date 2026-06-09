<?php

/**
 * Fails (non-zero exit) when the Clover line coverage is below a minimum.
 *
 * Usage: php bin/check-coverage.php <clover.xml> <min-percent>
 */

declare(strict_types=1);

$file = $argv[1] ?? 'coverage.xml';
$min = (float) ($argv[2] ?? 88);

if (!\is_file($file)) {
    \fwrite(\STDERR, "Coverage report not found: {$file}\n");
    exit(2);
}

$xml = \simplexml_load_file($file);
if ($xml === false || !isset($xml->project->metrics)) {
    \fwrite(\STDERR, "Unable to read coverage metrics from: {$file}\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements > 0 ? \round($covered / $statements * 100, 2) : 100.0;

\printf("Line coverage: %.2f%% (%d/%d) — minimum %.2f%%\n", $percent, $covered, $statements, $min);

exit($percent + 1e-9 < $min ? 1 : 0);
