(
	function () {
		'use strict';

		// WP Config Debug: badge toggle on checkbox change

		document.querySelectorAll( '.gdc-wpcfg-checkbox' ).forEach(
			function ( cb ) {
				cb.addEventListener(
					'change',
					function () {
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
					}
				);
			}
		);

		// Log Viewer: CodeMirror read-only view

		var logArea = document.getElementById( 'gdc-log-code' );

		if ( logArea && typeof wp !== 'undefined' && wp.codeEditor ) {
			var editor = wp.codeEditor.initialize(
				'gdc-log-code',
				{
					codemirror: {
						mode: null,
						readOnly: true,
						lineNumbers: true,
						lineWrapping: false,
						viewportMargin: Infinity,
					},
					}
			);

				var cm = editor.codemirror;
				cm.setSize( null, '640px' );

				var lineDataEl = document.getElementById( 'gdc-log-line-data' );
			if ( lineDataEl ) {
					JSON.parse( lineDataEl.textContent || '[]' ).forEach(
						function ( item ) {
							cm.addLineClass( item.l, 'wrap', item.c );
						}
					);
			}
		}
		// Log Viewer: Generate test error
		var genBtn = document.getElementById( 'gdc-generate-error' );
		if ( genBtn && typeof gdcLog !== 'undefined' ) {
			genBtn.addEventListener(
				'click',
				function () {
					var type = document.getElementById( 'gdc-error-type' ).value;
					var body = new FormData();
					body.append( 'action', 'gdc_generate_error' );
					body.append( 'type', type );
					body.append( '_ajax_nonce', gdcLog.nonce );
					genBtn.disabled = true;
					fetch( gdcLog.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function () { window.location.reload(); } )
						.catch( function () { genBtn.disabled = false; } );
				}
			);
		}

		// Log Viewer: Live auto-refresh (polls file mtime every 2 s)
		var liveBtn = document.getElementById( 'gdc-live-toggle' );
		if ( liveBtn && typeof gdcLog !== 'undefined' ) {
			var liveTimer = null;
			var liveMtime = gdcLog.mtime;

			liveBtn.addEventListener(
				'click',
				function () {
					if ( liveTimer ) {
						clearInterval( liveTimer );
						liveTimer = null;
						liveBtn.classList.remove( 'gdc-live-active' );
					} else {
						liveBtn.classList.add( 'gdc-live-active' );
						liveTimer = setInterval(
							function () {
								fetch(
									gdcLog.ajaxUrl + '?action=gdc_log_status',
									{ credentials: 'same-origin' }
								)
									.then( function ( r ) { return r.json(); } )
									.then(
										function ( data ) {
											if ( data.success && data.data.mtime !== liveMtime ) {
												window.location.reload();
											}
										}
									);
							},
							2000
						);
					}
				}
			);
		}
	}()
);
