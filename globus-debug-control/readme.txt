=== GLOBUS Debug Control ===
Contributors: globusstudio
Tags: debug, error-log, cron, wp-config, transients
Requires at least: 5.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.0
License: MIT
License URI: https://opensource.org/license/mit

Filter PHP debug noise, toggle WP_DEBUG, read logs, and inspect cron, transients, HTTP calls, and constants from one dashboard.

== Description ==

Turn `WP_DEBUG` into a focused debug dashboard - without SSH, SQL, or editing files. Filter noisy PHP error types, toggle debug constants, read `debug.log`, and inspect cron, transients, autoloaded options, outgoing HTTP calls, and runtime constants from the WordPress admin.

You enable `WP_DEBUG` to track down a bug in your code - and immediately get buried under hundreds of notices and deprecation warnings from third-party plugins and themes. Things you did not write. Things you cannot fix. The real error you are looking for is lost in the noise.

GLOBUS Debug Control solves this. Pick exactly which PHP error types you want to see, silence the rest, and get a full debug dashboard - error logs, cron health, transients, database bloat, outgoing HTTP calls, and wp-config constants - all in one place, all from the admin panel.

No SSH. No file editing. No installing five separate plugins for five separate problems.

= Why developers keep it installed =

* **One screen instead of five plugins.** Error filtering, log viewer, WP_DEBUG toggle, cron inspector, transient manager, autoloaded options, HTTP logger, and constants view live in one place.
* **Less noise, not less signal.** Notices, deprecations, and warnings are filtered independently. Fatal errors are never hidden.
* **No SSH, no SQL, no wp-config.php edits.** Everything happens in Settings -> GLOBUS Debug Control, including toggling WP_DEBUG constants.
* **Safe on a live site.** The diagnostic tabs are read-only. Every action is protected by capability checks and nonces. No external services, no tracking, no account.
* **Fixes what WP_DEBUG breaks.** Removes the php-error body class and keeps AJAX, REST, WP-Cron, and WP-CLI responses clean.
* **Works with any stack.** Any theme, any plugin set, single site or multisite. No setup wizard and no configuration files.

= Who it is for =

* **Agencies and freelancers** maintaining client sites where WP_DEBUG is enabled for a few minutes and then forgotten.
* **Plugin and theme developers** who need to see their own notices without drowning in deprecations from other code.
* **Support engineers** diagnosing a slow site, a stuck cron, or an unexpected outgoing HTTP request from the admin panel.

= The problems this plugin solves =

* **Error noise** - WP_DEBUG shows everything, including hundreds of notices from code you do not control. This plugin lets you suppress notices, deprecations, warnings, and strict errors independently, so you only see what matters to your own code.
* **Broken layouts in debug mode** - WordPress adds a `php-error` CSS class to `<body>` when a PHP error occurs, which can shift your page layout. This plugin removes it automatically via CSS and JavaScript.
* **Broken AJAX and REST responses** - PHP errors leak into JSON output and break your API calls. This plugin suppresses error display during AJAX, REST, WP-Cron, WP-CLI, XML-RPC, and other non-HTML contexts automatically.
* **Manual wp-config.php editing** - Every time you need to toggle `WP_DEBUG`, you SSH into the server, find wp-config.php, and edit a constant by hand. This plugin does it from a checkbox in the admin panel.
* **No log visibility** - The debug.log file sits on the server, growing silently. You only check it when something is already broken. This plugin shows it in the admin with color-coded lines, pagination, live polling, download, and one-click clear.
* **Invisible cron problems** - Overdue cron events, orphaned hooks, and duplicate schedules silently degrade performance. This plugin surfaces them all with source detection and lets you run or delete events on the spot.
* **Transient and option bloat** - Expired transients and heavy autoloaded options slow down every request but are invisible without raw SQL. This plugin shows them sorted by size with one-click cleanup.
* **Unknown outgoing HTTP calls** - Plugins and themes make external API calls you never asked for, and you have no idea which ones are slow or failing. This plugin captures them with status codes and response times.

= Settings tab =

**Choose which errors to see**

Turn off specific PHP error types independently:

* Notices - informational messages that rarely indicate a real problem
* Deprecated - warnings about outdated functions, usually from third-party code
* Warnings - optional, keep them on if you are actively debugging
* Strict - PHP 7 strict standards messages (not applicable in PHP 8+)

**Control error display**

