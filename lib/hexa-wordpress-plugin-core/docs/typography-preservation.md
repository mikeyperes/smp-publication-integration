# Typography Preservation

`Hexa\PluginCore\Typography\TypographyPreservation` defines reusable, prefix-scoped settings for preserving font family, font size, font color, and font weight while a host applies a visual template.

Use `defaults()` when defining host settings and `setting_keys()` when building persistence allowlists. Use `values()` or `preserves()` when deciding which CSS declarations the host should emit.

`Hexa\PluginCore\WpAdminComponents\TypographyPreservationControl` renders all four toggles. Place the component inside an element with `data-hpc-typography-scope` and pass target setting keys for controls that should be disabled while a value is preserved.

Core adds a prefix-scoped class such as `hpc-typography-article-heading-preserve-font-family` to the scope and dispatches `hexa-typography-preserve-change`. Host plugins keep responsibility for AJAX persistence, template selectors, and the CSS declarations specific to their output.
