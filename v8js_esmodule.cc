/*
  +----------------------------------------------------------------------+
  | PHP Version 7                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2025 The PHP Group                                |
  +----------------------------------------------------------------------+
  | http://www.opensource.org/licenses/mit-license.php  MIT License      |
  +----------------------------------------------------------------------+
  | Author: Nate Guchi <nate@ixn.digital>                                |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php_v8js_macros.h"
#include "v8js_esmodule.h"
#include "v8js_exceptions.h"

extern "C" {
#include "php.h"
#include "zend_exceptions.h"
}

/* ES Module resolution callback for V8 */
v8::MaybeLocal<v8::Module> v8js_module_resolve_callback(
	v8::Local<v8::Context> context,
	v8::Local<v8::String> specifier,
	v8::Local<v8::FixedArray> import_attributes,
	v8::Local<v8::Module> referrer
) {
	v8::Isolate *isolate = context->GetIsolate();
	v8js_ctx *c = (v8js_ctx *) isolate->GetData(0);

	v8::String::Utf8Value specifier_str(isolate, specifier);
	const char *specifier_cstr = *specifier_str;

	// Get referrer module identifier
	std::string referrer_name = "";
	
	// Try to find referrer name in loaded modules
	for (auto &pair : c->esmodules_loaded) {
		if (pair.second.Get(isolate)->GetIdentityHash() == referrer->GetIdentityHash()) {
			referrer_name = pair.first;
			break;
		}
	}

	// Extract the base path from referrer name
	size_t last_slash = referrer_name.find_last_of('/');
	std::string base_path = (last_slash != std::string::npos) 
		? referrer_name.substr(0, last_slash) 
		: "";

	// Normalize the module identifier
	std::string normalized_id;
	
	if (Z_TYPE(c->module_resolver) != IS_NULL) {
		// Call custom PHP resolver
		zval params[2];
		zval resolver_result;
		int call_result;

		zend_try {
			{
				isolate->Exit();
				v8::Unlocker unlocker(isolate);

				ZVAL_STRING(&params[0], base_path.c_str());
				ZVAL_STRING(&params[1], specifier_cstr);

				call_result = call_user_function(EG(function_table), NULL, &c->module_resolver,
													&resolver_result, 2, params);
			}

			isolate->Enter();

			if (call_result == FAILURE) {
				zval_ptr_dtor(&params[0]);
				zval_ptr_dtor(&params[1]);
				isolate->ThrowException(V8JS_SYM("Module resolver callback failed"));
				return v8::MaybeLocal<v8::Module>();
			}
		}
		zend_catch {
			v8js_terminate_execution(isolate);
			V8JSG(fatal_error_abort) = 1;
			call_result = FAILURE;
		}
		zend_end_try();

		zval_ptr_dtor(&params[0]);
		zval_ptr_dtor(&params[1]);

		if (call_result == FAILURE) {
			return v8::MaybeLocal<v8::Module>();
		}

		// Check if an exception was thrown
		if (EG(exception)) {
			zval_ptr_dtor(&resolver_result);
			return v8::MaybeLocal<v8::Module>();
		}

		if (Z_TYPE(resolver_result) != IS_STRING) {
			convert_to_string(&resolver_result);
		}

		normalized_id = std::string(Z_STRVAL(resolver_result));
		zval_ptr_dtor(&resolver_result);
	} else {
		// Simple path resolution
		if (specifier_cstr[0] == '.' && specifier_cstr[1] == '/') {
			// Relative path: ./module
			normalized_id = base_path.empty() ? std::string(specifier_cstr + 2) 
				: base_path + "/" + std::string(specifier_cstr + 2);
		} else if (specifier_cstr[0] == '.' && specifier_cstr[1] == '.' && specifier_cstr[2] == '/') {
			// Parent path: ../module
			size_t parent_slash = base_path.find_last_of('/');
			std::string parent_path = (parent_slash != std::string::npos) 
				? base_path.substr(0, parent_slash) 
				: "";
			normalized_id = parent_path.empty() ? std::string(specifier_cstr + 3)
				: parent_path + "/" + std::string(specifier_cstr + 3);
		} else {
			// Absolute or bare module specifier
			normalized_id = std::string(specifier_cstr);
		}
	}

	// Try to load the module
	return v8js_load_module(c, normalized_id.c_str(), referrer_name.c_str());
}

