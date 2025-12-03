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

#ifndef V8JS_ESMODULE_H
#define V8JS_ESMODULE_H

/* ES Module resolution callback for V8 */
v8::MaybeLocal<v8::Module> v8js_module_resolve_callback(
	v8::Local<v8::Context> context,
	v8::Local<v8::String> specifier,
	v8::Local<v8::FixedArray> import_attributes,
	v8::Local<v8::Module> referrer
);

/* Load and compile an ES module */
v8::MaybeLocal<v8::Module> v8js_load_module(
	v8js_ctx *c,
	const char *module_id,
	const char *referrer_name
);

/* Instantiate ES module and its dependency graph */
bool v8js_instantiate_module(
	v8js_ctx *c,
	v8::Local<v8::Module> module
);

/* Evaluate an ES module */
v8::MaybeLocal<v8::Value> v8js_evaluate_module(
	v8js_ctx *c,
	v8::Local<v8::Module> module
);

/* Dynamic import() callback for V8 */
v8::MaybeLocal<v8::Promise> v8js_module_dynamic_import_callback(
	v8::Local<v8::Context> context,
	v8::Local<v8::Data> host_defined_options,
	v8::Local<v8::Value> resource_name,
	v8::Local<v8::String> specifier,
	v8::Local<v8::FixedArray> import_attributes
);

/* Execute ES module code from string */
bool v8js_execute_module_string(
	zval *this_ptr,
	const zend_string *str,
	const zend_string *identifier,
	long flags,
	long time_limit,
	size_t memory_limit,
	zval **return_value
);

/* Synchronous require() function for CommonJS modules */
void v8js_require_callback(const v8::FunctionCallbackInfo<v8::Value>& info);

#endif /* V8JS_ESMODULE_H */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
