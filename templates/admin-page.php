<?php
/**
 * Admin page template for Variable Sneaker.
 *
 * @var int $kit_id  Active Elementor Kit ID (passed from render_page).
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div id="vsneaker-app" class="vsneaker-wrap">

	<!-- Header -->
	<header class="vsneaker-header">
		<div class="vsneaker-header__brand">
			<svg class="vsneaker-logo" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect width="32" height="32" rx="8" fill="#1f1f28"/>
				<rect x="0.5" y="0.5" width="31" height="31" rx="7.5" stroke="#2c2c3a"/>
				<text x="16" y="21" text-anchor="middle" fill="#6c72f1" font-size="14" font-weight="800" font-family="system-ui">VS</text>
			</svg>
			<div>
				<h1>Variable Sneaker <span class="vsneaker-header__version">v<?php echo esc_html( VSNEAKER_VERSION ); ?></span></h1>
				<p class="vsneaker-header__sub">Elementor V4 Variable Importer</p>
			</div>
		</div>
	</header>

	<!-- Tabs -->
	<nav class="vsneaker-tabs">
		<button class="vsneaker-tab vsneaker-tab--active" data-tab="import">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v9m0 0L5 7m3 3 3-3M2 12v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" transform="rotate(180 8 8)"/></svg>
			Import
		</button>
		<button class="vsneaker-tab" data-tab="variables">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M2 8h12M2 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
			Variables
		</button>
		<button class="vsneaker-tab" data-tab="export">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v9m0 0L5 7m3 3 3-3M2 12v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			Export
		</button>
	</nav>

	<!-- ══════ TAB: Import ══════ -->
	<div class="vsneaker-tab-content vsneaker-tab-content--active" data-tab="import">
		<div class="vsneaker-body">
			<section class="vsneaker-panel vsneaker-panel--input">
				<div class="vsneaker-panel__header">
					<h2>Paste Variables</h2>
					<button type="button" id="vsneaker-load-btn" class="vsneaker-btn vsneaker-btn--ghost" title="Load current variables into editor">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 0111.5-2.3M14 8a6 6 0 01-11.5 2.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 2v4h-4M2 14v-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						Load Current
					</button>
				</div>
				<div class="vsneaker-textarea-wrap">
					<textarea id="vsneaker-input" class="vsneaker-textarea" placeholder="Paste your variables here, one per line.&#10;&#10;Examples:&#10;  primary = #1A1A2E&#10;  --accent: #E94560;&#10;  body-size = 1rem&#10;  heading-xl = clamp(2.5rem, 2vw + 1rem, 4rem)&#10;  space-lg = 48px&#10;  --text-muted: rgb(150, 150, 150);&#10;&#10;Supports: hex, rgb(a), hsl(a), named colors,&#10;px, rem, em, %, vw/vh, clamp(), calc()" spellcheck="false"></textarea>
					<div class="vsneaker-textarea-footer">
						<span id="vsneaker-line-count" class="vsneaker-meta">0 lines</span>
						<button type="button" id="vsneaker-clear-btn" class="vsneaker-btn vsneaker-btn--text">Clear</button>
					</div>
				</div>
				<div class="vsneaker-actions">
					<button type="button" id="vsneaker-preview-btn" class="vsneaker-btn vsneaker-btn--secondary">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
						Preview
					</button>
					<div class="vsneaker-import-group">
						<select id="vsneaker-mode" class="vsneaker-select">
							<option value="skip">Skip duplicates</option>
							<option value="overwrite">Overwrite duplicates</option>
							<option value="merge_all">Import all (new IDs for dupes)</option>
						</select>
						<button type="button" id="vsneaker-import-btn" class="vsneaker-btn vsneaker-btn--primary" disabled>
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v9m0 0L5 7m3 3 3-3M2 12v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" transform="rotate(180 8 8)"/></svg>
							Import
						</button>
					</div>
				</div>
			</section>

			<section class="vsneaker-panel vsneaker-panel--preview">
				<div class="vsneaker-panel__header">
					<h2>Preview</h2>
					<div id="vsneaker-preview-stats" class="vsneaker-meta" style="display:none;">
						<span id="vsneaker-stat-total" class="vsneaker-stat"></span>
						<span id="vsneaker-stat-dupes" class="vsneaker-stat vsneaker-stat--warn"></span>
					</div>
				</div>
				<div id="vsneaker-preview-area" class="vsneaker-preview-area">
					<div class="vsneaker-empty-state">
						<svg width="48" height="48" viewBox="0 0 48 48" fill="none" opacity="0.3"><rect x="6" y="6" width="36" height="36" rx="8" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/><path d="M18 24h12M24 18v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
						<p>Paste variables and click <strong>Preview</strong> to see them here.</p>
					</div>
				</div>
			</section>
		</div>
	</div>

	<!-- ══════ TAB: Variables ══════ -->
	<div class="vsneaker-tab-content" data-tab="variables">
		<div class="vsneaker-panel">
			<div class="vsneaker-panel__header">
				<h2>Current Variables</h2>
				<div class="vsneaker-panel__actions">
					<input type="text" id="vsneaker-var-search" class="vsneaker-search" placeholder="Search…" />
					<button type="button" id="vsneaker-var-refresh" class="vsneaker-btn vsneaker-btn--ghost" title="Refresh">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 0111.5-2.3M14 8a6 6 0 01-11.5 2.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 2v4h-4M2 14v-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				</div>
			</div>

			<div class="vsneaker-subtabs">
				<button class="vsneaker-subtab vsneaker-subtab--active" data-subtab="colors">
					<span class="vsneaker-subtab__dot vsneaker-subtab__dot--color"></span>
					Colors <span id="vsneaker-color-count" class="vsneaker-subtab__count">0</span>
				</button>
				<button class="vsneaker-subtab" data-subtab="sizes">
					<span class="vsneaker-subtab__dot vsneaker-subtab__dot--size"></span>
					Sizes <span id="vsneaker-size-count" class="vsneaker-subtab__count">0</span>
				</button>
				<button class="vsneaker-subtab" data-subtab="fonts">
					<span class="vsneaker-subtab__dot vsneaker-subtab__dot--font"></span>
					Fonts <span id="vsneaker-font-count" class="vsneaker-subtab__count">0</span>
				</button>
			</div>

			<div id="vsneaker-var-list-area" class="vsneaker-preview-area vsneaker-preview-area--tall">
				<div class="vsneaker-empty-state"><p>Loading…</p></div>
			</div>
		</div>
	</div>

	<!-- ══════ TAB: Export ══════ -->
	<div class="vsneaker-tab-content" data-tab="export">
		<div class="vsneaker-body vsneaker-body--export">
			<section class="vsneaker-panel">
				<div class="vsneaker-panel__header"><h2>Export as Text</h2></div>
				<p class="vsneaker-panel__desc">Copy your current variables as simple text — pasteable right back into the Import tab or shareable with others.</p>
				<div class="vsneaker-textarea-wrap">
					<textarea id="vsneaker-export-text" class="vsneaker-textarea vsneaker-textarea--readonly" readonly placeholder="Click 'Generate' to load your variables here…" spellcheck="false"></textarea>
					<div class="vsneaker-textarea-footer">
						<span id="vsneaker-export-count" class="vsneaker-meta"></span>
						<button type="button" id="vsneaker-copy-btn" class="vsneaker-btn vsneaker-btn--text" disabled>Copy to Clipboard</button>
					</div>
				</div>
				<div class="vsneaker-actions" style="margin-top:12px;">
					<button type="button" id="vsneaker-gen-text-btn" class="vsneaker-btn vsneaker-btn--secondary">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M2 8h12M2 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
						Generate Text Export
					</button>
				</div>
			</section>

			<section class="vsneaker-panel">
				<div class="vsneaker-panel__header"><h2>Export as JSON</h2></div>
				<p class="vsneaker-panel__desc">Download the full variable data as a JSON file. Preserves IDs, order, and metadata — ideal for backup or cross-site migration.</p>
				<div class="vsneaker-actions" style="margin-top:16px;">
					<button type="button" id="vsneaker-dl-json-btn" class="vsneaker-btn vsneaker-btn--primary">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v9m0 0L5 7m3 3 3-3M2 12v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						Download JSON
					</button>
				</div>
			</section>

			<section class="vsneaker-panel" style="grid-column: 1 / -1;">
				<div class="vsneaker-panel__header"><h2>Raw DB View</h2></div>
				<p class="vsneaker-panel__desc">Debug: shows exactly what's stored in <code>_elementor_global_variables</code> post meta. Useful for comparing against a known-working export.</p>
				<div class="vsneaker-textarea-wrap">
					<textarea id="vsneaker-raw-db" class="vsneaker-textarea vsneaker-textarea--readonly" readonly placeholder="Click 'Load Raw DB' to view…" spellcheck="false" style="min-height:250px; font-size:12px;"></textarea>
				</div>
				<div class="vsneaker-actions" style="margin-top:12px;">
					<button type="button" id="vsneaker-raw-db-btn" class="vsneaker-btn vsneaker-btn--secondary">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M2 8h12M2 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
						Load Raw DB
					</button>
				</div>
			</section>
		</div>
	</div>

	<!-- Toast container -->
	<div id="vsneaker-toast-container" class="vsneaker-toast-container"></div>

	<!-- Footer -->
	<footer class="vsneaker-footer">
		<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3m0 2.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
		After importing, refresh the Elementor editor to see your variables. If they don't appear, try <strong>Clear Files &amp; Data</strong> in the admin bar.
	</footer>

	<div class="vsneaker-credit">
		<span class="vsneaker-credit__icon">👟</span>
		<div class="vsneaker-credit__text">
			<strong>Variable Sneaker</strong> is a free, open-source tool — a gift from <a href="https://github.com/XAR-1" target="_blank" rel="noopener">XAR-1</a> to the Elementor community. Built to save you time, one variable at a time.
		</div>
	</div>

</div>
