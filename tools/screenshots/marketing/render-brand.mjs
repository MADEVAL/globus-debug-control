#!/usr/bin/env node
// render-brand.mjs - compose the WordPress.org banners and the Open Graph card.
// Deterministic output: the icon comes from docs/wordpress-org/icon-256x256.png.
//
// Usage:
//   npm run brand
//   node marketing/render-brand.mjs --only banner --no-locales
//   node marketing/render-brand.mjs --only opengraph

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { chromium } from 'playwright';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const REPO_ROOT = path.resolve( HERE, '..', '..', '..' );
const LOGO_PATH = path.join( REPO_ROOT, 'docs', 'wordpress-org', 'icon-256x256.png' );

const DEFAULT_BANNER_OUT = path.join( REPO_ROOT, 'docs', 'wordpress-org' );
const DEFAULT_OG_OUT = path.join( REPO_ROOT, 'docs', 'social' );

const BANNER_WIDTH = 1544;
const BANNER_HEIGHT = 500;
const BANNERS = [
	{ width: 772, height: 250, file: 'banner-772x250.png' },
	{ width: BANNER_WIDTH, height: BANNER_HEIGHT, file: 'banner-1544x500.png' },
];
const OG_WIDTH = 1200;
const OG_HEIGHT = 630;
const OG_FILE = 'globus-debug-control-1200x630.png';

const DEFAULT_COPY = {
	lang: 'en',
	dir: 'ltr',
	eyebrow: 'Debug control for WordPress',
	headline: 'The debug panel WordPress forgot to ship',
	tagline: '<strong class="stat">8</strong> tools in one screen: error filtering, WP_DEBUG toggle, log viewer, cron, transients, autoloaded options, HTTP log and constants.',
	chips: [
		'Error filtering',
		'WP_DEBUG toggle',
		'Log viewer',
		'Cron tasks',
		'Transients',
		'Autoloaded options',
		'HTTP log',
		'Constants',
		'MU-plugin dropin',
		'wp-config backup',
		'REST + MCP abilities',
		'Secret masking',
		'One-click clear',
		'No SSH needed',
		'Single screen',
	],
};

// WordPress.org serves banner-<size>-<locale>.png on a localized plugin page.
const LOCALES = [
	{
		code: 'ru_RU',
		eyebrow: 'Панель отладки для WordPress',
		headline: 'Панель отладки, которой не хватает WordPress',
		tagline: '<strong class="stat">8</strong> инструментов на одном экране: фильтрация ошибок, переключатель WP_DEBUG, просмотр логов, cron, transients, autoload, HTTP-лог и константы.',
		chips: [
			'Фильтрация ошибок',
			'Переключатель WP_DEBUG',
			'Просмотр логов',
			'Cron-задачи',
			'Transients',
			'Autoload-опции',
			'HTTP-лог',
			'Константы',
			'MU-plugin',
			'Бэкап wp-config',
			'REST и MCP',
			'Маскировка секретов',
			'Очистка в один клик',
			'Без SSH',
			'Один экран',
		],
	},
	{
		code: 'uk',
		eyebrow: 'Панель налагодження для WordPress',
		headline: 'Панель налагодження, якої бракує WordPress',
		tagline: '<strong class="stat">8</strong> інструментів на одному екрані: фільтрація помилок, перемикач WP_DEBUG, перегляд логів, cron, transients, autoload, HTTP-лог і константи.',
		chips: [
			'Фільтрація помилок',
			'Перемикач WP_DEBUG',
			'Перегляд логів',
			'Cron-завдання',
			'Transients',
			'Autoload-опції',
			'HTTP-лог',
			'Константи',
			'MU-plugin',
			'Бекап wp-config',
			'REST і MCP',
			'Маскування секретів',
			'Очищення одним кліком',
			'Без SSH',
			'Один екран',
		],
	},
	{
		code: 'de_DE',
		eyebrow: 'Debug-Steuerung für WordPress',
		headline: 'Das Debug-Panel, das WordPress fehlt',
		tagline: '<strong class="stat">8</strong> Werkzeuge auf einem Bildschirm: Fehlerfilter, WP_DEBUG-Schalter, Log-Viewer, Cron, Transients, Autoload, HTTP-Log und Konstanten.',
		chips: [
			'Fehlerfilter',
			'WP_DEBUG-Schalter',
			'Log-Viewer',
			'Cron-Aufgaben',
			'Transients',
			'Autoload-Optionen',
			'HTTP-Log',
			'Konstanten',
			'MU-Plugin',
			'wp-config-Backup',
			'REST und MCP',
			'Secret-Maskierung',
			'Log leeren',
			'Ohne SSH',
			'Ein Bildschirm',
		],
	},
	{
		code: 'fr_FR',
		eyebrow: 'Contrôle de débogage pour WordPress',
		headline: 'Le panneau de débogage qui manque à WordPress',
		tagline: '<strong class="stat">8</strong> outils sur un seul écran : filtrage des erreurs, bascule WP_DEBUG, lecteur de logs, cron, transients, autoload, journal HTTP et constantes.',
		chips: [
			'Filtrage des erreurs',
			'Bascule WP_DEBUG',
			'Lecteur de logs',
			'Tâches cron',
			'Transients',
			'Options autoload',
			'Journal HTTP',
			'Constantes',
			'MU-plugin',
			'Sauvegarde wp-config',
			'REST et MCP',
			'Masquage des secrets',
			'Vider le log',
			'Sans SSH',
			'Un seul écran',
		],
	},
	{
		code: 'es_ES',
		eyebrow: 'Control de depuración para WordPress',
		headline: 'El panel de depuración que WordPress no incluye',
		tagline: '<strong class="stat">8</strong> herramientas en una pantalla: filtrado de errores, interruptor WP_DEBUG, visor de logs, cron, transients, autoload, log HTTP y constantes.',
		chips: [
			'Filtrado de errores',
			'Interruptor WP_DEBUG',
			'Visor de logs',
			'Tareas cron',
			'Transients',
			'Opciones autoload',
			'Log HTTP',
			'Constantes',
			'MU-plugin',
			'Copia de wp-config',
			'REST y MCP',
			'Enmascarar secretos',
			'Limpiar en un clic',
			'Sin SSH',
			'Una sola pantalla',
		],
	},
];

