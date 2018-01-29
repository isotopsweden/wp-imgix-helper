<?php

/**
 * Plugin Name: Imgix helper
 * Plugin URI: https://github.com/isotopsweden/wp-imgix-helper
 * Description: WordPress helpers and settings for imgix plugin.
 * Author: Isotop
 * Author URI: https://www.isotop.se
 * Version: 1.0.0
 * Textdomain: wp-imgix-helper
 */

// Load Composer autoload if it exists.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Boot the plugin.
 */
add_action( 'plugins_loaded', function () {
	\Isotop\Imgix\Imgix::instance();
} );