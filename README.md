<img src="assets/wp-org/icons/icon-256x256.png" alt="DrillNav" width="96">

# DrillNav – Smart Contextual Navigation for Deeply Nested Sites

A WordPress plugin that renders a contextual, drill-down navigation based on the current page's position in the site hierarchy. No manual configuration required — it works automatically.

## Features

- **Gutenberg block** with live editor preview and per-block settings
- **Shortcode** `[drillnav]` and **sidebar widget**
- **Mobile hamburger toggle** — optional side-drawer for themes without off-canvas menus
- **Colour scheme presets** — Default (inherits theme), Light, Dark
- **Blog / Posts integration** — category hierarchy, single post context, tag archives
- **Unlimited depth** — works with any number of hierarchy levels
- **WCAG 2.1 AA accessible** — ARIA attributes, keyboard focus management, Escape key
- **Fast** — assets load only where the navigation is placed; 7-day transient cache with automatic invalidation
- **No jQuery** — pure vanilla JavaScript
- **WPML & Polylang compatible** — language-aware navigation, cache, and label translation
- **WooCommerce category navigation** — available in DrillNav Pro

## Requirements

- WordPress 6.3+
- PHP 8.0+

## Usage

**Block** — search for "DrillNav" in the block editor and insert it.

**Shortcode**
```
[drillnav depth="2" show_home="yes" mobile_toggle="yes"]
```

| Attribute | Default | Description |
|---|---|---|
| `depth` | `0` | Max depth (0 = unlimited) |
| `show_home` | `yes` | Show home link in breadcrumb (yes / no) |
| `home_label` | site name | Label for the home link |
| `post_type` | `page` | Hierarchical post type to navigate |
| `mobile_toggle` | `no` | Hamburger icon + side drawer on mobile (yes / no) |

**Widget** — Appearance → Widgets → DrillNav – Contextual Navigation.

## Multilingual Support

DrillNav 1.1.0+ is fully compatible with **WPML** and **Polylang**. Once either plugin is active, DrillNav automatically:

- Resolves pages, categories, and the blog page to their **current-language equivalents**
- Uses **language-aware cache keys** — each language gets its own cached navigation
- Registers custom labels (Home, Nav, Blog) with the multilingual plugin's **string translation interface**

Three filter hooks are available for custom integrations:

```php
// Return the active language code (used in cache keys)
add_filter( 'drillnav_language', function( $lang ) {
    return 'de'; // or your custom language code
} );

// Translate a post ID to the current language version
add_filter( 'drillnav_translate_post_id', function( $post_id, $post_type ) {
    return $post_id; // return translated ID
}, 10, 2 );

// Translate a custom label string
add_filter( 'drillnav_translate_string', function( $value, $key ) {
    return $value; // return translated string
}, 10, 2 );
```

## Developer Hooks

```php
// Modify the complete navigation data before rendering
add_filter( 'drillnav_nav_items', function( $data, $args ) {
    return $data;
}, 10, 2 );

// Modify the resolved page context
add_filter( 'drillnav_current_context', function( $context ) {
    return $context;
} );

// Adjust cache TTL (default: 7 days)
add_filter( 'drillnav_cache_duration', function( $seconds ) {
    return WEEK_IN_SECONDS * 2;
} );
```

REST endpoint: `GET /wp-json/drillnav/v1/children?post_id=123&post_type=page`

## CSS Custom Properties

```css
.drillnav {
    --drillnav-color-bg:         transparent;
    --drillnav-color-text:       inherit;
    --drillnav-color-link:       inherit;
    --drillnav-color-current-bg: rgba(0, 0, 0, 0.06);
    --drillnav-color-border:     rgba(0, 0, 0, 0.1);
    --drillnav-item-padding-y:   0.5rem;
    --drillnav-item-padding-x:   0.75rem;
    --drillnav-border-radius:    4px;
    --drillnav-transition-speed: 200ms;
}
```

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
