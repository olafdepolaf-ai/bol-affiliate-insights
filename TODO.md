# TODO — TB Money Manager

> Prioriteitsvolgorde: als onduidelijk, eerst vragen aan Olaf voor we beginnen.
> Afgeronde taken blijven staan met ✅ en datum.

## 🔴 Hoogste prioriteit

### 1. Vonklinks ombouwen
Meest urgent. De huidige vonklinks moeten worden omgebouwd (structuur/format onbekend — uitzoeken wat precies nodig is).

---

## Bol.com tab — bugs & verbeteringen

### 2. Grafiek-bugs fixen
- Standaard view bij Dashboard moet **per week** zijn, niet per maand
- **Revenue** in de metric-dropdown geeft een lege grafiek
- Andere metrics lijken dezelfde data te tonen als Kliks — onderzoeken of de aggregatie-logica klopt

### 3. Analyse tab uitbreiden met echte insights
De huidige analyse-tab is een begin, maar we willen diepere inzichten:
- **Trendbreuk-detectie**: bijv. grote daling in kliks since april 2025 — wat is de oorzaak?
- **Links die vroeger goed scoorden maar nu niet meer** (vergelijk periodes)
- **Veel kliks maar 0 sales** — al aanwezig, maar uitbreiden
- **Overzicht over het laatste jaar** met gemarkeerde anomalieën
- Aparte "Insights" subtab met automatische conclusies/signalen

---

## Nieuwe tabs / integraties

### 4. Google Analytics koppeling
Nieuwe tab in de plugin:
- OAuth2 connectie met Google Analytics (GA4)
- Klik-tracking op affiliate links per dag, gesplitst per bron
- Overzicht: welke GA4-pagina's genereren de meeste affiliate klik-traffic

### 5. Pagina's zonder (genoeg) affiliate links — GA4 + Bol combinatie
Overzichtspagina die GA4 traffic koppelt aan aanwezige affiliate links:
- Tabel: meest bezochte pagina's (sorted by views, hoog naar laag)
- Filters: vandaag / gisteren / laatste 7 dagen / laatste maand / laatste kwartaal / laatste jaar
- Kolommen:
  - Pagina
  - Pageviews (in periode)
  - Aantal affiliate links in artikel
  - Totaal kliks op die links (in periode)
  - Totale commissie/revenue van die links (in periode)
- Doel: snel zien waar kansen liggen om links toe te voegen

### 6. Awin toevoegen als affiliate netwerk
Zelfde aanpak als TradeTracker — eigen tab met subtabs voor rapportage.

### 9. Amazon affiliate integratie
Lid van twee aparte Amazon-programma's: **Amazon.nl** en **Amazon.de** — aparte accounts, aparte credentials.

**Wat we weten (uitgezocht 2026-05-20):**
- PA-API 5.0 is **retired per 15 mei 2026** — niet meer bruikbaar
- Opvolger is de **Creators API** (OAuth 2.0) — maar die geeft alleen productdata, geen earnings/commissie/kliks
- Er bestaat **geen officiële Amazon API voor rapportage** (commissie, kliks, orders) voor gewone Associates — alleen via het dashboard handmatig
- Beide landen gebruiken dezelfde API-endpoints; marketplace wordt via parameter meegegeven (`www.amazon.nl` / `www.amazon.de`)
- Aparte Access Key + Secret Key nodig per account (nl én de)

**Conclusie:** een volwaardige rapportage-tab zoals bij Bol.com is niet mogelijk via een API. Opties:
- a) Handmatige CSV-import vanuit het Associates dashboard
- b) Scraping van het dashboard (fragiel)
- c) Alleen de **Creators API** gebruiken voor productzoekopdrachten/linkgeneratie, niet voor rapportage

**Volgende stap:** beslissen of we de moeite doen (CSV-import?) of Amazon overslaan voor rapportage en alleen linktool bouwen. Vraag aan Olaf.

### 7. "Over mij" tab met Buy Me a Coffee link
Simpele statische tab in de plugin met info over de maker en een Buy Me a Coffee knop/link.

---

## UX

### 8. Volgorde tabs aanpassen
Huidige volgorde: Link Scanner → TradeTracker → ThirstyAffiliates → Bol.com

Voorstel: **Bol.com eerst** (of aanpasbaar maken), want dat is de primaire use case.

---

---

## ✅ Gedaan

### ✅ 2026-05-20 — alc_ migratiecode verwijderd uit Installer.php (v0.2.19)
`migrate_from_alc()` en de aanroep in `activate()` verwijderd na meerdere releases.

### ✅ 2026-05-20 — Plugin hernoemd naar TB Money Manager, gereorganiseerd
Plugin hernoemd van "Affiliate Link Checker" naar "TB Money Manager". Directory verplaatst van `tools/affiliate-link-checker/` naar `tb-money-manager/` in de root. Namespace gewijzigd naar `TuinenBalkon\TBMoneyManager\`, constanten naar `TBMM_`, DB-sleutels naar `tbmm_`. Migratiecode toegevoegd die oude `alc_` data (tabel + opties + transients) opruimt bij activatie. Oude Bol Affiliate Insights code gearchiveerd in `archief/`.

### ✅ 2026-05-20 — Bol.com functionaliteit geïntegreerd in Affiliate Link Checker (v0.2.13)
Alle functionaliteit van de losse Bol Affiliate Insights plugin (Dashboard, Orders,
Commission & Revenue, Promotion Methods, Analyse, Affiliate Links, Instellingen) is
geport als nieuwe **Bol.com tab** in de Affiliate Link Checker plugin. Subtab-navigatie
in dezelfde stijl als TradeTracker. WP option keys ongewijzigd zodat bestaande
credentials gewoon blijven werken. Release: `alc-v0.2.13`.
