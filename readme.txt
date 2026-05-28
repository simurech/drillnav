=== DrillNav – Smart Contextual Navigation for Deeply Nested Sites ===
Contributors:      simon61
Tags:              navigation, contextual navigation, drilldown, page hierarchy, multilingual
Requires at least: 6.3
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        1.5.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Smart drill-down navigation that adapts to the current page – perfect for deeply nested WordPress sites with many hierarchy levels.

== Description ==

**Visitors get lost in deep page hierarchies.** Standard WordPress navigation shows every page at once – overwhelming on sites with hundreds of nested pages. DrillNav solves this with a contextual, drill-down navigation that shows only what's relevant **right now**.

On any page, DrillNav automatically displays:

* The **ancestor path** (where you came from)
* The **current level** (siblings of the active page)
* An expandable **drill-down** into child pages

No configuration required. Place the block, add the shortcode, or drop in the widget – DrillNav does the rest.

= Key Features =

* **Zero configuration** – works out of the box, adapts to any page hierarchy
* **Gutenberg block** with live editor preview and five ready-made block variations in the inserter
* **Shortcode** `[drillnav]` and **sidebar widget** for classic themes
* **Unlimited depth** – works with any number of hierarchy levels
* **Four layouts** – List (default), Horizontal, Accordion (Pro), Mega Grid (Pro)
* **Style presets** – Default, Compact, Comfortable, Cards (Pro)
* **Mobile hamburger toggle** – optional side-drawer mode for themes without an off-canvas menu
* **Colour scheme presets** – Default (inherits theme), Light, and Dark, all customisable via CSS custom properties
* **RTL support** – full right-to-left layout with mirrored animations and logical CSS properties
* **Live settings preview** – see colour scheme, layout, and style changes instantly on the Settings page
* **WCAG 2.1 AA accessible** – full keyboard navigation, correct ARIA attributes, automatic focus management after drill-down
* **Blazing fast** – assets load only on pages where the navigation is used; hover-preloading makes drill-down feel instant; 7-day intelligent caching with automatic cache invalidation
* **No jQuery** – pure vanilla JavaScript, no bloat
* **Developer-friendly** – filter hooks, REST API endpoint, CSS custom properties for easy theming
* **Translation ready** – fully internationalised
* **WPML & Polylang compatible** – navigation, cache, and label strings are fully language-aware

= Perfect For =

* Corporate websites with deep service or department hierarchies
* Knowledge bases and documentation sites
* Government and institutional sites
* Large school or university websites
* Any site with more than 3 levels of pages

= Pro Version – WooCommerce Support =