Three modes for showing errors on screen:

* **Inherit** - follows your `wp-config.php` settings (default, non-intrusive)
* **On** - always show errors, useful during active development
* **Off** - hide all errors from screen even if `WP_DEBUG_DISPLAY` is on

Errors are always hidden automatically during AJAX requests, REST API calls, WP-Cron, and WP-CLI, so your API responses stay clean regardless of the display setting.

**Error logging**

Enable logging to capture errors in a file instead of (or in addition to) showing them on screen:

* Set a custom log file path (absolute or relative to `wp-content/`)
* Set a maximum log file size (1-100 MB); the file rotates automatically when the limit is reached
* Defaults to `wp-content/debug.log` if no path is specified

**Remove the php-error CSS class**

WordPress adds a `php-error` class to the `<body>` tag whenever a PHP error occurs. This shifts margins and breaks your page layout - both on the front-end and in the admin. This option removes it via both CSS and JavaScript so your site looks normal even with errors present.

**Early error interception (optional)**

WordPress loads plugins in alphabetical order. If another plugin throws an error before GLOBUS Debug Control initialises, those errors will not be filtered. Enabling the **MU-plugin dropin** installs a small helper file into `wp-content/mu-plugins/` that runs before any regular plugin, ensuring your filter settings apply from the very start of the request. The dropin is removed automatically when the plugin is deactivated or uninstalled.

= WP_DEBUG tab =

Normally, toggling `WP_DEBUG` means SSH access, finding wp-config.php, and editing PHP constants by hand. This tab lets you flip the five most important debug constants from the admin panel:

* `WP_DEBUG` - master debug switch
* `WP_DEBUG_LOG` - write errors to a log file (supports a custom log path)
* `WP_DEBUG_DISPLAY` - show errors on screen
* `SCRIPT_DEBUG` - use unminified core JS/CSS
* `SAVEQUERIES` - log all database queries

The plugin locates your `wp-config.php` automatically and patches it in place, adding the constant if it is missing or updating the existing value. A timestamped backup copy of `wp-config.php` is created before the first write so the file can always be restored. Commented-out defines and block-comment sections are never touched. The current status of `WP_DEBUG` is always visible as a badge in the plugin header.

= MCP and REST abilities =

On WordPress 6.9+ the plugin registers abilities for MCP and REST clients:

* `gdc/get-schema` - every option with its type, default and allowed values, plus supported wp-config constants
* `gdc/get-options` - all plugin options and the effective runtime state (`error_reporting`, `display_errors`, `log_errors`, `error_log`, resolved log file, mu-plugin status, API context)
* `gdc/update-options` - patch any option by key; values are sanitised and applied immediately
* `gdc/reset-options` - restore defaults, requires `confirm=true`
* `gdc/get-log` - tail of the error log with severity levels, optional substring filter
* `gdc/clear-log` - truncate the log file, requires `confirm=true`
* `gdc/get-wp-config` - current values of `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG`, `SAVEQUERIES`
* `gdc/update-wp-config` - write those constants into `wp-config.php`, requires `confirm=true`
* `gdc/enable-debug-logging` - one-click enable `WP_DEBUG` + `WP_DEBUG_LOG`, requires `confirm=true`
* `gdc/get-transients` - database transients with summary and storage diagnostics
* `gdc/purge-expired-transients` - delete expired transients, requires `confirm=true`
* `gdc/get-constants` - every runtime constant grouped by category, with secrets masked

All abilities require the `manage_options` capability. The early-loader dropin is regenerated automatically after a plugin update - no manual re-save is needed. `gdc/update-wp-config` keeps a timestamped backup of `wp-config.php` before writing.

= Error Log tab =

Your debug.log file can grow to hundreds of megabytes without you noticing. This tab displays it directly in the admin panel using a CodeMirror editor with line numbers. Lines are color-coded by severity: fatal/parse errors, warnings, notices/deprecations, and cron-related messages. The log is paginated (200 lines per page), supports live polling for new entries, and can be downloaded or cleared with one click. Large log files (over 2 MB) are read from the tail to keep memory usage low. When `WP_DEBUG` is off, the tab explains why no new lines arrive, disables the test-error generator, and offers a one-click fix that writes the debug constants to `wp-config.php`.

= Cron Tasks tab =

