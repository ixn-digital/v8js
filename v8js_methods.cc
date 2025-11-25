/*
  +----------------------------------------------------------------------+
  | PHP Version 7                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2017 The PHP Group                                |
  +----------------------------------------------------------------------+
  | http://www.opensource.org/licenses/mit-license.php  MIT License      |
  +----------------------------------------------------------------------+
  | Author: Jani Taskinen <jani.taskinen@iki.fi>                         |
  | Author: Patrick Reilly <preilly@php.net>                             |
  | Author: Stefan Siegl <stesie@php.net>                                |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php_v8js_macros.h"
#include "v8js_exceptions.h"
#include "v8js_object_export.h"

extern "C" {
#include "zend_exceptions.h"
}

/* global.exit - terminate execution */
V8JS_METHOD(exit) /* {{{ */
{
	v8::Isolate *isolate = info.GetIsolate();
	v8js_terminate_execution(isolate);
}
/* }}} */

/* global.sleep - sleep for passed seconds */
V8JS_METHOD(sleep) /* {{{ */
{
	v8::Isolate *isolate = info.GetIsolate();
	v8js_ctx *c = (v8js_ctx *) isolate->GetData(0);

	v8::Maybe<int32_t> t = info[0]->Int32Value(v8::Local<v8::Context>::New(isolate, c->context));

	if (t.IsJust()) {
		php_sleep(t.FromJust());
	}
}
/* }}} */

/* global.print - php print() */
V8JS_METHOD(print) /* {{{ */
{
	v8::Isolate *isolate = info.GetIsolate();
	zend_long ret = 0;

	for (int i = 0; i < info.Length(); i++) {
		v8::String::Utf8Value str(isolate, info[i]);
		const char *cstr = ToCString(str);
		ret = PHPWRITE(cstr, strlen(cstr));
	}

	info.GetReturnValue().Set(zend_long_to_v8js(ret, isolate));
}
/* }}} */

