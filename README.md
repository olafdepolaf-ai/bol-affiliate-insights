# Bol Affiliate Insights

A WordPress plugin that connects your Bol.com Partner Program account to your WordPress admin dashboard. Fetches and visualises commission, click, revenue, and saldo data via the Bol.com Affiliate Reports API v2.

Built for [tuinenbalkon.nl](https://www.tuinenbalkon.nl).

---

## Features

**Dashboard**
- Key metrics at a glance: orders, clicks, revenue, commission, conversion rate
- Approved and pending saldo (balance) from your Bol.com account
- Interactive performance chart with configurable metric, period, granularity, and site filter

**Reports**
- Orders report with date range selector
- Commission & Revenue report
- Promotion Methods report — links Bol.com promotion data back to your affiliate links in ThirstyAffiliates

**Analysis**
- Top earning links for a given period
- High-click / zero-order links (optimisation opportunities)
- Scale candidates: high EPC, low volume
- High volume / low EPC links

**Affiliate Links**
- Overview of all Bol.com destination URLs stored in ThirstyAffiliates
- Direct edit links into the ThirstyAffiliates admin

**Settings**
- API credentials (Client ID / Client Secret)
- Per-site data filter
- Cache management (1-hour TTL, manual flush)
- Debug logging toggle

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- [ThirstyAffiliates](https://thirstyaffiliates.com/) plugin (for affiliate link integration)
- A [Bol.com Partner Program](https://partner.bol.com/) account with API credentials

---

## Installation

1. Run `bash build.sh` to produce `dist/bol-affiliate-insights.zip`
2. In WordPress admin go to **Plugins → Add New → Upload Plugin** and upload the zip
3. Activate the plugin
4. Go to **Bol Insights → Settings** and enter your Bol.com Client ID and Client Secret
5. Click **Test Connection** to verify the API connection

### Getting your Bol.com API credentials

1. Log in to your [Bol.com Partner Program account](https://partner.bol.com/)
2. Go to **Account → Open API**
3. Click **Toevoegen** to create a new set of credentials
4. Copy your Client ID and click **Toon secret** to reveal your Client Secret

Keep your Client Secret confidential — it is stored in the WordPress database and never exposed in the source code.

---

## Development

```
bol-affiliate-insights.php   # Plugin entry point & autoloader
src/
  Plugin.php                 # Singleton, wires all services
  Admin/
    MenuService.php
    SettingsPage.php          # All tab rendering
    AjaxHandlerService.php    # AJAX endpoints (chart, test connection, cache flush)
  Service/
    ApiAuthService.php        # OAuth2 token handling
    ApiClient.php             # Bol.com API calls
    ReportDataService.php     # Analysis logic & saldo metrics
    SettingsService.php       # WordPress settings registration
    Logger.php                # Debug logging
  AffiliateLink/
    AffiliateLinkAdapterInterface.php
    ThirstyAffiliatesAdapter.php
    NullAdapter.php           # Fallback when no affiliate plugin is active
  Table/
    OrdersListTable.php
    CommissionRevenueListTable.php
    PromotionMethodsListTable.php
assets/
  css/admin-styles.css
  js/admin-settings.js
build.sh                      # Produces dist/bol-affiliate-insights.zip
```

**Building a release:**

```bash
bash build.sh
# Output: dist/bol-affiliate-insights.zip
```

---

## License

GPLv2 or later — see [LICENSE](LICENSE).
