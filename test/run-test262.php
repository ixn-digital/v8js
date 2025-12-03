#!/usr/bin/env php
<?php
/**
 * Test262 Runner for V8js
 * 
 * This script discovers and executes Test262 conformance tests,
 * generating comprehensive reports on ECMAScript compliance.
 * 
 * Usage:
 *   php run-test262.php [options]
 * 
 * Options:
 *   --path=<path>     Specific test file or directory to run
 *   --filter=<regex>  Filter tests by path regex
 *   --parallel=<n>    Number of parallel workers (default: 1)
 *   --output=<file>   Output results to JSON file (default: test262-results.json)
 *   --verbose         Show detailed output
 *   --summary-only    Only show summary statistics
 */

require_once __DIR__ . '/Test262Adapter.php';

class Test262Runner
{
    private $config;
    private $adapter;
    private $results = [];
    private $stats = [
        'pass' => 0,
        'fail' => 0,
        'skip' => 0,
        'timeout' => 0,
        'error' => 0,
        'crash' => 0,
        'total' => 0
    ];
    private $verbose = false;
    private $summaryOnly = false;
    private $startTime;
    private $phpExecutable;
    private $wrapperScript;

    public function __construct()
    {
        $configPath = __DIR__ . '/config.json';
        if (!file_exists($configPath)) {
            $this->error("Config file not found: $configPath");
            exit(1);
        }

        $this->config = json_decode(file_get_contents($configPath), true);
        if ($this->config === null) {
            $this->error("Failed to parse config file");
            exit(1);
        }

        $this->adapter = new Test262Adapter($this->config);
        $this->startTime = microtime(true);
        
        // Detect PHP executable and extension path
        $this->phpExecutable = PHP_BINARY;
        $this->wrapperScript = __DIR__ . '/test262-wrapper.php';
    }

    /**
     * Discover test files in a directory
     */
    private function discoverTests($path, $filter = null)
    {
        $tests = [];
        
        if (is_file($path)) {
            if ($this->shouldIncludeTest($path, $filter)) {
                $tests[] = $path;
            }
            return $tests;
        }

        if (!is_dir($path)) {
            return $tests;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $filePath = $file->getPathname();
                // Exclude _FIXTURE.js files - they are imported by other tests, not standalone
                if (strpos($filePath, '_FIXTURE.js') === false && $this->shouldIncludeTest($filePath, $filter)) {
                    $tests[] = $filePath;
                }
            }
        }