WordPress cron runs silently. When events are overdue, hooks are orphaned, or schedules are duplicated, there is no built-in way to see it. This tab lists all scheduled cron events with hook name, next run time, recurrence interval, and arguments. Summary cards show total events, overdue count, orphaned hooks, and duplicates. Overdue events are highlighted. You can run any event immediately or delete orphaned hooks directly from the table.

= Transients tab =

Expired transients pile up in the database, and without raw SQL you have no way to see or clean them. This tab displays database-stored WordPress transients from the options table with key name, data size, and expiration time. On multisite, network transients from the sitemeta table are included too. Transients stored in an external object cache (Redis, Memcached) are not shown; when such a cache is detected, the tab says so instead of showing a bare empty state. Database errors and unexpected transient-like rows are also surfaced in the notice area. Expired transients are highlighted. You can delete individual transients or purge all expired ones in one click, including network site transients. Summary cards show total count, expired count, and total data size.

= Autoloaded tab =

Every autoloaded option is fetched on every single page load. When plugins leave behind large serialised blobs, your site slows down and there is no obvious way to find the culprit. This tab lists autoloaded options from `wp_options` sorted by data size (top 200). Summary cards display total autoloaded row count and total payload size with warnings when thresholds are exceeded (over 800 KB or over 100 rows).

= HTTP Log tab =

Plugins and themes silently make outgoing HTTP calls - license checks, update pings, API syncs - and a slow or failing endpoint can stall your entire page load. This tab captures outgoing WordPress HTTP API requests (`wp_remote_get`, `wp_remote_post`, etc.) via the `http_api_debug` hook. Capturing must be explicitly enabled and stores up to 100 entries (6-hour TTL). Each entry shows the request URL, HTTP method, status code, error message, transport class, and response duration. Sensitive query parameters (`key`, `token`, `secret`, `password`, etc.) are automatically masked before an entry is stored.

= Constants tab =

When you need to check what debug, performance, or security constants are active at runtime, you normally have to grep through wp-config.php and hope nothing is overridden elsewhere. This tab displays every user-defined PHP constant at runtime, grouped by category: Debug, Performance, Security, Database, Auth and Salts, Paths, Other WP_* constants, and Plugin & Theme constants from plugins, themes and custom code. A name filter and collapsible groups keep large lists manageable. Sensitive values (database password, auth keys, salts, and names containing PASSWORD, SECRET, TOKEN, API_KEY, and similar markers) are automatically masked.

== Screenshots ==

1. Settings - stop drowning in debug noise: pick the PHP error types to suppress, the display mode and the log file.
2. WP_DEBUG - toggle WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG and SAVEQUERIES without editing files.
3. Error Log - color-coded debug.log with pagination, live polling, download and one-click clear.
4. Cron Tasks - overdue events, orphaned hooks, source detection and immediate runs.
5. Transients - database and network transients with size and expiry, plus a bulk purge of expired rows.
6. Autoloaded - the heaviest autoloaded options, sorted by size, with warning thresholds.
7. HTTP Log - outgoing WordPress HTTP API calls with status, transport and duration.
8. Constants - every runtime constant, including plugin and theme constants, with secrets masked.

== Installation ==

1. Upload the `globus-debug-control` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings - GLOBUS Debug Control** and configure as needed.

== Frequently Asked Questions ==

= I enabled WP_DEBUG and now my site is full of warnings from other plugins. How do I fix this? =

This is exactly what GLOBUS Debug Control is built for. Go to **Settings - GLOBUS Debug Control**, enable the plugin, and uncheck the error types you want to suppress (Notices, Deprecated, Warnings). You will only see the errors that matter to your own code.

= My AJAX calls and REST API responses are broken because of PHP error output. =

The plugin automatically suppresses error display during AJAX requests, REST API calls, WP-Cron, WP-CLI, XML-RPC, and other non-HTML contexts. No configuration needed - it happens as soon as the plugin is active.

= I do not have SSH access. How can I toggle WP_DEBUG? =

Use the **WP_DEBUG** tab. It reads and writes debug constants directly in your `wp-config.php` from the admin panel. No terminal, no file manager, no FTP.

= Does the plugin require WP_DEBUG to be enabled? =

The plugin is always active, but error filtering and test errors only take effect when `WP_DEBUG` is `true`. The **Settings** and **Error Log** tabs show the current `WP_DEBUG` and `WP_DEBUG_LOG` state and offer a one-click fix that writes the constants to `wp-config.php` after creating a backup. You can also toggle them from the **WP_DEBUG** tab at any time.

