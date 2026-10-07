# Releasing Plugin Orphan Watch

## Before you submit to WordPress.org

1. Replace placeholders:
   - `Contributors:` in `readme.txt` -> your wordpress.org username
   - `Author`, `Plugin URI`, `Author URI` in `plugin-orphan-watch.php`
   - `ORWATCH_UPGRADE_URL` in `plugin-orphan-watch.php` -> your real Pro landing page
   - `Tested up to:` in `readme.txt` -> current WordPress release
2. Check the slug is free: https://wordpress.org/plugins/plugin-orphan-watch/ (404 = available).
   The slug is taken from the name, so renaming means renaming the folder + main file too.
3. Run Plugin Check (install the "Plugin Check" plugin, point it at this folder) and fix every error.
4. Test on a real site: activate, scan, ignore, Site Health, dashboard widget, uninstall.

## Submit

1. Zip the `plugin-orphan-watch` folder (folder at the zip root).
2. Upload at https://wordpress.org/plugins/developers/add/ - review takes days to weeks.
3. After approval you get an SVN repo: `https://plugins.svn.wordpress.org/plugin-orphan-watch/`

```
svn co https://plugins.svn.wordpress.org/plugin-orphan-watch svn
cp -r plugin-orphan-watch/* svn/trunk/
svn add --force svn/trunk
svn cp svn/trunk svn/tags/1.0.0
svn ci -m "Release 1.0.0"
```

Put icons/banners/screenshots in `svn/assets/` (`icon-256x256.png`, `banner-772x250.png`,
`banner-1544x500.png`, `screenshot-1.png`...), not in the plugin zip.

## Free -> Pro (when you are ready)

wordpress.org rules: the free plugin must be fully functional, must not contain locked/trialware
features or license checks, and upsells must be unobtrusive. So Pro is a **separate plugin**
(`plugin-orphan-watch-pro`) that requires the free one and hooks into it:

| Hook | Use in Pro |
| --- | --- |
| `orwatch_loaded` (action) | boot the add-on |
| `orwatch_is_pro` (filter) | return `true` -> hides the upsell box |
| `orwatch_result` (filter) | add vulnerability data / alternatives to each scan result |
| `orwatch_scan_complete` (action) | send alerts when a plugin becomes closed/abandoned |
| `orwatch_after_table` (action) | render Pro panels (schedule, alert settings, reports) |
| `orwatch_upgrade_url` (filter) | change the upgrade link |

Sell and license the Pro zip from your own site (Freemius, Lemon Squeezy, EDD + Software Licensing, ...).
Pro scans can run from WP-Cron by calling `ORWATCH_Scanner::scan( $plugin_file )` for each installed plugin.
