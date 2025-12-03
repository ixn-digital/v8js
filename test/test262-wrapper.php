#!/usr/bin/env php
<?php
/**
 * Single Test Wrapper for Test262
 * 
 * This script is designed to run a single test262 test in isolation.
 * Used by parallel execution systems or for debugging individual tests.
 * 
 * Usage:
 *   php test262-wrapper.php <test-file-path>
 * 
 * Output:
 *   JSON result on stdout
 */

require_once __DIR__ . '/Test262Adapter.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: {$argv[0]} <test-file-path>\n");
    exit(1);
}

$testPath = $argv[1];

if (!file_exists($testPath)) {
    $result = [
        'path' => $testPath,
        'status' => 'error',
        'message' => 'Test file not found',
        'duration' => 0
    ];
    echo json_encode($result) . "\n";
    exit(1);
}

// Load config
$configPath = __DIR__ . '/config.json';
$config = [];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

// Run test
$adapter = new Test262Adapter($config);
$result = $adapter->runTest($testPath);

// Output result as JSON
echo json_encode($result) . "\n";

// Exit with appropriate code
exit($result['status'] === 'pass' ? 0 : 1);
