=== GLOBUS Debug Control ===
Contributors: globusstudio
Tags: debug, error-log, cron, wp-config, transients
Requires at least: 5.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop wading through hundreds of irrelevant PHP notices. Debug WordPress on your terms - filter errors, toggle WP_DEBUG, read logs, and diagnose performance issues without leaving the admin panel or editing a single file.

== Description ==

You enable `WP_DEBUG` to track down a bug in your code - and immediately get buried under hundreds of notices and deprecation warnings from third-party plugins and themes. Things you did not write. Things you cannot fix. The real error you are looking for is lost in the noise.

GLOBUS Debug Control solves this. Pick exactly which PHP error types you want to see, silence the rest, and get a full debug dashboard - error logs, cron health, transients, database bloat, outgoing HTTP calls, and wp-config constants - all in one place, all from the admin panel.

No SSH. No file editing. No installing five separate plugins for five separate problems.

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

= Error Log tab =

Your debug.log file can grow to hundreds of megabytes without you noticing. This tab displays it directly in the admin panel using a CodeMirror editor with line numbers. Lines are color-coded by severity: fatal/parse errors, warnings, notices/deprecations, and cron-related messages. The log is paginated (200 lines per page), supports live polling for new entries, and can be downloaded or cleared with one click. Large log files (over 2 MB) are read from the tail to keep memory usage low.

= Cron Tasks tab =

WordPress cron runs silently. When events are overdue, hooks are orphaned, or schedules are duplicated, there is no built-in way to see it. This tab lists all scheduled cron events with hook name, next run time, recurrence interval, and arguments. Summary cards show total events, overdue count, orphaned hooks, and duplicates. Overdue events are highlighted. You can run any event immediately or delete orphaned hooks directly from the table.

= Transients tab =

Expired transients pile up in the database, and without raw SQL you have no way to see or clean them. This tab displays database-stored WordPress transients from the options table with key name, data size, and expiration time. Transients stored in an external object cache (Redis, Memcached) are not shown. Expired transients are highlighted. You can delete individual transients or purge all expired ones in one click. Summary cards show total count, expired count, and total data size.

= Autoloaded tab =

Every autoloaded option is fetched on every single page load. When plugins leave behind large serialised blobs, your site slows down and there is no obvious way to find the culprit. This tab lists autoloaded options from `wp_options` sorted by data size (top 200). Summary cards display total autoloaded row count and total payload size with warnings when thresholds are exceeded (over 800 KB or over 100 rows).

= HTTP Log tab =

Plugins and themes silently make outgoing HTTP calls - license checks, update pings, API syncs - and a slow or failing endpoint can stall your entire page load. This tab captures outgoing WordPress HTTP API requests (`wp_remote_get`, `wp_remote_post`, etc.) via the `http_api_debug` hook. Capturing must be explicitly enabled and stores up to 100 entries (6-hour TTL). Each entry shows the request URL, HTTP method, status code, error message, transport class, and response duration. Sensitive query parameters (`key`, `token`, `secret`, `password`, etc.) are automatically masked before an entry is stored.

= Constants tab =

When you need to check what debug, performance, or security constants are active at runtime, you normally have to grep through wp-config.php and hope nothing is overridden elsewhere. This tab displays all WordPress-related PHP constants defined at runtime, grouped by category: Debug, Performance, Security, Database, Auth and Salts, Paths, and Other WP_* constants. Sensitive values (database password, auth keys, salts) are automatically masked.

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

The plugin is always active, but error filtering only takes effect when `WP_DEBUG` is `true`. Error display and logging settings work independently of `WP_DEBUG`. You can toggle it from the **WP_DEBUG** tab at any time.

= I still see errors from plugins that load before this one. =

WordPress loads plugins in alphabetical order. Enable the **MU-plugin dropin** in the Settings tab - it installs a small helper into `wp-content/mu-plugins/` that runs before any regular plugin, so your filter settings apply from the very first line of the request. The dropin is removed automatically on deactivation or uninstall.

= My debug.log is huge and I cannot read it on the server. =

The **Error Log** tab shows it in the admin with color-coded lines and pagination. Files over 2 MB are read from the tail so the page loads fast. You can download the full file or clear it with one click.

= My site is slow and I suspect cron or database bloat. =

Check the **Cron Tasks** tab for overdue or orphaned events. Check the **Autoloaded** tab for oversized options that load on every request. Check the **Transients** tab for expired entries piling up in the database. All three are read-only and safe to use on a live site.

= Can I use this on a production site? =

Yes. The recommended setup: suppress all error types, disable error display, enable logging to a file, and keep the `php-error` class removal active. The diagnostic tabs (Cron Tasks, Transients, Autoloaded, HTTP Log, Constants) are read-only and safe at any time.

== Changelog ==

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

= 2.1.0 =
* Added automatic error suppression in API contexts (AJAX, REST API, Cron, CLI)
* Added removal of the php-error CSS body class via JavaScript and CSS

= 2.0.0 =
* Complete plugin rewrite

== Upgrade Notice ==

= 2.2.5 =
Major update: eight admin tabs including WP_DEBUG toggle, error log viewer, cron monitor, transient manager, autoloaded options inspector, HTTP request logger, and constants viewer.

= 2.2.0 =
Added optional MU-plugin dropin to catch errors from plugins that load before GLOBUS Debug Control.