/* Load and compile an ES module */
v8::MaybeLocal<v8::Module> v8js_load_module(
	v8js_ctx *c,
	const char *module_id,
	const char *referrer_name
) {
	v8::Isolate *isolate = c->isolate;
	v8::Local<v8::Context> context = v8::Local<v8::Context>::New(isolate, c->context);
	
	std::string module_id_str(module_id);

	// Check if module is already loaded
	if (c->esmodules_loaded.find(module_id_str) != c->esmodules_loaded.end()) {
		return v8::MaybeLocal<v8::Module>(
			v8::Local<v8::Module>::New(isolate, c->esmodules_loaded[module_id_str])
		);
	}

	// Check for cyclic dependencies
	for (auto &mod_name : c->modules_stack) {
		if (strcmp(mod_name, module_id) == 0) {
			isolate->ThrowException(V8JS_SYM("Module cyclic dependency"));
			return v8::MaybeLocal<v8::Module>();
		}
	}

	// Call PHP module loader if available
	if (Z_TYPE(c->module_loader) == IS_NULL) {
		isolate->ThrowException(V8JS_SYM("No module loader configured"));
		return v8::MaybeLocal<v8::Module>();
	}

	// Allocate and add module to stack for circular dependency detection
	char *module_id_copy = (char *)emalloc(strlen(module_id) + 1);
	strcpy(module_id_copy, module_id);
	c->modules_stack.push_back(module_id_copy);

	zval module_code;
	int call_result;
	zval params[1];

	{
		isolate->Exit();
		v8::Unlocker unlocker(isolate);

		zend_try {
			ZVAL_STRING(&params[0], module_id);
			call_result = call_user_function(EG(function_table), NULL, &c->module_loader, 
											&module_code, 1, params);
		}
		zend_catch {
			v8js_terminate_execution(isolate);
			V8JSG(fatal_error_abort) = 1;
			call_result = FAILURE;
		}
		zend_end_try();
	}

	isolate->Enter();

	zval_ptr_dtor(&params[0]);

	if (call_result == FAILURE || V8JSG(fatal_error_abort)) {
		c->modules_stack.pop_back();
		efree(module_id_copy);
		isolate->ThrowException(V8JS_SYM("Module loader callback failed"));
		return v8::MaybeLocal<v8::Module>();
	}

	// Check if an exception was thrown
	if (EG(exception)) {
		c->modules_stack.pop_back();
		efree(module_id_copy);
		zval_ptr_dtor(&module_code);
		return v8::MaybeLocal<v8::Module>();
	}

	// Convert to string
	if (Z_TYPE(module_code) != IS_STRING) {
		convert_to_string(&module_code);
	}

	// Compile the module
	v8::TryCatch try_catch(isolate);

	v8::Local<v8::String> source_str = V8JS_ZSTR(Z_STR(module_code));
	zval_ptr_dtor(&module_code);

	v8::ScriptOrigin origin(
		V8JS_STR(module_id),
		0,                                      // line offset
		0,                                      // column offset
		false,                                  // is cross origin
		-1,                                     // script id
		v8::Local<v8::Value>(),                // source map url
		false,                                  // is opaque
		false,                                  // is wasm
		true                                    // is module
	);

	v8::ScriptCompiler::Source source(source_str, origin);
	v8::MaybeLocal<v8::Module> maybe_module = v8::ScriptCompiler::CompileModule(isolate, &source);

	if (maybe_module.IsEmpty()) {
		c->modules_stack.pop_back();
		efree(module_id_copy);
		if (try_catch.HasCaught()) {
			try_catch.ReThrow();
		}
		return v8::MaybeLocal<v8::Module>();
	}

	v8::Local<v8::Module> module = maybe_module.ToLocalChecked();

	// Cache the module
	c->esmodules_loaded[module_id_str].Reset(isolate, module);
	c->esmodules_status[module_id_str] = module->GetStatus();
	
	// Remove from stack after successful load and cache
	c->modules_stack.pop_back();
	efree(module_id_copy);

	return maybe_module;
}

/* Instantiate ES module and its dependency graph */
bool v8js_instantiate_module(
	v8js_ctx *c,
	v8::Local<v8::Module> module
) {
	v8::Isolate *isolate = c->isolate;
	v8::Local<v8::Context> context = v8::Local<v8::Context>::New(isolate, c->context);

	v8::TryCatch try_catch(isolate);

	v8::Maybe<bool> result = module->InstantiateModule(context, v8js_module_resolve_callback);

	if (result.IsNothing() || !result.FromJust()) {
		if (try_catch.HasCaught()) {
			try_catch.ReThrow();
		}
		return false;
	}

	return true;
}

/* Evaluate an ES module */
v8::MaybeLocal<v8::Value> v8js_evaluate_module(
	v8js_ctx *c,
	v8::Local<v8::Module> module
) {
	v8::Isolate *isolate = c->isolate;
	v8::Local<v8::Context> context = v8::Local<v8::Context>::New(isolate, c->context);

	v8::TryCatch try_catch(isolate);

	v8::MaybeLocal<v8::Value> result = module->Evaluate(context);

	if (result.IsEmpty()) {
		if (try_catch.HasCaught()) {
			try_catch.ReThrow();
		}
		return v8::MaybeLocal<v8::Value>();
	}

	return result;
}