function usage() {
	return [
		'Usage: node render-brand.mjs [options]',
		'',
		'  --only <list>        banner, opengraph (default: both)',
		'  --locale <list>      Render only these banner locales (comma-separated codes)',
		'  --no-locales         English banner only',
		'  --banner-out <dir>   Banner directory (default: docs/wordpress-org/)',
		'  --og-out <dir>       Open Graph directory (default: docs/social/)',
		'  --help               Show this help',
	].join( '\n' );
}

function parseArgs( argv ) {
	const options = {
		only: [ 'banner', 'opengraph' ],
		bannerOut: DEFAULT_BANNER_OUT,
		ogOut: DEFAULT_OG_OUT,
		locales: null,
	};
	for ( let index = 0; index < argv.length; index += 1 ) {
		const flag = argv[ index ];
		const value = () => {
			index += 1;
			if ( index >= argv.length ) throw new Error( `${ flag } needs a value` );
			return argv[ index ];
		};
		if ( flag === '--only' ) options.only = value().split( ',' ).map( ( entry ) => entry.trim() ).filter( Boolean );
		else if ( flag === '--locale' ) options.locales = value().split( ',' ).map( ( entry ) => entry.trim() ).filter( Boolean );
		else if ( flag === '--no-locales' ) options.locales = [];
		else if ( flag === '--banner-out' ) options.bannerOut = path.resolve( value() );
		else if ( flag === '--og-out' ) options.ogOut = path.resolve( value() );
		else if ( flag === '--help' || flag === '-h' ) {
			console.log( usage() );
			process.exit( 0 );
		} else {
			throw new Error( `unknown option: ${ flag }` );
		}
	}
	for ( const name of options.only ) {
		if ( ! [ 'banner', 'opengraph' ].includes( name ) ) throw new Error( `unknown target: ${ name }` );
	}
	if ( options.locales ) {
		const known = new Set( LOCALES.map( ( locale ) => locale.code ) );
		for ( const code of options.locales ) {
			if ( ! known.has( code ) ) throw new Error( `unknown locale: ${ code }` );
		}
	}
	return options;
}

const logoUri = `data:image/png;base64,${ fs.readFileSync( LOGO_PATH ).toString( 'base64' ) }`;

