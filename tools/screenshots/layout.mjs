/**
 * Shared WordPress admin chrome for the screenshot templates.
 *
 * Templates contain only the contents of the GLOBUS Debug Control page.
 * This module wraps them in the same markup WordPress uses, so the vendored
 * wp-admin CSS renders a realistic admin screen.
 */

const MENU = [
	{ icon: 'dashboard', label: 'Dashboard' },
	{ icon: 'admin-post', label: 'Posts' },
	{ icon: 'admin-media', label: 'Media' },
	{ icon: 'admin-page', label: 'Pages' },
	{ icon: 'admin-comments', label: 'Comments', count: '3' },
	{ icon: 'admin-appearance', label: 'Appearance' },
	{ icon: 'admin-plugins', label: 'Plugins', count: '2' },
	{ icon: 'admin-users', label: 'Users' },
	{ icon: 'admin-tools', label: 'Tools' },
	{ icon: 'admin-settings', label: 'Settings', submenu: true },
	{ icon: 'admin-collapse', label: 'Collapse menu', collapse: true },
];

const SETTINGS_SUBMENU = [
	['General', false],
	['Writing', false],
	['Reading', false],
	['Discussion', false],
	['Media', false],
	['Permalinks', false],
	['Privacy', false],
	['GLOBUS Debug Control', true],
];

function menuItem( item, isSettings ) {
	const classes = [ 'menu-top', `menu-icon-${ item.icon }` ];
	if ( item.collapse ) {
		classes.push( 'wp-menu-separator' );
	}
	if ( item.submenu ) {
		classes.push( 'wp-has-submenu', 'wp-menu-open', 'wp-has-current-submenu', 'current', 'menu-top-first' );
	}

	let submenu = '';
	if ( item.submenu ) {
		const links = SETTINGS_SUBMENU.map( ( [ label, current ] ) => {
			const cls = current ? ' class="current"' : '';
			return `<li${ current ? ' class="current"' : '' }><a href="#"${ cls }>${ label }</a></li>`;
		} ).join( '' );
		submenu = `<ul class="wp-submenu wp-submenu-wrap"><li class="wp-submenu-head" aria-hidden="true">Settings</li>${ links }</ul>`;
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
			<li id="wp-admin-bar-site-name" class="menupop"><a class="ab-item" href="#" aria-haspopup="true">globus.studio</a></li>
			<li id="wp-admin-bar-updates"><a class="ab-item" href="#"><span class="ab-icon dashicons-before dashicons-update" aria-hidden="true"></span><span class="ab-label">2</span><span class="screen-reader-text">2 updates available</span></a></li>
			<li id="wp-admin-bar-new-content" class="menupop"><a class="ab-item" href="#" aria-haspopup="true"><span class="ab-icon dashicons-before dashicons-plus-alt" aria-hidden="true"></span><span class="ab-label">New</span></a></li>
		</ul>
		<ul id="wp-admin-bar-top-secondary" class="ab-top-secondary ab-top-menu">
			<li id="wp-admin-bar-my-account" class="menupop with-avatar"><a class="ab-item" href="#" aria-haspopup="true">Howdy, admin<img alt="" src="${ AVATAR }" class="avatar avatar-26 photo" height="26" width="26" decoding="async"></a></li>
		</ul>
	</div>
</div>`;
}

function sidebar() {
	return `<div id="adminmenumain" role="navigation" aria-label="Main menu">
	<a href="#adminmenuback" class="screen-reader-shortcut">Skip to main content</a>
	<div id="adminmenuback"></div>
	<div id="adminmenuwrap">
		<ul id="adminmenu">
			${ MENU.map( ( item ) => menuItem( item ) ).join( '\n' ) }
		</ul>
	</div>
</div>`;
}

export function pluginTabs( active ) {
	const tabs = [
		[ 'settings', 'Settings' ],
		[ 'wpcfg', 'WP_DEBUG' ],
		[ 'log', 'Error Log' ],
		[ 'cron', 'Cron Tasks' ],
		[ 'transients', 'Transients' ],
		[ 'autoloaded', 'Autoloaded' ],
		[ 'http', 'HTTP Log' ],
		[ 'constants', 'Constants' ],
	];

	return tabs.map( ( [ key, label ] ) => {
		const cls = key === active ? 'nav-tab nav-tab-active' : 'nav-tab';
		return `<a href="#" class="${ cls }"${ key === active ? ' aria-current="page"' : '' }>${ label }</a>`;
	} ).join( '\n' );
}

export function renderDocument( { tab, content, cssHrefs } ) {
	const links = cssHrefs.map( ( href ) => `<link rel="stylesheet" href="${ href }">` ).join( '\n\t' );

	return `<!DOCTYPE html>
<html lang="en-US" class="wp-toolbar">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>GLOBUS Debug Control</title>
	${ links }
</head>
<body class="wp-admin wp-core-ui js admin-bar auto-fold">
	${ adminBar() }
	<div id="wpwrap">
		${ sidebar() }
		<div id="wpcontent">
			<div id="wpbody" role="main">
				<div id="wpbody-content">
					<div class="wrap">
						<h1 class="wp-heading-inline">GLOBUS Debug Control</h1>
						<span class="gdc-debug-badge gdc-debug-on"><span class="dashicons dashicons-warning" style="font-size:14px;vertical-align:-2px;margin-right:2px;"></span>WP_DEBUG ON</span>
						<hr class="wp-header-end">
						<nav class="nav-tab-wrapper">
							${ pluginTabs( tab ) }
						</nav>
						<div class="gdc-tab-content">
${ content }
						</div>
					</div>
					<div class="clear"></div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
`;
}