= I still see errors from plugins that load before this one. =

WordPress loads plugins in alphabetical order. Enable the **MU-plugin dropin** in the Settings tab - it installs a small helper into `wp-content/mu-plugins/` that runs before any regular plugin, so your filter settings apply from the very first line of the request. The dropin is removed automatically on deactivation or uninstall.

= My debug.log is huge and I cannot read it on the server. =

The **Error Log** tab shows it in the admin with color-coded lines and pagination. Files over 2 MB are read from the tail so the page loads fast. You can download the full file or clear it with one click.

= My site is slow and I suspect cron or database bloat. =

Check the **Cron Tasks** tab for overdue or orphaned events. Check the **Autoloaded** tab for oversized options that load on every request. Check the **Transients** tab for expired entries piling up in the database. All three are read-only and safe to use on a live site.

= Can I use this on a production site? =

Yes. The recommended setup: suppress all error types, disable error display, enable logging to a file, and keep the `php-error` class removal active. The diagnostic tabs (Cron Tasks, Transients, Autoloaded, HTTP Log, Constants) are read-only and safe at any time.

= Does the plugin change global PHP settings? =

The plugin is a debug control panel, so it intentionally sets `error_reporting`, `display_errors`, `log_errors`, and the `error_log` path for the current request. The changes are scoped to that request and apply only while the plugin is enabled; `wp-config.php` remains the source of truth for the next request. Each change is guarded by `WP_DEBUG` and the plugin options, and can be reverted from the Settings screen or by disabling the plugin.

= Is wp-config.php backed up before changes? =

Yes. Before the plugin writes a debug constant it creates a timestamped `wp-config.php.gdc-backup-YYYYMMDD-HHMMSS` copy next to the file. The plugin does not restore it automatically: rename the backup over `wp-config.php` if you need to roll back. The same backup runs for the admin form, the one-click debug fix, and the `gdc/update-wp-config` ability.

= What does the "generate test error" button do? =

It is an admin-only tool in the **Error Log** tab. It requires `manage_options` and a valid nonce, and writes one sample line so you can confirm that logging works. It is not available on the front end and never runs automatically.

== Changelog ==

= 2.4.0 =
* Changed: the Settings tab now shows live WP_DEBUG and WP_DEBUG_LOG badges next to "Enable debug control", with a warning when error filtering cannot take effect
* Added: one-click "Enable WP_DEBUG + WP_DEBUG_LOG" action that writes the constants to wp-config.php after a timestamped backup; first-time enabling also turns WP_DEBUG_DISPLAY off
* Changed: the Error Log tab explains when WP_DEBUG is off, disables the test-error generator, and offers the same one-click fix
* Fixed: the generate-test-error AJAX endpoint now refuses to run when logging is inactive
* Fixed: WP_DEBUG / wp-config status pills were misaligned with their checkboxes
* Added: Transients tab diagnostics for persistent object caches, database query errors, and transient-like rows that could not be listed
* Fixed: "Purge expired" on multisite now also deletes expired network site transients from sitemeta
* Changed: Constants tab lists every user-defined constant, including plugin and theme constants, with a name filter and collapsible groups
* Added: broader secret masking for constants whose names contain PASSWORD, SECRET, TOKEN, API_KEY and similar markers
* Added abilities: `gdc/enable-debug-logging`, `gdc/get-transients`, `gdc/purge-expired-transients`, and `gdc/get-constants`
* Changed: `gdc/get-log` also returns the effective debug and logging state, so MCP clients can explain an empty log

= 2.3.0 =
* Added MCP and REST abilities: get/update/reset options, read/clear the error log, and read/update wp-config debug constants
* Added complete option schema with types, defaults and allowed values for `gdc/get-schema`
* Added runtime diagnostics to `gdc/get-options`: `error_reporting`, `display_errors`, `log_errors`, `error_log`, log size and API context
* Changed: disabling error logging now also redirects `error_log` to the OS null device, so direct `error_log()` calls stop writing to `debug.log`
* Changed: the early-loader mu-plugin dropin sets the same logging switch before regular plugins load
* Added: the mu-plugin dropin is regenerated automatically after a plugin update, with no user action required
* Added: stored dropin version option for the automatic upgrade check
* Added: `gdc/update-wp-config` creates a timestamped `wp-config.php` backup before writing
* Fixed: uninstall now removes the stored dropin version option