function logoMarkup() {
	return `<span class="logo-chip"><img src="${ logoUri }" alt="GLOBUS"><span class="wordmark"><b>GLOBUS</b><span>Debug Control</span></span></span>`;
}

function chipsHtml( chips ) {
	return chips.map( ( chip ) => `<span class="chip"><i></i>${ chip }</span>` ).join( '' );
}

function baseStyle() {
	return `
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { font-family: "Segoe UI", "Inter", "Helvetica Neue", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
  .canvas {
    position: relative;
    overflow: hidden;
    color: #f5f9ff;
    background:
      radial-gradient(900px 620px at 96% -18%, rgba(47, 107, 255, 0.55), transparent 62%),
      radial-gradient(720px 560px at -8% 118%, rgba(6, 182, 212, 0.30), transparent 60%),
      linear-gradient(140deg, #050a14 0%, #0b1730 58%, #07101f 100%);
  }
  .canvas::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 46px 46px;
    -webkit-mask-image: radial-gradient(80% 90% at 55% 40%, #000 10%, transparent 100%);
    mask-image: radial-gradient(80% 90% at 55% 40%, #000 10%, transparent 100%);
    pointer-events: none;
  }
  .canvas::after {
    content: "";
    position: absolute;
    width: 760px;
    height: 760px;
    right: -260px;
    top: -330px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(121, 167, 255, 0.22), transparent 68%);
    pointer-events: none;
  }
  .logo-chip { display: inline-flex; align-items: center; gap: 12px; background: #ffffff; border-radius: 18px; box-shadow: 0 26px 60px -28px rgba(0, 0, 0, 0.75); }
  .logo-chip img { display: block; border-radius: 10px; }
  .wordmark { display: inline-flex; gap: 7px; font-weight: 800; letter-spacing: -0.02em; color: #0a1220; white-space: nowrap; }
  .wordmark span { color: #2f6bff; font-weight: 700; }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid rgba(121, 167, 255, 0.34);
    background: rgba(47, 107, 255, 0.16);
    color: #9dc0ff;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
  }
  .eyebrow::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: #79a7ff; box-shadow: 0 0 0 4px rgba(121, 167, 255, 0.18); }
  h1 { margin: 0; font-weight: 800; letter-spacing: -0.03em; line-height: 1.04; color: #ffffff; }
  .tagline { margin: 0; color: #a9b9d6; line-height: 1.38; }
  .stat { color: #ffffff; font-weight: 800; direction: ltr; unicode-bidi: isolate; }
  .chips { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); width: 100%; }
  .chip {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 9px;
    min-width: 0;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.055);
    color: #dbe7ff;
    font-weight: 600;
    white-space: nowrap;
  }
  .chip i { flex: 0 0 auto; width: 7px; height: 7px; border-radius: 50%; background: #4f8cff; box-shadow: 0 0 0 3px rgba(79, 140, 255, 0.18); }
  `;
}

function bannerHtml( scale, copy ) {
	return `<!doctype html>
<html lang="${ copy.lang || copy.code }"><head><meta charset="utf-8">
<style>
${ baseStyle() }
  .canvas[dir="rtl"] .eyebrow { letter-spacing: 0; text-transform: none; }
  html, body { width: ${ BANNER_WIDTH }px; height: ${ BANNER_HEIGHT }px; }
  .canvas { width: ${ BANNER_WIDTH }px; height: ${ BANNER_HEIGHT }px; padding: 44px 64px; display: flex; flex-direction: column; justify-content: center; }
  .top { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
  .logo-chip { padding: 9px 18px 9px 10px; }
  .logo-chip img { height: 46px; width: 46px; }
  .wordmark { font-size: 30px; }
  .eyebrow { font-size: 13px; }
  .copy { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; margin-top: 18px; }
  h1 { font-size: 56px; max-width: 1416px; }
  .tagline { font-size: 20px; max-width: 1360px; }
  .chips { gap: 8px; margin-top: 14px; }
  .chip { padding: 7px 12px; font-size: 14px; }
  .viewport { width: ${ BANNER_WIDTH }px; height: ${ BANNER_HEIGHT }px; overflow: hidden; direction: ltr; }
  .inner { width: ${ BANNER_WIDTH }px; height: ${ BANNER_HEIGHT }px; transform: scale(${ scale }); transform-origin: top left; }
</style></head>
<body><div class="viewport"><div class="inner"><div class="canvas" dir="${ copy.dir || 'ltr' }">
  <div class="top">
    ${ logoMarkup() }
    <span class="eyebrow">${ copy.eyebrow }</span>
  </div>
  <div class="copy">
    <h1>${ copy.headline }</h1>
    <p class="tagline">${ copy.tagline }</p>
  </div>
  <div class="chips">${ chipsHtml( copy.chips ) }</div>
</div></div></div></body></html>`;
}

