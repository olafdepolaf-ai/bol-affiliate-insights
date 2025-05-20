=== Bol Affiliate Insights ===
Contributors: Olaf Lemmers
Tags: bol, bol.com, affiliate, insights, reports, sales, api, dashboard, woocommerce, marketing
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your Bol.com Partner Program account to WordPress. Fetches and displays commission, click, and revenue data via the Bol.com Affiliate Reporting API in your WordPress admin area.

== Description ==

The Bol Affiliate Insights plugin provides a seamless way to integrate your Bol.com affiliate performance data directly into your WordPress dashboard. 
Get insights into:
*   **Orders:** Detailed information about individual orders.
*   **Commission & Revenue:** Track your earnings and revenue over time.
*   **Promotion Methods:** Analyze the performance of your different promotional links and methods.
*   **Dashboard Overview:** At-a-glance metrics and charts for quick performance checks.

Features:
*   Securely connect to the Bol.com Affiliate Reports API v2.
*   View key metrics: Orders, Clicks, Revenue, Commission, Conversion Rate.
*   Interactive chart to visualize performance trends.
*   Detailed tables for Orders, Commission & Revenue, and Promotion Methods.
*   Date range selectors for all reports.
*   Data caching for improved performance and API rate limit respect.
*   Easy-to-use settings page for API configuration.

This plugin is designed for WordPress administrators and operates entirely within the admin area.

== Installation ==

1.  Upload the `bol-affiliate-insights` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the "Bol Insights" menu in your WordPress admin dashboard.
4.  Go to the "Settings" tab and enter your Bol.com Client ID and Client Secret.
5.  Save your settings and use the "Test Connection" button to ensure a successful connection.
6.  Explore your data on the Dashboard, Orders, Commission & Revenue, and Promotion Methods tabs!

== Frequently Asked Questions ==

= Where do I get my Bol.com Client ID and Client Secret? =

You can obtain these credentials from your Bol.com Partner Program account:
1.  Log in to your <a href="https://partner.bol.com/" target="_blank">Bol.com Partner Program account</a>.
2.  Navigate to 'Account'.
3.  Scroll down to the 'Open API' section.
4.  Click 'Toevoegen' (Add) to create new credentials if you don't have them.
5.  Enter a name for your credentials and save.
6.  Your Client ID will be visible. Click 'Toon secret' (Show secret) to view and copy your Client Secret.
    Keep your Client Secret confidential.

= How often is the data refreshed? =

API data is cached for one hour to improve performance and respect API rate limits. You will see fresh data approximately every hour when you visit the plugin pages.

= What version of the Bol.com API does this plugin use? =

This plugin uses the Bol.com Affiliate Reports API v2.

== Screenshots ==

(We don't have screenshots yet, but this section is standard. Can be left empty or with a note)
1.  The main dashboard view. (Placeholder)
2.  Orders report table. (Placeholder)
3.  Settings page. (Placeholder)

== Changelog ==

= 0.1.0 - 2024-05-20 =
*   Initial release.
*   Features: Dashboard overview, Orders, Commission & Revenue, Promotion Methods reports.
*   API connection via Client ID/Secret.
*   Data caching.

== Upgrade Notice ==

= 0.1.0 =
Initial release of the plugin.
