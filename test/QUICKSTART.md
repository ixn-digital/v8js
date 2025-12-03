# Test262 Quick Setup Guide

## Prerequisites

1. **Build the V8js extension**:

```bash
phpize
./configure --with-v8js=/path/to/v8
make
```

2. **Install Node.js** (recommended for concurrent testing):

```bash
# macOS
brew install node

# Ubuntu/Debian
sudo apt install nodejs npm

# Or download from https://nodejs.org/
```

3. **Initialize Test262 submodule**:

```bash
git submodule update --init test262
```

## Quick Test

### Using Make (Recommended)

```bash
# Run with default settings (4 concurrent workers)
make test262

# Run with 8 workers for faster execution
make test262-fast

# Run verbose mode
make test262-verbose

# Run filtered tests
make test262-filter FILTER="arrow-function"
```

### Using Node.js Coordinator Directly

```bash
# Test a small set with 4 workers
node tests/test262/test262-coordinator.js \
    --path=test262/test/language/expressions/arrow-function/ \
    --concurrency=4 \
    --php=php \
    --extension=modules/v8js.so
```

### Using PHP Runner (Fallback)

```bash
# Test a single file
php -dextension=modules/v8js.so tests/test262/test262-wrapper.php \
    test262/test/language/expressions/addition/S11.6.1_A1.js

# Test arrow functions (sequential)
php -dextension=modules/v8js.so tests/test262/run-test262.php \
    --path=test262/test/language/expressions/arrow-function/ \
    --summary-only
```

## Full Test Run

Run the complete test suite (29,000+ tests):

```bash
# With Node.js coordinator (recommended - handles crashes)
make test262

# Or with custom concurrency
node tests/test262/test262-coordinator.js \
    --concurrency=8 \
    --summary-only \
    --php=php \
    --extension=modules/v8js.so
```

This will test:

- Language expressions
- Language statements
- Built-in Array methods
- Built-in Object methods
- Built-in String methods
- Promise implementation
- Map and Set collections

Results are saved to `test262-results.json`.

## Why Node.js Coordinator?

The Node.js coordinator provides:

- **Crash isolation**: Segfaults don't stop the entire test suite
- **Concurrency**: 4-8x faster with parallel execution
- **Better reporting**: Separate crash statistics
- **Reliability**: Continues even when tests crash

## Expected Results

On first run, expect:

- **Pass**: 60-80% (core ES6+ features)
- **Skip**: 10-30% (unsupported features like BigInt, Atomics)
- **Fail**: 5-15% (edge cases, newer features)
- **Crash**: 0-5% (issues to investigate)

Track progress over time as you improve V8js!
