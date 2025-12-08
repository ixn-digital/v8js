#!/usr/bin/env node
/**
 * Test262 Coordinator for V8js
 * 
 * Node.js-based test coordinator that runs Test262 tests and PHP integration tests
 * concurrently in separate PHP processes, handling crashes and collecting results.
 * 
 * Usage:
 *   node test-coordinator.js [options]
 * 
 * Options:
 *   --path=<path>        Specific test file or directory to run
 *   --filter=<regex>     Filter tests by path regex
 *   --concurrency=<n>    Number of concurrent workers (default: 4)
 *   --output=<file>      Output results to JSON file (default: test262-results.json)
 *   --verbose            Show detailed output
 *   --summary-only       Only show summary statistics
 *   --php=<path>         Path to PHP executable
 *   --extension=<path>   Path to v8js.so extension
 *   --type=<type>        Test type to run: test262, integration, or all (default: all)
 */

const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const { promisify } = require('util');

const readdir = promisify(fs.readdir);
const stat = promisify(fs.stat);
const readFile = promisify(fs.readFile);
const writeFile = promisify(fs.writeFile);

class Test262Coordinator {
    constructor(options = {}) {
        this.options = {
            concurrency: 4,
            output: 'test262-results.json',
            failuresOutput: 'test262-failures.jsonl',
            verbose: false,
            summaryOnly: false,
            phpExecutable: 'php',
            extension: 'modules/v8js.so',
            type: 'all', // test262, integration, or all
            ...options
        };

        this.configPath = path.join(__dirname, 'config.json');
        this.wrapperPath = path.join(__dirname, 'test262-wrapper.php');
        this.phpTestRunner = path.join(__dirname, 'PHPTestRunner.php');
        this.baseDir = path.resolve(__dirname, '../..');
        
        this.stats = {
            pass: 0,
            fail: 0,
            skip: 0,
            timeout: 0,
            error: 0,
            crash: 0,
            total: 0,
            test262: 0,
            integration: 0
        };
        
        this.results = [];
        this.startTime = Date.now();
        this.config = null;
        this.failuresStream = null;
    }

    async init() {
        // Load config
        try {
            const configData = await readFile(this.configPath, 'utf8');
            this.config = JSON.parse(configData);
        } catch (err) {
            console.error(`Failed to load config: ${err.message}`);
            process.exit(1);
        }

        // Verify wrapper script exists
        if (!fs.existsSync(this.wrapperPath)) {
            console.error(`Wrapper script not found: ${this.wrapperPath}`);
            process.exit(1);
        }

        // Verify PHP executable
        try {
            await this.runCommand(this.options.phpExecutable, ['--version']);
        } catch (err) {
            console.error(`PHP executable not found or not working: ${this.options.phpExecutable}`);
            process.exit(1);
        }

        // Open failures stream for live updates
        this.failuresStream = fs.createWriteStream(this.options.failuresOutput, { flags: 'w' });
    }

    async runCommand(cmd, args, options = {}) {
        return new Promise((resolve, reject) => {
            const proc = spawn(cmd, args, {
                timeout: options.timeout || 30000,
                ...options
            });

            let stdout = '';
            let stderr = '';

            if (proc.stdout) {
                proc.stdout.on('data', data => stdout += data.toString());
            }
            if (proc.stderr) {
                proc.stderr.on('data', data => stderr += data.toString());
            }

            proc.on('close', code => {
                resolve({ code, stdout, stderr });
            });

            proc.on('error', err => {
                reject(err);
            });
        });
    }

    shouldIncludeTest(testPath, filter) {
        // Apply filter regex if provided
        if (filter && !new RegExp(filter).test(testPath)) {
            return false;
        }

        // Check skip patterns from config
        for (const pattern of this.config.skipPatterns || []) {
            if (testPath.includes(pattern)) {
                return false;
            }
        }

        return true;
    }

