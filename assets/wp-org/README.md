# WordPress.org Plugin Assets

Diese Dateien gehören **nicht** ins Plugin-ZIP (via `.distignore` ausgeschlossen), sondern werden
nach der Freigabe auf WordPress.org in den SVN-Ordner `/assets/` hochgeladen (flach, ohne Unterordner):

```
svn/
├── assets/          ← alle PNG-Dateien aus diesem Ordner
└── trunk/           ← Plugin-Code (Free-Version aus Freemius)
```

| Datei | Grösse | Zweck |
|---|---|---|
| `icon-128x128.png`, `icon-256x256.png` | 128², 256² | Plugin-Icon (Quelle: `icon.svg`) |
| `banner-772x250.png`, `banner-1544x500.png` | 772×250, 1544×500 | Header-Banner (Quelle: `banner.svg`) |
| `screenshot-1.png` … `screenshot-6.png` | diverse | Screenshots – Beschriftung in `readme.txt` unter `== Screenshots ==` (Nummern müssen übereinstimmen) |

## Neu erzeugen

Icon/Banner aus den SVG-Quellen (Markenfarben Lime `#e3f97a` + Anthrazit `#1d2327`):

```bash
rsvg-convert -w 256 -h 256 icon.svg -o icon-256x256.png
rsvg-convert -w 128 -h 128 icon.svg -o icon-128x128.png
rsvg-convert -w 1544 -h 500 banner.svg -o banner-1544x500.png
rsvg-convert -w 772 -h 250 banner.svg -o banner-772x250.png
```

Screenshots: echte Plugin-Ausgabe (per WP-CLI auf einer Demo-Seitenstruktur „Northwind Consulting"
gerendert) in einer neutralen Demo-Seite mit `frontend.css`/`frontend.js`, aufgenommen mit
headless Chrome (`--window-size=1280,800`, Drawer 390×800), anschliessend mit `magick -crop` zugeschnitten.

## Upload (nach WP.org-Freigabe)

```bash
svn co https://plugins.svn.wordpress.org/drillnav-drilldown-navigation svn-drillnav
cp assets/wp-org/*.png svn-drillnav/assets/
cd svn-drillnav && svn add --force assets && svn ci -m "Plugin assets"
```
