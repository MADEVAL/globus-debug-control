# Translation drafts

Source template: `globus-debug-control/languages/globus-debug-control.pot`.

These `.po` files are drafts for
[translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/globus-debug-control/).
They are **not** shipped in the plugin ZIP: the plugin relies on WordPress
language packs and does not call `load_plugin_textdomain()`.

| Locale | Language | File |
| --- | --- | --- |
| `ru_RU` | Russian | `ru_RU.po` |
| `uk` | Ukrainian | `uk.po` |
| `de_DE` | German | `de_DE.po` |
| `fr_FR` | French | `fr_FR.po` |
| `es_ES` | Spanish | `es_ES.po` |

## Validate and compile

```sh
composer pot    # refresh the template first

php vendor/wp-cli/wp-cli/php/boot-fs.php i18n make-mo docs/translations/ru_RU.po
```

## Test on a local site

WordPress looks for plugin language packs in `wp-content/languages/plugins/`:

```sh
cp docs/translations/ru_RU.mo wordpress/wp-content/languages/plugins/globus-debug-control-ru_RU.mo
```

Then set the site language to Russian. To generate the faster PHP translation
file (WordPress 6.5+):

```sh
php vendor/wp-cli/wp-cli/php/boot-fs.php i18n make-php docs/translations/ru_RU.po
```
