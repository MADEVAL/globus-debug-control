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

				var cm           = editor.codemirror;
				var editorHeight = Math.max( 420, Math.min( 640, window.innerHeight - 260 ) );
				cm.setSize( null, editorHeight + 'px' );

				var lineDataEl = document.getElementById( 'gdc-log-line-data' );
			if ( lineDataEl ) {
					JSON.parse( lineDataEl.textContent || '[]' ).forEach(
						function ( item ) {
							cm.addLineClass( item.l, 'wrap', item.c );
						}
					);
			}

			// Log Viewer: Word wrap toggle (only visible when log is not empty).
			var wrapToggleEl = document.getElementById( 'gdc-log-wrap-toggle' );
			var WRAP_KEY     = 'gdc_log_wrap';
			if ( wrapToggleEl ) {
				if ( sessionStorage.getItem( WRAP_KEY ) === '1' ) {
					wrapToggleEl.checked = true;
					cm.setOption( 'lineWrapping', true );
				}
				wrapToggleEl.addEventListener(
					'change',
					function () {
						cm.setOption( 'lineWrapping', wrapToggleEl.checked );
						if ( wrapToggleEl.checked ) {
							sessionStorage.setItem( WRAP_KEY, '1' );
						} else {
							sessionStorage.removeItem( WRAP_KEY );
						}
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

		// Log Viewer: Live auto-refresh (polls file mtime every 2 s; state persists across tab switches)
		var liveBtn = document.getElementById( 'gdc-live-toggle' );
		if ( liveBtn && typeof gdcLog !== 'undefined' ) {
			var liveTimer = null;
			var liveMtime = gdcLog.mtime;
			var LIVE_KEY  = 'gdc_live_enabled';

			function startLive() {
				liveBtn.classList.add( 'gdc-live-active' );
				sessionStorage.setItem( LIVE_KEY, '1' );
				liveTimer = setInterval(
					function () {
						fetch(
							gdcLog.ajaxUrl + '?action=gdc_log_status&_ajax_nonce=' + encodeURIComponent( gdcLog.statusNonce ),
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

			function stopLive() {
				clearInterval( liveTimer );
				liveTimer = null;
				liveBtn.classList.remove( 'gdc-live-active' );
				sessionStorage.removeItem( LIVE_KEY );
			}

			liveBtn.addEventListener(
				'click',
				function () {
					if ( liveTimer ) {
						stopLive();
					} else {
						startLive();
					}
				}
			);

			// Resume live mode if it was active before a tab switch or page reload.
			if ( sessionStorage.getItem( LIVE_KEY ) === '1' ) {
				startLive();
			}
		}

		// Cron Viewer: lazy-load Source column via streaming NDJSON.
		// Each hook result is rendered as soon as the server resolves it, so
		// only slow hooks keep their spinner while fast ones update immediately.
		var cronCells = document.querySelectorAll( '.gdc-cron-source[data-hook]' );
		if ( cronCells.length && typeof gdcCron !== 'undefined' ) {
			var hooks = [];
			cronCells.forEach(
				function ( td ) {
					var h = td.getAttribute( 'data-hook' );
					if ( h && hooks.indexOf( h ) === -1 ) {
						hooks.push( h );
					}
				}
			);

			function renderCronSource( hook, src ) {
				cronCells.forEach(
					function ( td ) {
						if ( td.getAttribute( 'data-hook' ) !== hook ) {
							return;
						}
						if ( ! src || 'unknown' === src.type ) {
							td.innerHTML = '<span class="gdc-text-muted">&mdash;</span>';
						} else if ( 'core' === src.type ) {
							td.innerHTML = '<span class="gdc-source-badge gdc-source-core">WordPress</span>';
						} else if ( 'plugin' === src.type ) {
							td.innerHTML = '<span class="gdc-source-badge gdc-source-plugin" title="' +
								src.slug.replace( /"/g, '&quot;' ) + '">' + src.name + '</span>';
						} else if ( 'theme' === src.type ) {
							td.innerHTML = '<span class="gdc-source-badge gdc-source-theme" title="' +
								src.slug.replace( /"/g, '&quot;' ) + '">' + src.name + '</span>';
						}
					}
				);
			}

			function clearSourceSpinners() {
				cronCells.forEach(
					function ( td ) { td.innerHTML = '<span class="gdc-text-muted">&mdash;</span>'; }
				);
			}

			var cronBody = new FormData();
			cronBody.append( 'action', 'gdc_cron_sources_stream' );
			cronBody.append( '_ajax_nonce', gdcCron.nonce );
			hooks.forEach( function ( h ) { cronBody.append( 'hooks[]', h ); } );

			fetch( gdcCron.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: cronBody } )
				.then(
					function ( response ) {
						var reader  = response.body.getReader();
						var decoder = new TextDecoder();
						var buf     = '';

						function pump() {
							return reader.read().then(
								function ( chunk ) {
									if ( chunk.done ) {
										return;
									}
									buf += decoder.decode( chunk.value, { stream: true } );
									var lines = buf.split( '\n' );
									buf = lines.pop();
									lines.forEach(
										function ( line ) {
											line = line.trim();
											if ( ! line ) {
												return;
											}
											try {
												var item = JSON.parse( line );
												if ( item.hook ) {
													renderCronSource( item.hook, item.source );
												}
											} catch ( e ) {}
										}
									);
									return pump();
								}
							);
						}
						return pump();
					}
				)
				.catch( clearSourceSpinners );
		}

		// Cron Viewer: callback toggle (expand/collapse).
		document.querySelectorAll( '.gdc-cron-cb-toggle' ).forEach(
			function ( btn ) {
				btn.addEventListener(
					'click',
					function () {
						var list     = btn.parentNode.querySelector( '.gdc-cron-callbacks' );
						var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
						btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
						btn.classList.toggle( 'is-open', ! expanded );
						if ( list ) {
							list.hidden = ! expanded;
						}
					}
				);
			}
		);

		// Cron Viewer: run now.
		document.querySelectorAll( '.gdc-cron-run' ).forEach(
			function ( btn ) {
				btn.addEventListener(
					'click',
					function () {
						if ( btn.disabled ) {
							return;
						}
						var hook = btn.getAttribute( 'data-hook' );
						var body = new FormData();
						body.append( 'action', 'gdc_cron_run' );
						body.append( '_ajax_nonce', gdcCron.nonceRun );
						body.append( 'hook', hook );

						btn.disabled = true;
						btn.classList.add( 'is-busy' );

						fetch( gdcCron.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
							.then( function ( r ) { return r.json(); } )
							.then(
								function ( resp ) {
									btn.classList.remove( 'is-busy' );
									btn.classList.add( resp.success ? 'is-ok' : 'is-error' );
									setTimeout(
										function () {
											btn.disabled = false;
											btn.classList.remove( 'is-ok', 'is-error' );
										},
										2000
									);
								}
							)
							.catch(
								function () {
									btn.disabled = false;
									btn.classList.remove( 'is-busy' );
								}
							);
					}
				);
			}
		);

	// Cron Viewer: bulk delete orphaned events.
	var bulkBtn = document.getElementById( 'gdc-cron-bulk-delete' );
	if ( bulkBtn && typeof gdcCron !== 'undefined' ) {
		bulkBtn.addEventListener(
			'click',
			function () {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( gdcCron.i18n.confirmBulk ) ) {
					return;
				}

				var events = [];
				document.querySelectorAll( 'tr.gdc-cron-orphan' ).forEach(
					function ( row ) {
						var hook = row.getAttribute( 'data-hook' );
						var ts   = row.getAttribute( 'data-ts' );
						var btn  = row.querySelector( '.gdc-cron-delete' );
						var args = btn ? btn.getAttribute( 'data-args' ) : '[]';
						events.push( JSON.stringify( { hook: hook, timestamp: Number( ts ), args: JSON.parse( args ) } ) );
					}
				);

				if ( ! events.length ) {
					return;
				}

				bulkBtn.disabled = true;
				var body = new FormData();
				body.append( 'action', 'gdc_cron_bulk_delete' );
				body.append( '_ajax_nonce', gdcCron.nonceBulk );
				events.forEach( function ( e ) { body.append( 'events[]', e ); } );

				fetch( gdcCron.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then(
						function ( resp ) {
							if ( resp.success ) {
								window.location.reload();
							} else {
								bulkBtn.disabled = false;
							}
						}
					)
					.catch( function () { bulkBtn.disabled = false; } );
			}
		);
	}

	// Cron Viewer: delete event.
	document.querySelectorAll( '.gdc-cron-delete' ).forEach(
		function ( btn ) {
			btn.addEventListener(
				'click',
				function () {
					if ( btn.disabled ) {
						return;
					}
					// eslint-disable-next-line no-alert
					if ( ! window.confirm( gdcCron.i18n.confirmDelete ) ) {
						return;
					}
					var hook = btn.getAttribute( 'data-hook' );
					var ts   = btn.getAttribute( 'data-ts' );
					var args = btn.getAttribute( 'data-args' );
					var body = new FormData();
					body.append( 'action', 'gdc_cron_delete' );
					body.append( '_ajax_nonce', gdcCron.nonceDelete );
					body.append( 'hook', hook );
					body.append( 'timestamp', ts );
					body.append( 'args', args );

					btn.disabled = true;
					btn.classList.add( 'is-busy' );
					var row = btn.closest( 'tr' );

					fetch( gdcCron.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then(
							function ( resp ) {
								if ( resp.success && row ) {
									row.style.transition = 'opacity 0.3s';
									row.style.opacity    = '0';
									setTimeout( function () { row.remove(); }, 320 );
								} else {
									btn.disabled = false;
									btn.classList.remove( 'is-busy' );
								}
							}
						)
						.catch(
							function () {
								btn.disabled = false;
								btn.classList.remove( 'is-busy' );
							}
						);
				}
			);
		}
	);

	// -------------------------------------------------------------------------
	// Transient viewer: per-row delete
	// -------------------------------------------------------------------------

	document.addEventListener(
		'click',
		function ( e ) {
			var btn = e.target.closest( '.gdc-transient-delete' );
			if ( ! btn || typeof gdcTransient === 'undefined' ) {
				return;
			}

			var row = btn.closest( 'tr' );
			var key = row ? row.dataset.key : '';

			if ( ! key ) {
				return;
			}

			btn.disabled = true;

			var body = new FormData();
			body.append( 'action', 'gdc_transient_delete' );
			body.append( '_ajax_nonce', gdcTransient.nonceDelete );
			body.append( 'key', key );

			fetch( gdcTransient.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then(
					function ( resp ) {
						if ( resp.success && row ) {
							row.style.transition = 'opacity 0.3s';
							row.style.opacity    = '0';
							setTimeout( function () { row.remove(); }, 320 );
						} else {
							btn.disabled = false;
						}
					}
				)
				.catch( function () { btn.disabled = false; } );
		}
	);

	// -------------------------------------------------------------------------
	// Transient viewer: purge expired
	// -------------------------------------------------------------------------

	var purgeBtn = document.getElementById( 'gdc-transient-purge' );
	if ( purgeBtn && typeof gdcTransient !== 'undefined' ) {
		purgeBtn.addEventListener(
			'click',
			function () {
				purgeBtn.disabled = true;

				var body = new FormData();
				body.append( 'action', 'gdc_transient_purge' );
				body.append( '_ajax_nonce', gdcTransient.noncePurge );

				fetch( gdcTransient.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then(
						function ( resp ) {
							if ( resp.success ) {
								window.location.reload();
							} else {
								purgeBtn.disabled = false;
							}
						}
					)
					.catch( function () { purgeBtn.disabled = false; } );
			}
		);
	}

	// -------------------------------------------------------------------------
	// Constants viewer: filter by name
	// -------------------------------------------------------------------------

	var constantsFilter = document.getElementById( 'gdc-constants-filter' );
	if ( constantsFilter ) {
		var constantGroups = document.querySelectorAll( '.gdc-constants-group' );

		var applyConstantsFilter = function () {
			var term = constantsFilter.value.trim().toLowerCase();

			constantGroups.forEach(
				function ( group ) {
					var rows = group.querySelectorAll( '.gdc-constant-row' );
					var visible = 0;

					rows.forEach(
						function ( row ) {
							var match = ! term || ( row.getAttribute( 'data-name' ) || '' ).indexOf( term ) !== -1;
							row.hidden = ! match;
							if ( match ) {
								visible++;
							}
						}
					);

					group.hidden = visible === 0;
					if ( term ) {
						group.open = visible > 0;
					} else {
						group.open = group.getAttribute( 'data-default-open' ) === '1';
					}
				}
			);
		};

		constantsFilter.addEventListener( 'input', applyConstantsFilter );
	}

	// -------------------------------------------------------------------------
	// HTTP log: toggle capturing
	// -------------------------------------------------------------------------

	var httpToggle = document.getElementById( 'gdc-http-toggle' );
	if ( httpToggle && typeof gdcHttp !== 'undefined' ) {
		httpToggle.addEventListener(
			'click',
			function () {
				httpToggle.disabled = true;
				var isCapturing     = httpToggle.dataset.capturing === '1';

				var body = new FormData();
				body.append( 'action', 'gdc_http_log_toggle' );
				body.append( '_ajax_nonce', gdcHttp.nonceToggle );
				body.append( 'enabled', isCapturing ? '0' : '1' );

				fetch( gdcHttp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then(
						function ( resp ) {
							if ( resp.success ) {
								window.location.reload();
							} else {
								httpToggle.disabled = false;
							}
						}
					)
					.catch( function () { httpToggle.disabled = false; } );
			}
		);
	}

	// -------------------------------------------------------------------------
	// HTTP log: clear log
	// -------------------------------------------------------------------------

	var httpClear = document.getElementById( 'gdc-http-clear' );
	if ( httpClear && typeof gdcHttp !== 'undefined' ) {
		httpClear.addEventListener(
			'click',
			function () {
				httpClear.disabled = true;

				var body = new FormData();
				body.append( 'action', 'gdc_http_log_clear' );
				body.append( '_ajax_nonce', gdcHttp.nonceClear );

				fetch( gdcHttp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then(
						function ( resp ) {
							if ( resp.success ) {
								window.location.reload();
							} else {
								httpClear.disabled = false;
							}
						}
					)
					.catch( function () { httpClear.disabled = false; } );
			}
		);
	}
	}()
);
