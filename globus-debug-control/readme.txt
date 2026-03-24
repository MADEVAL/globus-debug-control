=== GLOBUS Debug Control ===
Contributors: globusstudio
Tags: debug, error-reporting, logging, development, error-handling
Requires at least: 5.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full WordPress debug mode control: error type filtering, display and logging management, php-error body class removal.

== Description ==

GLOBUS Debug Control provides full control over WordPress debug mode directly from the admin panel.

**Features:**

* Filter error types (E_NOTICE, E_DEPRECATED, E_WARNING, E_STRICT)
* Control error display on screen (display_errors)
* Flexible error logging management with automatic log rotation
* Remove the `php-error` CSS class from the `<body>` tag
* Automatic error display suppression in API contexts (AJAX, REST API, Cron, CLI)
* MU-plugin support for early error interception

== Installation ==

1. Upload the `globus-debug-control` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure settings under "Settings" → "GLOBUS Debug".

== Frequently Asked Questions ==

= Why is the mu-plugin needed? =

WordPress loads plugins alphabetically. Errors from plugins loaded earlier (e.g. Elementor) occur before GLOBUS Debug Control initializes. The mu-plugin loads first and intercepts such errors.

= Does the plugin work without WP_DEBUG? =

The plugin is active, but error filtering (error_reporting) is only applied when WP_DEBUG is enabled.

== Changelog ==

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
