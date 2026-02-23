=== Variable Sneaker — Elementor V4 Import / Export ===
Contributors: zarvandev
Tags: elementor, variables, design tokens, css variables, design system
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk import, export, and manage Elementor V4 global variables — colors, sizes, and fonts — right from your WordPress dashboard.

== Description ==

Variable Sneaker lets you paste a simple list of design tokens and import them all into Elementor's V4 Variable Manager at once. No clicking through modals one by one. No copy-paste fatigue. Just paste, preview, import.

**Features:**

* **Paste-to-import** — Paste a simple text list (name = value) and import dozens of variables in seconds
* **Colors, sizes, and fonts** — Supports hex, rgb(a), hsl(a), named colors, px/rem/em/%, vw/vh, clamp(), calc(), and font families
* **Preview before import** — See exactly what will be created, with duplicate detection
* **Three conflict modes** — Skip duplicates, overwrite existing, or import all with new IDs
* **Variables viewer** — Browse all your current variables with Colors, Sizes, and Fonts tabs
* **Search and delete** — Find variables instantly and remove ones you don't need
* **Text export** — Generate a copyable text list of all your variables
* **JSON export** — Download the full variable data for backup or cross-site migration
* **CSS variable syntax** — Accepts --my-var: #FF0000; format directly from your stylesheets

**Supported Formats:**

* Colors: hex (#FF0000), rgb/rgba, hsl/hsla, named colors
* Sizes: px, rem, em, %, vw, vh, clamp(), calc()
* Fonts: Any font family name (Roboto, Playfair Display, etc.)

**Requirements:**

* Elementor (free) for color and font variables
* Elementor Pro for size variables

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/variable-sneaker` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Tools → Variable Sneaker to start importing variables.

== Frequently Asked Questions ==

= Why don't my variables show up in Elementor after importing? =

Usually a simple editor refresh is enough. If they still don't appear, click "Clear Files & Data" in the admin bar and reload the editor.

= Why can't I see size variables? =

Size variables require Elementor Pro. Color and font variables work with the free version.

= Can I import hundreds of variables at once? =

Yes. The plugin handles large batches efficiently.

= Does this work with Elementor V3? =

No. This plugin targets the V4 variable system. For V3 Global Colors/Fonts, use Elementor's built-in Site Settings.

= Will this break my existing variables? =

No. In "Skip duplicates" mode (default), existing variables are never modified. In "Overwrite" mode, only the value is updated — the variable ID stays the same, so references in your designs are preserved.

== Screenshots ==

1. Import tab — paste your variables and preview before importing
2. Variables tab — browse, search, and delete existing variables
3. Export tab — text export and JSON backup

== Changelog ==

= 1.1.0 =
* Added font variable support
* Added Variables viewer with Colors, Sizes, and Fonts tabs
* Added search filtering
* Added delete functionality
* Added JSON export with metadata
* Added Raw DB debug tool
* Dark theme UI

= 1.0.0 =
* Initial release
* Paste-to-import for colors and sizes
* Preview with duplicate detection
* Three conflict modes
* Text export with clipboard copy
