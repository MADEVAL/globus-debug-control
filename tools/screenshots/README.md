# Screenshot tool

Renders the eight WordPress.org screenshots from static HTML templates with
Playwright, so the images can be regenerated after any UI change.

The templates reproduce the real admin markup and load the actual plugin
stylesheet (`globus-debug-control/assets/css/admin.css`) plus a vendored copy of
the WordPress admin CSS. No WordPress install or database is needed.

## Requirements

- Node.js 18 or newer
- Playwright browsers (`npx playwright install chromium`)

## Usage

```sh
cd tools/screenshots
npm install
npm run capture
```

Output goes to `../../docs/screenshots/`:

- `screenshot-1.png` ... `screenshot-8.png` - 1200x900, in WordPress.org order
- `screenshots.json` - file names and captions

Useful flags:

```sh
npm run capture -- --only=settings   # one screen
npm run render                       # HTML previews in out/, no browser
```

Open `out/*.html` in a browser to inspect a template without capturing PNGs.

## Layout

```
templates/        per-tab page content (the .wrap body only)
layout.mjs        WordPress admin bar, menu, and tab chrome
capture.mjs       renders templates and takes screenshots
mock.css          screenshot-only fixes and the static log editor
vendor/wp/        WordPress admin CSS, dashicons, colour scheme (GPLv2)
out/              rendered HTML previews (gitignored)
```

## Publishing to WordPress.org

Screenshots live in the plugin's SVN `assets` directory:

```sh
svn checkout https://plugins.svn.wordpress.org/globus-debug-control/assets assets
cp ../../docs/screenshots/screenshot-*.png assets/
svn add assets/screenshot-*.png --force
svn ci assets -m "Update screenshots"
```

Captions are defined in `globus-debug-control/readme.txt` under
`== Screenshots ==`; keep the same order as the file names.

## Updating the vendored WordPress CSS

Copy from a local WordPress install (same files WordPress loads in wp-admin):

```
wp-admin/css/common.min.css
wp-admin/css/forms.min.css
wp-admin/css/admin-menu.min.css
wp-admin/css/list-tables.min.css
wp-admin/css/edit.min.css
wp-admin/css/colors/modern/colors.min.css
wp-includes/css/dashicons.min.css
wp-includes/css/buttons.min.css
wp-includes/css/admin-bar.min.css
wp-includes/fonts/dashicons.woff2
wp-includes/fonts/dashicons.woff
```

The colour scheme variables are defined manually in `mock.css`, because
WordPress normally prints them inline from PHP.
