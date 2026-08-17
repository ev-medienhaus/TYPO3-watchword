[![TYPO3 compatibility](https://img.shields.io/badge/TYPO3-12.4-ff8700?maxAge=3600&logo=typo3)](https://get.typo3.org/)
[![TYPO3 compatibility](https://img.shields.io/badge/TYPO3-13.4-ff8700?maxAge=3600&logo=typo3)](https://get.typo3.org/)
[![TYPO3 compatibility](https://img.shields.io/badge/TYPO3-14.0-ff8700?maxAge=3600&logo=typo3)](https://get.typo3.org/)

# TYPO3 extension `watchword`

Import and display of the daily *Losungen* (watchwords).

**[English](#english)** · **[Deutsch](#deutsch)**

## Links

|                    | URL                                                    |
|--------------------|--------------------------------------------------------|
| **Repository:**    | https://github.com/ev-medienhaus/TYPO3-watchword       |
| **Documentation:** | https://github.com/ev-medienhaus/TYPO3-watchword       |
| **TER:**           | https://extensions.typo3.org/extension/watchword       |

---

<a id="english"></a>

## English · [Deutsch](#deutsch)

### Features

- Backend module under **Web → Losungen** to import, list, edit and delete watchwords
- Two-step Excel/CSV import (upload → preview → confirm) from the official [losungen.de](https://www.losungen.de/download) export
- Frontend content element **Losungen: Tageslosung** that shows the watchword for the current calendar day
- Day slider: previous/next navigation loads the adjacent day via Ajax (JSON page type)
- Optional CSS and JavaScript via TypoScript constants (for sites without the main frontend bundle)
- Duplicate-safe import: existing dates are never overwritten or deleted
- Compatible with TYPO3 12.4, 13.4 and 14

### Installation

Require this package via Composer:

```bash
composer req emh/watchword
```

Activate the extension in the Extension Manager (or via Composer autoload) and include the static TypoScript template **Watchword** in your site template.

### Configuration

Set the storage folder in the extension configuration:

**Admin Tools → Settings → Extension Configuration → watchword**

| Setting       | Description                                                                 |
|---------------|-----------------------------------------------------------------------------|
| `storagePid`  | Page ID (sysfolder) where imported watchword records are stored. Default: `0` |

Records are hidden from the regular page-module list (`hideTable`) and are managed exclusively through the backend module.

TypoScript constants (Template → Constant Editor, category **plugin.tx_watchword**):

| Constant | Default | Description |
|----------|---------|-------------|
| `plugin.tx_watchword.settings.ajaxTypeNum` | `1786976500` | Frontend page type (`?type=`) for the JSON Ajax endpoint |
| `plugin.tx_watchword.includeJavascript` | `0` | Load `EXT:watchword/Resources/Public/JavaScript/Watchword.js` |
| `plugin.tx_watchword.includeCss` | `0` | Load `EXT:watchword/Resources/Public/Css/Watchword.css` |

Set `includeJavascript` and `includeCss` to `1` if the site does not already ship the watchwords script and stylesheet (for example the WBK frontend bundle). Do not enable them together with the same assets from the sitepackage, otherwise click handlers and styles are applied twice.

---

### Backend module: import and list

The module lives under **Web → Losungen**. Backend users need module access (`user`).

#### Overview (index)

The overview lists all imported watchwords, ordered by date:

- **Year filter** – restrict the list to one year, or show all years
- **Pagination** – 50 records per page
- **Columns** – date, weekday, Sunday/holiday name, Losung (verse + text), Lehrtext (verse + text)
- **Edit** – opens the record in the TYPO3 record editor
- **Delete** – soft-deletes the record via DataHandler (with a confirmation dialog)

The **Excel/CSV importieren** button in the module header starts the import workflow.

#### Import workflow

Import is always a two-step process. Nothing is written to the database until the editor confirms the preview.

```
Upload file  →  Parse & validate  →  Preview  →  Confirm  →  Insert new rows
```

**Step 1 – Upload**

1. Open **Web → Losungen** and click **Excel/CSV importieren**.
2. Select the original Excel file from losungen.de, or a CSV file with the same columns.
3. Allowed extensions: `.xlsx`, `.xls`, `.csv`.
4. Click **Datei prüfen**.

The file is stored temporarily under `var/transient/watchword/` with a random token. It is never persisted as a FAL file.

**Step 2 – Preview**

The service parses the file and shows a summary:

| Counter            | Meaning                                                                 |
|--------------------|-------------------------------------------------------------------------|
| New records        | Valid rows whose date does not yet exist in the database                |
| Already present    | Duplicates in the file or dates that already exist – these are skipped  |
| Invalid rows       | Rows with missing/invalid date or empty required columns – skipped      |

Invalid rows can be inspected in an expandable table (row number + reason).

- If there are new records: **Datensätze jetzt importieren** writes them.
- If there are none: the import is aborted, no data is stored.

**Step 3 – Confirm**

On confirm, the file is **parsed and validated again** against the current database. The preview result from the browser is never trusted for the write. New rows are inserted in a single database transaction on the configured `storagePid`. The temporary file is deleted afterwards.

A flash message reports how many records were imported, how many duplicates were skipped, and how many invalid rows were skipped.

#### File format

The first row must be a header. Column **order does not matter**; columns are mapped by name.

| Column         | Required | Description                                      |
|----------------|----------|--------------------------------------------------|
| `Datum`        | yes      | Date (Excel date, `d.m.Y`, `Y-m-d` or `d.m.y`)   |
| `Wtag`         | yes      | Weekday                                          |
| `Sonntag`      | no       | Sunday / holiday name                            |
| `Losungsvers`  | yes      | Watchword verse reference                        |
| `Losungstext`  | yes      | Watchword text                                   |
| `Lehrtextvers` | yes      | Teaching-text verse reference                    |
| `Lehrtext`     | yes      | Teaching text                                    |

CSV files: delimiter (`,`, `;` or tab) and encoding are detected automatically.

Empty rows are ignored. Dates that appear twice in the same file count as duplicates; only the first occurrence is imported.

**Import never updates or deletes existing watchwords.** Re-importing the same year only inserts dates that are not yet in the table (unique key on `date`).

If the header is invalid, the import is aborted immediately and the temp file is removed.

---

### Frontend: list view (daily watchword)

The plugin **Losungen: Tageslosung** (`watchword_list`) is a content element. Add it via the New Content Element wizard.

#### Behaviour

- Looks up the watchword for **today** (calendar date in UTC, time stripped).
- Renders date, weekday, optional Sunday/holiday name, Losung and Lehrtext.
- Previous/next buttons load the adjacent calendar day via Ajax without a page reload.
- If no record exists for today, a fallback message is shown: *Für heute ist noch keine Losung hinterlegt.*

The action is registered as **non-cacheable** (`USER_INT`). The result changes every calendar day, independent of the page cache lifetime.

The plugin has no FlexForm settings. Storage page is not respected in the frontend query: all non-hidden, non-deleted records are considered.

#### Day slider (Ajax)

The navigation buttons `data-watchwords-previous` and `data-watchwords-next` request the previous or next day. The endpoint URL is written to `data-watchwords-endpoint` (current page + `type={ajaxTypeNum}`).

Example request:

```
/?type=1786976500&date=18.08.2026&direction=previous
```

| Parameter | Values | Description |
|-----------|--------|-------------|
| `type` | `ajaxTypeNum` | TYPO3 page type of the JSON endpoint |
| `date` | `d.m.Y` or `Y-m-d` | Currently displayed day |
| `direction` | `previous` or `next` | Adjacent calendar day. Omit to return the given date |

JSON response (used to replace date, quotes and verse references in the widget):

```json
{
  "date": "17.08.2026",
  "dateTime": "2026-08-17",
  "weekday": "Mo",
  "sundayName": "",
  "content": [
    { "quote": "Losungstext", "reference": "Losungsvers", "url": "https://example.org/page" },
    { "quote": "Lehrtext", "reference": "Lehrtextvers", "url": "https://example.org/page" }
  ]
}
```

If no record exists for that day, the endpoint returns HTTP 404. The script then shows *Losung konnte nicht geladen werden*.

`date` and `direction` are excluded from the TYPO3 `cHash` calculation so the GET request works without a cache hash.

#### CSS and JavaScript

Standalone assets (no dependency on the site frontend bundle):

| Asset | Path |
|-------|------|
| JavaScript | `EXT:watchword/Resources/Public/JavaScript/Watchword.js` |
| CSS | `EXT:watchword/Resources/Public/Css/Watchword.css` |

Enable them with TypoScript constants:

```typoscript
plugin.tx_watchword.includeJavascript = 1
plugin.tx_watchword.includeCss = 1
```

This registers:

```typoscript
page.includeJSFooter.txWatchword = EXT:watchword/Resources/Public/JavaScript/Watchword.js
page.includeCSS.txWatchword = EXT:watchword/Resources/Public/Css/Watchword.css
```

The CSS covers the full widget (navigation, quotes, share button, footer, loading state) including `.watchwords--default` (accent colour `#2481c5`). Add that modifier class on the root element if you want the default blue header without relying on `--color-80` from the site theme.

#### Template

Default template: `EXT:watchword/Resources/Private/Templates/Watchword/List.html`

Override via TypoScript:

```typoscript
plugin.tx_watchword {
    view {
        templateRootPaths.20 = EXT:mysitepackage/Resources/Private/Templates/Watchword/
    }
}
```

Markup uses BEM classes. Style them in the sitepackage, or load the extension CSS (see above):

- `.watchwords`
- `.watchwords--default`
- `.watchwords__navigation`
- `.watchwords__title`
- `.watchwords__body`
- `.watchwords__quote`
- `.watchwords__reference`
- `.watchwords__share`
- `.watchwords__footer`

---

<a id="deutsch"></a>

## Deutsch · [English](#english)

### Funktionen

- Backend-Modul unter **Web → Losungen** zum Importieren, Auflisten, Bearbeiten und Löschen
- Zweistufiger Excel-/CSV-Import (Upload → Vorschau → Bestätigung) aus dem offiziellen Export von [losungen.de](https://www.losungen.de/download)
- Frontend-Inhaltselement **Losungen: Tageslosung**, das die Losung des aktuellen Kalendertags anzeigt
- Tages-Slider: Vor/Zurück lädt den Nachbar-Tag per Ajax (JSON-Page-Type)
- Optionales CSS und JavaScript per TypoScript-Konstanten (für Sites ohne Frontend-Bundle)
- Duplikatsicherer Import: vorhandene Daten werden weder überschrieben noch gelöscht
- Kompatibel mit TYPO3 12.4, 13.4 und 14

### Installation

Paket per Composer einbinden:

```bash
composer req emh/watchword
```

Extension aktivieren und das statische TypoScript-Template **Watchword** im Seiten-Template einbinden.

### Konfiguration

Speicherordner in der Extension-Konfiguration setzen:

**Admin Tools → Settings → Extension Configuration → watchword**

| Einstellung   | Beschreibung                                                                          |
|---------------|---------------------------------------------------------------------------------------|
| `storagePid`  | Seiten-ID (Sysordner), in dem importierte Losungen gespeichert werden. Standard: `0` |

Die Datensätze sind in der normalen Listenansicht des Seitenmoduls ausgeblendet (`hideTable`) und werden ausschließlich über das Backend-Modul verwaltet.

TypoScript-Konstanten (Template → Constant Editor, Kategorie **plugin.tx_watchword**):

| Konstante | Standard | Beschreibung |
|-----------|----------|--------------|
| `plugin.tx_watchword.settings.ajaxTypeNum` | `1786976500` | Frontend-Page-Type (`?type=`) für den JSON-Ajax-Endpunkt |
| `plugin.tx_watchword.includeJavascript` | `0` | Lädt `EXT:watchword/Resources/Public/JavaScript/Watchword.js` |
| `plugin.tx_watchword.includeCss` | `0` | Lädt `EXT:watchword/Resources/Public/Css/Watchword.css` |

`includeJavascript` und `includeCss` auf `1` setzen, wenn die Site das Watchwords-Script und -Stylesheet nicht bereits mitbringt (z. B. das WBK-Frontend-Bundle). Nicht zusammen mit denselben Assets aus dem Sitepackage aktivieren, sonst werden Klicks und Styles doppelt angewendet.

---

### Backend-Modul: Import und Übersicht

Das Modul liegt unter **Web → Losungen**. Backend-Benutzer benötigen Modulzugriff (`user`).

#### Übersicht (Index)

Die Übersicht listet alle importierten Losungen, sortiert nach Datum:

- **Jahresfilter** – Liste auf ein Jahr einschränken oder alle Jahre anzeigen
- **Paginierung** – 50 Datensätze pro Seite
- **Spalten** – Datum, Wochentag, Sonntag/Feiertag, Losung (Vers + Text), Lehrtext (Vers + Text)
- **Bearbeiten** – öffnet den Datensatz im TYPO3-Record-Editor
- **Löschen** – Soft-Delete über den DataHandler (mit Bestätigungsdialog)

Der Button **Excel/CSV importieren** in der Modul-Kopfzeile startet den Import.

#### Import-Ablauf

Der Import erfolgt immer in zwei Schritten. In die Datenbank wird erst geschrieben, wenn die Vorschau bestätigt wird.

```
Datei hochladen  →  Parsen & Prüfen  →  Vorschau  →  Bestätigen  →  Neue Zeilen einfügen
```

**Schritt 1 – Upload**

1. **Web → Losungen** öffnen und **Excel/CSV importieren** klicken.
2. Die Original-Exceldatei von losungen.de oder eine CSV-Datei mit denselben Spalten wählen.
3. Erlaubte Endungen: `.xlsx`, `.xls`, `.csv`.
4. **Datei prüfen** klicken.

Die Datei wird temporär unter `var/transient/watchword/` mit einem Zufallstoken abgelegt. Sie wird nicht als FAL-Datei gespeichert.

**Schritt 2 – Vorschau**

Der Service parst die Datei und zeigt eine Zusammenfassung:

| Zähler             | Bedeutung                                                                        |
|--------------------|----------------------------------------------------------------------------------|
| Neue Datensätze    | Gültige Zeilen, deren Datum noch nicht in der Datenbank existiert                |
| Bereits vorhanden  | Duplikate in der Datei oder bereits vorhandene Daten – werden übersprungen       |
| Ungültige Zeilen   | Zeilen mit fehlendem/ungültigem Datum oder leeren Pflichtfeldern – übersprungen  |

Ungültige Zeilen können in einer aufklappbaren Tabelle eingesehen werden (Zeilennummer + Grund).

- Gibt es neue Datensätze: **Datensätze jetzt importieren** schreibt sie.
- Gibt es keine: der Import wird abgebrochen, es werden keine Daten gespeichert.

**Schritt 3 – Bestätigung**

Beim Bestätigen wird die Datei **erneut gegen den aktuellen Datenbankstand** geparst und geprüft. Das Vorschau-Ergebnis aus dem Browser wird für den Schreibvorgang nicht vertraut. Neue Zeilen werden in einer Transaktion auf der konfigurierten `storagePid` eingefügt. Die temporäre Datei wird anschließend gelöscht.

Eine Flash-Meldung zeigt, wie viele Datensätze importiert, wie viele Duplikate übersprungen und wie viele ungültige Zeilen übersprungen wurden.

#### Dateiformat

Die erste Zeile muss eine Kopfzeile sein. Die **Spaltenreihenfolge ist egal**; die Zuordnung erfolgt über den Spaltennamen.

| Spalte         | Pflicht | Beschreibung                                      |
|----------------|---------|---------------------------------------------------|
| `Datum`        | ja      | Datum (Excel-Datum, `d.m.Y`, `Y-m-d` oder `d.m.y`) |
| `Wtag`         | ja      | Wochentag                                         |
| `Sonntag`      | nein    | Name des Sonntags / Feiertags                     |
| `Losungsvers`  | ja      | Bibelstelle der Losung                            |
| `Losungstext`  | ja      | Text der Losung                                   |
| `Lehrtextvers` | ja      | Bibelstelle des Lehrtexts                         |
| `Lehrtext`     | ja      | Text des Lehrtexts                                |

CSV: Trennzeichen (`,`, `;` oder Tab) und Zeichensatz werden automatisch erkannt.

Leere Zeilen werden ignoriert. Kommt ein Datum in derselben Datei zweimal vor, zählt es als Duplikat; nur das erste Vorkommen wird importiert.

**Der Import ändert oder löscht niemals vorhandene Losungen.** Ein erneuter Import desselben Jahres fügt nur Daten ein, die noch nicht in der Tabelle stehen (Unique-Key auf `date`).

Stimmt die Kopfzeile nicht, wird der Import sofort abgebrochen und die Temp-Datei entfernt.

---

### Frontend: List-View (Tageslosung)

Das Plugin **Losungen: Tageslosung** (`watchword_list`) ist ein Inhaltselement. Es wird über den Assistenten „Neues Inhaltselement“ eingefügt.

#### Verhalten

- Sucht die Losung für **heute** (Kalenderdatum in UTC, ohne Uhrzeit).
- Zeigt Datum, Wochentag, optionalen Sonntags-/Feiertagsnamen, Losung und Lehrtext.
- Die Buttons Vor/Zurück laden den benachbarten Kalendertag per Ajax, ohne Seitenreload.
- Fehlt ein Datensatz für heute, erscheint der Hinweis: *Für heute ist noch keine Losung hinterlegt.*

Die Action ist **nicht cachebar** (`USER_INT`). Das Ergebnis wechselt mit dem Kalendertag, unabhängig von der Cache-Lebensdauer der Seite.

Das Plugin hat keine FlexForm-Einstellungen. Die Storage-Seite wird in der Frontend-Abfrage nicht berücksichtigt: alle nicht versteckten, nicht gelöschten Datensätze werden einbezogen.

#### Tages-Slider (Ajax)

Die Navigationsbuttons `data-watchwords-previous` und `data-watchwords-next` fordern den vorherigen bzw. nächsten Tag an. Die Endpunkt-URL steht in `data-watchwords-endpoint` (aktuelle Seite + `type={ajaxTypeNum}`).

Beispiel-Request:

```
/?type=1786976500&date=18.08.2026&direction=previous
```

| Parameter | Werte | Bedeutung |
|-----------|-------|-----------|
| `type` | `ajaxTypeNum` | TYPO3-Page-Type des JSON-Endpunkts |
| `date` | `d.m.Y` oder `Y-m-d` | aktuell angezeigter Tag |
| `direction` | `previous` oder `next` | Nachbar-Kalendertag. Weglassen, um genau dieses Datum zu liefern |

JSON-Antwort (ersetzt Datum, Zitate und Bibelstellen im Widget):

```json
{
  "date": "17.08.2026",
  "dateTime": "2026-08-17",
  "weekday": "Mo",
  "sundayName": "",
  "content": [
    { "quote": "Losungstext", "reference": "Losungsvers", "url": "https://example.org/page" },
    { "quote": "Lehrtext", "reference": "Lehrtextvers", "url": "https://example.org/page" }
  ]
}
```

Fehlt ein Datensatz für den Tag, antwortet der Endpunkt mit HTTP 404. Das Script zeigt dann *Losung konnte nicht geladen werden*.

`date` und `direction` sind von der TYPO3-`cHash`-Berechnung ausgenommen, damit der GET-Request ohne Cache-Hash funktioniert.

#### CSS und JavaScript

Eigenständige Assets (keine Abhängigkeit vom Site-Frontend-Bundle):

| Asset | Pfad |
|-------|------|
| JavaScript | `EXT:watchword/Resources/Public/JavaScript/Watchword.js` |
| CSS | `EXT:watchword/Resources/Public/Css/Watchword.css` |

Aktivieren per TypoScript-Konstanten:

```typoscript
plugin.tx_watchword.includeJavascript = 1
plugin.tx_watchword.includeCss = 1
```

Das registriert:

```typoscript
page.includeJSFooter.txWatchword = EXT:watchword/Resources/Public/JavaScript/Watchword.js
page.includeCSS.txWatchword = EXT:watchword/Resources/Public/Css/Watchword.css
```

Das CSS deckt das komplette Widget ab (Navigation, Zitate, Teilen-Button, Footer, Ladezustand), inklusive `.watchwords--default` (Akzentfarbe `#2481c5`). Die Modifier-Klasse am Wurzelelement setzen, wenn der blaue Standard-Header ohne `--color-80` aus dem Site-Theme gelten soll.

#### Template

Standard-Template: `EXT:watchword/Resources/Private/Templates/Watchword/List.html`

Überschreiben per TypoScript:

```typoscript
plugin.tx_watchword {
    view {
        templateRootPaths.20 = EXT:mysitepackage/Resources/Private/Templates/Watchword/
    }
}
```

Das Markup verwendet BEM-Klassen. Styling im Sitepackage oder über das Extension-CSS (siehe oben):

- `.watchwords`
- `.watchwords--default`
- `.watchwords__navigation`
- `.watchwords__title`
- `.watchwords__body`
- `.watchwords__quote`
- `.watchwords__reference`
- `.watchwords__share`
- `.watchwords__footer`
