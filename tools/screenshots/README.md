# Screenshot and brand tool

Renders the WordPress.org listing images with Playwright from static HTML
templates, so every image can be regenerated after a UI or copy change.

Three steps:

1. `generate-templates.php` (dev only) renders the nine plugin screens from the
   local WordPress sandbox into `templates/`, with demo data and sanitised paths.
2. `capture.mjs` renders each template and saves a PNG of the plugin shell to
   `raw/` (gitignored).
3. `marketing/render.mjs` composes those captures into 1600x1000 marketing
   frames and writes `docs/screenshots/screenshot-N.jpg`.

Banners and the social card are separate: `marketing/render-brand.mjs` draws the
WordPress.org banners and the Open Graph image from the plugin icon.

The templates reproduce the real admin markup and load the actual plugin
stylesheets (`gdc-tokens.css`, `gdc-ui.css`, `gdc-components.css`) plus a
vendored copy of the WordPress admin CSS.

## Requirements

- Node.js 18 or newer
- Playwright browsers (`npx playwright install chromium`)
- For `generate-templates.php` only: the gitignored `wordpress/` sandbox with the
  plugin active. Capture and composition need no database.

## Usage

```sh
cd tools/screenshots
npm install

php generate-templates.php   # refresh templates from the sandbox (after UI changes)
npm run marketing            # capture + compose docs/screenshots/screenshot-N.jpg
npm run capture              # capture only, into raw/ (gitignored)
npm run brand                # banners into docs/wordpress-org/, OG card into docs/social/
```

Useful flags:

```sh
node capture.mjs --only=settings                  # one screen
npm run render                                    # HTML previews in out/, no browser
node marketing/render.mjs --only cron --no-preview
node marketing/render.mjs --format png --quality 95
node marketing/render-brand.mjs --only banner --no-locales
node marketing/render-brand.mjs --locale ru_RU,uk
```

After a run, open `out/preview.html` for a contact sheet of the composed
screenshots and `out/*.html` for a single template without the marketing frame.

## Layout

```
generate-templates.php  renders templates from the local sandbox (dev only)
templates/               the .gdc-app shell of each of the nine screens
fixtures/debug.log       demo log content used by the Error Log template
layout.mjs               WordPress admin bar, menu and page wrapper
capture.mjs              renders templates and captures raw/<shot>.png
mock.css                 screenshot-only fixes (admin colour variables, scrollbar)
marketing/slides.json    slide order, file names, themes and all copy
marketing/render.mjs     marketing frame composer
marketing/render-brand.mjs  WordPress.org banners and the Open Graph card
raw/                     element captures (gitignored)
out/                     rendered previews (gitignored)
vendor/wp/               WordPress admin CSS, dashicons, colour scheme (GPLv2)
```

`slides.json` is the single source of truth for listing copy. `maxHeight` on a
slide crops a very long screen at a readable point; `theme` switches the frame
between `dark` (default) and `light`.

## Output

| Path | Size | Use |
| --- | --- | --- |
| `docs/screenshots/screenshot-1.jpg` ... `screenshot-9.jpg` | 1600x1000 | WordPress.org listing screenshots |
| `docs/wordpress-org/banner-772x250.png` | 772x250 | WordPress.org plugin banner |
| `docs/wordpress-org/banner-1544x500.png` | 1544x500 | Retina banner |
| `docs/wordpress-org/banner-*-<locale>.png` | both sizes | Localized banners |
| `docs/social/globus-debug-control-1200x630.png` | 1200x630 | Social sharing card |

Banner locales follow the WordPress.org convention: `ru_RU`, `uk`, `de_DE`,
`fr_FR`, `es_ES`. WordPress.org serves the matching file on a localized plugin
page. Locale copy lives in `marketing/render-brand.mjs`.

## Publishing to WordPress.org

`.github/workflows/DEPLOY.yml` copies the screenshots, banners and icons into the
SVN `assets` directory on every release, so a normal release needs no manual SVN
work. The action syncs `assets/` with `--delete`: anything that must stay on the
plugin page has to live in `docs/screenshots/` or `docs/wordpress-org/`.

Manual fallback, only when assets must be updated without a version bump:

```sh
svn checkout https://plugins.svn.wordpress.org/globus-debug-control/assets assets
cp ../../docs/screenshots/screenshot-*.jpg assets/
cp ../../docs/wordpress-org/*.png assets/
svn add assets/* --force
svn ci assets -m "Update screenshots and banners"
```

Captions are defined in `globus-debug-control/readme.txt` under
`== Screenshots ==`; keep the same order as `slides.json`.

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
