<?php
/**
 * Master plugin class for the Discord integration plugin.
 *
 * @link https://github.com/XerShade/XerShade.WordPress.Discord
 * @package XerShade.WordPress.Discord
 */

namespace XerShade\WordPress\Discord;

require_once plugin_dir_path( __FILE__ ) . 'class-discord-oauth.php';

use XerShade\Discord\OAuth\Discord_OAuth;

/**
 * Master plugin class for the Discord integration plugin.
 */
class WordPress_Discord {
	/**
	 * The Discord application's identifier.
	 *
	 * @var string $discord_client_id
	 */
	protected $discord_client_id;
	/**
	 * The Discord application's secret key.
	 *
	 * @var string $discord_client_secret
	 */
	protected $discord_client_secret;
	/**
	 * Undocumented variable
	 *
	 * @var [type]
	 */
	protected $discord_oauth;

	/**
	 * Constructs the plugin class and loads configuration settings.
	 */
	public function __construct() {
		$this->discord_client_id     = get_option( 'discord_client_id' );
		$this->discord_client_secret = get_option( 'discord_client_secret' );
		$this->discord_oauth         = new Discord_OAuth( $this );

		// Register the OAuth keys with the OAuth service.
		$this->discord_oauth->attach_keys( $this->discord_client_id, $this->discord_client_secret );

		// Register WordPress hooks.
		add_action( 'admin_menu', array( $this, 'discord_oauth_config_menu' ) );
		add_action( 'wp_ajax_discord_unlink_account', array( $this, 'handle_discord_unlink_account' ) );
	}

	/**
	 * Handles any requests from the user to unlink their Discord account.
	 *
	 * @return void
	 */
	public function handle_discord_unlink_account() {
		if ( is_user_logged_in() ) {
			delete_user_meta( get_current_user_id(), 'discord_id' );
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Returns the WordPress identifier linked to the associated Discord identifier.
	 *
	 * @param string $discord_id The Discord identifier to search for.
	 * @return string The WordPress identifier, or 0 if no linked account is found.
	 */
	public function get_user_id_by_discord_id( $discord_id ) {
		$users = get_users(
			array(
				'meta_key'   => 'discord_id',
				'meta_value' => $discord_id,
				'number'     => 1,
			)
		);

		return ! empty( $users ) ? $users[0]->ID : 0;
	}

	/**
	 * Adds the configuration page link to the WordPress admin area menu.
	 *
	 * @return void
	 */
	public function discord_oauth_config_menu() {
		add_menu_page(
			'Discord OAuth Settings',
			'Discord OAuth',
			'manage_options',
			'discord-oauth-settings',
			array( $this, 'discord_oauth_config_page' ),
			'dashicons-shield',
			30
		);
	}

	/**
	 * Renders the configuration page for the plugin.
	 *
	 * @return void
	 */
	public function discord_oauth_config_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['discord_oauth_submit'] ) && isset( $_POST['discord_oauth_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['discord_oauth_nonce'] ), 'discord_oauth_nonce' ) ) {
			if ( isset( $_POST['discord_client_id'] ) ) {
				$this->discord_client_id = sanitize_text_field( wp_unslash( $_POST['discord_client_id'] ) );
				update_option( 'discord_client_id', $this->discord_client_id );
			}

			if ( isset( $_POST['discord_client_secret'] ) ) {
				$this->discord_client_secret = sanitize_text_field( wp_unslash( $_POST['discord_client_secret'] ) );
				update_option( 'discord_client_secret', $this->discord_client_secret );
			}

			$this->discord_oauth->attach_keys( $this->discord_client_id, $this->discord_client_secret );

			if ( isset( $_POST['discord_redirect_uri'] ) ) {
				$this->discord_oauth->assign_redirect_uri( sanitize_text_field( wp_unslash( $_POST['discord_redirect_uri'] ) ) );
			}

			isset( $_POST['discord_enable_oauth'] ) ? $this->discord_oauth->enable() : $this->discord_oauth->disable();
		}
		?>
		<div class="wrap">
			<h2>Discord Settings</h2>
			<form method="post" action="">
				<?php
					wp_nonce_field( 'discord_oauth_nonce', 'discord_oauth_nonce' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="discord_client_id">Client ID</label></th>
						<td><input type="text" name="discord_client_id" id="discord_client_id" value="<?php echo esc_attr( $this->discord_client_id ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="discord_client_secret">Client Secret</label></th>
						<td><input type="text" name="discord_client_secret" id="discord_client_secret" value="<?php echo esc_attr( $this->discord_client_secret ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<?php
				$this->discord_oauth->render_settings_page();
				?>
				<?php
				submit_button( 'Save Settings', 'primary', 'discord_oauth_submit' );
				?>
			</form>
		</div>
		<?php
	}
}
