<?php
/**
 * Plugin Name:  Variable Sneaker — Elementor V4 Import / Export
 * Plugin URI:   https://github.com/XAR-1/Variable-Sneaker
 * Description:  Bulk import, export, and manage Elementor V4 global variables — colors, sizes, and fonts. Paste-to-import with preview, duplicate detection, and merge/overwrite modes.
 * Version:      1.1.0
 * Author:       XAR-1
 * Author URI:   https://github.com/XAR-1
 * License:      GPLv2 or later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSNEAKER_VERSION', '1.1.0' );
define( 'VSNEAKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'VSNEAKER_URL', plugin_dir_url( __FILE__ ) );

/**
 * ─────────────────────────────────────────────
 *  Core Plugin Class
 * ─────────────────────────────────────────────
 */
class Variable_Sneaker_Plugin {

	const CAP          = 'manage_options';
	const NONCE_ACTION = 'vsneaker_nonce';
	const AJAX_PREVIEW  = 'vsneaker_preview';
	const AJAX_IMPORT   = 'vsneaker_import';
	const AJAX_EXPORT   = 'vsneaker_export';
	const AJAX_LIST     = 'vsneaker_list';
	const AJAX_DELETE   = 'vsneaker_delete';
	const AJAX_DOWNLOAD = 'vsneaker_download';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// AJAX handlers
		add_action( 'wp_ajax_' . self::AJAX_PREVIEW, [ $this, 'ajax_preview' ] );
		add_action( 'wp_ajax_' . self::AJAX_IMPORT, [ $this, 'ajax_import' ] );
		add_action( 'wp_ajax_' . self::AJAX_EXPORT, [ $this, 'ajax_export' ] );
		add_action( 'wp_ajax_' . self::AJAX_LIST, [ $this, 'ajax_list' ] );
		add_action( 'wp_ajax_' . self::AJAX_DELETE, [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_' . self::AJAX_DOWNLOAD, [ $this, 'ajax_download' ] );
	}

	/* ── Admin Menu ─────────────────────────── */

	public function register_menu() {
		add_management_page(
			'Variable Sneaker',
			'Variable Sneaker',
			self::CAP,
			'variable-sneaker',
			[ $this, 'render_page' ]
		);
	}

	/* ── Assets ─────────────────────────────── */

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'tools_page_variable-sneaker' ) {
			return;
		}

		wp_enqueue_style(
			'vsneaker-admin',
			VSNEAKER_URL . 'assets/admin.css',
			[],
			VSNEAKER_VERSION
		);

		wp_enqueue_script(
			'vsneaker-admin',
			VSNEAKER_URL . 'assets/admin.js',
			[ 'jquery' ],
			VSNEAKER_VERSION,
			true
		);