[DrillNav Pro](https://github.com/simurech/drillnav) extends the plugin with full WooCommerce product category navigation:

* Drill-down through product categories (unlimited depth)
* Live product count per category
* Smart empty-category filtering (checks actual stock availability, not just post count)
* Exclude specific categories from the navigation
* Context-aware: auto-detects category pages, product pages, and the shop page
* Priority support

= Multilingual Support =

DrillNav works out of the box on multilingual sites built with **WPML** or **Polylang**:

* Navigation tree always reflects the **active language** — pages, categories, and the blog page are automatically resolved to their translated equivalents
* Cache keys are **language-aware**, so each language gets its own cached navigation without interference
* Custom labels configured in **Settings > DrillNav** (Home label, Nav label, Blog label) can be translated via the respective plugin's string translation interface (WPML String Translation / Polylang)
* `get_posts()` queries pass `suppress_filters => false` so multilingual plugins can rewrite queries correctly

No additional configuration is required — just activate WPML or Polylang and DrillNav adapts automatically.

= Accessibility =

DrillNav is built accessibility-first:

* `<nav>` landmark with descriptive `aria-label`
* `aria-expanded` on expandable items, updated in real time
* `aria-current="page"` on the active item
* Keyboard focus automatically placed on the back button after each drill-down
* Escape key closes the current drill-down level
* Full `Tab`/`Shift+Tab` navigation without traps
* Visible `:focus-visible` styles (WCAG 1.4.11 compliant contrast)
* Respects `prefers-reduced-motion`

= Developer Hooks =

```php
// Filter the navigation items before rendering
add_filter( 'drillnav_nav_items', function( $data, $args ) {
    return $data;
}, 10, 2 );

// Filter the resolved page context
add_filter( 'drillnav_current_context', function( $context ) {
    return $context;
} );

// Add CSS classes to a navigation item's <li>
add_filter( 'drillnav_item_classes', function( $classes, $item, $layout ) {
    if ( $item['is_current'] ) {
        $classes[] = 'my-active-item';
    }
    return $classes;
}, 10, 3 );

// Add HTML attributes to a navigation item's <a>
add_filter( 'drillnav_item_attrs', function( $attrs, $item, $layout ) {
    $attrs['data-post-id'] = $item['id'];
    return $attrs;
}, 10, 3 );

// Adjust cache duration (default: 7 days)
add_filter( 'drillnav_cache_duration', function( $seconds ) {
    return WEEK_IN_SECONDS * 2;
} );
```

REST API: `GET /wp-json/drillnav/v1/children?post_id=123&post_type=page`

= Privacy =

DrillNav does not collect or transmit any personal data. No external connections are made. Fully GDPR-compliant.

== Installation ==

1. Upload the `drillnav-drilldown-navigation` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins > Installed Plugins**.
3. You'll see a brief notice with the three ways to use DrillNav:

**Option A – Gutenberg Block (recommended)**
Open any page in the block editor, search for **DrillNav**, and insert the block. A live preview appears immediately.

**Option B – Shortcode**
Add `[drillnav]` anywhere in a page, post, or widget area.

Available attributes:
`[drillnav depth="3" show_home="yes" home_label="Start" mobile_toggle="yes"]`

**Option C – Sidebar Widget**
Go to **Appearance > Widgets**, find **DrillNav – Contextual Navigation**, and drag it to any sidebar.

That's it. DrillNav automatically detects the page hierarchy and renders the correct navigation.

== Frequently Asked Questions ==

= Does it work with WPML or Polylang? =

Yes. DrillNav 1.1.0+ includes built-in WPML and Polylang compatibility. The navigation tree, cache keys, and blog page detection are all language-aware. Custom label strings (Home, Nav, Blog) registered in **Settings > DrillNav** can be translated via WPML String Translation or Polylang's string translation interface. No extra configuration is needed — just activate your multilingual plugin and DrillNav adapts automatically.

= Does this work with WooCommerce product categories? =

WooCommerce product category navigation is available in [DrillNav Pro](https://github.com/simurech/drillnav). The free version supports all hierarchical WordPress post types (Pages, and any custom post type with `hierarchical => true`).

= Does it work with my theme? =

Yes. DrillNav uses semantic HTML and CSS custom properties (`--drillnav-color-*`, `--drillnav-font-*`, etc.), making it easy to match any theme's design. You can also disable the plugin's CSS entirely in **Settings > DrillNav** and supply your own styles.

= My pages are flat (no hierarchy). Will this work? =

DrillNav is designed for hierarchical page structures. On pages without a parent/child relationship, it will show all top-level pages. For best results, make sure your pages have parent pages set in WordPress.

= How do I control which page types are used? =

In **Settings > DrillNav**, under **Post types**, select all hierarchical post types you want the navigation to respond to (e.g. Pages, a custom `services` post type, etc.).

= How do I customise the appearance? =

Override any CSS custom property in your theme's stylesheet:

```css
.drillnav {
    --drillnav-color-current-bg: #e8f4ff;
    --drillnav-color-link: #0073aa;
    --drillnav-transition-speed: 150ms;
}
```

= Is the navigation accessible for keyboard and screen reader users? =

Yes. DrillNav was built with WCAG 2.1 AA compliance from the start. All interactive elements are reachable by keyboard, ARIA states are updated dynamically, and focus is managed correctly during drill-down interactions.

= Does the plugin add any database tables? =

No. DrillNav uses standard WordPress options and transients – no custom database tables.

= Will it slow down my site? =

No. Assets are loaded only on pages where DrillNav is placed. Navigation data is cached for 7 days and automatically invalidated when pages change. The JavaScript is pure vanilla (no jQuery), weighing in at under 5 KB minified.

= Can I use multiple instances on one page? =

Yes. Each DrillNav block, shortcode, or widget instance is fully independent.

== Screenshots ==

1. DrillNav block in the Gutenberg editor with inspector controls
2. Frontend navigation on a page with multiple hierarchy levels
3. Drill-down into sub-pages with animated transition
4. Breadcrumb and back-button navigation
5. Settings page in WordPress admin

== Changelog ==

= 1.5.0 =
* New: Search/Filter (Pro) – live text filter above navigation items; debounced input hides non-matching items instantly; resets automatically on drill-down and back
* New: Lazy-loading Accordion (Pro) – accordion renders only the first level server-side; deeper levels are fetched via REST on expand; hover-preloading reduces perceived latency

= 1.4.0 =
* New: RTL support – logical CSS properties and mirrored slide animations for right-to-left layouts
* New: Block variations – five ready-made variations in the block inserter (Horizontal, Compact, Dark, Accordion Pro, Mega Pro)
* New: Item filter hooks – `drillnav_item_classes` and `drillnav_item_attrs` for per-item customisation
* New: Hover-preloading – children fetched on hover so drill-down clicks feel instant
* New: Live preview on the Settings page – colour scheme, layout, style preset, and custom CSS changes appear instantly
* New: Global settings for mobile toggle and all nine Pro CSS custom properties (previously only settable per block instance)
* Fix: Theme CSS compatibility – navigation links no longer inherit `text-decoration` from themes like Astra

= 1.3.0 =
* New: Layout option – Horizontal (Free), Accordion (Pro), Mega/CSS-Grid (Pro)
* New: Accordion layout server-side renders the full page tree with JS-powered expand/collapse
* New: Keyboard navigation – Escape closes the active accordion level

= 1.2.0 =
* New: Style presets – Compact, Comfortable, Cards (Pro)
* New: Nine granular Pro styling options via CSS custom properties (font size, padding, colours, border radius, transition speed)

= 1.1.0 =
* New: WPML compatibility – navigation, cache keys, and blog page detection are now language-aware
* New: Polylang compatibility – same language-aware behaviour via Polylang API
* New: Filter hook `drillnav_language` – return the active language code for custom integrations
* New: Filter hook `drillnav_translate_post_id` – translate a post ID to the current language
* New: Filter hook `drillnav_translate_string` – translate custom label strings (home_label, nav_label, blog_label)
* New: Custom labels registered with WPML String Translation and Polylang string translation
* Fix: `get_posts()` calls now pass `suppress_filters => false` so multilingual plugins can filter results correctly

= 1.0.1 =
* Fix: Escaped integer output in admin number field to satisfy WordPress coding standards
* Update: Freemius premium slug updated to `drillnav-drilldown-navigation-pro`
* Update: Freemius premium suffix changed to `(Pro)`

= 1.0.0 =
* Initial release
* Gutenberg block with live ServerSideRender preview in the editor
* Shortcode `[drillnav]` with depth, show_home, home_label, post_type, mobile_toggle attributes
* Sidebar widget
* Blog / Posts integration: category hierarchy, single post context, tag archives
* Mobile hamburger toggle: optional side-drawer mode (shortcode/block attribute)
* Colour scheme presets: Default, Light, Dark – fully customisable via CSS custom properties
* Block/shortcode settings override global plugin settings per instance
* WCAG 2.1 AA accessible: ARIA attributes, keyboard focus management, Escape key support
* 7-day transient caching with automatic invalidation on post save/delete
* REST API endpoint `GET /wp-json/drillnav/v1/children`
* Freemius integration for Pro licence management
* WooCommerce Pro integration (inactive without valid licence)
* Translation-ready with .pot file
* WordPress Coding Standards compliant

== Upgrade Notice ==

= 1.5.0 =
No upgrade steps required. New Pro features (Search/Filter, Lazy Accordion) are disabled by default.

= 1.4.0 =
No upgrade steps required. Global settings for `mobile_toggle` and custom CSS properties are now available in Settings > DrillNav.

= 1.1.0 =
No upgrade steps required. Cache is automatically cleared on upgrade.

= 1.0.1 =
No upgrade steps required.

= 1.0.0 =
Initial release. No upgrade steps required.
