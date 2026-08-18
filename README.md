<p align="center">
  <img src="assets/wp-org/icons/icon-256x256.png" alt="DrillNav" width="128">
</p>

# DrillNav – Smart Contextual Navigation for Deeply Nested Sites

[![Stable Tag](https://img.shields.io/badge/stable-1.7.5-blue.svg)](https://github.com/simurech/drillnav/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759b.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892be.svg)](https://php.net/)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Kontextuelle Drill-Down-Navigation, die sich an die aktuelle Seite anpasst — ideal für tief verschachtelte WordPress-Seitenstrukturen. Ohne Konfigurationsaufwand.

**→ Plugin auf WordPress.org (in Vorbereitung)** — vollständige Beschreibung, Screenshots, FAQ und Installationsanleitung.

---

## Was das Plugin macht

Die Standard-WordPress-Navigation zeigt alle Seiten auf einmal — bei Websites mit hunderten verschachtelten Seiten unübersichtlich. DrillNav zeigt nur, was **gerade relevant** ist:

- **Ancestor-Pfad** — Breadcrumb-artige Rückwärtsnavigation
- **Aktuelle Ebene** — Geschwister der aktiven Seite
- **Drill-Down** — Klick öffnet Unterseiten, per REST API lazy geladen

Keine manuelle Konfiguration nötig. Block platzieren, Shortcode einfügen oder Widget hinzufügen — DrillNav erkennt die Hierarchie automatisch.

---

## Funktionen

| Funktion | Free | Pro |
|---|:---:|:---:|
| Kontextuelle Drill-Down-Navigation | ✓ | ✓ |
| Gutenberg-Block mit Live-Vorschau | ✓ | ✓ |
| Shortcode `[drillnav]` + Sidebar-Widget | ✓ | ✓ |
| List-Layout | ✓ | ✓ |
| Horizontal-Layout | ✓ | ✓ |
| Block-Variationen (5 vorgefertigte Presets) | ✓ | ✓ |
| Style-Presets: Default, Compact, Comfortable | ✓ | ✓ |
| Farbschemata: Default, Light, Dark, Auto (OS) | ✓ | ✓ |
| Mobiler Hamburger-Toggle (Side-Drawer) | ✓ | ✓ |
| RTL-Unterstützung | ✓ | ✓ |
| WPML- & Polylang-Kompatibilität | ✓ | ✓ |
| Hover-Preloading (sofortiger Drill-Down) | ✓ | ✓ |
| Live-Vorschau der Einstellungen | ✓ | ✓ |
| Admin-Einstellungen in 7 organisierten Tabs | ✓ | ✓ |
| Native Page-Builder-Widgets (Elementor, Beaver Builder, Divi) | ✓ | ✓ |
| WCAG 2.1 AA barrierefrei | ✓ | ✓ |
| Accordion-Layout (voller Baum, ein-/ausklappbar) | — | ✓ |
| Lazy-Loading-Accordion (tiefere Ebenen via REST) | — | ✓ |
| Style-Preset „Cards" | — | ✓ |
| Granulare CSS-Overrides (9 Custom Properties) | — | ✓ |
| Suche/Filter innerhalb der Navigation | — | ✓ |
| Mindestanzahl Einträge für Suchfilter | — | ✓ |
| Pfeil-Icon-Auswahl (8 Optionen) | — | ✓ |
| Zurück-Button-Icon-Auswahl (7 Optionen) | — | ✓ |
| Konfigurierbarer mobiler Breakpoint | — | ✓ |
| Vollbild-Menü-Overlay | — | ✓ |
| WP-Menü als Navigationsquelle | — | ✓ |
| Hybrid-Menüs (WP-Menü + WooCommerce-Unterkategorien) | — | ✓ |
| Allgemeine hierarchische Taxonomie-Navigation | — | ✓ |
| AJAX-Content-Loading (SPA-artig, History API) | — | ✓ |
| WooCommerce-Produktkategorie-Navigation | — | ✓ |
| Attribut-basierte Produktfilterung | — | ✓ |
| Icon-Support (Dashicons, SVG, Emoji) | — | ✓ |
| Highlight-/Featured-Badges | — | ✓ |
| Analytics & Event-Tracking (GTM / DataLayer) | — | ✓ |
| Off-Canvas-Drawer: Blur, Logo, Top-Slide | — | ✓ |

---

## Systemanforderungen

| Komponente | Minimum | Empfohlen |
|---|---|---|
| WordPress | 6.3 | Aktuellste Version |
| PHP | 8.1 | Aktuellste Version |
| WooCommerce (nur Pro) | 8.0 | Aktuellste Version |

---

## Installation

**Über WordPress.org (empfohlen):**
1. Im WordPress-Dashboard zu **Plugins → Installieren** navigieren.
2. Nach **DrillNav** suchen und installieren.

**Manuelle Installation via GitHub:**
1. Die neueste Release-`.zip` von der [Releases-Seite](https://github.com/simurech/drillnav/releases) herunterladen.
2. In WordPress zu **Plugins → Installieren → Plugin hochladen** navigieren.

**Nach der Aktivierung:**
1. Den **DrillNav-Block** im Editor einfügen, `[drillnav]` auf einer Seite platzieren oder das Widget in eine Sidebar ziehen.
2. Eine beliebige Seite der Hierarchie besuchen — die Navigation passt sich automatisch an.
3. Globale Standardwerte unter **Einstellungen → DrillNav** konfigurieren.

---

## Verwendung

### Block (empfohlen)

Im Block-Inserter nach **DrillNav** suchen. Über das Inspector-Panel lässt sich jede Instanz einzeln konfigurieren. Fünf Block-Variationen sind enthalten:

| Variation | Layout | Beschreibung |
|---|---|---|
| DrillNav (Standard) | List | Standard-Kontextnavigation |
| Horizontal Navigation | Horizontal | Elemente in einer horizontalen Zeile |
| Compact List | List | Reduziertes Padding für schmale Sidebars |
| Dark Navigation | List | Dunkles Farbschema |
| DrillNav – Accordion (Pro) | Accordion | Vollständiger Seitenbaum, ausklappbar |

### Shortcode

```
[drillnav]
```

Alle Attribute sind optional und überschreiben die globalen Einstellungen für diese Instanz:

| Attribut | Standard | Beschreibung |
|---|---|---|
| `depth` | `0` | Maximale Hierarchietiefe (0 = unbegrenzt) |
| `show_home` | `yes` | Home-Link als ersten Rückwärtsnavigations-Schritt anzeigen |
| `home_label` | Website-Name | Eigenes Label für den Home-Link |
| `post_type` | `page` | Hierarchischer Post-Type für die Navigation |
| `layout` | `list` | `list`, `horizontal`, `accordion` (Pro) |
| `style_preset` | `default` | `default`, `compact`, `comfortable`, `cards` (Pro) |
| `mobile_toggle` | `no` | Hamburger-Icon + Side-Drawer auf Mobilgeräten (`yes` / `no`) |
| `max_width` | — | Containerbreite begrenzen, z. B. `300px` oder `60%` |
| `multiple_back_buttons` | `no` | Ein Zurück-Button pro durchlaufener Ebene (`yes` / `no`) |
| `search_filter` | `no` | Live-Textfilter über den Navigationselementen aktivieren (Pro) |
| `accordion_lazy` | `no` | Tiefere Accordion-Ebenen per REST lazy laden (Pro) |
| `menu_id` | `0` | Ein WP-Menü als Navigationsquelle verwenden (Pro) |
| `ajax_content` | `no` | Seiteninhalt per AJAX beim Klick laden (Pro) |
| `content_selector` | `main` | CSS-Selektor für den Inhaltscontainer (Pro) |

Beispiel:
```
[drillnav depth="3" layout="horizontal" style_preset="compact" mobile_toggle="yes"]
```

### Widget

Unter **Design → Widgets** das Widget **DrillNav – Contextual Navigation** zu einer Sidebar hinzufügen.

---

## Layouts

### List (Free)
Das Standard-Layout. Elemente vertikal gestapelt, Drill-Down ersetzt die sichtbare Liste.

### Horizontal (Free)
Elemente in einer Zeile angeordnet. Eignet sich gut für Header und Sticky-Bars.

### Accordion (Pro)
Der vollständige Seitenbaum wird serverseitig gerendert, mit JS-gestütztem Ein-/Ausklappen. Bei aktiviertem Lazy-Loading (Pro) werden tiefere Ebenen erst beim Ausklappen per REST nachgeladen.

---

## Entwickler-Hooks

```php
// Filter für die kompletten Navigationsdaten vor dem Rendering
add_filter( 'drillnav_nav_items', function( $data, $args ) {
    return $data;
}, 10, 2 );

// Filter für den aufgelösten Seitenkontext
add_filter( 'drillnav_current_context', function( $context ) {
    return $context;
} );

// Filter für die Children-Liste eines bestimmten Elternelements
add_filter( 'drillnav_children_items', function( $items, $parent_id, $args ) {
    return $items;
}, 10, 3 );

// CSS-Klassen zum <li> eines Navigationselements hinzufügen
add_filter( 'drillnav_item_classes', function( $classes, $item, $layout ) {
    if ( $item['is_current'] ) {
        $classes[] = 'my-active-item';
    }
    return $classes;
}, 10, 3 );

// HTML-Attribute zum <a> eines Navigationselements hinzufügen
add_filter( 'drillnav_item_attrs', function( $attrs, $item, $layout ) {
    $attrs['data-post-id'] = $item['id'];
    return $attrs;
}, 10, 3 );

// Cache-TTL anpassen (Standard: 7 Tage)
add_filter( 'drillnav_cache_duration', function( $seconds ) {
    return WEEK_IN_SECONDS * 2;
} );
```

**REST API:** `GET /wp-json/drillnav/v1/children?post_id=123&post_type=page`

---

## CSS Custom Properties

Jeden Wert im Theme-Stylesheet überschreiben:

```css
.drillnav {
    --drillnav-font-size:         1rem;
    --drillnav-color-bg:          transparent;
    --drillnav-color-text:        inherit;
    --drillnav-color-link:        inherit;
    --drillnav-color-current-bg:  rgba(0, 0, 0, 0.06);
    --drillnav-color-btn-hover:   rgba(0, 0, 0, 0.08);
    --drillnav-color-border:      rgba(0, 0, 0, 0.1);
    --drillnav-color-arrow:       rgba(0, 0, 0, 0.4);
    --drillnav-item-padding-y:    0.5rem;
    --drillnav-item-padding-x:    0.75rem;
    --drillnav-border-radius:     4px;
    --drillnav-transition-speed:  200ms;
    --drillnav-max-width:         none;
}
```

Pro-Nutzer können alle neun Eigenschaften global unter **Einstellungen → DrillNav → Appearance & Styling** oder pro Block-Instanz im Inspector-Panel setzen.

---

## Mehrsprachigkeit

DrillNav funktioniert von Haus aus mit **WPML** und **Polylang** (seit v1.1.0):

- Der Navigationsbaum spiegelt immer die aktive Sprache wider
- Cache-Keys sind sprachbewusst (jede Sprache wird unabhängig gecacht)
- Eigene Labels (Home, Nav, Blog) registrieren sich bei der String-Translation-Oberfläche des Mehrsprachigkeits-Plugins
- Drei Filter-Hooks für eigene Integrationen: `drillnav_language`, `drillnav_translate_post_id`, `drillnav_translate_string`

---

## RTL-Unterstützung

Vollständige Rechts-nach-links-Layout-Unterstützung (seit v1.4.0):

- Durchgängig CSS Logical Properties (`padding-inline-start`, `inset-inline-start`)
- Slide-Animationen werden unter `[dir="rtl"]` gespiegelt
- Der mobile Side-Drawer öffnet in RTL von rechts
- Accordion-Einrückung nutzt `padding-inline-start`

Keine JavaScript-Änderungen nötig — RTL wird vollständig per CSS gehandhabt.

---

## Lizenz

**DrillNav** steht unter der [GNU General Public License v2.0 oder später](https://www.gnu.org/licenses/gpl-2.0.html).

**Autor:** Pulacha Labs — [@simurech](https://github.com/simurech)
