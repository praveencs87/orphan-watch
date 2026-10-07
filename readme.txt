=== Orphan Watch ===
Contributors: coderbunch
Tags: security, plugins, abandoned plugins, maintenance, site health
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find abandoned, closed and outdated plugins on your site before they become a security problem.

== Description ==

Most hacked WordPress sites are compromised through a plugin that nobody has maintained for years. WordPress itself will happily keep running those plugins and will never warn you.

**Orphan Watch** checks every plugin you have installed against the WordPress.org directory and tells you which ones are:

* **Closed** - removed from WordPress.org (often for security reasons)
* **Abandoned** - no update for more than 2 years
* **Stale** - no update for more than 1 year
* **Untested** - not tested with the last few WordPress releases
* **Not on WordPress.org** - premium or custom plugins you should verify yourself

Features:

* One-click scan of all installed plugins, with live progress
* Colour-coded health status, last update date, "tested up to" and active installs
* Ignore plugins you have deliberately decided to keep
* Dashboard widget with a quick summary
* Native Site Health test (Tools > Site Health)
* No account, no API key, no tracking

= Orphan Watch Pro =

Want it to watch your site for you? Pro adds scheduled scans, email and Slack alerts, known-vulnerability data, suggested alternatives and reports. [Learn more](https://coderbunch.com/orphan-watch/pro/)

= Developers =

The plugin exposes hooks (`orwatch_result`, `orwatch_scan_complete`, `orwatch_after_table`, `orwatch_is_pro`, `orwatch_upgrade_url`) so add-ons can extend it.

== External services ==

To find out whether a plugin is still maintained, this plugin asks the WordPress.org Plugins API (`api.wordpress.org/plugins/info/`) for the public information of each installed plugin, using WordPress's built-in `plugins_api()` function. The request contains the plugin's directory slug, your site's URL, WordPress version and locale (the same data WordPress core sends when you browse Plugins > Add New). Nothing else is sent and no data is sent to the plugin author.

Service provided by WordPress.org: [Privacy policy](https://wordpress.org/about/privacy/).

== Installation ==

1. Upload the `orphan-watch` folder to `/wp-content/plugins/`, or install it from Plugins > Add New.
2. Activate the plugin.
3. Go to Plugins > Orphan Watch and click "Scan all plugins".

== Frequently Asked Questions ==

= Does it scan my premium plugins? =

Premium plugins that are not in the WordPress.org directory cannot be verified and are shown as "Not on WordPress.org". Plugins with their own update server (Update URI header) are shown as "Self-updating".

= Is an old plugin always unsafe? =

No. A plugin that does one small job may be finished and still fine. Use the "Ignore" action for plugins you have reviewed. But a plugin that is closed or untouched for years deserves a look.

= Will it slow down my site? =

No. Nothing runs on the front end. Scans only run when you click the button in the admin.

= Does it send my data anywhere? =

Only a plugin slug per request to WordPress.org, see "External services".

== Screenshots ==

1. The scan results table.
2. Dashboard widget.
3. Site Health test.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First release.
