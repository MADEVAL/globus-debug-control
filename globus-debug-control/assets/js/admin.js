/* global wp, gdcAdmin */
( function () {
	'use strict';

	// ── WP Config Debug: badge toggle on checkbox change ─────────────

	document.querySelectorAll( '.gdc-wpcfg-checkbox' ).forEach( function ( cb ) {
		cb.addEventListener( 'change', function () {
			var badge = this.nextElementSibling;
			if ( this.checked ) {
				badge.className = 'gdc-status-badge gdc-status-on';
				badge.innerHTML =
					'<span class="dashicons dashicons-yes-alt"></span>' +
					gdcAdmin.i18n.enabled;
			} else {
				badge.className = 'gdc-status-badge gdc-status-off';
				badge.innerHTML =
					'<span class="dashicons dashicons-no"></span>' +
					gdcAdmin.i18n.disabled;
			}
		} );
	} );

	// ── Log Viewer: CodeMirror read-only view ─────────────────────────

	var logArea = document.getElementById( 'gdc-log-code' );

	if ( logArea && typeof wp !== 'undefined' && wp.codeEditor ) {
		var editor = wp.codeEditor.initialize( 'gdc-log-code', {
			codemirror: {
				mode: null,
				readOnly: true,
				lineNumbers: true,
				lineWrapping: false,
				viewportMargin: Infinity,
			},
		} );

		var cm = editor.codemirror;
		cm.setSize( null, '640px' );

		var lineDataEl = document.getElementById( 'gdc-log-line-data' );
		if ( lineDataEl ) {
			JSON.parse( lineDataEl.textContent || '[]' ).forEach( function ( item ) {
				cm.addLineClass( item.l, 'wrap', item.c );
			} );
		}
	}
}() );
