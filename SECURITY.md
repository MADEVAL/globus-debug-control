# Security Policy

## Supported versions

Only the latest released version of GLOBUS Debug Control receives security fixes.
The current release is listed on the
[WordPress.org plugin page](https://wordpress.org/plugins/globus-debug-control/).

## Reporting a vulnerability

Please do **not** open a public GitHub issue for security problems.

Use GitHub's private vulnerability reporting:

1. Open <https://github.com/MADEVAL/globus-debug-control/security/advisories/new>.
2. Describe the issue, the affected version, and reproduction steps.
3. If possible, include a proof of concept and the impact you expect.

You can expect an initial response within a few days. Once the issue is confirmed
and a fix is ready, a new plugin version is published and the advisory is disclosed.

## Scope

The plugin edits `wp-config.php`, writes an optional MU-plugin dropin, reads
`debug.log`, and exposes diagnostic data in the WordPress admin. Reports about
missing capability checks, nonce verification, path traversal, credential leakage,
or unsafe file writes are especially relevant.
