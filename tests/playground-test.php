<?php
/**
 * Smoke test, run inside WordPress Playground (see tests/blueprint.json).
 * Not shipped (tests/ is in .distignore).
 */
require_once '/wordpress/wp-load.php';

$results = array();
$check   = static function ( $label, $ok ) use ( &$results ) {
	$results[] = ( $ok ? 'PASS ' : 'FAIL ' ) . $label;
};
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ), true ) ) {
			file_put_contents( __DIR__ . '/results.txt', 'FATAL: ' . print_r( $e, true ), FILE_APPEND );
		}
	}
);

require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );

$check( 'plugin loaded', class_exists( 'ORWATCH_Scanner' ) );

$installed = ORWATCH_Scanner::get_installed();
$check( 'installed plugins found', count( $installed ) >= 1 );
$check( 'slug for dir plugin', 'akismet' === ORWATCH_Scanner::slug_from_file( 'akismet/akismet.php' ) );
$check( 'slug for single-file plugin', 'hello' === ORWATCH_Scanner::slug_from_file( 'hello.php' ) );

// Live scan against WordPress.org (Akismet is always listed and maintained).
$file = isset( $installed['akismet/akismet.php'] ) ? 'akismet/akismet.php' : array_key_first( $installed );
$res  = ORWATCH_Scanner::scan( $file );
$check( 'live scan returns array', is_array( $res ) );
$check( 'live scan status is ok/known (' . ( is_array( $res ) ? $res['status'] : 'n/a' ) . ')', is_array( $res ) && isset( ORWATCH_Scanner::statuses()[ $res['status'] ] ) );
$check( 'live scan has last_updated', is_array( $res ) && $res['last_updated'] > 0 );
$check( 'result stored', isset( ORWATCH_Scanner::get_results()[ $file ] ) );

// Not-found detection with a fake plugin directory.
$fake_dir = WP_PLUGIN_DIR . '/zz-fake-orphan-plugin-xyz';
wp_mkdir_p( $fake_dir );
file_put_contents( $fake_dir . '/zz-fake-orphan-plugin-xyz.php', "<?php\n/**\n * Plugin Name: ZZ Fake\n * Version: 1.0\n */\n" );
wp_cache_delete( 'plugins', 'plugins' );
$fake = ORWATCH_Scanner::scan( 'zz-fake-orphan-plugin-xyz/zz-fake-orphan-plugin-xyz.php' );
$check( 'unknown slug -> not_listed (' . ( is_array( $fake ) ? $fake['status'] : 'n/a' ) . ')', is_array( $fake ) && 'not_listed' === $fake['status'] );

// Update URI -> external.
file_put_contents( $fake_dir . '/zz-fake-orphan-plugin-xyz.php', "<?php\n/**\n * Plugin Name: ZZ Fake\n * Version: 1.0\n * Update URI: https://example.com/updates\n */\n" );
wp_cache_delete( 'plugins', 'plugins' );
$ext = ORWATCH_Scanner::scan( 'zz-fake-orphan-plugin-xyz/zz-fake-orphan-plugin-xyz.php' );
$check( 'Update URI -> external', is_array( $ext ) && 'external' === $ext['status'] );

$check( 'unknown plugin -> WP_Error', is_wp_error( ORWATCH_Scanner::scan( 'nope/nope.php' ) ) );

// Ignore toggle.
$check( 'ignore on', true === ORWATCH_Scanner::toggle_ignored( $file ) );
$check( 'ignored excluded from summary', ORWATCH_Scanner::summary()['scanned'] < count( $installed ) );
$check( 'ignore off', false === ORWATCH_Scanner::toggle_ignored( $file ) );

// Rendering.
$row = ORWATCH_Admin::render_row( $file );
$check( 'row renders <tr data-plugin>', str_contains( $row, '<tr' ) && str_contains( $row, 'data-plugin="' . esc_attr( $file ) . '"' ) );
ob_start();
ORWATCH_Admin::render_page();
$page = ob_get_clean();
$check( 'page renders table', str_contains( $page, 'id="orwatch-table"' ) && str_contains( $page, 'Scan all plugins' ) );
$check( 'page shows upsell box', str_contains( $page, 'Want it to watch your site' ) );
add_filter( 'orwatch_is_pro', '__return_true' );
ob_start();
ORWATCH_Admin::render_page();
$page_pro = ob_get_clean();
$check( 'upsell hidden when pro', ! str_contains( $page_pro, 'Want it to watch your site' ) );
remove_filter( 'orwatch_is_pro', '__return_true' );

// Widget + Site Health.
ob_start();
ORWATCH_Admin::render_widget();
$check( 'widget renders', '' !== ob_get_clean() );
$sh = ORWATCH_Integrations::site_health_test();
$check( 'site health test shape', isset( $sh['status'], $sh['label'], $sh['test'] ) );

// AJAX security: no nonce => rejected.
$_POST = array( 'action' => 'orwatch_scan_plugin', 'plugin' => $file );
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new Exception( 'died' ); }; } );
$died = false;
try {
	ORWATCH_Admin::ajax_scan();
} catch ( Exception $e ) {
	$died = true;
}
$check( 'ajax without nonce is rejected', $died );

// Cleanup fake plugin.
unlink( $fake_dir . '/zz-fake-orphan-plugin-xyz.php' );
rmdir( $fake_dir );

$out  = "=== ORWATCH TEST RESULTS ===\n" . implode( "\n", $results ) . "\n";
$out .= ( false === strpos( implode( '', $results ), 'FAIL' ) ) ? "ALL PASSED\n" : "FAILURES\n";
file_put_contents( __DIR__ . '/results.txt', $out );
echo $out;
