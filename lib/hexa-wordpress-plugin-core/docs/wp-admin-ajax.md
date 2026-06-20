# WP Admin AJAX Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminAjax
```

Folder:

```text
src/WpAdminAjax/
```

Purpose:

- Create and verify WordPress nonces for admin AJAX endpoints.
- Read nonce values from `nonce`, `_ajax_nonce`, or `_wpnonce`.
- Enforce capabilities with consistent JSON errors.
- Wrap AJAX callbacks with capability checks, nonce checks, exception handling, and JSON responses.

Primary class:

```php
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;

$nonce = AjaxGuard::create_nonce( 'example_action' );
AjaxGuard::require_nonce_or_error( 'example_action' );
AjaxGuard::require_capability_or_error( 'manage_options' );

AjaxGuard::handle(
    static function () {
        return [ 'message' => 'Done' ];
    },
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_action',
    ]
);
```
