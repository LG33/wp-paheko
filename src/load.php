<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://paheko.cloud
 * @since             1.0.0
 * @package           WP-Paheko
 *
 * @wordpress-plugin
 * Plugin Name:       Paheko pour Wordpress
 * Plugin URI:        https://paheko.cloud
 * Description:       Ajoutez Paheko (logiciel libre de gestion et de comptabilité associative) directement à votre Wordpress !
 * Version:           1.0.0
 * Author:            Louis Gaillard
 * Author URI:        https://lgaillard.fr/
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain:       wp-paheko
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 */
define('WP_PAHEKO_VERSION', '1.3.20.0');

function wp_paheko_activation_redirect($plugin)
{
	if ($plugin == plugin_basename(__FILE__)) {
		copy('config.dist.php', 'config.local.php');
		exit(wp_redirect('/admin/'));
	}
}
add_action('activated_plugin', 'wp_paheko_activation_redirect');

function wp_paheko_add_menu_page()
{
	add_menu_page(
		esc_html__('Retourner dans Paheko', 'wp-paheko'),
		esc_html__('Retourner dans Paheko', 'wp-paheko'),
		'manage_options',
		esc_url(get_site_url(null, '/admin')),
		null,
		'dashicons-arrow-left-alt',
		0
	);
}
add_action('admin_menu', 'wp_paheko_add_menu_page', 1);

/**
 * Adds a link to the admin bar.
 *
 * @since n.e.x.t
 *
 * @param WP_Admin_Bar $admin_bar The admin bar object.
 */
function wp_paheko_adminbar_item($admin_bar)
{
	$args = array(
		'id' => 'wp-paheko',
		'title' => "Ouvrir Paheko",
		'href' => esc_url(get_site_url(null, '/admin')),
		'meta' => false,
	);
	$admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'wp_paheko_adminbar_item', 999);

function wp_paheko_cron_exec()
{
	require_once __DIR__ . '/config.local.php';
	
	if (!defined('Paheko\ROOT')) define('Paheko\ROOT', __DIR__);
	if (!defined('Paheko\DATA_ROOT')) define('Paheko\DATA_ROOT', \Paheko\ROOT . '/data');
	if (!defined('Paheko\PLUGINS_ROOT')) define('Paheko\PLUGINS_ROOT', \Paheko\DATA_ROOT . '/plugins');
	if (!defined('Paheko\CACHE_ROOT')) define('Paheko\CACHE_ROOT', \Paheko\DATA_ROOT . '/cache');
    if (!defined('Paheko\USER_TEMPLATES_CACHE_ROOT')) define('Paheko\USER_TEMPLATES_CACHE_ROOT', \Paheko\CACHE_ROOT . '/utemplates');
    if (!defined('Paheko\STATIC_CACHE_ROOT')) define('Paheko\STATIC_CACHE_ROOT', \Paheko\CACHE_ROOT . '/static');
    if (!defined('Paheko\SHARED_CACHE_ROOT')) define('Paheko\SHARED_CACHE_ROOT', \Paheko\CACHE_ROOT . '/shared');
    if (!defined('Paheko\SHARED_USER_TEMPLATES_CACHE_ROOT')) define('Paheko\SHARED_USER_TEMPLATES_CACHE_ROOT', \Paheko\SHARED_CACHE_ROOT . '/utemplates');
    if (!defined('Paheko\SMARTYER_CACHE_ROOT')) define('Paheko\SMARTYER_CACHE_ROOT', \Paheko\SHARED_CACHE_ROOT . '/compiled');
	if (!defined('Paheko\DB_FILE')) define('Paheko\DB_FILE', \Paheko\DATA_ROOT . '/association.sqlite');
	if (!defined('Paheko\SQLITE_JOURNAL_MODE')) define('Paheko\SQLITE_JOURNAL_MODE', 'TRUNCATE');
	if (!defined('Paheko\SQL_DEBUG')) define('Paheko\SQL_DEBUG', false);
	if (!defined('Paheko\ENABLE_PROFILER')) define('Paheko\ENABLE_PROFILER', false);
	if (!defined('Paheko\USE_CRON')) define('Paheko\USE_CRON', false);
	if (!defined('Paheko\FILE_VERSIONING_POLICY')) define('Paheko\FILE_VERSIONING_POLICY', null);
	if (!defined('Paheko\SYSTEM_SIGNALS')) define('Paheko\SYSTEM_SIGNALS', []);
	if (!defined('Paheko\PLUGINS_ALLOWLIST')) define('Paheko\PLUGINS_ALLOWLIST', null);
	if (!defined('Paheko\PLUGINS_BLOCKLIST')) define('Paheko\PLUGINS_BLOCKLIST', null);
	
	// Register PSR-0 autoloader for Paheko and KD2 classes
	spl_autoload_register(function (string $classname): void {
		$classname = ltrim($classname, '\\');
		$filename = str_replace('\\', '/', $classname);
		$path = \Paheko\ROOT . '/include/lib/' . $filename . '.php';
		if (file_exists($path)) {
			require_once $path;
		}
	}, true);

	if (file_exists(__DIR__ . '/data/plugins/helloasso_checkout/lib/HelloAsso.php')) {
		require_once __DIR__ . '/data/plugins/helloasso_checkout/lib/HelloAsso.php';
		require_once __DIR__ . '/data/plugins/helloasso_checkout/lib/API.php';
	}

	function paheko_version() {
		return 'unknown';
	}
	
	error_log( Paheko\USE_CRON );
	error_log( @filemtime(Paheko\CACHE_ROOT . '/last_cron_run') );

	if (!Paheko\USE_CRON && @filemtime(Paheko\CACHE_ROOT . '/last_cron_run') < (time() - 24*3600)) {
		touch(Paheko\CACHE_ROOT . '/last_cron_run');
		\KD2\DB\EntityManager::setGlobalDB(\Paheko\DB::getInstance());
		(new Paheko\CLI)->cron();
	    error_log( "Paheko cron executed" );
	}
}
add_action( 'wp_paheko_cron_hook', 'wp_paheko_cron_exec' );

