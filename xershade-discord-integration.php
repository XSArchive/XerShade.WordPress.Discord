<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.xershade.ca
 * @since             1.2.0
 * @package           Xershade_Discord_Integration
 *
 * @wordpress-plugin
 * Plugin Name:       XerShade's Discord Integration
 * Plugin URI:        https://github.com/XerShade/XerShade.WordPress.Discord
 * Description:       Integrates Discord into WordPress and allows for features like OAuth authentication, user role mapping, and post linking.
 * Version:           1.2.0
 * Author:            XerShade
 * Author URI:        https://www.xershade.ca/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xershade-discord-integration
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.2.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'XERSHADE_DISCORD_INTEGRATION_VERSION', '1.2.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xershade-discord-integration-activator.php
 */
function activate_xershade_discord_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xershade-discord-integration-activator.php';
	Xershade_Discord_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xershade-discord-integration-deactivator.php
 */
function deactivate_xershade_discord_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xershade-discord-integration-deactivator.php';
	Xershade_Discord_Integration_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_xershade_discord_integration' );
register_deactivation_hook( __FILE__, 'deactivate_xershade_discord_integration' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xershade-discord-integration.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.2.0
 */
function run_xershade_discord_integration() {

	$plugin = new Xershade_Discord_Integration();
	$plugin->run();

}
run_xershade_discord_integration();
