=== Debug Control ===
Contributors: globusstudio
Tags: debug, error-reporting, logging, development, error-handling
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full WordPress debug mode control: error type filtering, display and logging management, php-error body class removal.

== Description ==

Debug Control provides full control over WordPress debug mode directly from the admin panel.

**Features:**

* Filter error types (E_NOTICE, E_DEPRECATED, E_WARNING, E_STRICT)
* Control error display on screen (display_errors)
* Flexible error logging management with automatic log rotation
* Remove the `php-error` CSS class from the `<body>` tag
* Automatic error display suppression in API contexts (AJAX, REST API, Cron, CLI)
* MU-plugin support for early error interception

**Note for reviewers:**

This plugin is a **debug/error control tool** by design. Its core purpose is to manage PHP error reporting, display, and logging — functionality that inherently requires low-level PHP functions.

The following `phpcs:ignore` directives are used intentionally, each with an inline justification comment:

* `error_reporting()` — The plugin's primary function is to filter PHP error types.
* `set_error_handler()` — Required to intercept and suppress specific error types at runtime.
* `ini_set()` (display_errors, display_startup_errors, log_errors, error_log) — Required to control error display and logging per user settings.
* `fopen/fclose/chmod/touch/rename/is_writable` — Log file creation and rotation. These run on every request (including frontend, cron, REST, CLI) at `plugins_loaded` priority 1 and in the constructor, **before `WP_Filesystem` is initialized** and outside admin context where `request_filesystem_credentials()` cannot prompt for FTP/SSH credentials.
* `file_get_contents` — Reading the mu-plugin file to verify ownership (signature check) before overwriting or deleting. Runs in `sanitize_callback` and `register_deactivation_hook` where `WP_Filesystem` credential prompts are impossible.
* `file_put_contents` — Writing the auto-generated mu-plugin dropin. Same context limitations as above.

All file operations are scoped to the plugin's own log files and its own mu-plugin dropin (`gdc-early-loader.php`). No user-uploaded content or arbitrary paths are involved. `wp_delete_file()` is used where possible (mu-plugin removal).

== Installation ==

1. Upload the `debug-control` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure settings under "Settings" → "Debug Control".

== Frequently Asked Questions ==

= Why is the mu-plugin needed? =

WordPress loads plugins alphabetically. Errors from plugins loaded earlier (e.g. Elementor) occur before Debug Control initializes. The mu-plugin loads first and intercepts such errors.

= Does the plugin work without WP_DEBUG? =

The plugin is active, but error filtering (error_reporting) is only applied when WP_DEBUG is enabled.

== Changelog ==

= 2.2.3 =
* Fix minor bugs

= 2.2.2 =
* Removed deprecated load_plugin_textdomain() call (auto-loaded since WP 4.6)
* Improved escaping: wp_kses() for translated strings containing HTML
* Added singleton protection (__clone/__wakeup)
* Added type safety for $_POST superglobal checks
* Added index.php files for directory listing protection
* All phpcs:ignore directives include inline justification

= 2.2.1 =
* Fix minor bugs

= 2.2.0 =
* Added mu-plugin support for early error interception
* Improved logging management
* Added automatic log file rotation

= 2.1.0 =
* Added API context filtering
* php-error class removal via JavaScript and CSS

= 2.0.0 =
* Complete plugin architecture rewrite

== Upgrade Notice ==

= 2.2.0 =
Added mu-plugin support for early error interception from plugins loaded earlier in alphabetical order.
