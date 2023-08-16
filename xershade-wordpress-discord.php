<?php
/**
 * Plugin Name: XerShade's Discord Plugin
 * Description: A plugin that adds features that interact with Discord to WordPress.
 * Version: 1.1.0
 * Author: XerShade
 *
 * @link https://github.com/XerShade/XerShade.WordPress.Discord
 * @package XerShade.WordPress.Discord
 */
 
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wordpress-discord.php';

$xershade_wordpress_discord = new WordPress_Discord();