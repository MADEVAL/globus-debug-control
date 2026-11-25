/**
 * GLOBUS Debug Control - admin UI runtime.
 *
 * Vanilla JS, no jQuery. Sections:
 *   1. helpers            2. toasts           3. dropdowns
 *   4. command palette    5. section nav      6. misc (copy, confirm, flash)
 *
 * @package GlobusDebugControl
 */
(function () {
	'use strict';

	var data = window.gdcUiData || {};
	var i18n = data.i18n || {};

	/* ------------------------------------------------------------------ */
	/* 1. helpers                                                          */
	/* ------------------------------------------------------------------ */

	function qs(selector, scope) {
		return (scope || document).querySelector(selector);
	}

	function qsa(selector, scope) {
		return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
	}

	function on(el, type, handler) {
		if (el) {
			el.addEventListener(type, handler);
		}
	}

	function post(action, payload) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('_ajax_nonce', data.nonce || '');
		Object.keys(payload || {}).forEach(function (key) {
			body.append(key, payload[key]);
		});

		return fetch(data.ajaxurl || window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	/* ------------------------------------------------------------------ */
	/* 2. toasts                                                           */
	/* ------------------------------------------------------------------ */

	window.gdcToast = function (type, message) {
		var classes = {
			success: 'toast-success',
			error: 'toast-error',
			warning: 'toast-warning',
			info: 'toast-info'
		};

		if (typeof window.Toastify !== 'function') {
			window.console.warn('Toastify is not loaded:', type, message);
			return;
		}

		window.Toastify({
			text: message,
			duration: 4500,
			close: true,
			gravity: 'top',
			position: 'right',
			offset: { y: 64 },
			className: 'gdc-toast ' + (classes[type] || classes.info),
			stopOnFocus: true
		}).showToast();
	};

	window.gdcConfirm = function (message, onConfirm) {
		var previous = qs('#gdc-runtime-confirm');
		if (previous) {
			previous.parentNode.removeChild(previous);
		}

		var overlay = document.createElement('div');
		overlay.className = 'gdc-modal-overlay';
		overlay.id = 'gdc-runtime-confirm';

		var modal = document.createElement('div');
		modal.className = 'gdc-modal gdc-modal--sm';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');

		modal.innerHTML =
			'<div class="gdc-modal-header"><h3 class="gdc-modal-title"></h3>' +
			'<button type="button" class="gdc-btn gdc-btn--ghost gdc-btn--icon" aria-label="' +
			(i18n.close || 'Close') + '">\u00d7</button></div>' +
			'<div class="gdc-modal-body"><p></p></div>' +
			'<div class="gdc-modal-footer">' +
			'<button type="button" class="gdc-btn gdc-btn--ghost" data-gdc-cancel></button>' +
			'<button type="button" class="gdc-btn gdc-btn--danger" data-gdc-ok></button></div>';

		qs('.gdc-modal-title', modal).textContent = i18n.confirm_title || 'Please confirm';
		qs('.gdc-modal-body p', modal).textContent = message;
		qs('[data-gdc-cancel]', modal).textContent = i18n.cancel || 'Cancel';
		qs('[data-gdc-ok]', modal).textContent = i18n.confirm || 'Confirm';

		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		function destroy() {
			if (overlay.parentNode) {
				overlay.parentNode.removeChild(overlay);
			}
			document.removeEventListener('keydown', onKey);
		}

		function onKey(event) {
			if ('Escape' === event.key) {
				destroy();
			}
		}

		on(qs('.gdc-modal-header button', modal), 'click', destroy);
		on(qs('[data-gdc-cancel]', modal), 'click', destroy);
		on(qs('[data-gdc-ok]', modal), 'click', function () {
			destroy();
			if ('function' === typeof onConfirm) {
				onConfirm();
			}
		});
		on(overlay, 'click', function (event) {
			if (event.target === overlay) {
				destroy();
			}
		});
		document.addEventListener('keydown', onKey);
		qs('[data-gdc-ok]', modal).focus();
	};

	/* ------------------------------------------------------------------ */
	/* 3. dropdowns                                                        */
	/* ------------------------------------------------------------------ */

	function closeDropdowns(except) {
		qsa('.gdc-drop-menu').forEach(function (menu) {
			if (menu !== except) {
				menu.setAttribute('hidden', '');
				var trigger = menu.parentNode.querySelector('.gdc-drop-trigger');
				if (trigger) {
					trigger.setAttribute('aria-expanded', 'false');
				}
			}
		});
	}

	qsa('.gdc-drop-trigger').forEach(function (trigger) {
		on(trigger, 'click', function (event) {
			event.stopPropagation();
			var menu = trigger.parentNode.querySelector('.gdc-drop-menu');
			if (!menu) {
				return;
			}

			var isHidden = menu.hasAttribute('hidden');
			closeDropdowns(menu);

			if (isHidden) {
				menu.removeAttribute('hidden');
				trigger.setAttribute('aria-expanded', 'true');
			} else {
				menu.setAttribute('hidden', '');
				trigger.setAttribute('aria-expanded', 'false');
			}
		});
	});

	document.addEventListener('click', function () {
		closeDropdowns();
	});

	/* ------------------------------------------------------------------ */
	/* 4. command palette                                                  */
	/* ------------------------------------------------------------------ */

	var palette = qs('#gdc-palette');
	var paletteInput = qs('#gdc-pal-input');
	var paletteList = qs('#gdc-pal-list');
	var paletteState = { items: [], selected: 0 };

	function openPalette() {
		if (!palette) {
			return;
		}

		palette.removeAttribute('hidden');
		buildPalette('');
		if (paletteInput) {
			paletteInput.value = '';
			paletteInput.focus();
		}
	}

	function closePalette() {
		if (!palette) {
			return;
		}

		palette.setAttribute('hidden', '');
	}

	function buildPalette(query) {
		if (!paletteList) {
			return;
		}

		var needle = String(query || '').toLowerCase().trim();
		var source = data.searchIndex || [];
		var filtered = source.filter(function (item) {
			if (!needle) {
				return true;
			}
			return (item.t + ' ' + item.g + ' ' + (item.k || '')).toLowerCase().indexOf(needle) !== -1;
		});

		paletteList.textContent = '';
		paletteState.items = filtered;
		paletteState.selected = 0;

		if (!filtered.length) {
			var empty = document.createElement('div');
			empty.className = 'gdc-pal-empty';
			empty.textContent = i18n.no_results || 'Nothing found';
			paletteList.appendChild(empty);
			return;
		}

		var currentGroup = '';
		filtered.forEach(function (item, index) {
			if (item.g !== currentGroup) {
				currentGroup = item.g;
				var head = document.createElement('div');
				head.className = 'gdc-pal-head';
				head.textContent = currentGroup;
				paletteList.appendChild(head);
			}

			var row = document.createElement('a');
			row.className = 'gdc-pal-row' + (0 === index ? ' is-active' : '');
			row.href = item.u;
			row.setAttribute('data-index', String(index));

			var tile = document.createElement('span');
			tile.className = 'gdc-pal-ic';
			var icon = String(item.i || 'gear').replace(/[^A-Za-z0-9_-]/g, '');
			tile.innerHTML = '<svg class="gdc-ico"><use href="#gdc-i-' + icon + '"></use></svg>';

			var label = document.createElement('span');
			label.className = 'gdc-pal-label';
			label.textContent = item.t;

			row.appendChild(tile);
			row.appendChild(label);
			paletteList.appendChild(row);
		});

		highlightPalette(0);
	}

	function highlightPalette(index) {
		var rows = qsa('.gdc-pal-row', paletteList);
		if (!rows.length) {
			return;
		}

		paletteState.selected = Math.max(0, Math.min(index, rows.length - 1));
		rows.forEach(function (row, i) {
			row.classList.toggle('is-active', i === paletteState.selected);
		});
		rows[paletteState.selected].scrollIntoView({ block: 'nearest' });
	}

	on(qs('#gdc-search'), 'click', openPalette);
	on(qs('#gdc-search-mob'), 'click', openPalette);
	on(qs('#gdc-pal-close'), 'click', closePalette);

	on(paletteInput, 'input', function (event) {
		buildPalette(event.target.value);
	});

	on(paletteInput, 'keydown', function (event) {
		if ('ArrowDown' === event.key) {
			event.preventDefault();
			highlightPalette(paletteState.selected + 1);
		} else if ('ArrowUp' === event.key) {
			event.preventDefault();
			highlightPalette(paletteState.selected - 1);
		} else if ('Enter' === event.key) {
			event.preventDefault();
			var rows = qsa('.gdc-pal-row', paletteList);
			if (rows[paletteState.selected]) {
				window.location.href = rows[paletteState.selected].href;
			}
		} else if ('Escape' === event.key) {
			closePalette();
		}
	});

	on(palette, 'click', function (event) {
		if (event.target === palette) {
			closePalette();
		}
	});

	// Capture phase: WordPress core also binds Ctrl+K. Take the shortcut first.
	document.addEventListener('keydown', function (event) {
		var isShortcut = (event.ctrlKey || event.metaKey) && 'k' === String(event.key).toLowerCase();
		if (!isShortcut) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		if (typeof event.stopImmediatePropagation === 'function') {
			event.stopImmediatePropagation();
		}
		openPalette();
	}, true);

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			closePalette();
		}
	});

	/* ------------------------------------------------------------------ */
	/* 5. section navigation                                               */
	/* ------------------------------------------------------------------ */

	var snav = qs('#gdc-snav');

	if (snav) {
		var snavSearch = qs('.gdc-snav-search-input', snav);
		var snavClear = qs('.gdc-snav-search-clear', snav);
		var snavMobile = qs('.gdc-snav-toggle', snav);

		function filterSnav() {
			var needle = snavSearch.value.toLowerCase().trim();
			snav.classList.toggle('has-search', '' !== needle);

			qsa('.gdc-snav-item', snav).forEach(function (item) {
				var label = (item.getAttribute('data-snav-label') || '').toLowerCase();
				item.classList.toggle('is-hidden', !!needle && label.indexOf(needle) === -1);
			});

			qsa('.gdc-snav-group', snav).forEach(function (group) {
				var visible = qsa('.gdc-snav-item', group).some(function (item) {
					return !item.classList.contains('is-hidden');
				});
				group.style.display = visible ? '' : 'none';
			});
		}

		on(snavSearch, 'input', filterSnav);

		on(snavClear, 'click', function () {
			snavSearch.value = '';
			filterSnav();
			snavSearch.focus();
		});

		on(snavMobile, 'click', function () {
			var open = snav.classList.toggle('is-open');
			snavMobile.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ------------------------------------------------------------------ */
	/* 6. misc                                                             */
	/* ------------------------------------------------------------------ */

	qsa('[data-gdc-copy]').forEach(function (button) {
		on(button, 'click', function () {
			var text = button.getAttribute('data-gdc-copy');

			if (!text) {
				var scope = button.closest('.gdc-card, .gdc-field') || document;
				var target = qs(button.getAttribute('data-gdc-copy-target') || 'input, code', scope);
				text = target ? (target.value || target.textContent) : '';
			}

			if (!text || !navigator.clipboard) {
				return;
			}

			navigator.clipboard.writeText(String(text).trim()).then(function () {
				window.gdcToast('success', i18n.copied || 'Copied to clipboard.');
			});
		});
	});

	qsa('[data-gdc-confirm]').forEach(function (button) {
		on(button, 'click', function (event) {
			event.preventDefault();
			window.gdcConfirm(button.getAttribute('data-gdc-confirm'), function () {
				if (button.form) {
					button.form.submit();
					return;
				}
				if (button.getAttribute('data-gdc-href')) {
					window.location.href = button.getAttribute('data-gdc-href');
				}
			});
		});
	});

	// Deep links: flash the target card so the eye lands on it.
	var hash = (window.location.hash || '').replace(/^#/, '');
	if (hash) {
		var target = document.getElementById(hash);
		if (target) {
			target.classList.add('gdc-flash-highlight');
			window.setTimeout(function () {
				target.classList.remove('gdc-flash-highlight');
			}, 3000);
		}
	}

	/* ------------------------------------------------------------------ */
	/* 7. AJAX toggles                                                     */
	/* ------------------------------------------------------------------ */

	qsa('[data-gdc-ajax-toggle]').forEach(function (toggle) {
		on(toggle, 'click', function () {
			var key = toggle.getAttribute('data-key');
			var next = '1' === toggle.getAttribute('data-value') ? 0 : 1;

			toggle.disabled = true;

			post('gdc_toggle_setting', { key: key, value: next }).then(function (response) {
				toggle.disabled = false;

				if (!response || !response.success) {
					window.gdcToast('error', (response && response.data && response.data.message) || i18n.error || 'Request failed.');
					return;
				}

				toggle.setAttribute('data-value', next ? '1' : '0');
				toggle.setAttribute('aria-checked', next ? 'true' : 'false');
				toggle.classList.toggle('is-on', !!next);

				if ('enabled' === key) {
					qsa('[data-gdc-state-title]').forEach(function (el) {
						el.textContent = next ? (i18n.active || 'Debug control is active') : (i18n.paused || 'Debug control is paused');
					});
					qsa('[data-gdc-state-icon]').forEach(function (el) {
						el.classList.toggle('gdc-tx-green', !!next);
						el.classList.toggle('gdc-tx-amber', !next);
						var use = el.querySelector('use');
						if (use) {
							use.setAttribute('href', next ? '#gdc-i-shieldCheck' : '#gdc-i-shield');
						}
					});
				}

				window.gdcToast('success', i18n.saved || 'Saved.');
			}).catch(function () {
				toggle.disabled = false;
				window.gdcToast('error', i18n.error || 'Request failed.');
			});
		});
	});

	/* ------------------------------------------------------------------ */
	/* 8. Charts                                                           */
	/* ------------------------------------------------------------------ */

	function initCharts() {
		if ('function' !== typeof window.Chart) {
			return;
		}

		qsa('canvas[data-gdc-chart]').forEach(function (canvas) {
			if (canvas.getAttribute('data-gdc-ready')) {
				return;
			}

			var labels = [];
			var values = [];

			try {
				labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
				values = JSON.parse(canvas.getAttribute('data-values') || '[]');
			} catch (error) {
				return;
			}

			var horizontal = '1' === canvas.getAttribute('data-horizontal');
			var palette = ['#2271b1', '#3b82f6', '#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];

			new window.Chart(canvas.getContext('2d'), {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						label: canvas.getAttribute('data-label') || '',
						data: values,
						backgroundColor: horizontal ? '#2271b1' : palette,
						borderRadius: 4,
						maxBarThickness: 34
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					animation: { duration: 350 },
					plugins: {
						legend: { display: false },
						tooltip: { displayColors: false }
					},
					indexAxis: horizontal ? 'y' : 'x',
					scales: {
						x: {
							beginAtZero: true,
							grid: { display: horizontal, color: 'rgba(0,0,0,.06)' },
							ticks: { font: { size: 10 }, precision: 0 }
						},
						y: {
							beginAtZero: true,
							grid: { display: !horizontal, color: 'rgba(0,0,0,.06)' },
							ticks: { font: { size: 10 }, precision: 0 }
						}
					}
				}
			});

			canvas.setAttribute('data-gdc-ready', '1');
		});
	}

	initCharts();
})();
