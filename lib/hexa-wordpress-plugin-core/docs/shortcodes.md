# Shortcodes Namespace

Namespace:

```text
Hexa\PluginCore\ShortcodeRegistry
```

Folder:

```text
src/ShortcodeRegistry/
```

## Purpose

The shortcodes namespace standardizes how plugins document, display, and test shortcode output in admin screens.

It does not force every plugin to use the same shortcode tags. The host plugin provides definitions. The core provides the structure.

## Required Pieces

- `ShortcodeDefinition`: one shortcode's metadata and test template.
- `ShortcodeRegistry`: collection of shortcode definitions.
- `ShortcodeTestResult`: normalized test result.
- `ShortcodeTester`: runs one shortcode test at a time.

## Required Metadata For Each Shortcode

Every shortcode definition should include:

- ID
- label
- shortcode template
- description
- test method
- default input, if needed
- input label, if needed

Example:

```php
$registry->add(
    new ShortcodeDefinition(
        'display_year',
        'Current Year',
        '[display_year]',
        'Outputs the current four-digit year.',
        'Runs without input and checks for a non-empty year output.'
    )
);
```

## Admin UI Rule

Shortcode admin UIs should show one row per shortcode:

- shortcode
- description
- testing method
- test input
- output

Tests should run one at a time. A failed shortcode should show the shortcode as missing, empty, or errored instead of breaking the whole page.