static void v8js_dumper(v8::Isolate *isolate, v8::Local<v8::Value> var, int level) /* {{{ */
{
	v8js_ctx *c = (v8js_ctx *) isolate->GetData(0);
	v8::Local<v8::Context> v8_context = v8::Local<v8::Context>::New(isolate, c->context);

	if (level > 1) {
		php_printf("%*c", (level - 1) * 2, ' ');
	}

	if (var.IsEmpty())
	{
		php_printf("<empty>\n");
		return;
	}
	if (var->IsNull() || var->IsUndefined() /* PHP compat */)
	{
		php_printf("NULL\n");
		return;
	}
	if (var->IsInt32())
	{
		v8::Maybe<int64_t> value = var->IntegerValue(v8_context);
		if (value.IsNothing())
		{
			php_printf("<empty>\n");
		}
		else
		{
			php_printf("int(%ld)\n", (long) value.FromJust());
		}
		return;
	}
	if (var->IsUint32())
	{
		v8::Maybe<uint32_t> value = var->Uint32Value(v8_context);
		if (value.IsNothing())
		{
			php_printf("<empty>\n");
		}
		else
		{
			php_printf("int(%lu)\n", (unsigned long) value.FromJust());
		}
		return;
	}
	if (var->IsNumber())
	{
		v8::Maybe<double> value = var->NumberValue(v8_context);
		if (value.IsNothing())
		{
			php_printf("<empty>\n");
		}
		else
		{
			php_printf("float(%f)\n", value.FromJust());
		}
		return;
	}
	if (var->IsBoolean())
	{
		bool value = var->BooleanValue(isolate);
		php_printf("bool(%s)\n", value ? "true" : "false");
		return;
	}

	v8::TryCatch try_catch(isolate); /* object.toString() can throw an exception */
	v8::Local<v8::String> details;

	if(var->IsRegExp()) {
		v8::RegExp *re = v8::RegExp::Cast(*var);
		details = re->GetSource();
	}
	else {
		details = var->ToDetailString(v8_context).FromMaybe(v8::Local<v8::String>());

		if (try_catch.HasCaught()) {
			details = V8JS_SYM("<toString threw exception>");
		}
	}

	v8::String::Utf8Value str(isolate, details);
	const char *valstr = ToCString(str);
	size_t valstr_len = str.length();

	if (var->IsString())
	{
		php_printf("string(%zu) \"", valstr_len);
		PHPWRITE(valstr, valstr_len);
		php_printf("\"\n");
	}
	else if (var->IsDate())
	{
		// fake the fields of a PHP DateTime
		php_printf("Date(%s)\n", valstr);
	}
	else if (var->IsRegExp())
	{
		php_printf("regexp(/%s/)\n", valstr);
	}
	else if (var->IsArray())
	{
		v8::Local<v8::Array> array = v8::Local<v8::Array>::Cast(var);
		uint32_t length = array->Length();

		php_printf("array(%d) {\n", length);

		for (unsigned i = 0; i < length; i++) {
			php_printf("%*c[%d] =>\n", level * 2, ' ', i);

			v8::MaybeLocal<v8::Value> value = array->Get(v8_context, i);
			if (value.IsEmpty())
			{
				php_printf("<empty>\n");
			}
			else
			{
				v8js_dumper(isolate, value.ToLocalChecked(), level + 1);
			}
		}

		if (level > 1) {
			php_printf("%*c", (level - 1) * 2, ' ');
		}

		ZEND_PUTS("}\n");
	}
	else if (var->IsObject())
	{
		v8::Local<v8::Object> object = v8::Local<v8::Object>::Cast(var);
		v8::String::Utf8Value cname(isolate, object->GetConstructorName());
		int hash = object->GetIdentityHash();

		if (var->IsFunction() && strcmp(ToCString(cname), "Closure") != 0)
		{
			php_printf("object(Closure)#%d {\n%*c", hash, level * 2 + 2, ' ');

			v8::Local<v8::String> source;
			if (object->ToString(v8_context).ToLocal(&source))
			{
				v8::String::Utf8Value csource(isolate, source);
				php_printf("%s\n",  ToCString(csource));
			}
			else
			{
				php_printf("<empty>\n");
			}
		}
		else
		{
			v8::MaybeLocal<v8::Array> keys = object->GetOwnPropertyNames(v8_context);
			uint32_t length = keys.IsEmpty() ? 0 : keys.ToLocalChecked()->Length();

			if (strcmp(ToCString(cname), "Array") == 0 ||
				strcmp(ToCString(cname), "V8Object") == 0) {
				php_printf("array");
			} else {
				php_printf("object(%s)#%d", ToCString(cname), hash);
			}
			php_printf(" (%d) {\n", length);

			for (unsigned i = 0; i < length; i++) {
				v8::MaybeLocal<v8::Value> key_slot = keys.ToLocalChecked()->Get(v8_context, i);
				v8::Local<v8::String> key;

				if (key_slot.IsEmpty() || !key_slot.ToLocalChecked()->ToString(v8_context).ToLocal(&key))
				{
					key = V8JS_SYM("<empty>");
				}

				v8::String::Utf8Value key_name(isolate, key);
				php_printf("%*c[\"%s\"] =>\n", level * 2, ' ', ToCString(key_name));

				v8::MaybeLocal<v8::Value> value = object->Get(v8_context, key);
				if (value.IsEmpty())
				{
					php_printf("<empty>\n");
				}
				else
				{
					v8js_dumper(isolate, value.ToLocalChecked(), level + 1);
				}
			}
		}

		if (level > 1) {
			php_printf("%*c", (level - 1) * 2, ' ');
		}

		ZEND_PUTS("}\n");
	}
	else /* null, undefined, etc. */
	{
		php_printf("<%s>\n", valstr);
	}
}
/* }}} */

/* global.var_dump - Dump JS values */
V8JS_METHOD(var_dump) /* {{{ */
{
	v8::Isolate *isolate = info.GetIsolate();

	for (int i = 0; i < info.Length(); i++) {
		v8js_dumper(isolate, info[i], 1);
	}

	info.GetReturnValue().Set(V8JS_NULL);
}
/* }}} */

void v8js_register_methods(v8::Local<v8::ObjectTemplate> global, v8js_ctx *c) /* {{{ */
{
	v8::Isolate *isolate = c->isolate;
	global->Set(V8JS_SYM("exit"), v8::FunctionTemplate::New(isolate, V8JS_MN(exit)), v8::ReadOnly);
	global->Set(V8JS_SYM("sleep"), v8::FunctionTemplate::New(isolate, V8JS_MN(sleep)), v8::ReadOnly);
	global->Set(V8JS_SYM("print"), v8::FunctionTemplate::New(isolate, V8JS_MN(print)), v8::ReadOnly);
	global->Set(V8JS_SYM("var_dump"), v8::FunctionTemplate::New(isolate, V8JS_MN(var_dump)), v8::ReadOnly);
}
/* }}} */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
