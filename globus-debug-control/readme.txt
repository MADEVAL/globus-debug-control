=== GLOBUS Debug Control ===
Contributors: globusstudio
Tags: debug, error-reporting, logging, development, error-handling
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress debug mode from the admin panel - toggle debug constants, view the error log, monitor cron tasks, and keep your frontend clean.

== Description ==

Working with `WP_DEBUG` enabled often floods the screen with notices and deprecation warnings from third-party plugins and themes - things you neither wrote nor can fix. GLOBUS Debug Control lets you filter out the noise and focus on what actually matters.

All settings are available in **Settings → GLOBUS Debug Control**. No code editing needed.

**Choose which errors to see**

Turn off specific PHP error types independently:

* Notices - informational messages that rarely indicate a real problem
* Deprecated - warnings about outdated functions, usually from third-party code
* Warnings - optional, keep them on if you're actively debugging
* Strict - PHP 7 strict standards messages (not applicable in PHP 8+)

**Control error display**

Three modes for showing errors on screen:

* **Inherit** - follows your `wp-config.php` settings (default, non-intrusive)
* **On** - always show errors, useful during active development
* **Off** - hide all errors from screen even if `WP_DEBUG_DISPLAY` is on

Errors are always hidden automatically during AJAX requests, REST API calls, WP-Cron, and WP-CLI - so your API responses stay clean regardless of the display setting.

**Error logging**

Enable logging to capture errors in a file instead of - or in addition to - showing them on screen:

* Set a custom log file path (absolute or relative to `wp-content/`)
* Set a maximum log file size (1–100 MB); the file rotates automatically when the limit is reached
* Defaults to `wp-content/debug.log` if no path is specified

**Remove the `php-error` CSS class**

When WordPress encounters a PHP error, it adds a `php-error` class to the `<body>` tag - which can break your page layout. This option removes it via both CSS and JavaScript so your front-end and admin look normal even in debug mode.

**Early error interception (optional)**

WordPress loads plugins in alphabetical order. If another plugin (such as Elementor, WooCommerce, or any "A…" plugin) throws an error before GLOBUS Debug Control initializes, those errors won't be filtered. Enabling the **MU-plugin dropin** installs a small helper file into `wp-content/mu-plugins/` that runs before any regular plugin - ensuring your filter settings apply to all PHP errors from the very start of the request.

**Toggle WP_DEBUG constants without editing files**

The **WP_DEBUG** tab lets you enable or disable the five most important debug constants directly from the admin panel - no manual `wp-config.php` editing required:

* `WP_DEBUG` - master debug switch
* `WP_DEBUG_LOG` - write errors to a log file
* `WP_DEBUG_DISPLAY` - show errors on screen
* `SCRIPT_DEBUG` - use unminified JS/CSS
* `SAVEQUERIES` - log all database queries

The plugin locates your `wp-config.php` automatically and patches it in place, adding the constant if it is missing or updating the existing value. Commented-out defines and block-comment sections are never touched. The current status of `WP_DEBUG` is always visible as a badge in the plugin header.

**Error log viewer**

The **Error Log** tab displays the contents of your `debug.log` file directly in the admin panel. Lines are color-coded by severity (fatal errors, warnings, notices, cron messages). The log is paginated (200 lines per page) and can be cleared with one click.

**Cron task viewer**

The **Cron Tasks** tab lists all scheduled WordPress cron events - hook name, next run time, recurrence interval, and arguments. Overdue events are highlighted so you can spot stuck or misfiring tasks at a glance.

== Installation ==

1. Upload the `globus-debug-control` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → GLOBUS Debug Control** and configure as needed.

== Frequently Asked Questions ==

= Does the plugin require WP_DEBUG to be enabled? =

The plugin is always active, but error filtering only takes effect when `WP_DEBUG` is set to `true` in `wp-config.php`. Error display and logging settings work independently of `WP_DEBUG`. You can toggle `WP_DEBUG` and related constants from the **WP_DEBUG** tab without editing any files manually.

= Will it hide errors in AJAX or REST API responses? =

Yes. Errors are always suppressed from output during AJAX requests, REST API calls, WP-Cron, and WP-CLI - regardless of your display settings. This prevents broken JSON responses.

= What is the MU-plugin dropin and do I need it? =

It's an optional small file installed into `wp-content/mu-plugins/`. It makes GLOBUS Debug Control run earlier than any regular plugin, so errors thrown during early plugin initialization are also filtered. Enable it if you see errors from plugins that load before this one. The dropin is removed automatically when the plugin is deactivated.

= Where is the log file saved? =

By default, errors are logged to `wp-content/debug.log`. You can set a custom path in the plugin settings. The log file rotates automatically once it exceeds the size limit you set. The **Error Log** tab lets you read and clear the log directly from the admin panel.

= Can I see scheduled cron tasks? =

Yes. The **Cron Tasks** tab lists all registered WordPress cron events with their next run time, interval, and arguments. Overdue events are highlighted.

= Can I use this on a live site? =

Yes. The recommended setup for a production site is: suppress all error types, disable error display, enable logging to a file, and keep the `php-error` class removal active.

== Changelog ==

= 2.2.5 =
* Added WP_DEBUG tab: toggle WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES directly from the admin panel
* WP_DEBUG status indicator badge shown in plugin header
* Added Error Log viewer with color-coded lines, pagination, and one-click clear
* Added Cron Tasks viewer showing all scheduled events with overdue highlighting
* Plugin settings UI reorganized into four tabs: Settings, WP_DEBUG, Error Log, Cron Tasks

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
New: toggle WP_DEBUG constants from the admin panel, built-in error log viewer, and cron task monitor. No configuration required after update.

= 2.2.0 =
Added optional MU-plugin dropin to catch errors from plugins that load before GLOBUS Debug Control.
