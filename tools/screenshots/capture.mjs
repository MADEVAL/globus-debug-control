/**
 * Renders the GLOBUS Debug Control admin screens from the HTML templates in
 * `templates/` and captures WordPress.org screenshots with Playwright.
 *
 * Usage:
 *   npm install
 *   npm run capture             # render templates + write docs/screenshots/*.png
 *   npm run capture -- --only=settings
 *   npm run render              # render HTML only, no browser
 *
 * Output:
 *   out/                      rendered HTML previews (gitignored)
 *   ../../docs/screenshots/   screenshot-N.png, ready for the wp.org SVN assets
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
const shotDir = path.join( repoRoot, 'docs', 'screenshots' );

const VIEWPORT = { width: 1200, height: 900 };
const SCALE = 1;

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
		caption: 'Error Log tab: colour-coded debug.log with pagination, live polling, download, and clear.',
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
		caption: 'Constants tab: runtime WordPress constants grouped by category, with secrets masked.',
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
	await mkdir( shotDir, { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage( {
		viewport: VIEWPORT,
		deviceScaleFactor: SCALE,
		reducedMotion: 'reduce',
	} );

	for ( const screen of rendered ) {
		await page.goto( `file://${ screen.file.replace( /\\/g, '/' ) }`, { waitUntil: 'load' } );
		await page.evaluate( () => document.fonts.ready );
		await page.waitForTimeout( 150 );

		const target = path.join( shotDir, `screenshot-${ screen.number }.png` );
		await page.screenshot( { path: target, fullPage: false } );
		console.log( `screenshot-${ screen.number }.png  ${ screen.caption }` );
	}

	await browser.close();

	const manifest = SCREENS.map( ( { slug, caption }, index ) => ( {
		file: `screenshot-${ index + 1 }.png`,
		slug,
		caption,
	} ) );
	await writeFile(
		path.join( shotDir, 'screenshots.json' ),
		`${ JSON.stringify( manifest, null, '\t' ) }\n`,
		'utf8'
	);
}

const options = parseArgs( process.argv.slice( 2 ) );
const rendered = await renderPages( options );

if ( options.renderOnly ) {
	console.log( `Rendered ${ rendered.length } page(s) to ${ path.relative( repoRoot, previewDir ) }/` );
} else {
	await capture( rendered );
	console.log( `\nWritten to ${ path.relative( repoRoot, shotDir ) }/` );
}
