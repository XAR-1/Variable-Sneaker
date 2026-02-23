(function ($) {
	'use strict';

	// ── Import tab elements ────────────────────
	const $input      = $('#vsneaker-input');
	const $lineCount  = $('#vsneaker-line-count');
	const $previewBtn = $('#vsneaker-preview-btn');
	const $importBtn  = $('#vsneaker-import-btn');
	const $clearBtn   = $('#vsneaker-clear-btn');
	const $loadBtn    = $('#vsneaker-load-btn');
	const $mode       = $('#vsneaker-mode');
	const $preview    = $('#vsneaker-preview-area');
	const $stats      = $('#vsneaker-preview-stats');
	const $statTotal  = $('#vsneaker-stat-total');
	const $statDupes  = $('#vsneaker-stat-dupes');
	const $toasts     = $('#vsneaker-toast-container');

	// ── Variables tab elements ─────────────────
	const $varSearch  = $('#vsneaker-var-search');
	const $varRefresh = $('#vsneaker-var-refresh');
	const $varList    = $('#vsneaker-var-list-area');
	const $colorCount = $('#vsneaker-color-count');
	const $sizeCount  = $('#vsneaker-size-count');
	const $fontCount  = $('#vsneaker-font-count');

	// ── Export tab elements ────────────────────
	const $exportText   = $('#vsneaker-export-text');
	const $exportCount  = $('#vsneaker-export-count');
	const $copyBtn      = $('#vsneaker-copy-btn');
	const $genTextBtn   = $('#vsneaker-gen-text-btn');
	const $dlJsonBtn    = $('#vsneaker-dl-json-btn');

	let lastPreviewData = null;
	let varData = { colors: [], sizes: [], fonts: [] };
	let activeSubtab = 'colors';

	// ══════════════════════════════════════════════
	//  TAB NAVIGATION
	// ══════════════════════════════════════════════

	$('.vsneaker-tab').on('click', function () {
		const tab = $(this).data('tab');
		$('.vsneaker-tab').removeClass('vsneaker-tab--active');
		$(this).addClass('vsneaker-tab--active');
		$('.vsneaker-tab-content').removeClass('vsneaker-tab-content--active');
		$('.vsneaker-tab-content[data-tab="' + tab + '"]').addClass('vsneaker-tab-content--active');

		// Auto-load variables when switching to Variables tab
		if (tab === 'variables' && varData.colors.length === 0 && varData.sizes.length === 0 && varData.fonts.length === 0) {
			loadVariableList();
		}
	});

	// ── Subtabs ────────────────────────────────
	$('.vsneaker-subtab').on('click', function () {
		activeSubtab = $(this).data('subtab');
		$('.vsneaker-subtab').removeClass('vsneaker-subtab--active');
		$(this).addClass('vsneaker-subtab--active');
		renderVariableList();
	});

	// ══════════════════════════════════════════════
	//  IMPORT TAB
	// ══════════════════════════════════════════════

	function updateLineCount() {
		const text  = $input.val().trim();
		const lines = text === '' ? 0 : text.split(/\r\n|\r|\n/).filter(l => l.trim() !== '').length;
		$lineCount.text(lines + (lines === 1 ? ' line' : ' lines'));
	}

	$input.on('input', updateLineCount);
	updateLineCount();

	$clearBtn.on('click', function () {
		$input.val('').trigger('input');
		resetPreview();
	});

	// Load current variables into textarea
	$loadBtn.on('click', function () {
		setLoading($loadBtn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_export',
			nonce:  vSneaker.nonce,
		}).done(function (res) {
			if (res.success) {
				$input.val(res.data.text).trigger('input');
				toast('Loaded ' + res.data.count + ' variable(s) from your Kit.', 'success');
				resetPreview();
			} else {
				toast(res.data?.message || 'Failed to load.', 'error');
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($loadBtn, false);
		});
	});

	// Preview
	$previewBtn.on('click', function () {
		const raw = $input.val().trim();
		if (!raw) { toast('Please paste some variables first.', 'error'); return; }
		setLoading($previewBtn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_preview', nonce: vSneaker.nonce, variables: raw,
		}).done(function (res) {
			if (res.success) {
				lastPreviewData = res.data;
				renderPreview(res.data);
				$importBtn.prop('disabled', false);
			} else {
				toast(res.data?.message || 'Preview failed.', 'error');
				resetPreview();
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($previewBtn, false);
		});
	});

	// Import
	$importBtn.on('click', function () {
		const raw = $input.val().trim();
		if (!raw) { toast('Nothing to import.', 'error'); return; }
		const count = lastPreviewData?.count || '?';
		if (!confirm('Import ' + count + ' variable(s) into your Elementor Kit?\n\nMode: ' + $mode.find(':selected').text())) return;
		setLoading($importBtn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_import', nonce: vSneaker.nonce, variables: raw, mode: $mode.val(),
		}).done(function (res) {
			if (res.success) {
				toast(res.data.message, 'success');
				$previewBtn.trigger('click');
				// Refresh variables tab data
				varData = { colors: [], sizes: [], fonts: [] };
			} else {
				toast(res.data?.message || 'Import failed.', 'error');
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($importBtn, false);
		});
	});

	// Ctrl+Enter to preview
	$input.on('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
			e.preventDefault();
			$previewBtn.trigger('click');
		}
	});

	function renderPreview(data) {
		const vars = data.variables || [];
		if (!vars.length) { resetPreview(); return; }

		$statTotal.text(data.count + ' variable' + (data.count !== 1 ? 's' : ''));
		if (data.dupes > 0) {
			$statDupes.text(data.dupes + ' duplicate' + (data.dupes !== 1 ? 's' : '')).show();
		} else { $statDupes.hide(); }
		$stats.show();

		let html = '<ul class="vsneaker-var-list">';
		vars.forEach(function (v) {
			const isDupe = v.is_duplicate;
			const rowCls = isDupe ? ' vsneaker-var-item--dupe' : '';
			const isColor = v.type === 'color';
			const isFont  = v.type === 'font';
			html += '<li class="vsneaker-var-item' + rowCls + '">';
			if (isColor) {
				html += '<span class="vsneaker-swatch" style="background:' + escHtml(v.display) + '"></span>';
			} else if (isFont) {
				html += '<span class="vsneaker-font-icon">F</span>';
			} else {
				html += '<span class="vsneaker-size-icon">S</span>';
			}
			html += '<span class="vsneaker-var-label">' + escHtml(v.label) + '</span>';
			html += '<span class="vsneaker-var-value" title="' + escHtml(v.display) + '">' + escHtml(v.display) + '</span>';
			if (isColor) {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--color">Color</span>';
			} else if (isFont) {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--font">Font</span>';
			} else {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--size">Size</span>';
			}
			if (isDupe) html += '<span class="vsneaker-var-badge vsneaker-var-badge--dupe">Exists</span>';
			html += '</li>';
		});
		html += '</ul>';
		$preview.html(html);
	}

	function resetPreview() {
		$preview.html(
			'<div class="vsneaker-empty-state">' +
			'<svg width="48" height="48" viewBox="0 0 48 48" fill="none" opacity="0.3"><rect x="6" y="6" width="36" height="36" rx="8" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/><path d="M18 24h12M24 18v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
			'<p>Paste variables and click <strong>Preview</strong> to see them here.</p></div>'
		);
		$stats.hide();
		$importBtn.prop('disabled', true);
		lastPreviewData = null;
	}

	// ══════════════════════════════════════════════
	//  VARIABLES TAB
	// ══════════════════════════════════════════════

	function loadVariableList() {
		$varList.html('<div class="vsneaker-empty-state"><p>Loading…</p></div>');
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_list', nonce: vSneaker.nonce,
		}).done(function (res) {
			if (res.success) {
				varData = res.data;
				$colorCount.text(res.data.total_colors);
				$sizeCount.text(res.data.total_sizes);
				$fontCount.text(res.data.total_fonts || 0);
				renderVariableList();
			} else {
				$varList.html('<div class="vsneaker-empty-state"><p>Failed to load variables.</p></div>');
			}
		}).fail(function () {
			$varList.html('<div class="vsneaker-empty-state"><p>Request failed.</p></div>');
		});
	}

	function renderVariableList() {
		const items = activeSubtab === 'colors' ? varData.colors : (activeSubtab === 'fonts' ? (varData.fonts || []) : varData.sizes);
		const search = $varSearch.val().toLowerCase().trim();

		if (!items || items.length === 0) {
			$varList.html('<div class="vsneaker-empty-state"><p>No ' + activeSubtab + ' found.</p></div>');
			return;
		}

		let html = '<ul class="vsneaker-var-list">';
		let visibleCount = 0;

		items.forEach(function (v) {
			const matchesSearch = !search || v.label.toLowerCase().includes(search) || v.display.toLowerCase().includes(search);
			const hiddenCls = matchesSearch ? '' : ' vsneaker-var-item--hidden';
			if (matchesSearch) visibleCount++;

			html += '<li class="vsneaker-var-item' + hiddenCls + '" data-id="' + escHtml(v.id) + '">';

			if (v.type === 'color') {
				html += '<span class="vsneaker-swatch" style="background:' + escHtml(v.display) + '"></span>';
			} else if (v.type === 'font') {
				html += '<span class="vsneaker-font-icon">F</span>';
			} else {
				html += '<span class="vsneaker-size-icon">S</span>';
			}

			html += '<span class="vsneaker-var-label">' + escHtml(v.label) + '</span>';
			html += '<span class="vsneaker-var-value" title="' + escHtml(v.display) + '">' + escHtml(v.display) + '</span>';

			if (v.type === 'color') {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--color">Color</span>';
			} else if (v.type === 'font') {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--font">Font</span>';
			} else {
				html += '<span class="vsneaker-var-badge vsneaker-var-badge--size">Size</span>';
			}

			html += '<button type="button" class="vsneaker-var-delete" data-id="' + escHtml(v.id) + '" data-label="' + escHtml(v.label) + '" title="Delete">';
			html += '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 4h10M5 4V3a1 1 0 011-1h2a1 1 0 011 1v1m1 0v7a1 1 0 01-1 1H5a1 1 0 01-1-1V4h6z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			html += '</button>';

			html += '</li>';
		});

		html += '</ul>';
		$varList.html(html);

		if (visibleCount === 0 && search) {
			$varList.append('<div class="vsneaker-empty-state" style="min-height:100px"><p>No matches for "' + escHtml(search) + '"</p></div>');
		}
	}

	// Search
	$varSearch.on('input', function () {
		renderVariableList();
	});

	// Refresh
	$varRefresh.on('click', function () {
		loadVariableList();
	});

	// Delete variable
	let deletedCount = 0;

	$(document).on('click', '.vsneaker-var-delete', function (e) {
		e.stopPropagation();
		const id    = $(this).data('id');
		const label = $(this).data('label');

		if (!confirm('Delete variable "' + label + '"?\n\nThis removes it from the Elementor Kit.')) return;

		const $row = $(this).closest('.vsneaker-var-item');
		$row.css({ opacity: 0.4, pointerEvents: 'none' });

		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_delete', nonce: vSneaker.nonce, var_id: id,
		}).done(function (res) {
			if (res.success) {
				toast(res.data.message, 'success');
				$row.slideUp(200, function () { $(this).remove(); });
				// Update local data
				varData.colors = varData.colors.filter(v => v.id !== id);
				varData.sizes  = varData.sizes.filter(v => v.id !== id);
				varData.fonts  = (varData.fonts || []).filter(v => v.id !== id);
				$colorCount.text(varData.colors.length);
				$sizeCount.text(varData.sizes.length);
				$fontCount.text((varData.fonts || []).length);

				// Show persistent reminder after first delete
				deletedCount++;
				showEditorReminder();
			} else {
				toast(res.data?.message || 'Delete failed.', 'error');
				$row.css({ opacity: 1, pointerEvents: '' });
			}
		}).fail(function () {
			toast('Request failed.', 'error');
			$row.css({ opacity: 1, pointerEvents: '' });
		});
	});

	function showEditorReminder() {
		if ($('#vsneaker-editor-reminder').length) {
			$('#vsneaker-editor-reminder .vsneaker-reminder__count').text(deletedCount);
			return;
		}
		const html =
			'<div id="vsneaker-editor-reminder" class="vsneaker-reminder">' +
				'<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3m0 2.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' +
				'<span><span class="vsneaker-reminder__count">' + deletedCount + '</span> variable(s) deleted. ' +
				'<strong>Reload the Elementor editor</strong> (or click Clear Files & Data in the admin bar) for changes to appear there.</span>' +
			'</div>';
		$varList.before(html);
	}

	// ══════════════════════════════════════════════
	//  EXPORT TAB
	// ══════════════════════════════════════════════

	// Generate text export
	$genTextBtn.on('click', function () {
		setLoading($genTextBtn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_export', nonce: vSneaker.nonce,
		}).done(function (res) {
			if (res.success) {
				$exportText.val(res.data.text);
				$exportCount.text(res.data.count + ' variable(s)');
				$copyBtn.prop('disabled', false);
				toast('Loaded ' + res.data.count + ' variable(s).', 'success');
			} else {
				toast(res.data?.message || 'Export failed.', 'error');
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($genTextBtn, false);
		});
	});

	// Copy to clipboard
	$copyBtn.on('click', function () {
		const text = $exportText.val();
		if (!text) return;

		navigator.clipboard.writeText(text).then(function () {
			toast('Copied to clipboard!', 'success');
		}).catch(function () {
			// Fallback
			$exportText.select();
			document.execCommand('copy');
			toast('Copied to clipboard!', 'success');
		});
	});

	// Download JSON
	$dlJsonBtn.on('click', function () {
		setLoading($dlJsonBtn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_download', nonce: vSneaker.nonce,
		}).done(function (res) {
			if (res.success) {
				const blob = new Blob([JSON.stringify(res.data.json, null, 2)], { type: 'application/json' });
				const url  = URL.createObjectURL(blob);
				const a    = document.createElement('a');
				a.href     = url;
				a.download = res.data.filename;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
				toast('JSON downloaded!', 'success');
			} else {
				toast(res.data?.message || 'Download failed.', 'error');
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($dlJsonBtn, false);
		});
	});

	// Raw DB view
	$('#vsneaker-raw-db-btn').on('click', function () {
		const $btn = $(this);
		setLoading($btn, true);
		$.post(vSneaker.ajaxUrl, {
			action: 'vsneaker_download', nonce: vSneaker.nonce,
		}).done(function (res) {
			if (res.success) {
				// Pretty-print the raw DB string
				try {
					const parsed = JSON.parse(res.data.raw_db);
					$('#vsneaker-raw-db').val(JSON.stringify(parsed, null, 2));
				} catch(e) {
					$('#vsneaker-raw-db').val(res.data.raw_db);
				}
				toast('Raw DB loaded.', 'success');
			} else {
				toast(res.data?.message || 'Failed.', 'error');
			}
		}).fail(function () {
			toast('Request failed.', 'error');
		}).always(function () {
			setLoading($btn, false);
		});
	});

	// ══════════════════════════════════════════════
	//  UTILS
	// ══════════════════════════════════════════════

	function toast(message, type) {
		type = type || 'success';
		const $el = $('<div class="vsneaker-toast vsneaker-toast--' + type + '">' + escHtml(message) + '</div>');
		$toasts.append($el);
		setTimeout(function () {
			$el.addClass('vsneaker-toast--exit');
			setTimeout(function () { $el.remove(); }, 300);
		}, 3500);
	}

	function setLoading($btn, state) {
		if (state) {
			$btn.addClass('vsneaker-btn--loading').prop('disabled', true);
		} else {
			$btn.removeClass('vsneaker-btn--loading').prop('disabled', false);
		}
	}

	function escHtml(str) {
		const div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

})(jQuery);
