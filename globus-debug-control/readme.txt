=== GLOBUS Debug Control ===
Contributors: globusstudio
Tags: debug, error-reporting, logging, development, error-handling
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress debug mode from the admin panel - choose which errors to see, where to log them, and keep your frontend clean.

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

== Installation ==

1. Upload the `globus-debug-control` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → GLOBUS Debug Control** and configure as needed.

== Frequently Asked Questions ==

= Does the plugin require WP_DEBUG to be enabled? =

The plugin is always active, but error filtering only takes effect when `WP_DEBUG` is set to `true` in `wp-config.php`. Error display and logging settings work independently of `WP_DEBUG`.

= Will it hide errors in AJAX or REST API responses? =

Yes. Errors are always suppressed from output during AJAX requests, REST API calls, WP-Cron, and WP-CLI - regardless of your display settings. This prevents broken JSON responses.

= What is the MU-plugin dropin and do I need it? =

It's an optional small file installed into `wp-content/mu-plugins/`. It makes GLOBUS Debug Control run earlier than any regular plugin, so errors thrown during early plugin initialization are also filtered. Enable it if you see errors from plugins that load before this one. The dropin is removed automatically when the plugin is deactivated.

= Where is the log file saved? =

By default, errors are logged to `wp-content/debug.log`. You can set a custom path in the plugin settings. The log file rotates automatically once it exceeds the size limit you set.

= Can I use this on a live site? =

Yes. The recommended setup for a production site is: suppress all error types, disable error display, enable logging to a file, and keep the `php-error` class removal active.

== Changelog ==

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

= 2.2.0 =
Added optional MU-plugin dropin to catch errors from plugins that load before GLOBUS Debug Control.
