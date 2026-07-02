# MenuBar Tasks

Ein macOS Menüleisten-Aufgabenmanager mit Liquid-Glass-Interface, lokalem PHP-Backend und ohne Cloud-Abhängigkeit.

![macOS 13+](https://img.shields.io/badge/macOS-13%2B-black?style=flat-square)
![Swift](https://img.shields.io/badge/Swift-5.9-F05138?style=flat-square&logo=swift)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php)
![SQLite](https://img.shields.io/badge/SQLite-lokal-003B57?style=flat-square&logo=sqlite)

## Funktionen

- **Liquid Glass UI** — NSVisualEffectView + transparente WKWebView, passt sich Hell/Dunkel automatisch an
- **Globaler Hotkey** `⌥T` — überall öffnen/schließen, keine Barrierefreiheits-Berechtigung erforderlich
- **Eigene Listen** — Listen erstellen, umbenennen und löschen; Aufgaben Listen zuweisen
- **Unteraufgaben** — verschachtelte Checkliste pro Aufgabe, als JSON gespeichert
- **Notizen** — aufklappbares Textfeld pro Aufgabe
- **Natürliche Sprache** — „Meeting morgen" oder „Bericht nächsten Freitag" setzen das Datum automatisch
- **Vollbild-Übersicht** `⌘L` — alle offenen Aufgaben im Vollbild, gruppiert nach Überfällig / Heute / Demnächst / Ohne Datum
- **Automatische Übersicht** — erscheint beim Öffnen des Rechners (Start, Aufwachen, Entsperren) und zu festen Zeiten (07:55, 10:08, 11:38, 12:48, 14:48, danach alle 2 Stunden bis Mitternacht), damit keine Aufgabe vergessen wird
- **Bearbeiten-Modus** — `Esc` schaltet die Vollbild-Übersicht in einen editierbaren Modus: abhaken und Titel inline ändern
- **Erinnerungen** — fällige Aufgaben lösen eine Benachrichtigung mit angenehmem Ton (Glass) aus
- **Suche** — Echtzeitfilter über Titel und Notizen
- **Rückgängig-Toast** — 5 Sekunden Zeit zum Wiederherstellen gelöschter Aufgaben
- **Abzeichen** — Anzahl offener Aufgaben in der Menüleiste, rot wenn überfällige vorhanden
- **Ton & Haptik** — natives macOS-Feedback beim Erledigen
- **Als Markdown kopieren** — alle Aufgaben ins Clipboard exportieren
- **Aufräumen** — erledigte Aufgaben mit einem Tap archivieren
- **Serie & Statistiken** — Tages-Serie und Wochenanzahl im Header
- **Drag & Drop** — Aufgaben per Drag neu anordnen
- **Prioritäten** — Niedrig / Mittel / Hoch mit Farbmarkierung
- **Fälligkeitsdaten** — Datumsauswahl mit relativen Labels (Heute, Morgen, In 3d…)
- **Autostart** — optionaler Start beim Einloggen via Rechtsklick-Menü
- **Kein Xcode nötig** — baut direkt mit `swiftc`

## Technologie-Stack

| Schicht   | Technologie |
|-----------|-------------|
| App-Host  | Swift + AppKit (NSStatusItem, NSPopover, NSPanel) |
| UI        | WKWebView → PHP-gelieferte HTML/CSS/JS |
| Backend   | PHP 8 Built-in-Server auf Port 8742 |
| Datenbank | SQLite via PDO unter `~/Library/Application Support/MenuBarTasks/tasks.db` |
| Hotkey    | Carbon HIToolbox (keine Barrierefreiheits-Berechtigung) |

## Voraussetzungen

- macOS 13 Ventura oder neuer
- Xcode Command Line Tools (`xcode-select --install`)
- PHP 8.1+ (`brew install php`)

## Build & Start

```bash
# Bauen
make build

# Starten (baut neu + startet)
make run

# Stoppen
make stop

# Build-Artefakte löschen
make clean
```

`make run` beendet automatisch jede vorherige Instanz.

## Architektur

```
MenuBarTasks.app/
├── MacOS/
│   └── MenuBarTasks          # Swift-Binary
└── Resources/
    ├── Info.plist
    ├── router.php            # PHP-Router (Einstiegspunkt für Built-in-Server)
    └── www/
        ├── index.php         # App-HTML-Shell
        ├── detail.php        # Vollbild-Aufgaben-Editor (NSPanel)
        ├── overview.php      # Vollbild-Übersicht (⌘L, Autostart, feste Zeiten) + Bearbeiten-Modus
        ├── api/
        │   ├── tasks.php     # REST-API + SQLite
        │   └── lists.php     # Listen-CRUD
        ├── css/style.css     # Liquid Glass Design-Tokens + Komponenten
        └── js/app.js         # Frontend-Logik (kein Framework)
```

### Swift → PHP Brücke

`PHPServerManager` startet beim Launch den PHP-Built-in-Server auf `127.0.0.1:8742`. Die WKWebView lädt `http://127.0.0.1:8742/`. Alle Aufgaben-Operationen laufen über `fetch()`-Aufrufe an die lokale API.

### JS → Swift Brücke

`window.webkit.messageHandlers.bridge.postMessage(msg)` für native Aktionen:

| `msg.type`   | Effekt |
|--------------|--------|
| `badge`      | Menüleisten-Zähler und Farbe aktualisieren (rot bei überfälligen) |
| `haptic`     | NSHapticFeedbackManager auslösen |
| `sound`      | NSSound nach Name (Pop, Funk, …) |
| `copy`       | Text in NSPasteboard kopieren |
| `openDetail` | NSPanel mit Vollbild-Editor für `taskId` / `listId` öffnen |
| `closeOverview` | Vollbild-Übersicht schließen (Klick auf ✕) |

### Datenpersistenz

SQLite-Datenbank unter `~/Library/Application Support/MenuBarTasks/tasks.db` — überlebt Neuentwicklungen und App-Updates. Schema-Migrationen via `ALTER TABLE ADD COLUMN` in try/catch.

## API-Endpunkte

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| GET | `/api/tasks` | Alle Aufgaben auflisten |
| GET | `/api/tasks?list_id=X` | Aufgaben einer Liste |
| POST | `/api/tasks` | Aufgabe erstellen |
| PUT | `/api/tasks/:id` | Aufgabe aktualisieren |
| DELETE | `/api/tasks/:id` | Aufgabe löschen |
| POST | `/api/tasks/reorder` | Reihenfolge mit `{ids:[…]}` ändern |
| DELETE | `/api/tasks/done` | Alle erledigten löschen |
| GET | `/api/stats` | Serie + Woche + Gesamt |
| GET | `/api/lists` | Alle Listen |
| POST | `/api/lists` | Liste erstellen |
| PUT | `/api/lists/:id` | Liste umbenennen/Farbe ändern |
| DELETE | `/api/lists/:id` | Liste löschen (Aufgaben bleiben) |
| GET | `/detail?task_id=X` | Vollbild-Editor für eine Aufgabe |
| GET | `/detail?list_id=X` | Vollbild-Übersicht einer Liste |
| GET | `/overview` | Vollbild-Übersicht aller offenen Aufgaben (Bearbeiten-Modus via Esc) |

## Natürliche Sprache — Datumserkennung

Das Datum-Schlüsselwort wird aus dem Titel entfernt und als Fälligkeitsdatum gesetzt.

| Eingabe | Ergebnis |
|---------|----------|
| `Meeting heute` | Heute |
| `Bericht morgen` | Morgen |
| `Abgabe übermorgen` | Übermorgen |
| `Präsentation freitag` | Nächsten Freitag |
| `Review in 3 Tagen` | In 3 Tagen |
| `nächsten Montag` | Nächsten Montag |

Englische und spanische Schlüsselwörter werden ebenfalls erkannt.

## Tastenkürzel

| Kürzel | Aktion |
|--------|--------|
| `⌥T` | Menüleisten-Popover öffnen/schließen (global) |
| `⌘L` | Vollbild-Übersicht aller Aufgaben öffnen (global) |
| `Esc` (in Übersicht) | Bearbeiten-Modus umschalten (abhaken + Titel inline) |
| `Enter` | Aufgabe hinzufügen / Bearbeitung speichern |
| `Escape` | Inline-Bearbeitung abbrechen |
| `Doppelklick` Titel | Inline bearbeiten |
| `Rechtsklick` Aufgabe | Kontextmenü (Vollbild, Titel bearbeiten, Liste zuweisen, Löschen) |

## Lizenz

MIT