/* Dynamic import() callback for V8 */
v8::MaybeLocal<v8::Promise> v8js_module_dynamic_import_callback(
	v8::Local<v8::Context> context,
	v8::Local<v8::Data> host_defined_options,
	v8::Local<v8::Value> resource_name,
	v8::Local<v8::String> specifier,
	v8::Local<v8::FixedArray> import_attributes
) {
	v8::Isolate *isolate = context->GetIsolate();
	v8js_ctx *c = (v8js_ctx *) isolate->GetData(0);

	// Create a promise resolver
	v8::Local<v8::Promise::Resolver> resolver;
	if (!v8::Promise::Resolver::New(context).ToLocal(&resolver)) {
		return v8::MaybeLocal<v8::Promise>();
	}

	v8::Local<v8::Promise> promise = resolver->GetPromise();

	// Get the specifier string
	v8::String::Utf8Value specifier_str(isolate, specifier);
	const char *specifier_cstr = *specifier_str;

	// Get the referrer name from resource_name
	std::string referrer_name;
	if (resource_name->IsString()) {
		v8::String::Utf8Value resource_str(isolate, resource_name.As<v8::String>());
		referrer_name = std::string(*resource_str);
	}

	// Extract base path from referrer
	std::string base_path;
	size_t last_slash = referrer_name.find_last_of('/');
	if (last_slash != std::string::npos) {
		base_path = referrer_name.substr(0, last_slash);
	}

	// Normalize the module identifier
	std::string normalized_id;
	
	if (Z_TYPE(c->module_resolver) != IS_NULL) {
		// Call custom PHP resolver
		zval params[2];
		zval resolver_result;
		int call_result;

		zend_try {
			{
				isolate->Exit();
				v8::Unlocker unlocker(isolate);

				ZVAL_STRING(&params[0], base_path.c_str());
				ZVAL_STRING(&params[1], specifier_cstr);

				call_result = call_user_function(EG(function_table), NULL, &c->module_resolver,
													&resolver_result, 2, params);
			}

			isolate->Enter();

			if (call_result == FAILURE) {
				zval_ptr_dtor(&params[0]);
				zval_ptr_dtor(&params[1]);
				
				v8::Local<v8::String> error_msg = V8JS_STR("Module resolver callback failed");
				resolver->Reject(context, v8::Exception::Error(error_msg)).Check();
				return promise;
			}
		}
		zend_catch {
			v8js_terminate_execution(isolate);
			V8JSG(fatal_error_abort) = 1;
			call_result = FAILURE;
		}
		zend_end_try();

		zval_ptr_dtor(&params[0]);
		zval_ptr_dtor(&params[1]);

		if (call_result == FAILURE) {
			return v8::MaybeLocal<v8::Promise>();
		}

		if (Z_TYPE(resolver_result) != IS_STRING) {
			convert_to_string(&resolver_result);
		}

		normalized_id = std::string(Z_STRVAL(resolver_result));
		zval_ptr_dtor(&resolver_result);
	} else {
		// Simple path resolution
		if (specifier_cstr[0] == '.' && specifier_cstr[1] == '/') {
			normalized_id = base_path.empty() ? std::string(specifier_cstr + 2) 
				: base_path + "/" + std::string(specifier_cstr + 2);
		} else if (specifier_cstr[0] == '.' && specifier_cstr[1] == '.' && specifier_cstr[2] == '/') {
			size_t parent_slash = base_path.find_last_of('/');
			if (parent_slash != std::string::npos) {
				normalized_id = base_path.substr(0, parent_slash) + "/" + std::string(specifier_cstr + 3);
			} else {
				normalized_id = std::string(specifier_cstr + 3);
			}
		} else {
			normalized_id = std::string(specifier_cstr);
		}
	}

	// Try to load the module
	v8::MaybeLocal<v8::Module> maybe_module = v8js_load_module(c, normalized_id.c_str(), referrer_name.c_str());

	if (maybe_module.IsEmpty()) {
		v8::Local<v8::String> error_msg = V8JS_STR(("Cannot find module: " + normalized_id).c_str());
		resolver->Reject(context, v8::Exception::Error(error_msg)).Check();
		return promise;
	}

	v8::Local<v8::Module> module = maybe_module.ToLocalChecked();

	// Instantiate the module
	if (!v8js_instantiate_module(c, module)) {
		v8::Local<v8::String> error_msg = V8JS_STR(("Failed to instantiate module: " + normalized_id).c_str());
		resolver->Reject(context, v8::Exception::Error(error_msg)).Check();
		return promise;
	}

	// Evaluate the module
	v8::MaybeLocal<v8::Value> result = v8js_evaluate_module(c, module);

	if (result.IsEmpty()) {
		v8::Local<v8::String> error_msg = V8JS_STR(("Failed to evaluate module: " + normalized_id).c_str());
		resolver->Reject(context, v8::Exception::Error(error_msg)).Check();
		return promise;
	}

	// Get the module namespace object
	v8::Local<v8::Object> module_namespace = module->GetModuleNamespace().As<v8::Object>();

	// Resolve the promise with the module namespace
	resolver->Resolve(context, module_namespace).Check();

	return promise;
}

