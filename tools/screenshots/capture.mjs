/**
 * Renders the GLOBUS Debug Control admin screens from the HTML templates in
 * `templates/` and captures the plugin page with Playwright.
 *
 * The capture is the raw material for `marketing/render.mjs`, which composes
 * the 1600x1000 WordPress.org listing images. Templates are rendered with the
 * real plugin stylesheet and a vendored copy of the WordPress admin CSS, so no
 * WordPress install or database is needed.
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
const pluginCss = path.join( repoRoot, 'globus-debug-control', 'assets', 'css', 'admin.css' );
const previewDir = path.join( here, 'out' );
const rawDir = path.join( here, 'raw' );

const VIEWPORT = { width: 1500, height: 2400 };
const SCALE = 1;
const SHOT_SELECTOR = '.wrap.gdc-page';

// Optional per-slide crop height, used to stop long tabs at a card boundary.
const MANIFEST = path.join( here, 'marketing', 'slides.json' );
const SLIDES = JSON.parse( await readFile( MANIFEST, 'utf8' ) ).slides || [];
const MAX_HEIGHTS = Object.fromEntries( SLIDES.map( ( slide ) => [ slide.shot, slide.maxHeight || 1000 ] ) );

const SCREENS = [
	{
		slug: 'settings',
		template: '01-settings.html',
		tab: 'settings',
		caption: 'Settings tab: filter PHP error types, choose the error display mode, and configure logging.',
	},
	{
		slug: 'wp-debug',
		template: '02-wp-debug.html',
		tab: 'wpcfg',
		caption: 'WP_DEBUG tab: toggle debug constants in wp-config.php without editing files.',
	},
	{
		slug: 'error-log',
		template: '03-error-log.html',
		tab: 'log',
		caption: 'Error Log tab: color-coded debug.log with pagination, live polling, download, and clear.',
	},
	{
		slug: 'cron',
		template: '04-cron.html',
		tab: 'cron',
		caption: 'Cron Tasks tab: overdue events, orphaned hooks, source detection, and one-click actions.',
	},
	{
		slug: 'transients',
		template: '05-transients.html',
		tab: 'transients',
		caption: 'Transients tab: every database transient with size and expiry, plus bulk purge of expired rows.',
	},
	{
		slug: 'autoloaded',
		template: '06-autoloaded.html',
		tab: 'autoloaded',
		caption: 'Autoloaded tab: the heaviest autoloaded options, sorted by size, to spot request bloat.',
	},
	{
		slug: 'http-log',
		template: '07-http-log.html',
		tab: 'http',
		caption: 'HTTP Log tab: outgoing WordPress HTTP API calls with status, transport, and duration.',
	},
	{
		slug: 'constants',
		template: '08-constants.html',
		tab: 'constants',
		caption: 'Constants tab: every runtime constant, including plugin and theme constants, with filtering and secret masking.',
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
		pluginCss,
		path.join( here, 'mock.css' ),
	];

	return files.map( ( file ) => toHref( previewDir, file ) );
}

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
	const rendered = [];

	for ( const [ index, screen ] of SCREENS.entries() ) {
		if ( options.only && screen.slug !== options.only ) {
			continue;
		}

		const template = await readFile( path.join( here, 'templates', screen.template ), 'utf8' );
		const html = renderDocument( { tab: screen.tab, content: template, cssHrefs: hrefs } );
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
		await page.waitForTimeout( 150 );

		const target = path.join( rawDir, `${ screen.slug }.png` );
		const panel = page.locator( SHOT_SELECTOR );
		const box = await panel.boundingBox();
		if ( ! box ) {
			throw new Error( `selector ${ SHOT_SELECTOR } not found on ${ screen.slug }` );
		}
		// Very long tabs (Settings) are cropped at a card boundary so the
		// composed marketing frame stays legible at 950px wide.
		const maxHeight = MAX_HEIGHTS[ screen.slug ] || 1000;
		await page.screenshot( {
			path: target,
			clip: {
				x: box.x,
				y: box.y,
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
	console.log( `\nWritten to ${ path.relative( repoRoot, rawDir ) }/` );
}
