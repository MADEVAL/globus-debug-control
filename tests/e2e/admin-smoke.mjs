/**
 * End-to-end smoke test for the GLOBUS Debug Control admin screens.
 *
 * Dev-only. Uses the Playwright copy that ships with tools/screenshots, so no
 * extra npm install is needed. Requires a running local WordPress with the
 * plugin active and an administrator account.
 *
 * Usage:
 *   $env:GDC_E2E_USER='admin'; $env:GDC_E2E_PASS='...'; node tests/e2e/admin-smoke.mjs
 *
 * Environment:
 *   GDC_E2E_URL   Base URL (default http://127.0.0.1:8088)
 *   GDC_E2E_USER  Administrator login (required)
 *   GDC_E2E_PASS  Administrator password (required)
 */

import { chromium } from '../../tools/screenshots/node_modules/playwright/index.mjs';

const BASE = process.env.GDC_E2E_URL || 'http://127.0.0.1:8088';
const USER = process.env.GDC_E2E_USER;
const PASS = process.env.GDC_E2E_PASS;

if ( ! USER || ! PASS ) {
	console.log( 'SKIP: set GDC_E2E_USER and GDC_E2E_PASS to run the admin smoke test.' );
	process.exit( 0 );
}

const PAGES = [
	[ 'globus-debug-control', 'Overview' ],
	[ 'gdc-settings', 'Settings' ],
	[ 'gdc-wp-debug', 'WP_DEBUG' ],
	[ 'gdc-log', 'Error Log' ],
	[ 'gdc-cron', 'Cron Tasks' ],
	[ 'gdc-transients', 'Transients' ],
	[ 'gdc-autoloaded', 'Autoloaded Options' ],
	[ 'gdc-http', 'HTTP Log' ],
	[ 'gdc-constants', 'Constants' ],
];

const failures = [];
const pageErrors = [];

function check( name, condition, detail = '' ) {
	if ( condition ) {
		console.log( `  ok   ${ name }` );
		return;
	}

	failures.push( `${ name }${ detail ? ' - ' + detail : '' }` );
	console.log( `  FAIL ${ name }${ detail ? ' - ' + detail : '' }` );
}

async function gotoPage( page, slug ) {
	const response = await page.goto( `${ BASE }/wp-admin/admin.php?page=${ slug }`, {
		waitUntil: 'domcontentloaded',
	} );
	await page.waitForTimeout( 500 );
	return response;
}

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: { width: 1500, height: 1000 } } );
const page = await context.newPage();

page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );

try {
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );

	if ( await page.locator( '#user_login' ).count() ) {
		await page.fill( '#user_login', USER );
		await page.fill( '#user_pass', PASS );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
			page.click( '#wp-submit' ),
		] );
	}

	console.log( '\nScreens' );

	for ( const [ slug, heading ] of PAGES ) {
		const response = await gotoPage( page, slug );
		const h1 = ( await page.locator( '.gdc-pagehead-title' ).first().textContent().catch( () => '' ) || '' ).trim();

		check( `${ slug } loads`, response && response.status() === 200 );
		check( `${ slug } renders the shell`, ( await page.locator( '.gdc-app' ).count() ) === 1 );
		check( `${ slug } heading`, h1 === heading, h1 );
		check( `${ slug } nav highlight`, ( await page.locator( '.gdc-snav-item.is-active' ).count() ) === 1 );
	}

	console.log( '\nCommand palette' );

	await gotoPage( page, 'globus-debug-control' );
	await page.keyboard.press( 'Control+k' );
	await page.waitForTimeout( 200 );
	check( 'Ctrl+K opens', ( await page.locator( '#gdc-palette:not([hidden])' ).count() ) === 1 );
	check( 'input is focused', await page.evaluate( () => document.activeElement && document.activeElement.id === 'gdc-pal-input' ) );

	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	check(
		'Tab stays inside the dialog',
		await page.evaluate( () => document.querySelector( '#gdc-palette' ).contains( document.activeElement ) )
	);

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 150 );
	check( 'Escape closes', ( await page.locator( '#gdc-palette[hidden]' ).count() ) === 1 );

	console.log( '\nConstants filter' );

	await gotoPage( page, 'gdc-constants' );
	const allRows = await page.locator( '.gdc-constant-row:visible' ).count();
	await page.fill( '#gdc-constants-filter', 'WP_DEBUG' );
	await page.waitForTimeout( 250 );
	const filteredRows = await page.locator( '.gdc-constant-row:visible' ).count();
	check( 'filter reduces rows', allRows > filteredRows && filteredRows > 0, `${ allRows } -> ${ filteredRows }` );

	console.log( '\nMaster switch' );

	await gotoPage( page, 'globus-debug-control' );
	const toggle = page.locator( '[data-gdc-ajax-toggle]' ).first();
	const before = await toggle.getAttribute( 'aria-checked' );
	await toggle.click();
	await page.waitForTimeout( 1200 );
	const after = await toggle.getAttribute( 'aria-checked' );
	check( 'AJAX toggle flips state', before !== after, `${ before } -> ${ after }` );
	check( 'hero title follows', ( await page.locator( '[data-gdc-state-title]' ).textContent() || '' ).trim().length > 0 );

	if ( before !== after ) {
		await toggle.click();
		await page.waitForTimeout( 1200 );
	}
	check( 'state restored', ( await toggle.getAttribute( 'aria-checked' ) ) === before );

	console.log( '\nSettings save' );

	await gotoPage( page, 'gdc-settings' );
	const warnings = page.locator( '#gdc-opt-warnings' );
	const warningsBefore = await warnings.isChecked();
	await warnings.setChecked( ! warningsBefore, { force: true } );

	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.locator( '.gdc-pagehead-actions button[type="submit"]' ).click(),
	] );
	await page.waitForTimeout( 400 );
	check( 'success alert appears', ( await page.locator( '.gdc-alert--success' ).count() ) >= 1 );
	check( 'value persisted', ( await page.locator( '#gdc-opt-warnings' ).isChecked() ) === ! warningsBefore );

	await page.locator( '#gdc-opt-warnings' ).setChecked( warningsBefore, { force: true } );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.locator( '.gdc-pagehead-actions button[type="submit"]' ).click(),
	] );
	await page.waitForTimeout( 300 );

	console.log( '\nDashboard widget' );

	const dashboard = await page.goto( `${ BASE }/wp-admin/index.php`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 500 );
	check( 'dashboard loads', dashboard && dashboard.status() === 200 );
	check( 'widget rendered', ( await page.locator( '.gdc-widget' ).count() ) >= 1 );

	console.log( '\nRuntime errors' );
	check( 'no page errors', pageErrors.length === 0, pageErrors.join( '; ' ) );
} finally {
	await browser.close();
}

if ( failures.length ) {
	console.error( `\n${ failures.length } check(s) failed:` );
	failures.forEach( ( failure ) => console.error( ` - ${ failure }` ) );
	process.exit( 1 );
}

console.log( '\nAll checks passed.' );