/* Execute ES module code from string */
bool v8js_execute_module_string(
	zval *this_ptr,
	const zend_string *str,
	const zend_string *identifier,
	long flags,
	long time_limit,
	size_t memory_limit,
	zval **return_value
) {
	v8js_ctx *c = Z_V8JS_CTX_OBJ_P(this_ptr);

	if (!c->isolate) {
		return false;
	}

	v8::Isolate *isolate = c->isolate;
	v8::Locker locker(isolate);
	v8::Isolate::Scope isolate_scope(isolate);
	v8::HandleScope handle_scope(isolate);
	v8::Local<v8::Context> v8_context = v8::Local<v8::Context>::New(isolate, c->context);
	v8::Context::Scope context_scope(v8_context);

	// Generate module identifier
	const char *module_id = identifier && ZSTR_LEN(identifier) > 0 
		? ZSTR_VAL(identifier) 
		: "main";

	// Compile module
	v8::Local<v8::String> source_str = V8JS_ZSTR(str);

	v8::ScriptOrigin origin(
		V8JS_STR(module_id),
		0,                                      // line offset
		0,                                      // column offset
		false,                                  // is cross origin
		-1,                                     // script id
		v8::Local<v8::Value>(),                // source map url
		false,                                  // is opaque
		false,                                  // is wasm
		true                                    // is module
	);

	v8::ScriptCompiler::Source source(source_str, origin);
	v8::TryCatch try_catch(isolate);

	v8::MaybeLocal<v8::Module> maybe_module = v8::ScriptCompiler::CompileModule(isolate, &source);

	if (maybe_module.IsEmpty()) {
		v8js_throw_script_exception(c->isolate, &try_catch);
		return false;
	}

	v8::Local<v8::Module> module = maybe_module.ToLocalChecked();

	// Cache the module
	std::string module_id_str(module_id);
	c->esmodules_loaded[module_id_str].Reset(isolate, module);
	c->esmodules_status[module_id_str] = module->GetStatus();

	// Instantiate module
	if (!v8js_instantiate_module(c, module)) {
		v8js_throw_script_exception(c->isolate, &try_catch);
		return false;
	}

	// Evaluate module
	std::function< v8::MaybeLocal<v8::Value>(v8::Isolate *) > v8_call = [c, module](v8::Isolate *isolate) {
		v8::Local<v8::Context> context = v8::Local<v8::Context>::New(isolate, c->context);
		v8::MaybeLocal<v8::Value> result = module->Evaluate(context);
		
		// Process microtasks after evaluation to handle promises
		isolate->PerformMicrotaskCheckpoint();
		
		// If evaluation returned a promise (e.g., from top-level await),
		// we need to wait for it to settle
		if (!result.IsEmpty()) {
			v8::Local<v8::Value> result_value = result.ToLocalChecked();
			
			if (result_value->IsPromise()) {
				v8::Local<v8::Promise> promise = result_value.As<v8::Promise>();
				
				// Wait for promise to settle (with timeout protection)
				int max_iterations = 1000;
				int iterations = 0;
				
				while (promise->State() == v8::Promise::kPending && iterations < max_iterations) {
					// Run microtasks to progress promise resolution
					isolate->PerformMicrotaskCheckpoint();
					iterations++;
					
					// Small yield to prevent tight loop
					if (iterations > 10 && promise->State() == v8::Promise::kPending) {
						// Still pending after many iterations, might be waiting on external I/O
						// In a real implementation, you might want to integrate with event loop
						break;
					}
				}
				
				// Handle settled promise
				if (promise->State() == v8::Promise::kFulfilled) {
					// Promise resolved, continue to return module namespace
				} else if (promise->State() == v8::Promise::kRejected) {
					// Create an exception from the rejection reason
					v8::Local<v8::Value> rejection = promise->Result();
					isolate->ThrowException(rejection);
					return v8::MaybeLocal<v8::Value>();
				}
				// If still pending, continue to return module namespace
			}
		}
		
		// Return the module namespace (exports) instead of the evaluation result
		return v8::MaybeLocal<v8::Value>(module->GetModuleNamespace());
	};

	v8js_v8_call(c, return_value, flags, time_limit, memory_limit, v8_call);

	if (V8JSG(fatal_error_abort)) {
		zend_bailout();
	}

	return true;
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