    async discoverTests(testPath, filter = null) {
        const tests = [];

        try {
            const stats = await stat(testPath);

            if (stats.isFile()) {
                // Test262 tests: .js files (excluding _FIXTURE.js)
                if (testPath.endsWith('.js') && !testPath.includes('_FIXTURE.js') && this.shouldIncludeTest(testPath, filter)) {
                    tests.push({ path: testPath, type: 'test262' });
                }
                // PHP integration tests: .php files
                else if (testPath.endsWith('.php') && this.shouldIncludeTest(testPath, filter)) {
                    tests.push({ path: testPath, type: 'integration' });
                }
                return tests;
            }

            if (stats.isDirectory()) {
                const entries = await readdir(testPath);
                
                for (const entry of entries) {
                    const fullPath = path.join(testPath, entry);
                    const subTests = await this.discoverTests(fullPath, filter);
                    tests.push(...subTests);
                }
            }
        } catch (err) {
            // Ignore errors for non-existent paths
        }

        return tests;
    }

    async runTest(test) {
        const testPath = test.path;
        const testType = test.type;
        
        const result = {
            path: testPath,
            type: testType,
            status: 'unknown',
            message: null,
            duration: 0
        };

        const startTime = Date.now();

        try {
            const timeout = (this.config.timeout || 10000) + 5000; // Add buffer to PHP timeout
            
            // Choose the appropriate runner based on test type
            const runnerScript = testType === 'integration' ? this.phpTestRunner : this.wrapperPath;
            
            const { code, stdout, stderr } = await this.runCommand(
                this.options.phpExecutable,
                [
                    `-dextension=${this.options.extension}`,
                    runnerScript,
                    testPath
                ],
                { timeout }
            );

            result.duration = Date.now() - startTime;

            // Check for segfault or crash
            if (code === 139 || code === 137 || code < 0) {
                result.status = 'crash';
                result.message = `Process crashed with exit code ${code}`;
                if (stderr) {
                    result.message += `: ${stderr.substring(0, 200)}`;
                }
                return result;
            }

            // Parse JSON output from wrapper/runner
            try {
                const output = stdout.trim().split('\n').pop(); // Get last line
                const parsed = JSON.parse(output);
                return { ...parsed, type: testType, duration: result.duration };
            } catch (parseErr) {
                result.status = 'error';
                result.message = `Failed to parse output: ${parseErr.message}`;
                if (stdout) {
                    result.message += ` | stdout: ${stdout.substring(0, 200)}`;
                }
                return result;
            }

        } catch (err) {
            result.duration = Date.now() - startTime;
            
            if (err.killed || err.signal) {
                result.status = 'timeout';
                result.message = 'Test execution timed out';
            } else {
                result.status = 'error';
                result.message = `Execution error: ${err.message}`;
            }
            
            return result;
        }
    }

    async runTestsConcurrently(tests) {
        const total = tests.length;
        let completed = 0;
        let running = 0;
        let index = 0;

        const progressInterval = setInterval(() => {
            if (!this.options.verbose) {
                const status = `[${completed}/${total}] Pass: ${this.stats.pass} Fail: ${this.stats.fail} Skip: ${this.stats.skip} Crash: ${this.stats.crash} Running: ${running}`;
                process.stdout.write(`\r${status}`);
            }
        }, 100);

        const runNext = async () => {
            if (index >= tests.length) {
                return;
            }

            const test = tests[index++];
            running++;

            if (this.options.verbose) {
                const testName = path.basename(test.path);
                const testType = test.type === 'integration' ? '[PHP]' : '[JS]';
                console.log(`[${completed + 1}/${total}] ${testType} Running: ${testName}`);
            }

            try {
                const result = await this.runTest(test);
                this.processResult(result);
                
                if (this.options.verbose) {
                    console.log(`  → ${result.status}${result.message ? ': ' + result.message : ''}`);
                }
            } catch (err) {
                console.error(`Error running test ${test.path}:`, err);
            }

            completed++;
            running--;

            // Run next test
            await runNext();
        };

        // Start initial batch of workers
        const workers = [];
        for (let i = 0; i < this.options.concurrency && i < tests.length; i++) {
            workers.push(runNext());
        }

        // Wait for all workers to complete
        await Promise.all(workers);

        if (progressInterval) {
            clearInterval(progressInterval);
        }
        
        if (!this.options.verbose) {
            // Final update with all stats
            const status = `[${completed}/${total}] Pass: ${this.stats.pass} Fail: ${this.stats.fail} Skip: ${this.stats.skip} Crash: ${this.stats.crash}`;
            process.stdout.write(`\r${status}\n`);
        }
    }

