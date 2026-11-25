# Testing GLOBUS Debug Control

The repository has no CI test job. Run the local checks before every commit and use
the live procedures below when a change touches runtime behaviour, MCP abilities, or
the admin UI.

## 1. Unit and static checks

```sh
composer install
composer test
composer cs
composer pot
```

`composer pot` rewrites the POT creation timestamp. Revert that timestamp if the only
diff is `POT-Creation-Date`.

Plugin Check against the local WordPress install:

```sh
php wp-cli.phar --path=wordpress plugin check globus-debug-control --format=table
php wp-cli.phar --path=wordpress plugin check globus-debug-control --mode=update --format=table
```

Both commands must report `No errors found`.

## 2. Local WordPress sandbox

The `wordpress/` directory is gitignored. Link the working copy into it instead of
copying files:

```powershell
New-Item -ItemType Junction `
  -Path wordpress/wp-content/plugins/globus-debug-control `
  -Target globus-debug-control
```

Start a PHP server on the URL stored in `wp_options` (usually `127.0.0.1:8088`):

```powershell
php -S 127.0.0.1:8088 -t wordpress <path-to-router.php>
```

The router serves real files and falls back to `wordpress/index.php`, so REST routes
work with plain permalinks through `index.php?rest_route=...`:

```php
<?php
$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$file = rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/\\' ) . $path;

if ( '/' !== $path && file_exists( $file ) ) {
	return false;
}

require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
```

Install the MCP Adapter plugin and create an Application Password:

```sh
php wp-cli.phar --path=wordpress plugin install mcp-adapter --activate
php wp-cli.phar --path=wordpress user application-password create admin "Local MCP" --porcelain
```

Core disables Application Passwords over plain HTTP. For local testing only, add a
mu-plugin filter:

```php
<?php
add_filter( 'wp_is_application_passwords_available', '__return_true' );
add_filter( 'wp_is_application_passwords_available_for_user', '__return_true' );
```

Do not add that mu-plugin to production.

## 3. MCP smoke test

`tools/mcp-smoke-test.mjs` performs a read-only MCP handshake and checks all public
reads: schema, options, wp-config, log, transients and constants.

```sh
WP_URL=http://127.0.0.1:8088 \
WP_USER=admin \
WP_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
node tools/mcp-smoke-test.mjs
```

PowerShell:

```powershell
$env:WP_URL = 'http://127.0.0.1:8088'
$env:WP_USER = 'admin'
$env:WP_APP_PASSWORD = 'xxxx xxxx xxxx xxxx'
node tools/mcp-smoke-test.mjs
```

Expected output ends with `MCP smoke test passed.` The script calls
`mcp-adapter-execute-ability` through `/index.php?rest_route=/mcp/mcp-adapter-default-server`.

For write paths, call the abilities explicitly with `confirm=true`:

- `gdc/update-options` then `gdc/reset-options`.
- `gdc/update-wp-config` with `WP_DEBUG=false`, verify `gdc/get-log` reports
  `debug.wp_debug=false`, then call `gdc/enable-debug-logging` and verify the
  constants and backup.
- `gdc/purge-expired-transients` after creating an expired transient.

## 4. Admin UI with Playwright

Run the committed smoke test with the Playwright copy in `tools/screenshots`:

```powershell
$env:GDC_E2E_URL  = 'http://127.0.0.1:8088'
$env:GDC_E2E_USER = 'admin'
$env:GDC_E2E_PASS = '<password>'
node tests/e2e/admin-smoke.mjs
```

It checks all nine screens, the command palette (focus trap included), the
constants filter, the AJAX master switch, the settings save round-trip and the
dashboard widget. Every changed setting is restored. Without credentials the
script prints `SKIP` and exits 0. See `tests/e2e/README.md`.

Manual spot checks that need real site state:

- Error Log with debug off: `.gdc-debug-dependency` is visible,
  `#gdc-generate-error` is disabled, and `.gdc-log-generate-disabled` explains why.
- One-click fix: submitting `.gdc-quick-debug-form` writes the constants, reloads
  with a success alert, and enables the generator.
- Generate: select `warning`, click `#gdc-generate-error`, and confirm
  `#gdc-log-code` contains the test warning after reload.
- Transients: `.gdc-transient-table` lists rows, or the empty state plus
  `.gdc-transient-cache-notice` appears with an external object cache.
- Constants: groups contain `Plugin & Theme` and secrets show `*** (masked)`.

Keep temporary Playwright scripts out of commits.

## 5. Redis and Memcached

