# BoltKint

Kint extension for Bolt

Provides the standard [Kint](http://raveren.github.io/kint/) debug functions (`d`, `dd`, `ddd`, `s`, `sd`) for twig templates, including backtrace support.

Kint is a debugger similar to the bolt `dump` function, but with more details and features. This extension includes kint's ability to perform a backtrace when called with a literal `1` (As opposed to a variable that evaluates to `1`) and provide a mini backtrace below all function calls.

Things this extension does not provide (yet):

* Does not pass through parameter names
* Does not apply unary operators (IE: Prefix debug call with `+` to remove recursion depth limit)

Instructions concerning installation:

`jnvsor/boltkint` depends on `raveren/kint`, so until bolt extension dependancies are sorted out, the easiest way to install it is to:

1. `cd extensions`
2. Remove `"packagist": false` from `composer.json`
3. Remove `"provide": { "bolt/bolt...` from `composer.json`
4. Add `"replace": { "bolt/bolt": "2.*" }` to `composer.json`
5. `composer require jnvsor/boltkint`