    processResult(result) {
        // Track test type
        if (result.type === 'integration') {
            this.stats.integration++;
        } else {
            this.stats.test262++;
        }
        
        // For integration tests with individual test results, write each failure separately
        if (result.type === 'integration' && result.results && Array.isArray(result.results)) {
            // Write one line per failed test method
            for (const testResult of result.results) {
                if (testResult.status !== 'pass' && testResult.status !== 'skip') {
                    const failureEntry = {
                        path: result.path,
                        type: result.type,
                        test: testResult.test,
                        class: testResult.class,
                        status: testResult.status,
                        message: testResult.message,
                        duration: testResult.duration,
                        assertions: testResult.assertions
                    };
                    
                    if (testResult.trace) {
                        failureEntry.trace = testResult.trace;
                    }
                    
                    // Write to JSONL stream
                    if (this.failuresStream) {
                        this.failuresStream.write(JSON.stringify(failureEntry) + '\n');
                    }
                }
            }
            
            // Still store the file-level result for summary
            if (result.status !== 'pass' && result.status !== 'skip') {
                this.results.push(result);
            }
        } else {
            // For Test262 tests or integration tests without detailed results,
            // write the entire result as before
            if (result.status !== 'pass' && result.status !== 'skip') {
                this.results.push(result);
                
                // Write to JSONL stream immediately for live updates
                if (this.failuresStream) {
                    this.failuresStream.write(JSON.stringify(result) + '\n');
                }
            }
        }
        
        const status = result.status;
        if (this.stats.hasOwnProperty(status)) {
            this.stats[status]++;
        }
    }

    async outputResults() {
        const duration = (Date.now() - this.startTime) / 1000;

        // Close the failures stream
        if (this.failuresStream) {
            this.failuresStream.end();
        }

        const output = {
            timestamp: new Date().toISOString(),
            duration,
            stats: this.stats,
            results: this.results
        };

        await writeFile(this.options.output, JSON.stringify(output, null, 2));
        console.log(`\nResults saved to: ${this.options.output}`);
        console.log(`Failures saved to: ${this.options.failuresOutput} (JSONL format)`);

        this.printSummary(duration);
    }

    printSummary(duration) {
        const total = this.stats.total;
        const passRate = total > 0 ? (this.stats.pass / total * 100) : 0;

        console.log('\n' + '='.repeat(70));
        console.log('TEST SUMMARY');
        console.log('='.repeat(70));
        console.log(`Total tests:  ${total.toString().padStart(6)}`);
        console.log(`  Test262:    ${this.stats.test262.toString().padStart(6)}`);
        console.log(`  PHP Tests:  ${this.stats.integration.toString().padStart(6)}`);
        console.log('-'.repeat(70));
        console.log(`Passed:       ${this.stats.pass.toString().padStart(6)} (${passRate.toFixed(1)}%)`);
        console.log(`Failed:       ${this.stats.fail.toString().padStart(6)} (${total > 0 ? (this.stats.fail / total * 100).toFixed(1) : 0}%)`);
        console.log(`Skipped:      ${this.stats.skip.toString().padStart(6)} (${total > 0 ? (this.stats.skip / total * 100).toFixed(1) : 0}%)`);
        console.log(`Timeout:      ${this.stats.timeout.toString().padStart(6)}`);
        console.log(`Crash:        ${this.stats.crash.toString().padStart(6)}`);
        console.log(`Error:        ${this.stats.error.toString().padStart(6)}`);
        console.log('-'.repeat(70));
        console.log(`Duration:     ${duration.toFixed(2)} seconds`);
        console.log(`Throughput:   ${(total / duration).toFixed(1)} tests/second`);
        console.log('='.repeat(70));

        if (passRate >= 90) {
            console.log('✓ EXCELLENT test compliance');
        } else if (passRate >= 70) {
            console.log('✓ GOOD test compliance');
        } else if (passRate >= 50) {
            console.log('⚠ MODERATE test compliance');
        } else {
            console.log('✗ LOW test compliance');
        }
        console.log();
    }