function wp_paheko_init($plugin)
{
	if(!wp_next_scheduled( 'wp_paheko_cron_hook' )) {
		wp_schedule_event( time(), 'daily', 'wp_paheko_cron_hook' );
	}

	if (!function_exists('dd')) {
		function dd($arg)
		{
			return die(var_dump($arg));
		}
	}

	require_once ABSPATH . 'wp-load.php';

	$uri = explode('?', $_SERVER['REQUEST_URI'])[0];

	if (strpos($uri, '/p/') === 0 || strpos($uri, '/m/') === 0 || strpos($uri, '/admin/p/') === 0 || strpos($uri, '/admin/m/') === 0) {
		require_once __DIR__ . '/www/_route.php';
		exit();
	} elseif (strpos($uri, '/admin') === 0 || strpos($uri, '/documents') === 0 || strpos($uri, '/config') === 0 || strpos($uri, '/transaction') === 0) {
		if(!empty($_POST)) $_POST = wp_unslash( $_POST );

		$explode = explode('.', $uri);
		
		if (count($explode) > 1) {
			if (strpos($explode[1], 'php') === false) {
				if (str_contains($uri, 'favicon.png'))
					$redirect = get_site_icon_url(32);
				else if (str_contains($uri, 'icon.png') || str_contains($uri, 'logo.png')) {
					if (get_theme_mod('custom_logo')) {
						$logos = wp_get_attachment_image_src(get_theme_mod('custom_logo'), [150, 150]);
						if (!empty($logos))
							$redirect = $logos[0];
						else
							$redirect = get_site_icon_url(32);
					} else
						$redirect = get_site_icon_url(32);
				} else
					$redirect = WP_CONTENT_URL . DIRECTORY_SEPARATOR . implode('/', array_slice(explode('/', __DIR__), -3, 3)) . '/www' . $uri . '?' . $_SERVER['QUERY_STRING'];

				wp_redirect($redirect, 301);
				die();
			}
		} else {
			if (strpos($uri, '/', min(strlen($uri), 7)) === false)
				$uri .= '/';
			$uri .= 'index.php';
		}

		$file_uri = __DIR__ . '/www' . $uri;
		if (file_exists($file_uri))
			require_once $file_uri;
		else
			require_once __DIR__ . '/www/_route.php';

		exit();
	}
}
add_action('init', 'wp_paheko_init');

function wp_paheko_deactivate() {
    $timestamp = wp_next_scheduled( 'wp_paheko_cron_hook' );
    wp_unschedule_event( $timestamp, 'wp_paheko_cron_hook' );
}
register_deactivation_hook( __FILE__, 'wp_paheko_deactivate' ); 
