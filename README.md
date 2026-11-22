# IPSTileHomeBuilder

Eigenständiges IP-Symcon-Modul zum Erstellen einer Multi-Raumübersicht und einzelner Raumstatusseiten mit dem Community-Modul Room Tiles.

## Voraussetzungen

- IP-Symcon 8.2 oder neuer
- Room Tiles installiert (Multi Room Tile und Room Tile)
- Die Zielstruktur `Visualization (42569) → Tile Home (52221) → Haus`

## Installation

1. Dieses Repository in IP-Symcon unter **Kerninstanzen → Modules** als Git-Repository eintragen.
2. Eine `TileHomeBuilder`-Instanz direkt unter `Tile Home (52221)` anlegen.
3. Als Raumwurzel eine Kategorie wählen, deren direkte Unterkategorien deine Räume sind.
4. **Raumvisualisierung erstellen / aktualisieren** ausführen.

Das Modul liest Variablen unter den ausgewählten Raumkategorien und zeigt sie nur lesend an. Es erstellt oder aktualisiert ausschließlich die Übersicht, die generierten Raumdetailseiten und die Generatorinstanz innerhalb von `Tile Home (52221)`. Es verändert keine Geräte- oder Hauskategorien außerhalb dieses Zielbereichs.

## Erzeugte Struktur

```text
Visualization (42569)
└── Tile Home (52221)
    ├── TileHomeBuilder
    ├── Raumstatus (Multi Room Tile)
    └── Haus
        └── Raumdetails
            └── <Raumname>
                └── Status (Room Tile)
```

