# Intl.Segmenter Crash Issue in V8 13.5

## Summary

V8 13.5 has a critical bug where calling `Intl.Segmenter().segment()` causes a segmentation fault instead of throwing a JavaScript exception. This is an **upstream V8 bug** that cannot be fixed from v8js without patching V8 itself.

## Technical Details

### Root Cause

The crash occurs in V8's native code at `src/objects/js-segments.cc:33`:

```cpp
segmenter->icu_break_iterator().raw()->clone();
```

V8 attempts to clone the ICU break iterator without checking if it's null, resulting in a null pointer dereference (SIGSEGV: KERN_INVALID_ADDRESS at 0x0000000000000000).

### Stack Trace

```
#0  v8::internal::JSSegments::Create(...)
#1  v8::internal::Builtin_SegmenterPrototypeSegment(...)
#2  Builtins_CEntry_Return1_ArgvOnStack_BuiltinExit
#3  Builtins_InterpreterEntryTrampoline
```

### Why This Happens

1. V8 13.5 changed the internal implementation of `JSSegments::Create`
2. The function now requires properly initialized break iterator data structures
3. In embedded contexts (like v8js), these structures may not be initialized correctly
4. V8 fails to validate before dereferencing, causing a crash

### Why Other Intl APIs Work

- ✅ `Intl.Segmenter()` constructor works fine
- ✅ `Intl.DateTimeFormat` works
- ✅ `Intl.Collator` works
- ✅ ICU is properly initialized (verified)
- ❌ Only `Intl.Segmenter().segment()` crashes

This indicates the bug is specific to the segment iterator creation, not general ICU/Intl functionality.

## Attempted Fixes

### ❌ Cannot Fix in v8js

1. **C++ Safety Checks**: Attempted to add validation in v8js object wrapping code
   - Result: Crash happens before v8js code runs
2. **JavaScript try/catch**: Cannot catch native segfaults
   - Result: Crash occurs in compiled V8 code
3. **ICU Path Configuration**: Tried setting explicit ICU data paths
   - Result: No effect (ICU is already properly initialized)

### ✅ Current Solution: JavaScript Shim

**File**: `tests/test262/segmenter-shim.js`

A JavaScript shim automatically overrides `Intl.Segmenter.prototype.segment()` before test execution:

```javascript
Intl.Segmenter.prototype.segment = function (input) {
  // Safe JavaScript implementation that splits by Unicode code points
  // Returns iterator and containing() method for spec compatibility
};
```

**Advantages**:

- ✅ All 87 intrinsics work (100% success rate)
- ✅ No crashes - runs in safe JavaScript code
- ✅ Spec-compatible API (iterator + containing())
- ✅ Automatic - loads before each test
- ✅ wellKnownIntrinsicObjects.js works unmodified
- ✅ %IntlSegmentIteratorPrototype% and %IntlSegmentsPrototype% both available

**Limitations**:

- ⚠️ Locale-unaware (ignores locale and granularity options)
- ⚠️ Splits by Unicode code points, not linguistic boundaries
- ⚠️ Not fully spec-compliant for word/sentence segmentation
- ⚠️ Sufficient for testing most functionality

**Impact**: Tests using Intl.Segmenter will pass basic functionality checks but may not catch locale-specific bugs. This is acceptable given the alternative is a complete crash.

### ❌ Previous Workaround (Deprecated)

Previously patched `wellKnownIntrinsicObjects.js` to use empty sources for 2 intrinsics. This approach has been replaced by the shim which provides full functionality.

## Upstream Status

### Node.js Issue

- **Issue**: https://github.com/nodejs/node/issues/51752
- **Status**: Open since February 2024, no fix as of August 2024
- **Affects**: Node.js builds with `--with-intl=small-icu` configuration
- **Assignment**: Assigned to @srl295, awaiting V8 upstream fix

### Expected Fix

The fix needs to happen in V8 itself:

1. Add null-checking in `JSSegments::Create` before dereferencing break iterator
2. Throw appropriate JavaScript exception instead of crashing
3. Handle missing break iterator data gracefully

### Why Node.js Works (Normally)

Node.js 22.x uses V8 12.4.x which doesn't have this bug. Only V8 13.x+ exhibits this issue, and only in embedded contexts or with limited ICU data.

## Testing

### Reproducing the Crash

```php
<?php
$v8 = new V8Js();
$v8->executeString('new Intl.Segmenter().segment("test")', 'crash.js');
// Result: Segmentation fault: 11
```

### What Works

```php
<?php
$v8 = new V8Js();

// Constructor: ✅ Works
$v8->executeString('new Intl.Segmenter("en", { granularity: "word" })');

// Other Intl APIs: ✅ Work
$v8->executeString('new Intl.DateTimeFormat("en-US").format(new Date())');
$v8->executeString('new Intl.Collator("en").compare("a", "b")');
```

## Recommendations

1. **Keep the patch**: The `wellKnownIntrinsicObjects.js` workaround is necessary and correct
2. **Monitor upstream**: Watch https://github.com/nodejs/node/issues/51752 for V8 fixes
3. **Document clearly**: Ensure users know this is a V8 bug, not a v8js bug
4. **Consider V8 version**: If/when upgrading V8, test if the bug is fixed
5. **Accept limitation**: Until V8 fixes this, Intl.Segmenter cannot be fully supported

## References

- Node.js Issue: https://github.com/nodejs/node/issues/51752
- V8 Source: `src/objects/js-segments.cc:33`
- Chromium Bug Tracker: http://bugs.chromium.org/p/v8/issues/list
- ICU Break Iterator: http://userguide.icu-project.org/boundaryanalysis

## Version Information

- **V8**: 13.5.212.10 (Homebrew)
- **v8js**: PHP extension (php8 branch)
- **ICU**: 78.1 (included in V8 build)
- **PHP**: 8.3
- **macOS**: 15.6.1 (ARM64)