Use WSL services, but run WordPress through the WSL PHP build so the `redis` and
`memcached` extensions are available.

```sh
wsl -e bash -lc "redis-cli ping"
wsl -e bash -lc "php -m | grep -Ei 'redis|memcach'"
```

Create a disposable database copy and serve the same WordPress files from WSL:

```sh
# Dump the Windows/MySQL database (enter the local DB password when prompted)
mysqldump -u root -p --databases gdc_wp_test > gdc_wp_test.sql

# Create the database in WSL MySQL, then import
wsl -e bash -lc "mysql -u root -p -e 'CREATE DATABASE gdc_wp_test'"
wsl -e bash -lc "mysql -u root -p gdc_wp_test < /mnt/c/path/to/gdc_wp_test.sql"

# WSL PHP server
wsl -e bash -lc "cd /mnt/d/path/to/wordpress-parent && php -S 0.0.0.0:8088 -t wordpress"
```

If Windows localhost forwarding does not reach the WSL server, point the test site
at the WSL IP for the duration of the test and restore it afterwards.

### Redis

```sh
wsl -e bash -lc "cd /mnt/d/path/to/repo && php wp-cli.phar --path=wordpress plugin install redis-cache --activate"
wsl -e bash -lc "cd /mnt/d/path/to/repo && php wp-cli.phar --path=wordpress redis enable"
```

Verify:

- `wp_using_ext_object_cache()` is `true`.
- A probe transient is readable.
- The probe has zero rows in `wp_options`.
- `gdc/get-transients` returns `diagnostics.external_cache=true` and does not list
  the probe as a database row.
- The Transients tab shows `.gdc-transient-cache-notice` and the cache-aware empty
  state.

### Memcached

Remove the Redis drop-in first, then use a Memcached drop-in:

```sh
wsl -e bash -lc "cd /mnt/d/path/to/repo && php wp-cli.phar --path=wordpress redis disable"
wsl -e bash -lc "cd /mnt/d/path/to/repo && php wp-cli.phar --path=wordpress plugin deactivate redis-cache"
```

A minimal local drop-in can be built from
`object-cache-4-everyone/object-cache-memcached-template.php` with
`OC4EVERYONE_PREDEFINED_SERVER` set to `127.0.0.1:11211`. Repeat the Redis assertions.

### Restore the sandbox

```sh
# Stop the WSL PHP server
wsl -e kill <pid>

# Remove object-cache.php and the cache test plugins
# Restore single-site wp-config.php from the backup
# Drop the WSL test database
# Restart the original Windows PHP server
```

## 6. Multisite

Back up `wp-config.php` first. Then convert the disposable database:

```sh
php wp-cli.phar --path=wordpress core multisite-convert --title="GDC Multisite"
```

Create a fresh and an expired network transient with an eval-file:

```php
<?php
set_site_transient( 'gdc_site_probe', 'site-value', 3600 );
set_site_transient( 'gdc_site_expired', 'old-value', 3600 );

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"UPDATE {$wpdb->sitemeta} SET meta_value = %d WHERE meta_key = %s",
		1000,
		'_site_transient_timeout_gdc_site_expired'
	)
);
```

Expected:

- `gdc/get-transients` lists `_site_transient_gdc_site_probe` with `site=true`.
- `_site_transient_gdc_site_expired` has `is_expired=true`.
- `gdc/purge-expired-transients` deletes both sides of the expired pair from
  `wp_sitemeta`.
- The UI lists the fresh network transient and renders its Delete button.

Restore the original `wp-config.php` and database after the test. This is the path
that caught the missing sitemeta purge in 2.4.0.

## 7. Release dry run

The DEPLOY workflow publishes only when both release flags are `true`. Always run a
dry run first:

```yaml
RELEASE_MODE: "true"
PUBLISH_WPORG: "true"
WPORG_DRY_RUN: "true"
```

Push to `main`, watch the run:

```sh
gh run list -R MADEVAL/globus-debug-control -L 2
gh run watch <run-id> -R MADEVAL/globus-debug-control --exit-status
```

The log must contain `Dry run: Files not committed.` and the run must be green.

For publication, set `WPORG_DRY_RUN: "false"`, keep both release flags `true`, push,
and watch again. Verify:

- `Committed revision` appears in the SVN log.
- The plugin API reports the new version.
- `https://downloads.wordpress.org/plugin/globus-debug-control.<version>.zip`
  returns HTTP 200.
- The changelog and upgrade notice are live.

After publishing, set both release flags back to `"false"` and push.
