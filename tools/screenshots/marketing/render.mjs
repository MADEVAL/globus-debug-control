#!/usr/bin/env node
// render.mjs - compose the WordPress.org listing screenshots.
//
// Input:
//   slides.json                     copy, order and capture names
//   ../raw/<shot>.png               element captures written by ../capture.mjs
// Output:
//   ../../../docs/screenshots/screenshot-N.jpg   1600x1000 listing images
//   ../out/preview.html                          local contact sheet (gitignored)
//
// The raw capture and the composition are separate steps: `npm run marketing`
// does both, `npm run capture` only refreshes the raw PNGs.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { chromium } from 'playwright';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const REPO_ROOT = path.resolve( HERE, '..', '..', '..' );
const LOGO_PATH = path.join( REPO_ROOT, 'docs', 'wordpress-org', 'icon-256x256.png' );

const DEFAULT_MANIFEST = path.join( HERE, 'slides.json' );
const DEFAULT_SHOTS = path.join( HERE, '..', 'raw' );
const DEFAULT_OUT = path.join( REPO_ROOT, 'docs', 'screenshots' );
const DEFAULT_PREVIEW = path.join( HERE, '..', 'out', 'preview.html' );

function usage() {
	return [
		'Usage: node render.mjs [options]',
		'',
		'  --manifest <file>  Slide copy manifest (default: marketing/slides.json)',
		'  --shots <dir>      Raw capture directory (default: tools/screenshots/raw)',
		'  --out <dir>        Output directory (default: docs/screenshots/)',
		'  --only <list>      Comma-separated slide ids',
		'  --format <ext>     jpg or png (default: jpg)',
		'  --quality <1-100>  JPEG quality (default: 92)',
		'  --no-preview       Do not write preview.html',
		'  --help             Show this help',
	].join( '\n' );
}

function parseArgs( argv ) {
	const options = {
		manifest: DEFAULT_MANIFEST,
		shots: DEFAULT_SHOTS,
		out: DEFAULT_OUT,
		preview: DEFAULT_PREVIEW,
		only: [],
		format: 'jpg',
		quality: 92,
		previewEnabled: true,
	};
	for ( let index = 0; index < argv.length; index += 1 ) {
		const flag = argv[ index ];
		const value = () => {
			index += 1;
			if ( index >= argv.length ) throw new Error( `${ flag } needs a value` );
			return argv[ index ];
		};
		if ( flag === '--manifest' ) options.manifest = path.resolve( value() );
		else if ( flag === '--shots' ) options.shots = path.resolve( value() );
		else if ( flag === '--out' ) options.out = path.resolve( value() );
		else if ( flag === '--preview' ) options.preview = path.resolve( value() );
		else if ( flag === '--only' ) options.only = value().split( ',' ).map( ( entry ) => entry.trim() ).filter( Boolean );
		else if ( flag === '--format' ) options.format = value().trim().toLowerCase();
		else if ( flag === '--quality' ) options.quality = Number( value() );
		else if ( flag === '--no-preview' ) options.previewEnabled = false;
		else if ( flag === '--help' || flag === '-h' ) {
			console.log( usage() );
			process.exit( 0 );
		} else {
			throw new Error( `unknown option: ${ flag }` );
		}
	}
	if ( ! [ 'jpg', 'jpeg', 'png' ].includes( options.format ) ) throw new Error( '--format must be jpg or png' );
	if ( options.format === 'jpeg' ) options.format = 'jpg';
	if ( ! Number.isFinite( options.quality ) || options.quality < 1 || options.quality > 100 ) {
		throw new Error( '--quality must be a number from 1 to 100' );
	}
	return options;
}

function readJson( file ) {
	return JSON.parse( fs.readFileSync( file, 'utf8' ) );
}

function escapeHtml( value ) {
	return String( value )
		.replaceAll( '&', '&amp;' )
		.replaceAll( '<', '&lt;' )
		.replaceAll( '>', '&gt;' )
		.replaceAll( '"', '&quot;' )
		.replaceAll( "'", '&#39;' );
}

function dataUri( file, mime ) {
	return `data:${ mime };base64,${ fs.readFileSync( file ).toString( 'base64' ) }`;
}

function findShot( shotsRoot, shot ) {
	const exact = path.join( shotsRoot, `${ shot }.png` );
	if ( fs.existsSync( exact ) ) return exact;

	const directory = path.join( shotsRoot, shot );
	if ( fs.existsSync( directory ) ) {
		const candidates = fs.readdirSync( directory )
			.filter( ( name ) => name.toLowerCase().endsWith( '.png' ) )
			.sort();
		if ( candidates.length ) return path.join( directory, candidates[ 0 ] );
	}

	throw new Error( `missing capture for "${ shot }": run npm run capture first (expected ${ exact })` );
}

