# Font Family Control

Namespace:

```text
Hexa\PluginCore\BrandColors
Hexa\PluginCore\WpAdminComponents
```

`FontFamilyProvider` discovers Elementor global typography and resolves saved source identifiers to safe CSS values. `FontFamilyControl` renders the reusable WordPress admin selector.

Available choices are:

- Template font, which emits no override and preserves the host template.
- Native primary and native secondary, which use Elementor global CSS variables.
- Each unique Elementor font family, stored by its Elementor typography ID.

The host plugin owns persistence and frontend selectors. Core owns discovery, validation, grouping, labels, unavailable states, and control markup. Core never loads a font or accepts arbitrary CSS as a saved font value.

```php
use Hexa\PluginCore\BrandColors\FontFamilyProvider;
use Hexa\PluginCore\WpAdminComponents\FontFamilyControl;

echo FontFamilyControl::render([
    'key'          => 'heading_font_family',
    'label'        => 'Font',
    'value'        => $settings['heading_font_family'] ?? FontFamilyProvider::TEMPLATE,
    'select_class' => 'my-plugin-setting',
]);

$saved = FontFamilyProvider::normalize_selection(
    (string) ($_POST['heading_font_family'] ?? '')
);
$css_value = FontFamilyProvider::css_value($saved);
```

When `$css_value` is empty, the host must omit its `font-family` declaration.
