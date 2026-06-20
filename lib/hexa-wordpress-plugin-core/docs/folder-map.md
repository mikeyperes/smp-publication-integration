# Folder Map

This package uses one root namespace and explicit folders for every sub-namespace.

## Fixed Root

```text
Hexa\PluginCore\
```

The Composer package name is:

```text
hexa/plugin-core
```

The repository folder is:

```text
hexa-wordpress-plugin-core
```

The package version is stored in the root `VERSION` file.

## Sub-Namespace Folders

| Folder | Namespace | Purpose |
| --- | --- | --- |
| `src/ActivityLog/` | `Hexa\PluginCore\ActivityLog` | Activity logs and activity storage adapters. |
| `src/CoreBootstrap/` | `Hexa\PluginCore\CoreBootstrap` | Core setup, module registration, and lifecycle. |
| `src/CoreContracts/` | `Hexa\PluginCore\CoreContracts` | Interfaces shared across modules and host plugins. |
| `src/CorePackageUpdates/` | `Hexa\PluginCore\CorePackageUpdates` | Vendored Hexa WordPress Plugin Core version checks and package update UI. |
| `src/CoreRuntime/` | `Hexa\PluginCore\CoreRuntime` | Shared value objects and small helpers. |
| `src/CredentialVault/` | `Hexa\PluginCore\CredentialVault` | Encrypted credential/API-key storage, masking, and credential field examples. |
| `src/LogFiles/` | `Hexa\PluginCore\LogFiles` | Error-log sources, readers, classifiers, and reusable viewer panels. |
| `src/PluginProvisioning/` | `Hexa\PluginCore\PluginProvisioning` | Plugin discovery, status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation. |
| `src/PluginUpdates/` | `Hexa\PluginCore\PluginUpdates` | Host plugin GitHub version checks, update transients, zip downloads, and updater panels. |
| `src/ShortcodeRegistry/` | `Hexa\PluginCore\ShortcodeRegistry` | Shortcode definitions, registries, dashboard lists, and testing. |
| `src/SmartSearch/` | `Hexa\PluginCore\SmartSearch` | Smart search/X-Search AJAX endpoints and reusable typeahead renderers. |
| `src/SystemEnvironment/` | `Hexa\PluginCore\SystemEnvironment` | Safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting. |
| `src/WpAdminAjax/` | `Hexa\PluginCore\WpAdminAjax` | WordPress admin-AJAX nonce, capability, and callback guards. |
| `src/WpAdminComponents/` | `Hexa\PluginCore\WpAdminComponents` | Shared UI primitives: cards, subcards, buttons, pills, tooltips, and collapsibles. |
| `src/WpAdminTabs/` | `Hexa\PluginCore\WpAdminTabs` | Admin tab definitions, registries, rendering contracts, and the automatic core tab. |
| `src/WpConfigFile/` | `Hexa\PluginCore\WpConfigFile` | Safe wp-config.php constant and ini_set reads/writes with validation and rollback backup handling. |
| `src/WpCronTasks/` | `Hexa\PluginCore\WpCronTasks` | WP-Cron interval registration, scheduling, unscheduling, event inspection, and health status payloads. |

## Naming Rules

Class names are singular unless they are collection registries.

Good:

```text
ActivityLogEntry
ActivityLogger
CoreBootstrap
PluginContext
ShortcodeDefinition
ShortcodeRegistry
TabDefinition
TabRegistry
UpdaterConfig
```

Bad:

```text
HwsActivityLogger
HexaBaseToolsTabs
PluginCoreShortcodesManagerThing
UpdaterStuff
```

## Adding A New Namespace

Do not add a new sub-namespace casually.

Before adding one, document:

1. Why none of the existing folders fit.
2. The exact folder name.
3. The exact namespace.
4. The public classes that will live there.
5. Which host plugin needs it first.

Then update:

- `README.md`
- `AGENTS.md`
- `docs/folder-map.md`