function ogHtml() {
	return `<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<style>
${ baseStyle() }
  html, body { width: ${ OG_WIDTH }px; height: ${ OG_HEIGHT }px; }
  .canvas { width: ${ OG_WIDTH }px; height: ${ OG_HEIGHT }px; padding: 52px 72px 44px; display: flex; flex-direction: column; justify-content: flex-start; }
  .top { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
  .logo-chip { padding: 12px 20px 12px 12px; border-radius: 16px; }
  .logo-chip img { height: 58px; width: 58px; }
  .wordmark { font-size: 34px; }
  .eyebrow { font-size: 13px; }
  .copy { display: flex; flex-direction: column; gap: 14px; margin-top: 30px; }
  h1 { font-size: 48px; max-width: 1048px; }
  .tagline { font-size: 21px; max-width: 1048px; }
  .chips { gap: 10px; margin-top: 26px; }
  .chip { padding: 8px 12px; font-size: 13px; }
  .footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
  .domain { font-weight: 700; font-size: 19px; letter-spacing: 0.02em; color: #9dc0ff; }
</style></head>
<body><div class="canvas">
  <div class="top">
    ${ logoMarkup() }
    <span class="eyebrow">${ DEFAULT_COPY.eyebrow }</span>
  </div>
  <div class="copy">
    <h1>${ DEFAULT_COPY.headline }</h1>
    <p class="tagline">${ DEFAULT_COPY.tagline }</p>
  </div>
  <div class="chips">${ chipsHtml( DEFAULT_COPY.chips.slice( 0, 10 ) ) }</div>
  <div class="footer">
    <span class="domain">wordpress.org/plugins/globus-debug-control</span>
  </div>
</div></body></html>`;
}

async function main() {
	const options = parseArgs( process.argv.slice( 2 ) );
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage( { viewport: { width: BANNER_WIDTH, height: BANNER_HEIGHT } } );
		if ( options.only.includes( 'banner' ) ) {
			fs.mkdirSync( options.bannerOut, { recursive: true } );
			const localized = options.locales === null
				? LOCALES
				: LOCALES.filter( ( locale ) => options.locales.includes( locale.code ) );
			const copies = [ DEFAULT_COPY, ...localized ];
			for ( const copy of copies ) {
				for ( const banner of BANNERS ) {
					const scale = banner.width / BANNER_WIDTH;
					const suffix = copy.code ? `-${ copy.code }` : '';
					const file = path.join( options.bannerOut, banner.file.replace( /\.png$/, `${ suffix }.png` ) );
					await page.setViewportSize( { width: banner.width, height: banner.height } );
					await page.setContent( bannerHtml( scale, copy ), { waitUntil: 'load' } );
					await page.screenshot( { path: file, type: 'png' } );
					console.log( `banner ${ banner.width }x${ banner.height }${ suffix || ' (en)' } -> ${ path.relative( REPO_ROOT, file ) }` );
				}
			}
		}
		if ( options.only.includes( 'opengraph' ) ) {
			fs.mkdirSync( options.ogOut, { recursive: true } );
			await page.setViewportSize( { width: OG_WIDTH, height: OG_HEIGHT } );
			await page.setContent( ogHtml(), { waitUntil: 'load' } );
			const file = path.join( options.ogOut, OG_FILE );
			await page.screenshot( { path: file, type: 'png' } );
			console.log( `opengraph ${ OG_WIDTH }x${ OG_HEIGHT } -> ${ path.relative( REPO_ROOT, file ) }` );
		}
	} finally {
		await browser.close();
	}
}

main().catch( ( error ) => {
	console.error( error.message );
	process.exit( 1 );
} );
