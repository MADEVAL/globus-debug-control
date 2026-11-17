# WordPress.org plugin assets

Icons and banners shown on the [WordPress.org plugin page](https://wordpress.org/plugins/globus-debug-control/).
`DEPLOY.yml` copies this directory and `docs/screenshots/` to the SVN `assets`
directory.

| File | Size |
| --- | --- |
| `icon-128x128.png` | 128x128 |
| `icon-256x256.png` | 256x256 |
| `banner-772x250.png` | 772x250 |
| `banner-1544x500.png` | 1544x500 |
| `banner-<size>-<locale>.png` | localized banners for `ru_RU`, `uk`, `de_DE`, `fr_FR`, `es_ES` |

Regenerate the banners and icons' placement with:

```sh
cd tools/screenshots
npm run brand
```

The icons are the live copies from SVN. Refresh them with:

```sh
svn export https://plugins.svn.wordpress.org/globus-debug-control/assets/icon-128x128.png
svn export https://plugins.svn.wordpress.org/globus-debug-control/assets/icon-256x256.png
```

Screenshots are generated, not stored here - see [`../screenshots`](../screenshots).
