# WordPress.org Plugin Assets

Diese Grafiken werden beim Einreichen auf WordPress.org **direkt in den SVN `/assets/`-Ordner** hochgeladen
(nicht als Teil des Plugin-ZIPs). Beim SVN-Checkout liegen sie parallel zum `trunk/`-Ordner.

```
svn/
├── assets/          ← Inhalt dieses Ordners kommt hierhin
│   ├── icon-128x128.png
│   ├── icon-256x256.png
│   ├── banner-772x250.png
│   ├── banner-1544x500.png
│   ├── screenshot-1.png
│   └── ...
└── trunk/           ← Plugin-Quellcode
    └── ...
```

WP-CLI-Befehl zum Synchronisieren:
```bash
svn add assets/* --force
svn commit -m "Update plugin assets"
```

---

## Spezifikationen

### Icons (Pflicht)

| Datei | Grösse | Format | Verwendung |
|-------|--------|--------|-----------|
| `icons/icon-128x128.png` | 128 × 128 px | PNG (transparent OK) | Plugin-Liste in WP-Admin |
| `icons/icon-256x256.png` | 256 × 256 px | PNG (transparent OK) | Retina / Plugin-Detailseite |

**Empfehlung:** Einfaches, klares Icon. Das „D" mit einem Pfeil nach unten oder ein stilisiertes
Drill-down-Pfad-Icon. Kein Text im Icon (wird zu klein). Hintergrundfarbe optional.

### Banners (Pflicht)

| Datei | Grösse | Format | Verwendung |
|-------|--------|--------|-----------|
| `banners/banner-772x250.png` | 772 × 250 px | PNG / JPG | Standard-Auflösung |
| `banners/banner-1544x500.png` | 1544 × 500 px | PNG / JPG | Retina / HiDPI |

**Inhalt:** Plugin-Name, kurze Tagline, visuelles Element (z.B. vereinfachte Darstellung einer
Drill-down-Navigation). Farben sollten zum Frontend-CSS passen.

### Screenshots (empfohlen, min. 2)

| Datei | Grösse | Beschreibung |
|-------|--------|-------------|
| `screenshots/screenshot-1.png` | 1280 × 800 px | Block im Gutenberg-Editor mit Inspector |
| `screenshots/screenshot-2.png` | 1280 × 800 px | Frontend: Navigation auf einer tief verschachtelten Seite |
| `screenshots/screenshot-3.png` | 1280 × 800 px | Drill-down-Animation (zweite Ebene geöffnet) |
| `screenshots/screenshot-4.png` | 1280 × 800 px | Admin-Einstellungsseite |
| `screenshots/screenshot-5.png` | 1280 × 800 px | (Optional) WooCommerce Pro: Kategorie-Navigation |

**Wichtig:** Die Nummerierung in `readme.txt` muss mit der Datei-Nummerierung übereinstimmen.
Dateiname genau `screenshot-1.png`, `screenshot-2.png` usw. (kein Unterordner beim Upload).

---

## Checkliste vor der Einreichung

- [ ] Icon 128×128 erstellt
- [ ] Icon 256×256 erstellt
- [ ] Banner 772×250 erstellt
- [ ] Banner 1544×500 erstellt
- [ ] Mindestens 2 Screenshots erstellt
- [ ] `readme.txt` Screenshot-Beschreibungen aktualisiert
- [ ] WP.org Plugin-Seite: Grafiken in SVN `/assets/` hochgeladen
