<?php
/**
 * Plugin Name:       Orphan Watch
 * Plugin URI:        https://coderbunch.com/orphan-watch/
 * Description:       Find abandoned, closed and outdated plugins on your site before they become a security problem. Scans every installed plugin against WordPress.org.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Coderbunch
 * Author URI:        https://coderbunch.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orphan-watch
 *
 * @package OrphanWatch
 */

defined( 'ABSPATH' ) || exit;

define( 'ORWATCH_VERSION', '1.0.0' );
define( 'ORWATCH_FILE', __FILE__ );
define( 'ORWATCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORWATCH_URL', plugin_dir_url( __FILE__ ) );

/**
 * Where the "Pro" call-to-action points. Override with the `orwatch_upgrade_url` filter.
 */
if ( ! defined( 'ORWATCH_UPGRADE_URL' ) ) {
	define( 'ORWATCH_UPGRADE_URL', 'https://coderbunch.com/orphan-watch/pro/' );
}

require_once ORWATCH_DIR . 'includes/class-orwatch-scanner.php';
require_once ORWATCH_DIR . 'includes/class-orwatch-admin.php';
require_once ORWATCH_DIR . 'includes/class-orwatch-integrations.php';

/**
 * Is a premium add-on active?
 *
 * The premium add-on (a separate plugin that depends on this one) returns true
 * from this filter. The free plugin never ships locked or disabled features.
 *
 * @return bool
 */
function orwatch_is_pro() {
	return (bool) apply_filters( 'orwatch_is_pro', false );
}

/**
 * Upgrade URL, with a campaign marker so you can see which screen converted.
 *
 * @param string $source Where the link is displayed.
 * @return string
 */
function orwatch_upgrade_url( $source = 'admin' ) {
	$url = apply_filters( 'orwatch_upgrade_url', ORWATCH_UPGRADE_URL );
	return add_query_arg(
		array(
			'utm_source'   => 'plugin',
			'utm_medium'   => $source,
			'utm_campaign' => 'orphan-watch',
		),
		$url
	);
}

/**
 * Boot the plugin.
 */
function orwatch_init() {
	ORWATCH_Admin::init();
	ORWATCH_Integrations::init();

	/**
	 * Fires once the free plugin is loaded. A premium add-on should hook here.
	 */
	do_action( 'orwatch_loaded' );
}
add_action( 'plugins_loaded', 'orwatch_init' );
