# Brand Colors

Namespace:

```text
Hexa\PluginCore\BrandColors
```

Folder:

```text
src/BrandColors/
```

## Purpose

Brand color helpers keep host plugins from rebuilding the same HWS Base Tools Brand Assets lookup and color conversion logic.

## Classes

```text
BrandColorProvider
```

## Contract

- `BrandColorProvider::primary_color($fallback)` returns the HWS Base Tools primary color, normalized as a six-character hex value.
- `BrandColorProvider::secondary_color($fallback)` returns the HWS Base Tools secondary color.
- `BrandColorProvider::payload($fallback)` returns source labels, hex values, RGB strings, and the Brand Assets admin URL.
- `BrandColorProvider::elementor_payload($fallback_primary, $fallback_secondary)` returns Elementor primary/secondary color and font-family tokens when Elementor site settings exist.
- `BrandColorProvider::rgb_string($hex)` converts a hex value to `rgb(r, g, b)`.
- `Hexa\PluginCore\WpAdminComponents\ColorControl::render()` owns the visual picker/hex/RGB/swatch/copy/import control.
- `Hexa\PluginCore\WpAdminComponents\DetailedColorPicker::render()` owns the paired primary/secondary visual picker and optional font controls.

## Host Plugin Rule

Host plugins pass setting keys and wire AJAX persistence. Core owns the reusable visual structure and HWS brand color lookup.

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\ColorControl;

$brand = BrandColorProvider::payload('#2d5277');

echo ColorControl::render([
    'key' => 'accent_color',
    'label' => 'Accent color',
    'value' => $settings['accent_color'] ?? $brand['primary_color'],
    'default' => $brand['primary_color'],
    'import_brand' => true,
]);
```

## Detailed Color Picker

Use this when a feature needs primary and secondary colors together, optional Elementor token import, and optional font controls.

Visual example:

```text
Detailed Color Picker
+----------------------+----------------------+
| Primary color        | Secondary color      |
| Picker Hex RGB Copy  | Picker Hex RGB Copy  |
| Swatch               | Swatch               |
+----------------------+----------------------+
[Import Elementor colors and fonts]
```

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Loop item design tokens',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_primary' => true,
    'show_secondary' => true,
    'show_elementor_import' => true,
    'show_fonts' => true,
    'fonts' => [
        [
            'key' => 'primary_font_family',
            'token' => 'primary_font_family',
            'label' => 'Primary font family',
            'value' => $settings['primary_font_family'] ?? '',
        ],
        [
            'key' => 'secondary_font_family',
            'token' => 'secondary_font_family',
            'label' => 'Secondary font family',
            'value' => $settings['secondary_font_family'] ?? '',
        ],
    ],
]);
```
