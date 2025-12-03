<?php
/**
 * PHP Integration Test Runner for V8js
 * 
 * This runner discovers and executes PHP integration tests that extend
 * PHPIntegrationTest, outputting results in JSON format compatible with
 * the test262-coordinator.js system.
 * 
 * Usage:
 *   php PHPTestRunner.php <test-file-path>
 * 
 * Output:
 *   JSON object with test results on stdout
 */

require_once __DIR__ . '/PHPIntegrationTest.php';

class PHPTestRunner
{
    private $testFile;
    
    public function __construct(string $testFile)
    {
        $this->testFile = $testFile;
    }
    
    /**
     * Run the test file and return results
     */
    public function run(): array
    {
        if (!file_exists($this->testFile)) {
            return [
                'path' => $this->testFile,
                'status' => 'error',
                'message' => 'Test file not found',
                'duration' => 0
            ];
        }
        
        $startTime = microtime(true);
        
        try {
            // Load the test file
            require_once $this->testFile;
            
            // Find the test class (should be the last declared class)
            $classes = get_declared_classes();
            $testClass = null;
            
            // Look for a class that extends PHPIntegrationTest
            foreach (array_reverse($classes) as $class) {
                if (is_subclass_of($class, 'PHPIntegrationTest')) {
                    $testClass = $class;
                    break;
                }
            }
            
            if (!$testClass) {
                return [
                    'path' => $this->testFile,
                    'status' => 'error',
                    'message' => 'No test class found that extends PHPIntegrationTest',
                    'duration' => (microtime(true) - $startTime) * 1000
                ];
            }
            
            // Run all tests in the class
            $results = $testClass::runAll();
            
            // Aggregate results
            $stats = [
                'pass' => 0,
                'fail' => 0,
                'skip' => 0,
                'error' => 0,
                'total' => count($results)
            ];
            
            $failedTests = [];
            
            foreach ($results as $result) {
                $stats[$result['status']]++;
                
                if ($result['status'] !== 'pass' && $result['status'] !== 'skip') {
                    $failedTests[] = $result;
                }
            }
            
            // Determine overall status
            $overallStatus = 'pass';
            if ($stats['fail'] > 0 || $stats['error'] > 0) {
                $overallStatus = 'fail';
            } elseif ($stats['total'] === $stats['skip']) {
                $overallStatus = 'skip';
            }
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            return [
                'path' => $this->testFile,
                'status' => $overallStatus,
                'message' => $overallStatus === 'pass' 
                    ? "All {$stats['total']} test(s) passed" 
                    : $this->buildFailureMessage($stats, $failedTests),
                'duration' => $duration,
                'stats' => $stats,
                'results' => $results,
                'failures' => $failedTests
            ];
            
        } catch (Throwable $e) {
            return [
                'path' => $this->testFile,
                'status' => 'error',
                'message' => get_class($e) . ': ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => (microtime(true) - $startTime) * 1000
            ];
        }
    }
    
    /**
     * Build a failure message from stats and failed tests
     */
    private function buildFailureMessage(array $stats, array $failedTests): string
    {
        $messages = [];
        
        if ($stats['fail'] > 0) {
            $messages[] = "{$stats['fail']} failed";
        }
        if ($stats['error'] > 0) {
            $messages[] = "{$stats['error']} error(s)";
        }
        if ($stats['pass'] > 0) {
            $messages[] = "{$stats['pass']} passed";
        }
        if ($stats['skip'] > 0) {
            $messages[] = "{$stats['skip']} skipped";
        }
        
        $summary = implode(', ', $messages);
        
        // Add first failure details
        if (!empty($failedTests)) {
            $first = $failedTests[0];
            $summary .= " | First failure: {$first['test']} - {$first['message']}";
        }
        
        return $summary;
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        fwrite(STDERR, "Usage: php PHPTestRunner.php <test-file-path>\n");
        exit(1);
    }
    
    $testFile = $argv[1];
    $runner = new PHPTestRunner($testFile);
    $result = $runner->run();
    
    // Output JSON result (single line for coordinator parsing)
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
    
    // Exit with appropriate code
    exit($result['status'] === 'pass' ? 0 : 1);
}
