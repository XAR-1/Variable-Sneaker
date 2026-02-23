# 👟 Variable Sneaker

**Bulk import, export, and manage Elementor V4 global variables — colors, sizes, and fonts — right from your WordPress dashboard.**

Variable Sneaker lets you paste a simple list of design tokens and sneak them all into Elementor's V4 Variable Manager at once. No clicking through modals one by one. No copy-paste fatigue. Just paste, preview, import.

---

## ✨ Features

- **Paste-to-import** — Paste a simple text list (`name = value`) and import dozens of variables in seconds
- **Colors, sizes, and fonts** — Supports hex, rgb(a), hsl(a), named colors, px/rem/em/%, vw/vh, clamp(), calc(), and font families
- **Preview before import** — See exactly what will be created, with duplicate detection
- **Three conflict modes** — Skip duplicates, overwrite existing, or import all with new IDs
- **Variables viewer** — Browse all your current variables in a clean interface with Colors, Sizes, and Fonts tabs
- **Search and delete** — Find variables instantly and remove ones you don't need
- **Text export** — Generate a copyable text list of all your variables
- **JSON export** — Download the full variable data for backup or cross-site migration
- **CSS variable syntax** — Accepts `--my-var: #FF0000;` format directly from your stylesheets
- **Smart parser** — Strips `--` prefixes, trailing semicolons, and handles `=`, `:`, or tab delimiters

---

## 📦 Installation

1. Download the latest release (`.zip` file)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin
5. Find it under **Tools → Variable Sneaker**

**Requirements:**
- WordPress 6.0+
- PHP 7.4+
- Elementor (free) for colors and fonts
- Elementor Pro for size variables

---

## 🚀 Quick Start

### Importing Variables

1. Go to **Tools → Variable Sneaker**
2. Paste your variables in the text area:

```
primary = #1A1A2E
secondary = #16213E
accent = #E94560
text = #1E1E1E
white = #FFFFFF

body-font = Inter
heading-font = Playfair Display

fs-sm = clamp(0.8rem, 0.17vi + 0.76rem, 0.89rem)
fs-base = clamp(1rem, 0.34vi + 0.91rem, 1.19rem)
fs-md = clamp(1.25rem, 0.61vi + 1.1rem, 1.58rem)
fs-lg = clamp(1.56rem, 1vi + 1.31rem, 2.11rem)
fs-xl = clamp(1.95rem, 1.56vi + 1.56rem, 2.81rem)

space-sm = 8px
space-md = 16px
space-lg = 32px
space-xl = 64px
```

3. Click **Preview** to check everything looks right
4. Choose your conflict mode and click **Import**
5. Refresh the Elementor editor — your variables are there

### CSS Variable Syntax

Already have your tokens as CSS custom properties? Paste them directly:

```css
--primary: #1A1A2E;
--secondary: #16213E;
--accent: #E94560;
--fs-base: clamp(1rem, 0.34vi + 0.91rem, 1.19rem);
--heading-font: Playfair Display;
```

Variable Sneaker strips the `--` prefix and `;` suffix automatically.

---

## 📖 Supported Formats

### Colors
| Format | Example |
|--------|---------|
| Hex (3, 6, or 8 digit) | `#FF0000`, `#F00`, `#FF000080` |
| RGB / RGBA | `rgb(255, 0, 0)`, `rgba(255, 0, 0, 0.5)` |
| HSL / HSLA | `hsl(0, 100%, 50%)`, `hsla(0, 100%, 50%, 0.5)` |
| Named colors | `red`, `tomato`, `coral`, `transparent` |

### Sizes
| Format | Example |
|--------|---------|
| Pixels | `16px`, `48px` |
| Relative units | `1rem`, `1.5em`, `100%` |
| Viewport units | `50vw`, `100vh`, `50dvh` |
| Fluid / responsive | `clamp(1rem, 2vw + 0.5rem, 3rem)` |
| Calc expressions | `calc(100% - 32px)` |

### Fonts
| Format | Example |
|--------|---------|
| Single word | `Roboto`, `Arial` |
| Multi-word | `Playfair Display`, `JetBrains Mono` |
| Quoted | `"Open Sans"`, `'Fira Code'` |

> Any value that doesn't match a color or size pattern is automatically treated as a font family.

---

## 🔄 After Importing

In most cases, your variables will appear as soon as you refresh the Elementor editor — no extra steps needed.

If they don't show up, click **Clear Files & Data** in the WordPress admin bar (or Elementor → Tools → Clear Cache), then reopen the editor.

---

## 💾 Exporting

### Text Export
Go to the **Export** tab and click **Generate Text Export**. This creates a simple `name = value` list you can:
- Copy to clipboard
- Paste into another site's Variable Sneaker
- Share with your team
- Include in project documentation

### JSON Export
Click **Download JSON** for a full backup including variable IDs, order, timestamps, and metadata. Use this for:
- Backing up before major changes
- Migrating variables between WordPress sites
- Version controlling your design system

---

## 🗑️ Deleting Variables

In the **Variables** tab, hover over any variable and click the trash icon. You can batch-delete multiple variables — a reminder banner will appear telling you to reload the Elementor editor when you're done.

---

## ❓ FAQ

**Why don't my variables show up in Elementor after importing?**
Usually a simple editor refresh is enough. If they still don't appear, click "Clear Files & Data" in the admin bar and reload the editor.

**Why can't I see size variables?**
Size variables require **Elementor Pro**. Color and font variables work with the free version.

**Can I import hundreds of variables at once?**
Yes. The plugin handles large batches efficiently.

**Does this work with Elementor V3?**
No. This plugin targets the V4 variable system (`_elementor_global_variables` post meta). For V3 Global Colors/Fonts, use Elementor's built-in Site Settings.

**Will this break my existing variables?**
No. In "Skip duplicates" mode (default), existing variables are never modified. In "Overwrite" mode, only the value is updated — the variable ID stays the same, so references in your designs are preserved.

---

## 🛠️ Technical Details

- Variables are stored in `wp_postmeta` as a JSON string under the key `_elementor_global_variables` on the active Elementor Kit post
- The plugin reads and writes this meta value directly, using `wp_json_encode` + `wp_slash` for correct storage
- Variable IDs follow the `e-gv-XXXXXXX` format (7-char hex hash)
- The watermark counter is incremented for each new variable to trigger Elementor's change detection
- Cache clearing includes: post meta cache, Elementor transients, Elementor file manager, and WordPress object cache

---

## 📄 License

GPLv2 or later. See [LICENSE](LICENSE) for details.

---

## 🤝 Contributing

Found a bug? Have an idea? Open an issue or submit a pull request.

---

A free gift from [XAR-1](https://github.com/XAR-1) to the Elementor community 👟