function pngSize( file ) {
	const buffer = fs.readFileSync( file );
	if ( buffer.length < 24 || buffer.toString( 'ascii', 1, 4 ) !== 'PNG' ) {
		throw new Error( `capture is not a PNG: ${ file }` );
	}
	return { width: buffer.readUInt32BE( 16 ), height: buffer.readUInt32BE( 20 ) };
}

function checkMark( canvas ) {
	const size = canvas.bulletIcon || 22;
	return `<svg class="check" viewBox="0 0 22 22" width="${ size }" height="${ size }" aria-hidden="true"><circle cx="11" cy="11" r="11"/><path d="M6.3 11.4l3.1 3.1 6.3-6.5"/></svg>`;
}

function logoMarkup( logoUri ) {
	return `<span class="logo-chip"><img class="logo" src="${ logoUri }" alt="GLOBUS"><span class="wordmark"><b>GLOBUS</b><span>Debug Control</span></span></span>`;
}

function slideHtml( slide, context ) {
	const { width, height, logoUri, shotUri, check, viewHeight, manifest } = context;
	const theme = slide.theme === 'dark' ? 'theme-dark' : 'theme-light';
	const bullets = ( slide.bullets || [] )
		.map( ( bullet ) => `<li>${ check }<span>${ escapeHtml( bullet ) }</span></li>` )
		.join( '' );
	const brandline = slide.brandline || manifest.brandline || 'Debug control for WordPress';
	const footerLeft = ( manifest.footer && manifest.footer.left ) || 'GLOBUS Debug Control';
	const footerRight = ( manifest.footer && manifest.footer.right ) || 'wordpress.org/plugins/globus-debug-control';

	return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page { size: ${ width }px ${ height }px; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; width: ${ width }px; height: ${ height }px; overflow: hidden; }
  body { font-family: "Segoe UI", "Inter", "Helvetica Neue", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
  .canvas {
    position: relative;
    width: ${ width }px;
    height: ${ height }px;
    padding: 48px 64px;
    overflow: hidden;
    --ink: #0a1220;
    --muted: #53627a;
    --soft: #72819a;
    --accent: #2f6bff;
    --accent-soft: rgba(47, 107, 255, 0.12);
    --line: rgba(15, 23, 42, 0.09);
    --card: #ffffff;
    --wash:
      radial-gradient(1100px 760px at 92% -12%, rgba(47, 107, 255, 0.18), transparent 62%),
      radial-gradient(900px 680px at -12% 112%, rgba(14, 165, 233, 0.14), transparent 60%),
      linear-gradient(180deg, #f9fbff 0%, #eef3fb 100%);
    --grid: rgba(15, 23, 42, 0.045);
    --bar: #f7f9fd;
    --bar-line: #e6ecf6;
    --addr: #7f8da5;
    --shadow: 0 36px 90px -34px rgba(15, 23, 42, 0.5), 0 10px 28px -16px rgba(15, 23, 42, 0.28);
    background: var(--wash);
    color: var(--ink);
  }
  .canvas.theme-dark {
    --ink: #f5f9ff;
    --muted: #a9b9d6;
    --soft: #8496b8;
    --accent: #79a7ff;
    --accent-soft: rgba(121, 167, 255, 0.16);
    --line: rgba(255, 255, 255, 0.13);
    --card: #0b1424;
    --wash:
      radial-gradient(1100px 820px at 88% -8%, rgba(47, 107, 255, 0.42), transparent 62%),
      radial-gradient(860px 700px at -8% 108%, rgba(14, 165, 233, 0.22), transparent 60%),
      linear-gradient(158deg, #060c18 0%, #0b1730 56%, #07101f 100%);
    --grid: rgba(255, 255, 255, 0.05);
    --bar: #0e1a2d;
    --bar-line: rgba(255, 255, 255, 0.1);
    --addr: #8fa1c1;
    --shadow: 0 40px 100px -30px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.04);
  }
  .canvas::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px);
    background-size: 44px 44px;
    -webkit-mask-image: radial-gradient(75% 80% at 60% 45%, #000 20%, transparent 100%);
    mask-image: radial-gradient(75% 80% at 60% 45%, #000 20%, transparent 100%);
    pointer-events: none;
  }
  .halo {
    position: absolute;
    width: 780px;
    height: 780px;
    right: -210px;
    top: -260px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--accent-soft), transparent 66%);
    pointer-events: none;
  }
  .halo-small {
    position: absolute;
    width: 380px;
    height: 380px;
    left: -140px;
    bottom: -150px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--accent-soft), transparent 68%);
    pointer-events: none;
  }
  header {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 58px;
  }
  .logo-chip { display: inline-flex; align-items: center; gap: 12px; }
  .theme-dark .logo-chip { background: #ffffff; border-radius: 14px; padding: 8px 16px 8px 10px; box-shadow: 0 10px 30px -16px rgba(0, 0, 0, 0.6); }
  .logo { height: 38px; width: 38px; display: block; border-radius: 9px; }
  .wordmark { display: inline-flex; gap: 6px; font-size: 24px; font-weight: 800; letter-spacing: -0.02em; color: #0a1220; white-space: nowrap; }
  .wordmark b { font-weight: 800; color: #0a1220; }
  .wordmark span { color: #2f6bff; font-weight: 700; }
  .brandline { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 600; letter-spacing: 0.18em; color: var(--soft); text-transform: uppercase; }
  .brandline::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
  main {
    position: relative;
    z-index: 2;
    margin-top: 34px;
    display: grid;
    grid-template-columns: 470px minmax(0, 1fr);
    gap: 52px;
    align-items: center;
    height: 756px;
  }
  .copy { display: flex; flex-direction: column; justify-content: center; }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    align-self: flex-start;
    padding: 7px 13px;
    border-radius: 999px;
    background: var(--accent-soft);
    color: var(--accent);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }
  .eyebrow::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  h1 {
    margin: 24px 0 0;
    font-size: 54px;
    line-height: 1.045;
    letter-spacing: -1.5px;
    font-weight: 800;
    color: var(--ink);
  }
  .lead {
    margin: 22px 0 0;
    font-size: 22px;
    line-height: 1.42;
    color: var(--muted);
    max-width: 440px;
  }
  ul {
    list-style: none;
    margin: 30px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 15px;
    color: var(--ink);
  }
  li { display: flex; align-items: flex-start; gap: 12px; font-size: 17px; line-height: 1.35; font-weight: 500; }
  .check { flex: 0 0 auto; margin-top: 1px; }
  .check circle { fill: var(--accent); }
  .check path { fill: none; stroke: #ffffff; stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; }
  .shot { min-width: 0; }
  .window {
    margin: 0;
    border-radius: 20px;
    overflow: hidden;
    background: var(--card);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
  }
  .bar {
    display: flex;
    align-items: center;
    gap: 7px;
    height: 46px;
    padding: 0 16px;
    background: var(--bar);
    border-bottom: 1px solid var(--bar-line);
  }
  .dot { width: 9px; height: 9px; border-radius: 50%; background: #ff5f57; }
  .dot:nth-child(2) { background: #febc2e; }
  .dot:nth-child(3) { background: #28c840; }
  .addr {
    margin-left: 12px;
    flex: 1;
    min-width: 0;
    height: 27px;
    padding: 0 12px;
    border-radius: 7px;
    border: 1px solid var(--bar-line);
    background: var(--card);
    color: var(--addr);
    font-size: 12px;
    line-height: 25px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .viewport {
    position: relative;
    height: ${ viewHeight }px;
    overflow: hidden;
    background: #eef2f8;
  }
  .theme-dark .viewport { background: #0a1424; }
  .blur {
    position: absolute;
    inset: -34px;
    background-size: cover;
    background-position: center;
    filter: blur(40px) saturate(1.15);
    opacity: 0.22;
    transform: scale(1.06);
  }
  .theme-dark .blur { opacity: 0.2; }
  .shot-img {
    position: relative;
    z-index: 1;
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top center;
  }
  footer {
    position: absolute;
    z-index: 2;
    left: 64px;
    right: 64px;
    bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--soft);
  }
  footer .rule { flex: 1; height: 1px; margin: 0 22px; background: var(--line); }
</style>
</head>
<body>
<div class="canvas ${ theme }">
  <div class="halo"></div>
  <div class="halo-small"></div>
  <header>
    ${ logoMarkup( logoUri ) }
    <span class="brandline">${ escapeHtml( brandline ) }</span>
  </header>
  <main>
    <section class="copy">
      <span class="eyebrow">${ escapeHtml( slide.eyebrow || '' ) }</span>
      <h1>${ escapeHtml( slide.headline ) }</h1>
      <p class="lead">${ escapeHtml( slide.lead || '' ) }</p>
      <ul>${ bullets }</ul>
    </section>
    <section class="shot">
      <figure class="window">
        <div class="bar">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="addr">${ escapeHtml( slide.address || 'example.com/wp-admin' ) }</span>
        </div>
        <div class="viewport">
          <div class="blur" style="background-image:url('${ shotUri }')"></div>
          <img class="shot-img" src="${ shotUri }" alt="">
        </div>
      </figure>
    </section>
  </main>
  <footer>
    <span>${ escapeHtml( footerLeft ) }</span>
    <span class="rule"></span>
    <span>${ escapeHtml( footerRight ) }</span>
  </footer>
</div>
</body>
</html>`;
}

function previewHtml( slides, options ) {
	const ext = options.format;
	const cards = slides.map( ( slide ) => {
		const src = path.relative( path.dirname( options.preview ), path.join( options.out, `${ slide.file }.${ ext }` ) ).split( path.sep ).join( '/' );
		return `<figure>
  <img src="${ escapeHtml( src ) }" alt="${ escapeHtml( slide.headline ) }">
  <figcaption><strong>${ escapeHtml( slide.headline ) }</strong><span>${ escapeHtml( slide.eyebrow || '' ) }</span></figcaption>
</figure>`;
	} ).join( '\n' );

	return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GLOBUS Debug Control screenshots</title>
<style>
  body { margin: 0; padding: 40px; background: #eef2f8; font-family: "Segoe UI", Arial, sans-serif; color: #0a1220; }
  h1 { margin: 0 0 8px; font-size: 28px; }
  p { margin: 0 0 32px; color: #53627a; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 28px; }
  figure { margin: 0; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 18px 40px -24px rgba(15, 23, 42, 0.45); }
  img { display: block; width: 100%; height: auto; }
  figcaption { display: flex; flex-direction: column; gap: 4px; padding: 14px 16px 16px; font-size: 13px; }
  figcaption span { color: #7f8da5; font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; }
</style>
</head>
<body>
<h1>GLOBUS Debug Control screenshots</h1>
<p>Generated by tools/screenshots/marketing/render.mjs</p>
<div class="grid">
${ cards }
</div>
</body>
</html>`;
}

async function main() {
	const options = parseArgs( process.argv.slice( 2 ) );
	const manifest = readJson( options.manifest );
	const canvas = { width: 1600, height: 1000, bulletIcon: 22, ...( manifest.canvas || {} ) };
	let slides = manifest.slides || [];
	if ( ! slides.length ) throw new Error( `no slides in ${ options.manifest }` );
	if ( options.only.length ) {
		slides = slides.filter( ( slide ) => options.only.includes( slide.id ) || options.only.includes( slide.shot ) );
		if ( ! slides.length ) throw new Error( `no slides matched --only ${ options.only.join( ',' ) }` );
	}
	for ( const slide of slides ) {
		for ( const field of [ 'id', 'shot', 'file', 'headline' ] ) {
			if ( ! slide[ field ] ) throw new Error( `slide is missing ${ field }` );
		}
	}
	fs.mkdirSync( options.out, { recursive: true } );
	const logoUri = dataUri( LOGO_PATH, 'image/png' );
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage( { viewport: { width: canvas.width, height: canvas.height }, deviceScaleFactor: 1 } );
		for ( const slide of slides ) {
			const shotPath = findShot( options.shots, slide.shot );
			const shotUri = dataUri( shotPath, 'image/png' );
			const natural = pngSize( shotPath );
			const shotWidth = canvas.width - 128 - 470 - 52;
			const fittedHeight = Math.round( shotWidth * natural.height / natural.width );
			const viewHeight = Math.max( 320, Math.min( 710, fittedHeight ) );
			await page.setContent( slideHtml( slide, { width: canvas.width, height: canvas.height, logoUri, shotUri, check: checkMark( canvas ), viewHeight, manifest } ), { waitUntil: 'load' } );
			const target = path.join( options.out, `${ slide.file }.${ options.format }` );
			const imageOptions = { path: target, type: options.format === 'png' ? 'png' : 'jpeg', animations: 'disabled' };
			if ( options.format === 'jpg' ) imageOptions.quality = options.quality;
			await page.screenshot( imageOptions );
			console.log( `RENDER ${ slide.id } -> ${ path.relative( REPO_ROOT, target ) }` );
		}
	} finally {
		await browser.close();
	}
	if ( options.previewEnabled ) {
		fs.mkdirSync( path.dirname( options.preview ), { recursive: true } );
		fs.writeFileSync( options.preview, previewHtml( slides, options ), 'utf8' );
		console.log( `RENDER preview -> ${ path.relative( REPO_ROOT, options.preview ) }` );
	}
	console.log( `RENDER done (${ slides.length } slides)` );
}

main().catch( ( error ) => {
	console.error( `RENDER FAILED: ${ error.message }` );
	process.exit( 1 );
} );
