<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://lgaillard.fr
 * @since             1.0.0
 * @package           WP-Paheko
 *
 * @wordpress-plugin
 * Plugin Name:       WP-Paheko
 * Plugin URI:        https://paheko.cloud
 * Description:       This is a description of the plugin.
 * Version:           1.0.0
 * Author:            Louis Gaillard
 * Author URI:        https://lgaillard.fr/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-paheko
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WP_PAHEKO_VERSION', '1.0.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-paheko-activator.php
 */
function activate_wp_paheko()
{
	add_rewrite_rule('((m|p|admin\/p)\/?.*)$', 'wp-content/plugins/' . basename(__DIR__) . '/www/_route.php/$1', 'top');
	add_rewrite_rule('(admin\/?.*)$', 'wp-content/plugins/' . basename(__DIR__) . '/www/$1', 'top');
	flush_rewrite_rules();
}
//register_activation_hook( __FILE__, 'activate_wp_paheko' );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-paheko-deactivator.php
 */
function deactivate_wp_paheko()
{
	flush_rewrite_rules();
}
//register_deactivation_hook( __FILE__, 'deactivate_wp_paheko' );

function wp_paheko_activation_redirect($plugin)
{
	if ($plugin == plugin_basename(__FILE__)) {
		exit(wp_redirect('/admin/'));
	}
}
add_action('activated_plugin', 'wp_paheko_activation_redirect');

function wp_paheko_add_menu_page()
{
	add_menu_page(
		esc_html__('Retourner dans Wasso', 'return-to-wasso'),
		esc_html__('Retourner dans Wasso', 'return-to-wasso'),
		'manage_options',
		__DIR__ . '/www/index.php',
		null, // callback
		'dashicons-arrow-left-alt',
		0
	);
}
add_action('admin_menu', 'wp_paheko_add_menu_page', 1);

function wp_paheko_init($plugin)
{
	if (!function_exists('dd')) {
		function dd($arg)
		{
			return die(var_dump($arg));
		}
	}

	require_once ABSPATH . 'wp-config.php';

	$uri = explode('?', $_SERVER['REQUEST_URI'])[0];

	if (strpos($uri, '/p/') === 0 || strpos($uri, '/m/') === 0 || strpos($uri, '/admin/p/') === 0 || strpos($uri, '/admin/m/') === 0) {
		require_once __DIR__ . '/www/_route.php';
		exit();
	} elseif (strpos($uri, '/admin') === 0 || strpos($uri, '/documents') === 0 || strpos($uri, '/config') === 0 || strpos($uri, '/transaction') === 0) {
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
					$redirect = '/wp-content/plugins/' . basename(__DIR__) . '/www' . $uri . '?' . $_SERVER['QUERY_STRING'];

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