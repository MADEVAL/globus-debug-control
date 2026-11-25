/**
 * Renders the GLOBUS Debug Control admin screens from the HTML templates in
 * `templates/` and captures the plugin page with Playwright.
 *
 * The capture is the raw material for `marketing/render.mjs`, which composes
 * the 1600x1000 WordPress.org listing images. Templates are rendered with the
 * real plugin stylesheets and a vendored copy of the WordPress admin CSS, so no
 * WordPress install or database is needed.
 *
 * Templates are generated from the sandbox by `generate-templates.php`; rerun it
 * after a UI change, then `npm run marketing`.
 *
 * Usage:
 *   npm install
 *   npm run capture             # render templates + write raw/*.png
 *   npm run capture -- --only=settings
 *   npm run render              # render HTML only, no browser
 *   npm run marketing           # capture + compose docs/screenshots/*.jpg
 *   npm run brand               # banners and the Open Graph card
 *
 * Output:
 *   out/     rendered HTML previews (gitignored)
 *   raw/     element captures of the plugin page (gitignored)
 */

import { chromium } from 'playwright';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderDocument } from './layout.mjs';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const repoRoot = path.resolve( here, '../..' );
const pluginAssets = path.join( repoRoot, 'globus-debug-control', 'assets' );
const previewDir = path.join( here, 'out' );
const rawDir = path.join( here, 'raw' );

const VIEWPORT = { width: 1600, height: 2400 };
const SCALE = 1;
const SHOT_SELECTOR = '.gdc-app';

// Optional per-slide crop height, used to stop long screens at a card boundary.
const MANIFEST = path.join( here, 'marketing', 'slides.json' );
const SLIDES = JSON.parse( await readFile( MANIFEST, 'utf8' ) ).slides || [];
const MAX_HEIGHTS = Object.fromEntries( SLIDES.map( ( slide ) => [ slide.shot, slide.maxHeight || 1000 ] ) );

const SCREENS = [
	{
		slug: 'overview',
		page: 'globus-debug-control',
		template: '01-overview.html',
		caption: 'Overview: health score, KPIs, cron chart and autoload payload at a glance.',
	},
	{
		slug: 'settings',
		page: 'gdc-settings',
		template: '02-settings.html',
		caption: 'Settings: error filtering, display mode, logging and early interception.',
	},
	{
		slug: 'wp-debug',
		page: 'gdc-wp-debug',
		template: '03-wp-debug.html',
		caption: 'WP_DEBUG: toggle wp-config.php constants with a backup on every write.',
	},
	{
		slug: 'error-log',
		page: 'gdc-log',
		template: '04-error-log.html',
		caption: 'Error Log: dark console with pagination, live polling, download and clear.',
	},
	{
		slug: 'cron',
		page: 'gdc-cron',
		template: '05-cron.html',
		caption: 'Cron Tasks: overdue events, orphaned hooks, source detection and actions.',
	},
	{
		slug: 'transients',
		page: 'gdc-transients',
		template: '06-transients.html',
		caption: 'Transients: size and expiry for every row, plus a bulk purge of expired entries.',
	},
	{
		slug: 'autoloaded',
		page: 'gdc-autoloaded',
		template: '07-autoloaded.html',
		caption: 'Autoloaded Options: the heaviest rows, sorted by size, with warning thresholds.',
	},
	{
		slug: 'http-log',
		page: 'gdc-http',
		template: '08-http-log.html',
		caption: 'HTTP Log: outgoing API calls with status, transport, duration and masked URLs.',
	},
	{
		slug: 'constants',
		page: 'gdc-constants',
		template: '09-constants.html',
		caption: 'Constants: grouped runtime constants with filtering and secret masking.',
	},
];

function toHref( fromDir, target ) {
	return path.relative( fromDir, target ).split( path.sep ).join( '/' );
}

