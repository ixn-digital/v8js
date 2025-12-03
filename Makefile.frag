# Override shared_objects_v8js to use build/ directory
shared_objects_v8js = build/v8js_array_access.lo build/v8js_class.lo build/v8js_convert.lo build/v8js_esmodule.lo build/v8js_exceptions.lo build/v8js_generator_export.lo build/v8js_main.lo build/v8js_methods.lo build/v8js_object_export.lo build/v8js_timer.lo build/v8js_v8.lo build/v8js_v8object_class.lo build/v8js_variables.lo

# Rule to build object files in build/ directory from src/ files  
build/%.lo: $(srcdir)/src/%.cc
	@test -d build || $(mkinstalldirs) build
	$(LIBTOOL) --tag=CXX --mode=compile $(CXX) -I. -I$(srcdir) $(COMMON_FLAGS) $(CXXFLAGS_CLEAN) $(EXTRA_CXXFLAGS) -Wno-narrowing -std=c++20 -DZEND_COMPILE_DL_EXT=1 -c $< -o $@ -MMD -MF build/$*.dep -MT $@

# Include dependency files
-include build/v8js_array_access.dep
-include build/v8js_class.dep
-include build/v8js_convert.dep
-include build/v8js_esmodule.dep
-include build/v8js_exceptions.dep
-include build/v8js_generator_export.dep
-include build/v8js_main.dep
-include build/v8js_methods.dep
-include build/v8js_object_export.dep
-include build/v8js_timer.dep
-include build/v8js_v8.dep
-include build/v8js_v8object_class.dep
-include build/v8js_variables.dep

# add json extension, if needed (ie, for PHP >= 5.5)
ifneq (,$(realpath $(EXTENSION_DIR)/json.so))
PHP_TEST_SHARED_EXTENSIONS+=-d extension=$(EXTENSION_DIR)/json.so
endif

# add pthreads extension, if available
ifneq (,$(realpath $(EXTENSION_DIR)/pthreads.so))
PHP_TEST_SHARED_EXTENSIONS+=-d extension=$(EXTENSION_DIR)/pthreads.so
endif

# add dom extension, if available
ifneq (,$(realpath $(EXTENSION_DIR)/dom.so))
PHP_TEST_SHARED_EXTENSIONS+=-d extension=$(EXTENSION_DIR)/dom.so
endif

# Test262 targets
test262: test262-init
	@echo "Running Test262 conformance tests with Node.js coordinator..."
	@if command -v node >/dev/null 2>&1; then \
		node tests/test262/test262-coordinator.js --summary-only --php=$(PHP_EXECUTABLE) --extension=modules/v8js.so; \
	else \
		echo "Node.js not found, falling back to PHP runner..."; \
		$(PHP_EXECUTABLE) -dextension=modules/v8js.so tests/test262/run-test262.php --summary-only; \
	fi

test262-init:
	@if [ ! -d test262/test ]; then \
		echo "Initializing test262 submodule..."; \
		git submodule update --init test262; \
	fi

test262-verbose: test262-init
	@echo "Running Test262 conformance tests (verbose)..."
	@if command -v node >/dev/null 2>&1; then \
		node tests/test262/test262-coordinator.js --verbose --php=$(PHP_EXECUTABLE) --extension=modules/v8js.so; \
	else \
		$(PHP_EXECUTABLE) -dextension=modules/v8js.so tests/test262/run-test262.php --verbose; \
	fi

test262-fast: test262-init
	@echo "Running Test262 conformance tests (8 concurrent workers)..."
	node tests/test262/test262-coordinator.js --concurrency=8 --summary-only --php=$(PHP_EXECUTABLE) --extension=modules/v8js.so

test262-filter: test262-init
	@echo "Running filtered Test262 tests (set FILTER=<pattern>)..."
	@if command -v node >/dev/null 2>&1; then \
		node tests/test262/test262-coordinator.js --filter="$(FILTER)" --summary-only --php=$(PHP_EXECUTABLE) --extension=modules/v8js.so; \
	else \
		$(PHP_EXECUTABLE) -dextension=modules/v8js.so tests/test262/run-test262.php --filter="$(FILTER)" --summary-only; \
	fi

.PHONY: test262 test262-init test262-verbose test262-fast test262-filter

