/**
 * Read-only MCP smoke test for GLOBUS Debug Control.
 *
 * Usage:
 *   WP_URL=http://127.0.0.1:8088 \
 *   WP_USER=admin \
 *   WP_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
 *   node tools/mcp-smoke-test.mjs
 *
 * Requires the MCP Adapter plugin and an Application Password. The script
 * never writes options, wp-config.php, or transients.
 */

const base = ( process.env.WP_URL || '' ).replace( /\/$/, '' );
const user = process.env.WP_USER || '';
const password = process.env.WP_APP_PASSWORD || '';

if ( ! base || ! user || ! password ) {
	console.error( 'Set WP_URL, WP_USER and WP_APP_PASSWORD.' );
	process.exit( 1 );
}

const endpoint = `${ base }/index.php?rest_route=/mcp/mcp-adapter-default-server`;
const auth = 'Basic ' + Buffer.from( `${ user }:${ password }` ).toString( 'base64' );
let sessionId = '';

async function rpc( payload ) {
	const headers = {
		Authorization: auth,
		'Content-Type': 'application/json',
		Accept: 'application/json, text/event-stream',
	};
	if ( sessionId ) {
		headers[ 'Mcp-Session-Id' ] = sessionId;
		headers[ 'MCP-Protocol-Version' ] = '2025-11-25';
	}

	const response = await fetch( endpoint, {
		method: 'POST',
		headers,
		body: JSON.stringify( payload ),
	} );
	sessionId = response.headers.get( 'mcp-session-id' ) || sessionId;
	const text = await response.text();

	if ( ! response.ok ) {
		throw new Error( `HTTP ${ response.status }: ${ text.slice( 0, 300 ) }` );
	}

	return text ? JSON.parse( text ) : null;
}

async function ability( name, parameters = {} ) {
	const json = await rpc( {
		jsonrpc: '2.0',
		id: Math.floor( Math.random() * 100000 ),
		method: 'tools/call',
		params: {
			name: 'mcp-adapter-execute-ability',
			arguments: { ability_name: name, parameters },
		},
	} );
	const result = json?.result;

	if ( ! result ) {
		throw new Error( `${ name }: no MCP result` );
	}
	if ( result.isError ) {
		throw new Error( `${ name }: ${ result.content?.[ 0 ]?.text || 'tool error' }` );
	}

	const structured = result.structuredContent;
	if ( structured && 'success' in structured ) {
		if ( ! structured.success ) {
			throw new Error( `${ name }: ${ structured.error || 'unknown error' }` );
		}
		return structured.data;
	}

	const text = result.content?.[ 0 ]?.text;
	if ( ! text ) {
		throw new Error( `${ name }: empty result` );
	}

	try {
		return JSON.parse( text );
	} catch ( error ) {
		throw new Error( `${ name }: result is not JSON` );
	}
}

function check( condition, message ) {
	if ( ! condition ) {
		throw new Error( `CHECK FAILED: ${ message }` );
	}
	console.log( `OK  ${ message }` );
}

await rpc( {
	jsonrpc: '2.0',
	id: 1,
	method: 'initialize',
	params: {
		protocolVersion: '2025-11-25',
		capabilities: {},
		clientInfo: { name: 'gdc-mcp-smoke-test', version: '1.0' },
	},
} );
await rpc( { jsonrpc: '2.0', method: 'notifications/initialized' } );

const schema = await ability( 'gdc/get-schema' );
check( Array.isArray( schema.abilities ) && schema.abilities.length === 12, 'schema lists 12 abilities' );
check( schema.abilities.includes( 'gdc/get-transients' ), 'schema lists gdc/get-transients' );
check( schema.abilities.includes( 'gdc/get-constants' ), 'schema lists gdc/get-constants' );

const options = await ability( 'gdc/get-options' );
check( options.options && typeof options.options.enabled === 'number', 'get-options returns options' );
check( options.runtime && typeof options.runtime.wp_debug === 'boolean', 'get-options returns runtime state' );

const config = await ability( 'gdc/get-wp-config' );
check( config.constants && config.constants.WP_DEBUG, 'get-wp-config returns WP_DEBUG' );

const log = await ability( 'gdc/get-log', { lines: 5 } );
check( log.debug && typeof log.debug.wp_debug === 'boolean', 'get-log returns debug state' );
check( Array.isArray( log.lines ), 'get-log returns lines' );

const transients = await ability( 'gdc/get-transients' );
check( transients.summary && typeof transients.summary.total === 'number', 'get-transients returns summary' );
check( transients.diagnostics && 'external_cache' in transients.diagnostics, 'get-transients returns diagnostics' );

const constants = await ability( 'gdc/get-constants' );
check( constants.total > 0 && constants.groups.Debug, 'get-constants returns runtime groups' );
check( constants.groups[ 'Plugin & Theme' ]?.GDC_FILE, 'get-constants includes plugin constants' );

console.log( '\nMCP smoke test passed.' );