function stylesheetHrefs() {
	const wp = path.join( here, 'vendor', 'wp', 'css' );
	const files = [
		path.join( wp, 'common.min.css' ),
		path.join( wp, 'forms.min.css' ),
		path.join( wp, 'admin-menu.min.css' ),
		path.join( wp, 'list-tables.min.css' ),
		path.join( wp, 'edit.min.css' ),
		path.join( wp, 'dashicons.min.css' ),
		path.join( wp, 'buttons.min.css' ),
		path.join( wp, 'admin-bar.min.css' ),
		path.join( wp, 'colors', 'modern', 'colors.min.css' ),
		path.join( pluginAssets, 'css', 'gdc-tokens.css' ),
		path.join( pluginAssets, 'css', 'gdc-ui.css' ),
		path.join( pluginAssets, 'css', 'gdc-components.css' ),
		path.join( here, 'mock.css' ),
	];

	return files.map( ( file ) => toHref( previewDir, file ) );
}

function scriptHrefs() {
	return [
		toHref( previewDir, path.join( pluginAssets, 'vendor', 'chartjs', 'chart.umd.js' ) ),
		toHref( previewDir, path.join( pluginAssets, 'js', 'gdc-ui.js' ) ),
	];
}

const ASSET_PREFIX = 'https://example.com/wp-content/plugins/globus-debug-control/assets/';

function parseArgs( argv ) {
	const options = { renderOnly: false, only: null };
	for ( const arg of argv ) {
		if ( arg === '--render-only' ) {
			options.renderOnly = true;
		} else if ( arg.startsWith( '--only=' ) ) {
			options.only = arg.slice( '--only='.length );
		}
	}
	return options;
}

async function renderPages( options ) {
	await mkdir( previewDir, { recursive: true } );
	const hrefs = stylesheetHrefs();
	const scripts = scriptHrefs();
	const rendered = [];
	const assetHref = toHref( previewDir, pluginAssets ) + '/';
	let sprite = '';

	try {
		sprite = await readFile( path.join( here, 'templates', 'sprite.html' ), 'utf8' );
	} catch {
		sprite = '';
	}

	for ( const [ index, screen ] of SCREENS.entries() ) {
		if ( options.only && screen.slug !== options.only ) {
			continue;
		}

		const template = await readFile( path.join( here, 'templates', screen.template ), 'utf8' );
		const content = ( sprite ? sprite + '\n' : '' ) + template.replaceAll( ASSET_PREFIX, assetHref );
		const html = renderDocument( {
			page: screen.page,
			content,
			cssHrefs: hrefs,
			jsHrefs: scripts,
		} );
		const file = path.join( previewDir, `${ String( index + 1 ).padStart( 2, '0' ) }-${ screen.slug }.html` );

		await writeFile( file, html, 'utf8' );
		rendered.push( { ...screen, file, number: index + 1 } );
	}

	return rendered;
}

async function capture( rendered ) {
	await mkdir( rawDir, { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage( {
		viewport: VIEWPORT,
		deviceScaleFactor: SCALE,
		reducedMotion: 'reduce',
	} );

	for ( const [ index, screen ] of rendered.entries() ) {
		await page.goto( `file://${ screen.file.replace( /\\/g, '/' ) }`, { waitUntil: 'load' } );
		await page.evaluate( () => document.fonts.ready );
		// Chart.js animations are not affected by prefers-reduced-motion.
		await page.waitForTimeout( 600 );

		const target = path.join( rawDir, `${ screen.slug }.png` );
		const panel = page.locator( SHOT_SELECTOR );
		const box = await panel.boundingBox();
		if ( ! box ) {
			throw new Error( `selector ${ SHOT_SELECTOR } not found on ${ screen.slug }` );
		}
		// Very long screens are cropped at a card boundary so the composed
		// marketing frame stays legible at 950px wide.
		const maxHeight = MAX_HEIGHTS[ screen.slug ] || 1000;
		await page.screenshot( {
			path: target,
			clip: {
				x: box.x,
				y: Math.max( 0, box.y ),
				width: box.width,
				height: Math.min( box.height, maxHeight ),
			},
		} );
		console.log( `${ String( index + 1 ).padStart( 2, '0' ) }-${ screen.slug }.png  ${ screen.caption }` );
	}

	await browser.close();
}

const options = parseArgs( process.argv.slice( 2 ) );
const rendered = await renderPages( options );

if ( options.renderOnly ) {
	console.log( `Rendered ${ rendered.length } page(s) to ${ path.relative( repoRoot, previewDir ) }/` );
} else {
	await capture( rendered );
	console.log( `\nWritten to ${ path.relative( repoRoot, rawDir )}/` );
}