= 2.2.7 =
* Redesigned admin screens: card-based settings groups, two-row toolbars, clearer cron and HTTP tables, and a single grouped constants table
* Improved table readability on narrow screens with horizontal scrolling and capped column widths
* Badges and accents now follow the active WordPress admin color scheme
* Fixed Dashicons alignment inside buttons
* Added translation drafts for Russian, Ukrainian, German, French, and Spanish
* Regenerated the WordPress.org screenshots for the new interface
* Styles and scripts now bust browser cache when the files change
* The .pot template is now generated with WP-CLI (189 strings, up from 172)
* Compatibility: tested on WordPress 7.1.4; passes Plugin Check 2.1 with no errors

= 2.2.6 =
* Fix: WP_Filesystem failures no longer cause fatal errors in the mu-plugin dropin management (direct file fallback added)
* Fix: cron events with falsy arguments (0, false, empty string) can now be deleted correctly
* Fix: log path no longer accepts parent-traversal ("..") values that could point at critical files
* Fix: uninstall no longer relies on wp_delete_file() which is not available in the uninstall context
* Fix: front-end php-error removal no longer depends on jQuery being loaded
* Fix: cron source names are HTML-escaped before being rendered in the admin table
* Fix: removed ineffective enable_wp_debug_mode_checks registration (core runs wp_debug_mode() before any plugin loads)
* Added: transport class and response duration columns in the HTTP Log, plus automatic masking of sensitive query parameters
* Added: notice in the Error Log tab when only the last 2 MB of a larger file are shown
* Added: timestamped backup of wp-config.php before the WP_DEBUG tab writes to it
* Added: custom log path field for WP_DEBUG_LOG in the WP_DEBUG tab
* Added: cron source lookups are cached in a 24 h transient to avoid repeated full-tree scans
* Added: plugin options are no longer autoloaded
* Added: README and version consistency are enforced by tests

= 2.2.5 =
* Added WP_DEBUG tab: toggle WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES directly from the admin panel
* WP_DEBUG status indicator badge shown in plugin header
* Added Error Log viewer with color-coded lines, pagination, live polling, download, and one-click clear
* Added Cron Tasks viewer with summary cards, overdue highlighting, immediate run, and orphan cleanup
* Added Transients viewer with size/expiry info, per-row delete, and bulk purge of expired entries
* Added Autoloaded Options viewer showing the heaviest autoloaded rows with size warnings
* Added HTTP Log tab for capturing outgoing WordPress HTTP API requests with status codes and timing
* Added Constants viewer displaying all WP-related PHP constants grouped by category with sensitive value masking
* Plugin settings UI reorganised into eight tabs: Settings, WP_DEBUG, Error Log, Cron Tasks, Transients, Autoloaded, HTTP Log, Constants
* Improved error log rendering for large files (tail-read for files over 2 MB)
* Added nonce verification to log status polling endpoint
* Switched log download to streaming output for lower memory usage

= 2.2.4 =
* Replaced inline style/script output with wp_add_inline_style() and wp_add_inline_script()
* Renamed option key from gdc_options to globus_debug_control_options with automatic migration
* Updated Author URI

= 2.2.3 =
* Fixed minor bugs

= 2.2.2 =
* Removed deprecated load_plugin_textdomain() call (auto-loaded since WP 4.6)
* Improved output escaping
* Added singleton protection
* Added index.php files for directory listing protection

= 2.2.1 =
* Fixed minor bugs

= 2.2.0 =
* Added optional MU-plugin dropin for early error interception
* Improved log file management
* Added automatic log file rotation

== Upgrade Notice ==

= 2.4.0 =
Clearer debug-state handling: live WP_DEBUG badges, a one-click wp-config fix, transient-cache diagnostics, and a complete constants viewer with plugin and theme constants.

= 2.3.0 =
Adds MCP/REST abilities for options, log and wp-config constants. The early-loader dropin updates itself after the upgrade.

= 2.2.7 =
Refreshed admin interface, aligned button icons, five translation drafts, regenerated screenshots, and updated developer tooling.

= 2.2.5 =
Major update: eight admin tabs including WP_DEBUG toggle, error log viewer, cron monitor, transient manager, autoloaded options inspector, HTTP request logger, and constants viewer.

= 2.2.0 =
Added optional MU-plugin dropin to catch errors from plugins that load before GLOBUS Debug Control.
