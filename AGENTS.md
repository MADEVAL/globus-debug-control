# AGENTS.md

Always on skill `caveman` in `ultra` mode

## Project

WordPress plugin "GLOBUS Debug Control" — error filtering, WP_DEBUG toggle, log/cron/transient/HTTP/constants viewers. PHP 7.4+, text domain `globus-debug-control`.

## Layout

- `globus-debug-control/` — the deployable plugin (this is what ships)
- `globus-debug-control/globus-debug-control.php` — main entry, singleton `Globus_Debug_Control`
- `globus-debug-control/include/` — one file per feature: traits (`trait-*.php`) compose the main class, standalone classes (`log-viewer.php`, `cron-viewer.php`, etc.)
- `tests/` — PHPUnit tests (namespace `GDC\Tests`)
- `tools/gen-pot.php` — i18n `.pot` generator

## Commands

```sh
composer install          # install deps (vendor/ is gitignored)
composer test             # run PHPUnit
composer cs               # lint with PHPCS (WordPress standard)
composer cs-fix           # auto-fix with PHPCBF
php tools/gen-pot.php     # regenerate languages/globus-debug-control.pot
```

Run `composer cs` and `composer test` before committing.

## Code Style

- **WordPress Coding Standards** enforced by PHPCS (`phpcs.xml.dist`)
- **Tabs for indentation**, not spaces
- WordPress-style naming: `snake_case` functions, `Class_Name` classes
- `phpcs.xml.dist` intentionally excludes sniffs for `ini_set`, `error_reporting`, `set_error_handler`, `@` operator, and `is_writable` — these are core to the plugin's purpose. Do not "fix" or re-enable them.

## Testing

Tests do **not** use the WordPress test suite. `tests/bootstrap.php` provides a complete mock layer: all WP functions (`add_action`, `get_option`, `wp_die`, etc.) are stubbed and record calls into `$GLOBALS['gdc_test_state']`.

- Extend `GDC\Tests\WordPressTestCase` (in `tests/WordPressTestCase.php`) for new tests
- State resets automatically between tests via `gdc_test_reset_state()`
- Use `$this->plugin()` to get the singleton, `$this->refreshOptions([...])` to set plugin options, `$this->state('key')` to inspect recorded WP calls
- `$this->invokePrivate('method_name', $args)` to test private/protected methods
- `GDC_Test_WpDieException` is thrown by `wp_die()` / `wp_send_json_*()` — catch it in tests
- `GDC_Test_RedirectException` is thrown by `wp_safe_redirect()`
- Temp files go under `gdc_test_runtime_root()` (auto-cleaned)

## CI / Deploy

No CI tests. `DEPLOY.yml` rsyncs `globus-debug-control/` to production on push to `main`. Only the plugin subdirectory is deployed — root-level dev files (`composer.json`, `tests/`, `tools/`) are not shipped.
