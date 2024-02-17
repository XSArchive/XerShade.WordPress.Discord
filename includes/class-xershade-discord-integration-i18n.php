<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.xershade.ca
 * @since      1.2.0
 *
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.2.0
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 * @author     XerShade <xershade.ca@gmail.com>
 */
class Xershade_Discord_Integration_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.2.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'xershade-discord-integration',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