    async run() {
        await this.init();

        // Determine test paths
        let testPaths = [];
        if (this.options.path) {
            testPaths = [this.options.path];
        } else {
            // Use configured test paths for Test262
            if (this.options.type === 'all' || this.options.type === 'test262') {
                for (const configPath of this.config.testPaths || []) {
                    const fullPath = path.join(this.baseDir, configPath);
                    if (fs.existsSync(fullPath)) {
                        testPaths.push(fullPath);
                    }
                }
            }
            
            // Add PHP integration test path
            if (this.options.type === 'all' || this.options.type === 'integration') {
                const integrationPath = path.join(__dirname, 'integration');
                if (fs.existsSync(integrationPath)) {
                    testPaths.push(integrationPath);
                }
            }
        }

        // Discover all tests
        console.log('Discovering tests...');
        const allTests = [];
        for (const testPath of testPaths) {
            const tests = await this.discoverTests(testPath, this.options.filter);
            allTests.push(...tests);
        }

        // Filter by test type if specified
        let filteredTests = allTests;
        if (this.options.type !== 'all') {
            filteredTests = allTests.filter(test => test.type === this.options.type);
        }

        this.stats.total = filteredTests.length;

        if (filteredTests.length === 0) {
            console.error('No tests found');
            return;
        }

        console.log(`Found ${filteredTests.length} tests to run with ${this.options.concurrency} concurrent workers`);
        if (this.options.type === 'all') {
            const test262Count = filteredTests.filter(t => t.type === 'test262').length;
            const integrationCount = filteredTests.filter(t => t.type === 'integration').length;
            console.log(`  - ${test262Count} Test262 tests`);
            console.log(`  - ${integrationCount} PHP integration tests`);
        }
        console.log();

        // Run tests
        await this.runTestsConcurrently(filteredTests);

        // Output results
        await this.outputResults();
    }
}

// Parse command line arguments
function parseArgs() {
    const args = process.argv.slice(2);
    const options = {};

    for (const arg of args) {
        if (arg === '--help') {
            console.log(`
Test262 and PHP Integration Test Coordinator for V8js

Usage:
  node test-coordinator.js [options]

Options:
  --path=<path>        Specific test file or directory to run
  --filter=<regex>     Filter tests by path regex
  --concurrency=<n>    Number of concurrent workers (default: 4)
  --output=<file>      Output results to JSON file (default: test262-results.json)
  --type=<type>        Test type: test262, integration, or all (default: all)
  --verbose            Show detailed output
  --summary-only       Only show summary statistics
  --php=<path>         Path to PHP executable (default: php)
  --extension=<path>   Path to v8js.so extension (default: modules/v8js.so)
  --help               Show this help message

Test Types:
  test262              Run only Test262 ECMAScript conformance tests
  integration          Run only PHP integration tests
  all                  Run both Test262 and PHP integration tests (default)

Examples:
  # Run all tests (Test262 + PHP integration) with 8 workers
  node test-coordinator.js --concurrency=8

  # Run only PHP integration tests
  node test-coordinator.js --type=integration

  # Run specific test directory
  node test-coordinator.js --path=test/integration/

  # Run tests matching a pattern
  node test-coordinator.js --filter="arrow-function"

  # Run with custom PHP
  node test-coordinator.js --php=/usr/local/bin/php --extension=/usr/local/lib/php/extensions/v8js.so
`);
            process.exit(0);
        } else if (arg.startsWith('--')) {
            const [key, value] = arg.slice(2).split('=');
            
            if (value === undefined) {
                options[key] = true;
            } else if (!isNaN(value)) {
                options[key] = parseInt(value, 10);
            } else {
                options[key] = value;
            }
        }
    }

    // Map some option names
    if (options.php) {
        options.phpExecutable = options.php;
        delete options.php;
    }

    return options;
}

// Main
const options = parseArgs();
const coordinator = new Test262Coordinator(options);

coordinator.run().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
