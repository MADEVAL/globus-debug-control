/**
 * Shared WordPress admin chrome for the screenshot templates.
 *
 * Templates contain the whole `.gdc-app` shell of one plugin screen.
 * This module wraps them in the same admin markup WordPress uses, so the
 * vendored wp-admin CSS renders a realistic screen.
 */

const MENU = [
	{ icon: 'dashboard', label: 'Dashboard' },
	{ icon: 'admin-post', label: 'Posts' },
	{ icon: 'admin-media', label: 'Media' },
	{ icon: 'admin-page', label: 'Pages' },
	{ icon: 'admin-comments', label: 'Comments', count: '2' },
	{ icon: 'admin-appearance', label: 'Appearance' },
	{ icon: 'admin-plugins', label: 'Plugins', count: '3' },
	{ icon: 'admin-users', label: 'Users' },
	{ icon: 'admin-tools', label: 'Tools' },
	{ icon: 'admin-settings', label: 'Settings' },
];

const GLOBUS_SUBMENU = [
	[ 'Overview', 'globus-debug-control' ],
	[ 'Settings', 'gdc-settings' ],
	[ 'WP_DEBUG', 'gdc-wp-debug' ],
	[ 'Error Log', 'gdc-log' ],
	[ 'Cron Tasks', 'gdc-cron' ],
	[ 'Transients', 'gdc-transients' ],
	[ 'Autoloaded Options', 'gdc-autoloaded' ],
	[ 'HTTP Log', 'gdc-http' ],
	[ 'Constants', 'gdc-constants' ],
];

function menuItem( item, currentPage ) {
	const isCurrent = item.slug && item.slug === currentPage;
	const classes = [ 'menu-top', `menu-icon-${ item.icon }` ];
	if ( item.slug ) {
		classes.push( 'wp-has-submenu', 'wp-menu-open', 'wp-has-current-submenu', 'current', 'menu-top-first' );
	}

	let submenu = '';
	if ( item.slug ) {
		const links = GLOBUS_SUBMENU.map( ( [ label, slug ] ) => {
			const current = slug === currentPage ? ' class="current"' : '';
			return `<li${ current }><a href="#"${ current }>${ label }</a></li>`;
		} ).join( '' );
		submenu = `<ul class="wp-submenu wp-submenu-wrap"><li class="wp-submenu-head" aria-hidden="true">GLOBUS Debug</li>${ links }</ul>`;
	}

	const badge = item.count ? `<span class="update-plugins count-${ item.count }"><span class="plugin-count">${ item.count }</span></span>` : '';

	return `<li class="wp-first-item ${ classes.join( ' ' ) }">
		<a href="#" class="menu-top" aria-haspopup="true"><div class="wp-menu-arrow"><div></div></div><div class="wp-menu-image dashicons-before dashicons-${ item.icon }" aria-hidden="true"><br></div><div class="wp-menu-name">${ item.label }${ badge }</div></a>
		${ submenu }
	</li>`;
}

const AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='26' height='26'%3E%3Ccircle cx='13' cy='13' r='13' fill='%23c3c4c7'/%3E%3Ctext x='13' y='18' font-family='Arial, sans-serif' font-size='12' font-weight='600' text-anchor='middle' fill='%2350575e'%3EA%3C/text%3E%3C/svg%3E";

function adminBar() {
	return `<div id="wpadminbar" class="nojq">
	<div class="quicklinks" id="wp-toolbar" role="navigation" aria-label="Toolbar">
		<ul id="wp-admin-bar-root-default" class="ab-top-menu">
			<li id="wp-admin-bar-wp-logo" class="menupop"><a class="ab-item" href="#" aria-haspopup="true"><span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">About WordPress</span></a></li>
			<li id="wp-admin-bar-site-name" class="menupop"><a class="ab-item" href="#" aria-haspopup="true">example.com</a></li>
			<li id="wp-admin-bar-updates"><a class="ab-item" href="#"><span class="ab-icon dashicons-before dashicons-update" aria-hidden="true"></span><span class="ab-label">1</span><span class="screen-reader-text">1 update available</span></a></li>
			<li id="wp-admin-bar-new-content" class="menupop"><a class="ab-item" href="#" aria-haspopup="true"><span class="ab-icon dashicons-before dashicons-plus-alt" aria-hidden="true"></span><span class="ab-label">New</span></a></li>
		</ul>
		<ul id="wp-admin-bar-top-secondary" class="ab-top-secondary ab-top-menu">
			<li id="wp-admin-bar-my-account" class="menupop with-avatar"><a class="ab-item" href="#" aria-haspopup="true">Howdy, admin<img alt="" src="${ AVATAR }" class="avatar avatar-26 photo" height="26" width="26" decoding="async"></a></li>
		</ul>
	</div>
</div>`;
}

function sidebar( currentPage ) {
	const items = [
		...MENU.map( ( item ) => menuItem( item, currentPage ) ),
		menuItem( { icon: 'editor-code', label: 'GLOBUS Debug', slug: 'globus-debug-control' }, currentPage ),
		menuItem( { icon: 'admin-collapse', label: 'Collapse menu', collapse: true }, currentPage ),
	];

	return `<div id="adminmenumain" role="navigation" aria-label="Main menu">
	<a href="#adminmenuback" class="screen-reader-shortcut">Skip to main content</a>
	<div id="adminmenuback"></div>
	<div id="adminmenuwrap">
		<ul id="adminmenu">
			${ items.join( '\n' ) }
		</ul>
	</div>
</div>`;
}

/**
 * Builds the full HTML document for one plugin screen.
 *
 * @param {Object}   options
 * @param {string}   options.page      Admin page slug of the screen.
 * @param {string}   options.content   Rendered `.gdc-app` markup.
 * @param {string[]} options.cssHrefs  Stylesheet URLs relative to the document.
 * @param {string[]} options.jsHrefs   Script URLs relative to the document.
 */
export function renderDocument( { page, content, cssHrefs, jsHrefs = [] } ) {
	const links = cssHrefs.map( ( href ) => `<link rel="stylesheet" href="${ href }">` ).join( '\n\t' );
	const scripts = jsHrefs.map( ( href ) => `<script src="${ href }"></script>` ).join( '\n\t' );
	const bodyClass = 'globus-debug-control' === page
		? 'toplevel_page_globus-debug-control'
		: `globus-debug_page_${ page }`;

	return `<!DOCTYPE html>
<html lang="en-US" class="wp-toolbar">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>GLOBUS Debug Control</title>
	${ links }
</head>
<body class="wp-admin wp-core-ui js admin-bar auto-fold ${ bodyClass }">
	${ adminBar() }
	<div id="wpwrap">
		${ sidebar( page ) }
		<div id="wpcontent">
			<div id="wpbody" role="main">
				<div id="wpbody-content">
${ content }
					<div class="clear"></div>
				</div>
			</div>
		</div>
	</div>
	<script>window.gdcUiData = { searchIndex: [], i18n: {} };</script>
	${ scripts }
</body>
</html>
`;
}
