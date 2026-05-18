# tools/

Éénmalige migratiescripts en hulpprogramma's voor tuinenbalkon.nl.

---

## bol-search-url-migrator

### Waarom

Bol.com heeft de URL-structuur van zoekresultatenpagina's gewijzigd:

| | Formaat |
|---|---|
| **Oud (vervalt 30 juni 2026)** | `https://www.bol.com/nl/nl/s/<zoekterm>/` |
| **Nieuw** | `https://www.bol.com/nl/nl/s/?searchtext=<zoekterm>` |

Tot 30 juni 2026 stuurt Bol.com het oude formaat automatisch door, inclusief affiliate-tracking. Na die datum werken de oude links niet meer en gaat commissie verloren.

De affiliate links op tuinenbalkon.nl gaan via `partner.bol.com/click/click?...&url=<encoded-bol-url>` (ThirstyAffiliates). De `url`-parameter bevat de Bol.com zoekpagina URL in gecodeerde vorm. Die moet worden bijgewerkt.

### Wat doet de plugin

`bol-search-url-migrator` is een tijdelijke WordPress-plugin die:

1. Alle ThirstyAffiliates affiliate links (`thirstylink` post type) scant op het oude zoek-URL-patroon
2. Een veilig migratieproces doorloopt met zelftest, handmatige preview, backup en restore
3. De `_ta_destination_url` post meta bijwerkt via `update_post_meta()` — geen directe SQL

### Bestanden

```
bol-search-url-migrator/
  bol-search-url-migrator.php   # Plugin broncode (upload als zip via WP admin)
```

> De `.zip` wordt niet gecommit (staat in `.gitignore`). Bouw lokaal:
> ```bash
> cd tools
> zip -r bol-search-url-migrator.zip bol-search-url-migrator/
> ```

### Stappenplan

| Stap | Actie | Doel |
|------|-------|------|
| 1 | Zelftest | 19 tests: URL-transformatielogica, backup round-trip, post_meta write+restore |
| 2 | Klikbare preview | Handmatige controle van elke link (oud vs. nieuw) via affiliate-URL |
| 3 | Dry-run | Exacte `_ta_destination_url` waarden tonen die worden geschreven |
| 4 | Backup + update | Backup opslaan in `wp_options`, direct verifiëren, daarna schrijven |
| 5 | Verify backup | Backup-waarden vergelijken met huidige database |
| 6 | Cleanup | Backup verwijderen na succesvolle migratie |
| — | Restore | Bij problemen: alles terugzetten naar backup (idempotent) |

### Installeren

1. Bouw de zip: `cd tools && zip -r bol-search-url-migrator.zip bol-search-url-migrator/`
2. WP admin → Plugins → Plugin uploaden → `bol-search-url-migrator.zip`
3. Activeren → Extra → Bol URL Migratie

### Na afronding

Deactiveer en verwijder de plugin via WP admin → Plugins.  
De backup-optie (`bol_search_url_migration_backup`) wordt automatisch verwijderd door de cleanup-stap.

### Technische context

- Links worden opgeslagen als WordPress post type `thirstylink` (ThirstyAffiliates)
- Destination URL zit in post meta `_ta_destination_url`
- Het oude zoek-URL-patroon in de encoded partner-URL: `%2Fnl%2Fnl%2Fs%2F<term>%2F`
- Vervangen door: `%2Fnl%2Fnl%2Fs%2F%3Fsearchtext%3D<term>`
- Backup opgeslagen als WordPress optie: `bol_search_url_migration_backup`
