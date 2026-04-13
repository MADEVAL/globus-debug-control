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

			// Suppress warning: show inline badge when selected type is suppressed by plugin settings.
			var typeSelectEl   = document.getElementById( 'gdc-error-type' );
			var suppressWarnEl = document.getElementById( 'gdc-suppress-warn' );
			if ( typeSelectEl && suppressWarnEl && gdcLog.suppressedTypes ) {
				function updateSuppressWarn() {
					suppressWarnEl.hidden = gdcLog.suppressedTypes.indexOf( typeSelectEl.value ) === -1;
				}
				typeSelectEl.addEventListener( 'change', updateSuppressWarn );
				updateSuppressWarn();
			}

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
						var icon     = btn.querySelector( '.dashicons' );
						var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
						btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
						if ( list ) {
							list.hidden = expanded;
						}
						if ( icon ) {
							icon.className = expanded
								? 'dashicons dashicons-arrow-down-alt2'
								: 'dashicons dashicons-arrow-up-alt2';
							icon.style.cssText = 'font-size:12px;width:12px;height:12px;vertical-align:middle;';
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
						var icon = btn.querySelector( '.dashicons' );
						if ( icon ) {
							icon.className = 'dashicons dashicons-update gdc-spin';
							icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;display:inline-block;';
						}

						fetch( gdcCron.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
							.then( function ( r ) { return r.json(); } )
							.then(
								function ( resp ) {
									if ( icon ) {
										if ( resp.success ) {
											icon.className = 'dashicons dashicons-yes gdc-cron-run-ok';
										} else {
											icon.className = 'dashicons dashicons-no gdc-cron-run-error';
										}
										icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;';
									}
									setTimeout(
										function () {
											btn.disabled = false;
											if ( icon ) {
												icon.className = 'dashicons dashicons-controls-play';
												icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;';
											}
										},
										2000
									);
								}
							)
							.catch(
								function () {
									btn.disabled = false;
									if ( icon ) {
										icon.className = 'dashicons dashicons-controls-play';
										icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;';
									}
								}
							);
					}
				);
			}
		);

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
						var row  = btn.closest( 'tr' );
						var icon = btn.querySelector( '.dashicons' );
						if ( icon ) {
							icon.className = 'dashicons dashicons-update gdc-spin';
							icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;color:#d63638;display:inline-block;';
						}

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
										if ( icon ) {
											icon.className = 'dashicons dashicons-trash';
											icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;color:#d63638;';
										}
									}
								}
							)
							.catch(
								function () {
									btn.disabled = false;
									if ( icon ) {
										icon.className = 'dashicons dashicons-trash';
										icon.style.cssText = 'font-size:14px;width:14px;height:14px;vertical-align:middle;color:#d63638;';
									}
								}
							);
					}
				);
			}
		);
	}()
);