        return $tests;
    }

    /**
     * Check if test should be included
     */
    private function shouldIncludeTest($path, $filter)
    {
        // Apply filter regex if provided
        if ($filter !== null && !preg_match($filter, $path)) {
            return false;
        }

        // Check skip patterns from config
        foreach ($this->config['skipPatterns'] as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run tests
     */
    public function run($options = [])
    {
        $this->verbose = $options['verbose'] ?? false;
        $this->summaryOnly = $options['summary-only'] ?? false;

        // Determine test paths
        $testPaths = [];
        if (isset($options['path'])) {
            $testPaths[] = $options['path'];
        } else {
            // Use configured test paths
            $baseDir = dirname(dirname(__DIR__));
            foreach ($this->config['testPaths'] as $path) {
                $fullPath = $baseDir . '/' . $path;
                if (file_exists($fullPath)) {
                    $testPaths[] = $fullPath;
                }
            }
        }

        // Discover all tests
        $filter = isset($options['filter']) ? $options['filter'] : null;
        $allTests = [];
        foreach ($testPaths as $path) {
            $tests = $this->discoverTests($path, $filter);
            $allTests = array_merge($allTests, $tests);
        }

        $this->stats['total'] = count($allTests);
        
        if ($this->stats['total'] === 0) {
            $this->error("No tests found");
            return;
        }

        $this->log("Found {$this->stats['total']} tests to run\n");

        // Run tests
        $parallel = $options['parallel'] ?? 1;
        
        if ($parallel > 1) {
            $this->runParallel($allTests, $parallel);
        } else {
            $this->runSequential($allTests);
        }

        // Output results
        $this->outputResults($options['output'] ?? 'test262-results.json');
    }

    /**
     * Run tests sequentially
     */
    private function runSequential($tests)
    {
        $count = 0;
        $total = count($tests);

        foreach ($tests as $testPath) {
            $count++;
            
            if (!$this->summaryOnly) {
                $this->log("[$count/$total] Running: " . basename($testPath));
            }

            $result = $this->adapter->runTest($testPath);
            $this->processResult($result);

            if ($this->verbose) {
                $this->log(" - {$result['status']}");
                if ($result['message']) {
                    $this->log("  Message: {$result['message']}");
                }
            } elseif (!$this->summaryOnly) {
                $this->log(" [{$result['status']}]");
            }
        }
    }

    /**
     * Run tests in parallel (simplified version)
     */
    private function runParallel($tests, $workers)
    {
        $this->log("Parallel execution not yet implemented, falling back to sequential");
        $this->runSequential($tests);
    }

    /**
     * Process test result
     */
    private function processResult($result)
    {
        // Only store non-passing tests, excluding unsupported feature skips
        if ($result['status'] !== 'pass' && $result['status'] !== 'skip') {
            $this->results[] = $result;
        }
        
        $status = $result['status'];
        if (isset($this->stats[$status])) {
            $this->stats[$status]++;
        }
    }

    /**
     * Output results to file and console
     */
    private function outputResults($outputFile)
    {
        $duration = microtime(true) - $this->startTime;

        // Save detailed results to JSON
        $output = [
            'timestamp' => date('Y-m-d H:i:s'),
            'duration' => $duration,
            'stats' => $this->stats,
            'results' => $this->results
        ];

        file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT));
        $this->log("\nResults saved to: $outputFile");

        // Print summary
        $this->printSummary($duration);
    }

    /**
     * Print summary statistics
     */
    private function printSummary($duration)
    {
        $this->log("\n" . str_repeat("=", 70));
        $this->log("TEST262 SUMMARY");
        $this->log(str_repeat("=", 70));
        $this->log(sprintf("Total tests:  %6d", $this->stats['total']));
        $this->log(sprintf("Passed:       %6d (%.1f%%)", 
            $this->stats['pass'], 
            $this->stats['total'] > 0 ? ($this->stats['pass'] / $this->stats['total'] * 100) : 0
        ));
        $this->log(sprintf("Failed:       %6d (%.1f%%)", 
            $this->stats['fail'], 
            $this->stats['total'] > 0 ? ($this->stats['fail'] / $this->stats['total'] * 100) : 0
        ));
        $this->log(sprintf("Skipped:      %6d (%.1f%%)", 
            $this->stats['skip'], 
            $this->stats['total'] > 0 ? ($this->stats['skip'] / $this->stats['total'] * 100) : 0
        ));
        $this->log(sprintf("Timeout:      %6d", $this->stats['timeout']));
        $this->log(sprintf("Error:        %6d", $this->stats['error']));
        $this->log(str_repeat("-", 70));
        $this->log(sprintf("Duration:     %.2f seconds", $duration));
        $this->log(str_repeat("=", 70) . "\n");

        // Success/failure indication
        $passRate = $this->stats['total'] > 0 ? ($this->stats['pass'] / $this->stats['total'] * 100) : 0;
        if ($passRate >= 90) {
            $this->log("✓ EXCELLENT compliance with Test262");
        } elseif ($passRate >= 70) {
            $this->log("✓ GOOD compliance with Test262");
        } elseif ($passRate >= 50) {
            $this->log("⚠ MODERATE compliance with Test262");
        } else {
            $this->log("✗ LOW compliance with Test262");
        }
    }

    /**
     * Log message
     */
    private function log($message)
    {
        echo $message . "\n";
    }

    /**
     * Error message
     */
    private function error($message)
    {
        fwrite(STDERR, "ERROR: $message\n");
    }
}

// Parse command line options
$options = [];
$args = array_slice($argv, 1);

foreach ($args as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0];
        $value = isset($parts[1]) ? $parts[1] : true;
        $options[$key] = $value;
    }
}

// Show help
if (isset($options['help'])) {
    echo <<<HELP
Test262 Runner for V8js

Usage:
  php run-test262.php [options]

Options:
  --path=<path>       Specific test file or directory to run
  --filter=<regex>    Filter tests by path regex
  --parallel=<n>      Number of parallel workers (default: 1)
  --output=<file>     Output results to JSON file (default: test262-results.json)
  --verbose           Show detailed output
  --summary-only      Only show summary statistics
  --help              Show this help message

Examples:
  # Run all configured tests
  php run-test262.php

  # Run specific test file
  php run-test262.php --path=test262/test/language/expressions/arrow-function/basic.js

  # Run tests matching a pattern
  php run-test262.php --filter="/arrow-function/"

  # Run with verbose output
  php run-test262.php --verbose

HELP;
    exit(0);
}

// Run tests
$runner = new Test262Runner();
$runner->run($options);
