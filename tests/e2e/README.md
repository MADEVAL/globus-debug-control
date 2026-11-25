# Admin end-to-end smoke test

`admin-smoke.mjs` drives the nine admin screens in a real browser and checks the
shell, navigation, command palette focus handling, constants filter, the AJAX
master switch, the settings form save and the dashboard widget.

It reuses the Playwright copy in `tools/screenshots/node_modules`, so no extra
npm install is needed there.

## Run

```powershell
# local sandbox
$env:GDC_E2E_URL  = 'http://127.0.0.1:8088'
$env:GDC_E2E_USER = 'admin'
$env:GDC_E2E_PASS = '<password>'
node tests/e2e/admin-smoke.mjs
```

The script restores every setting it changes. Without `GDC_E2E_USER` and
`GDC_E2E_PASS` it prints `SKIP` and exits 0.

Checks:

- all nine pages return 200, render `.gdc-app`, one active nav item and the
  expected heading;
- `Ctrl+K` opens the palette, focuses the input, traps Tab and closes on Escape;
- the constants filter narrows the table;
- the Overview master switch flips over AJAX and restores;
- the Settings form saves, shows the success alert and persists the value;
- the dashboard widget renders;
- no uncaught page errors.
