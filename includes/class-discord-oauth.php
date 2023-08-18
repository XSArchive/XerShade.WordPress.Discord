<?php
/**
 * DESCRIPTION
 *
 * @link https://github.com/XerShade/XerShade.WordPress.Discord
 * @package XerShade.WordPress.Discord
 */

namespace XerShade\Discord\OAuth;

use XerShade\WordPress\Discord\WordPress_Discord;
use XerShade\WordPress\OAuth\WordPress_OAuth;

require_once plugin_dir_path( __FILE__ ) . 'class-wordpress-oauth.php';
require_once plugin_dir_path( __FILE__ ) . 'class-wordpress-discord.php';

if ( ! class_exists( 'XerShade\Discord\OAuth\Discord_OAuth' ) ) {
	/**
	 * Undocumented class
	 */
	class Discord_OAuth extends WordPress_OAuth {
		/**
		 * Undocumented function
		 *
		 * @param WordPress_Discord $parent The parent class that owns this OAuth class.
		 */
		public function __construct( WordPress_Discord $parent ) {
			parent::__construct( 'discord', $parent );
		}
	}
}