		wp_localize_script( 'vsneaker-admin', 'vSneaker', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
		] );
	}

	/* ── Render Page ────────────────────────── */

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$kit_id = $this->get_active_kit_id();

		include VSNEAKER_PATH . 'templates/admin-page.php';
	}

	/* ── AJAX: Preview ──────────────────────── */

	public function ajax_preview() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSS values with # symbols
		$raw = wp_check_invalid_utf8( wp_unslash( $_POST['variables'] ?? '' ) );
		if ( empty( trim( $raw ) ) ) {
			wp_send_json_error( [ 'message' => 'No input provided.' ] );
		}

		$parsed = $this->parse_variables( $raw );
		if ( empty( $parsed ) ) {
			wp_send_json_error( [ 'message' => 'Could not parse any variables. Use format: name = value (one per line).' ] );
		}

		// Get existing variables for duplicate detection
		$kit_id   = $this->get_active_kit_id();
		$existing = $this->get_existing_variable_labels( $kit_id );

		// Annotate parsed vars with duplicate status
		$result = [];
		foreach ( $parsed as $var ) {
			$var['is_duplicate'] = in_array( strtolower( $var['label'] ), $existing, true );
			$result[]            = $var;
		}

		wp_send_json_success( [
			'variables' => $result,
			'count'     => count( $result ),
			'dupes'     => count( array_filter( $result, fn( $v ) => $v['is_duplicate'] ) ),
		] );
	}

	/* ── AJAX: Import ───────────────────────── */

	public function ajax_import() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSS values with # symbols
		$raw  = wp_check_invalid_utf8( wp_unslash( $_POST['variables'] ?? '' ) );
		$mode = sanitize_text_field( wp_unslash( $_POST['mode'] ?? 'skip' ) );

		if ( empty( $raw ) ) {
			wp_send_json_error( [ 'message' => 'No input provided.' ] );
		}

		$parsed = $this->parse_variables( $raw );
		if ( empty( $parsed ) ) {
			wp_send_json_error( [ 'message' => 'Could not parse any variables.' ] );
		}

		$kit_id = $this->get_active_kit_id();
		if ( $kit_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Active Elementor Kit not found. Is Elementor installed and activated?' ] );
		}

		$current_vars = $this->get_current_variables( $kit_id );
		$current_data = ( isset( $current_vars['data'] ) && is_array( $current_vars['data'] ) ) ? $current_vars['data'] : [];

		// Build lookup of existing labels → IDs
		$label_to_id = [];
		foreach ( $current_data as $id => $obj ) {
			if ( is_array( $obj ) && isset( $obj['label'] ) && empty( $obj['deleted_at'] ) ) {
				$label_to_id[ strtolower( $obj['label'] ) ] = $id;
			}
		}

		// Determine next order value
		$max_order = 0;
		foreach ( $current_data as $obj ) {
			if ( is_array( $obj ) && isset( $obj['order'] ) ) {
				$max_order = max( $max_order, (int) $obj['order'] );
			}
		}

		// Determine watermark
		$watermark = isset( $current_vars['watermark'] ) ? (int) $current_vars['watermark'] : $max_order;

		$imported = 0;
		$skipped  = 0;
		$updated  = 0;
		$now      = gmdate( 'Y-m-d H:i:s' );

		foreach ( $parsed as $var ) {
			$label_lower = strtolower( $var['label'] );
			$exists      = isset( $label_to_id[ $label_lower ] );

			if ( $exists && $mode === 'skip' ) {
				$skipped++;
				continue;
			}

			if ( $exists && $mode === 'overwrite' ) {
				// Update existing
				$existing_id                            = $label_to_id[ $label_lower ];
				$current_data[ $existing_id ]['value']   = $var['value_data'];
				$current_data[ $existing_id ]['updated_at'] = $now;
				$updated++;
				continue;
			}

			// Add new
			$max_order++;
			$watermark++;
			$new_id = 'e-gv-' . substr( md5( uniqid( wp_rand(), true ) ), 0, 7 );

			$current_data[ $new_id ] = [
				'label'      => $var['label'],
				'value'      => $var['value_data'],
				'order'      => $max_order,
				'type'       => $var['el_type'],
				'created_at' => $now,
				'updated_at' => $now,
			];

			$imported++;
		}

		// Save back
		$current_vars['data']      = $current_data;
		$current_vars['watermark'] = $watermark;
		if ( ! isset( $current_vars['version'] ) ) {
			$current_vars['version'] = 2;
		}

		update_post_meta( $kit_id, '_elementor_global_variables', wp_slash( wp_json_encode( $current_vars ) ) );

		// Aggressive cache clear
		$this->clear_elementor_cache( $kit_id );

		wp_send_json_success( [
			'message'  => sprintf(
				'Done! %d imported, %d updated, %d skipped.',
				$imported,
				$updated,
				$skipped
			),
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
		] );
	}

	/* ── AJAX: Export Current Variables ──────── */

	public function ajax_export() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		$kit_id = $this->get_active_kit_id();
		if ( $kit_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Active Elementor Kit not found.' ] );
		}

		$current_vars = $this->get_current_variables( $kit_id );
		$data         = ( isset( $current_vars['data'] ) && is_array( $current_vars['data'] ) ) ? $current_vars['data'] : [];

		$output = [];
		foreach ( $data as $id => $obj ) {
			if ( ! is_array( $obj ) || ! empty( $obj['deleted_at'] ) ) {
				continue;
			}

			$label      = $obj['label'] ?? $id;
			$value      = $obj['value'] ?? [];
			$inner_type = $value['$$type'] ?? 'unknown';
			$outer_type = $obj['type'] ?? '';

			if ( $inner_type === 'color' ) {
				$output[] = $label . ' = ' . ( $value['value'] ?? '' );
			} elseif ( $inner_type === 'size' ) {
				$size_val = $value['value'] ?? [];
				if ( is_array( $size_val ) ) {
					$unit = $size_val['unit'] ?? '';
					$size = $size_val['size'] ?? '';
					if ( $unit === 'custom' || is_string( $size ) ) {
						$output[] = $label . ' = ' . $size;
					} else {
						$output[] = $label . ' = ' . $size . $unit;
					}
				} else {
					$output[] = $label . ' = ' . $size_val;
				}
			} elseif ( $inner_type === 'string' || $outer_type === 'global-font-variable' ) {
				$output[] = $label . ' = ' . ( $value['value'] ?? '' );
			}
		}

		wp_send_json_success( [
			'text'  => implode( "\n", $output ),
			'count' => count( $output ),
		] );
	}

	/* ── AJAX: List All Variables ────────────── */

	public function ajax_list() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		$kit_id = $this->get_active_kit_id();
		if ( $kit_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Active Elementor Kit not found.' ] );
		}

		$current_vars = $this->get_current_variables( $kit_id );
		$data         = ( isset( $current_vars['data'] ) && is_array( $current_vars['data'] ) ) ? $current_vars['data'] : [];

		$colors = [];
		$sizes  = [];
		$fonts  = [];

		foreach ( $data as $id => $obj ) {
			if ( ! is_array( $obj ) || ! empty( $obj['deleted_at'] ) ) {
				continue;
			}

			$label = $obj['label'] ?? $id;
			$value = $obj['value'] ?? [];
			$inner_type = $value['$$type'] ?? 'unknown';
			$outer_type = $obj['type'] ?? '';
			$order = $obj['order'] ?? 0;

			// Determine category from outer type field (more reliable)
			$is_font  = ( $outer_type === 'global-font-variable' || $inner_type === 'string' );
			$is_color = ( $outer_type === 'global-color-variable' || $inner_type === 'color' );
			$is_size  = ( $outer_type === 'global-size-variable' || $inner_type === 'size' );

			$display = '';
			if ( $is_color ) {
				$display = $value['value'] ?? '';
			} elseif ( $is_size ) {
				$size_val = $value['value'] ?? [];
				if ( is_array( $size_val ) ) {
					$unit = $size_val['unit'] ?? '';
					$size = $size_val['size'] ?? '';
					$display = ( $unit === 'custom' || is_string( $size ) ) ? (string) $size : $size . $unit;
				} else {
					$display = (string) $size_val;
				}
			} elseif ( $is_font ) {
				$display = $value['value'] ?? '';
			} else {
				$display = is_string( $value['value'] ?? '' ) ? $value['value'] : '';
			}

			$var_type = $is_color ? 'color' : ( $is_font ? 'font' : 'size' );

			$entry = [
				'id'         => $id,
				'label'      => $label,
				'type'       => $var_type,
				'display'    => $display,
				'order'      => (int) $order,
				'created_at' => $obj['created_at'] ?? '',
				'updated_at' => $obj['updated_at'] ?? '',
			];

			if ( $is_color ) {
				$colors[] = $entry;
			} elseif ( $is_font ) {
				$fonts[] = $entry;
			} else {
				$sizes[] = $entry;
			}
		}

		// Sort by order
		usort( $colors, fn( $a, $b ) => $a['order'] - $b['order'] );
		usort( $sizes, fn( $a, $b ) => $a['order'] - $b['order'] );
		usort( $fonts, fn( $a, $b ) => $a['order'] - $b['order'] );

		wp_send_json_success( [
			'colors'       => $colors,
			'sizes'        => $sizes,
			'fonts'        => $fonts,
			'total_colors' => count( $colors ),
			'total_sizes'  => count( $sizes ),
			'total_fonts'  => count( $fonts ),
		] );
	}

	/* ── AJAX: Delete Variable ──────────────── */

	public function ajax_delete() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		$var_id = sanitize_text_field( wp_unslash( $_POST['var_id'] ?? '' ) );
		if ( empty( $var_id ) ) {
			wp_send_json_error( [ 'message' => 'No variable ID provided.' ] );
		}

		$kit_id = $this->get_active_kit_id();
		if ( $kit_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Active Elementor Kit not found.' ] );
		}

		$current_vars = $this->get_current_variables( $kit_id );
		$data         = ( isset( $current_vars['data'] ) && is_array( $current_vars['data'] ) ) ? $current_vars['data'] : [];

		if ( ! isset( $data[ $var_id ] ) ) {
			wp_send_json_error( [ 'message' => 'Variable not found.' ] );
		}

		$label = $data[ $var_id ]['label'] ?? $var_id;
		unset( $data[ $var_id ] );

		$current_vars['data'] = $data;

		update_post_meta( $kit_id, '_elementor_global_variables', wp_slash( wp_json_encode( $current_vars ) ) );
		$this->clear_elementor_cache( $kit_id );

		wp_send_json_success( [
			'message' => 'Deleted "' . $label . '". Reload the Elementor editor to see the change.',
		] );
	}

	/* ── AJAX: Download JSON Export ─────────── */

	public function ajax_download() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		$kit_id = $this->get_active_kit_id();
		if ( $kit_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Active Elementor Kit not found.' ] );
		}

		$current_vars = $this->get_current_variables( $kit_id );

		// Also get the raw DB value for debugging
		$raw_db = get_post_meta( $kit_id, '_elementor_global_variables', true );

		$payload = [
			'meta' => [
				'exported_at_utc' => gmdate( 'c' ),
				'site_url'        => home_url(),
				'kit_id'          => $kit_id,
				'export_name'     => 'Variable Sneaker Export',
				'format'          => 'vsneaker_variables_v1',
			],
			'data' => [
				'_elementor_global_variables' => $current_vars,
			],
		];

		wp_send_json_success( [
			'json'     => $payload,
			'filename' => 'vsneaker-variables-' . gmdate( 'Ymd-His' ) . '.json',
			'raw_db'   => is_string( $raw_db ) ? $raw_db : wp_json_encode( $raw_db ),
		] );
	}

	/* ── Parser ─────────────────────────────── */

	/**
	 * Parses simple text input into structured variable data.
	 *
	 * Supported formats (one per line):
	 *   label = #HEXCOLOR
	 *   label = rgb(r, g, b)
	 *   label = rgba(r, g, b, a)
	 *   label = hsl(h, s%, l%)
	 *   label = hsla(h, s%, l%, a)
	 *   label = clamp(...)
	 *   label = 1.5rem
	 *   label = 16px
	 *   label = 100%
	 *   label = 2em
	 *   label = 50vw / 50vh / 50svh / 50dvh etc.
	 *
	 * The delimiter between label and value can be: = , : , or a tab.
	 * Lines starting with # or // are treated as comments.
	 */
	public function parse_variables( string $raw ): array {
		$lines  = preg_split( '/\r\n|\r|\n/', $raw );
		$result = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines and comments
			if ( $line === '' || substr( $line, 0, 2 ) === '//' ) {
				continue;
			}

			// Allow "# comment" lines (but not "#hex" which is a color)
			if ( preg_match( '/^#\s+/', $line ) ) {
				continue;
			}

			// Split on = or : or tab (first occurrence)
			$parts = preg_split( '/\s*[=:]\s*|\t+/', $line, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			$label = trim( $parts[0] );
			$value = trim( $parts[1] );

			// Strip trailing semicolons (CSS syntax tolerance)
			$value = rtrim( $value, '; ' );

			// Clean label: strip leading -- (CSS variable syntax tolerance)
			$label = preg_replace( '/^-{1,2}/', '', $label );
			$label = trim( $label );

			if ( $label === '' || $value === '' ) {
				continue;
			}

			// Determine type
			$parsed = $this->classify_value( $value );
			if ( $parsed === null ) {
				continue;
			}

			$result[] = array_merge( [ 'label' => $label ], $parsed );
		}

		return $result;
	}

	/**
	 * Classifies a value string as color, size, or font and returns
	 * the Elementor-compatible data structure.
	 */
	private function classify_value( string $value ): ?array {
		// ── Colors ──
		// Hex colors
		if ( preg_match( '/^#([0-9a-fA-F]{3,8})$/', $value ) ) {
			return [
				'type'       => 'color',
				'el_type'    => 'global-color-variable',
				'display'    => $value,
				'value_data' => [
					'$$type' => 'color',
					'value'  => strtoupper( $value ),
				],
			];
		}

		// rgb/rgba/hsl/hsla
		if ( preg_match( '/^(rgba?|hsla?)\s*\(/', $value ) ) {
			// Normalize to hex if possible for Elementor, otherwise store raw
			$hex = $this->css_color_to_hex( $value );
			return [
				'type'       => 'color',
				'el_type'    => 'global-color-variable',
				'display'    => $hex ?: $value,
				'value_data' => [
					'$$type' => 'color',
					'value'  => $hex ?: $value,
				],
			];
		}

		// Named CSS colors (basic set)
		$named = $this->named_color_to_hex( $value );
		if ( $named ) {
			return [
				'type'       => 'color',
				'el_type'    => 'global-color-variable',
				'display'    => $named,
				'value_data' => [
					'$$type' => 'color',
					'value'  => $named,
				],
			];
		}

		// ── Sizes ──
		// clamp(), calc(), min(), max(), var()
		if ( preg_match( '/^(clamp|calc|min|max|var)\s*\(/', $value ) ) {
			return [
				'type'       => 'size',
				'el_type'    => 'global-size-variable',
				'display'    => $value,
				'value_data' => [
					'$$type' => 'size',
					'value'  => [
						'size' => $value,
						'unit' => 'custom',
					],
				],
			];
		}

		// Numeric with unit: 1.5rem, 16px, 100%, 2em, 50vw, 50vh, etc.
		if ( preg_match( '/^(-?[\d.]+)\s*(px|rem|em|%|vw|vh|svw|svh|dvw|dvh|lvw|lvh|vmin|vmax|ch|ex|cm|mm|in|pt|pc)$/i', $value, $m ) ) {
			$numeric = $m[1];
			$unit    = strtolower( $m[2] );

			// Check if numeric is actually a number
			if ( ! is_numeric( $numeric ) ) {
				return null;
			}

			return [
				'type'       => 'size',
				'el_type'    => 'global-size-variable',
				'display'    => $value,
				'value_data' => [
					'$$type' => 'size',
					'value'  => [
						'size' => floatval( $numeric ),
						'unit' => $unit,
					],
				],
			];
		}

		// Bare number (unitless) — treat as px
		if ( is_numeric( $value ) ) {
			return [
				'type'       => 'size',
				'el_type'    => 'global-size-variable',
				'display'    => $value . 'px',
				'value_data' => [
					'$$type' => 'size',
					'value'  => [
						'size' => floatval( $value ),
						'unit' => 'px',
					],
				],
			];
		}

		// ── Fonts ──
		// Anything that doesn't match color/size is treated as a font family.
		// Clean up quotes if present (e.g. "Roboto" → Roboto, 'Open Sans' → Open Sans)
		$font_value = trim( $value, "\"'" );
		if ( $font_value !== '' && preg_match( '/^[a-zA-Z]/', $font_value ) ) {
			return [
				'type'       => 'font',
				'el_type'    => 'global-font-variable',
				'display'    => $font_value,
				'value_data' => [
					'$$type' => 'string',
					'value'  => $font_value,
				],
			];
		}

		return null;
	}

	/* ── Helpers ─────────────────────────────── */

	private function get_active_kit_id(): int {
		$kit_id = (int) get_option( 'elementor_active_kit' );

		if ( $kit_id <= 0 ) {
			$q = new WP_Query( [
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'tax_query'      => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'taxonomy' => 'elementor_library_type',
					'field'    => 'slug',
					'terms'    => [ 'kit' ],
				] ],
				'fields' => 'ids',
			] );
			if ( ! empty( $q->posts[0] ) ) {
				$kit_id = (int) $q->posts[0];
			}
		}

		return $kit_id;
	}

	private function get_current_variables( int $kit_id ): array {
		$raw = get_post_meta( $kit_id, '_elementor_global_variables', true );

		// Elementor may store as JSON string or PHP serialized array
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		if ( is_array( $raw ) ) {
			return $raw;
		}

		return [ 'data' => [], 'watermark' => 0, 'version' => 2 ];
	}

	private function get_existing_variable_labels( int $kit_id ): array {
		if ( $kit_id <= 0 ) {
			return [];
		}
		$vars   = $this->get_current_variables( $kit_id );
		$labels = [];
		$data   = $vars['data'] ?? [];
		foreach ( $data as $obj ) {
			if ( is_array( $obj ) && isset( $obj['label'] ) && empty( $obj['deleted_at'] ) ) {
				$labels[] = strtolower( $obj['label'] );
			}
		}
		return $labels;
	}

	private function verify_ajax() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token. Please reload the page.' ], 403 );
		}
	}

	private function clear_elementor_cache( int $kit_id = 0 ) {
		// Clear WordPress meta cache for this kit
		if ( $kit_id > 0 ) {
			wp_cache_delete( $kit_id, 'post_meta' );
		}

		// Delete known Elementor transients via WP API
		delete_transient( 'elementor_remote_info_api_data_' . ELEMENTOR_VERSION );
		delete_transient( 'elementor_pro_remote_info_api_data_' . ( defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '' ) );

		// Clear Elementor file/cache managers
		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				$plugin = \Elementor\Plugin::$instance;
				if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
					$plugin->files_manager->clear_cache();
				}
			} catch ( \Throwable $e ) {
				// Silent
			}
		}

		// Flush WordPress object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Convert rgb/rgba/hsl/hsla to hex. Returns null on failure.
	 */
	private function css_color_to_hex( string $color ): ?string {
		// rgb(r, g, b) or rgb(r g b)
		if ( preg_match( '/^rgb\(\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*\)$/i', $color, $m ) ) {
			return sprintf( '#%02X%02X%02X', (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		// rgba — store with alpha notation
		if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*[,\/\s]\s*([\d.]+)\s*\)$/i', $color, $m ) ) {
			$alpha = floatval( $m[4] );
			$alphaHex = str_pad( dechex( (int) round( $alpha * 255 ) ), 2, '0', STR_PAD_LEFT );
			return sprintf( '#%02X%02X%02X%s', (int) $m[1], (int) $m[2], (int) $m[3], strtoupper( $alphaHex ) );
		}
		// hsl(h, s%, l%)
		if ( preg_match( '/^hsl\(\s*([\d.]+)\s*[,\s]\s*([\d.]+)%?\s*[,\s]\s*([\d.]+)%?\s*\)$/i', $color, $m ) ) {
			return $this->hsl_to_hex( floatval( $m[1] ), floatval( $m[2] ), floatval( $m[3] ) );
		}
		return null;
	}

	private function hsl_to_hex( float $h, float $s, float $l ): string {
		$s /= 100;
		$l /= 100;
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		if ( $h < 60 )       { $r = $c; $g = $x; $b = 0; }
		elseif ( $h < 120 )  { $r = $x; $g = $c; $b = 0; }
		elseif ( $h < 180 )  { $r = 0; $g = $c; $b = $x; }
		elseif ( $h < 240 )  { $r = 0; $g = $x; $b = $c; }
		elseif ( $h < 300 )  { $r = $x; $g = 0; $b = $c; }
		else                 { $r = $c; $g = 0; $b = $x; }

		return sprintf( '#%02X%02X%02X',
			(int) round( ( $r + $m ) * 255 ),
			(int) round( ( $g + $m ) * 255 ),
			(int) round( ( $b + $m ) * 255 )
		);
	}

	private function named_color_to_hex( string $name ): ?string {
		$map = [
			'black'       => '#000000', 'white'        => '#FFFFFF',
			'red'         => '#FF0000', 'green'        => '#008000',
			'blue'        => '#0000FF', 'yellow'       => '#FFFF00',
			'cyan'        => '#00FFFF', 'magenta'      => '#FF00FF',
			'orange'      => '#FFA500', 'purple'       => '#800080',
			'pink'        => '#FFC0CB', 'gray'         => '#808080',
			'grey'        => '#808080', 'silver'       => '#C0C0C0',
			'navy'        => '#000080', 'teal'         => '#008080',
			'maroon'      => '#800000', 'olive'        => '#808000',
			'aqua'        => '#00FFFF', 'lime'         => '#00FF00',
			'coral'       => '#FF7F50', 'salmon'       => '#FA8072',
			'tomato'      => '#FF6347', 'gold'         => '#FFD700',
			'indigo'      => '#4B0082', 'violet'       => '#EE82EE',
			'turquoise'   => '#40E0D0', 'tan'          => '#D2B48C',
			'crimson'     => '#DC143C', 'chocolate'    => '#D2691E',
			'transparent' => '#00000000',
		];
		$lower = strtolower( trim( $name ) );
		return $map[ $lower ] ?? null;
	}
}

new Variable_Sneaker_Plugin();
