<?php

/**
 * Master plugin class for the Discord integration plugin.
 */
class XerShade_WordPress_Discord {
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
	 * The redirect uri that Discord sends the user to after authenticating.
	 *
	 * @var string $discord_redirect_uri
	 */
	protected $discord_redirect_uri;

	/**
	 * Constructs the plugin class and loads configuration settings.
	 */
	public function __construct() {
		$this->discord_client_id     = get_option( 'discord_client_id' );
		$this->discord_client_secret = get_option( 'discord_client_secret' );
		$this->discord_redirect_uri  = get_option( 'discord_redirect_uri', '/wp-admin/admin-ajax.php?action=discord_oauth_callback' );

		// Register WordPress hooks.
		add_action( 'login_form', array( $this, 'discord_oauth_login_button' ) );
		add_action( 'wp_ajax_discord_oauth_callback', array( $this, 'handle_discord_oauth_callback' ) );
		add_action( 'wp_ajax_nopriv_discord_oauth_callback', array( $this, 'handle_discord_oauth_callback' ) );
		add_action( 'admin_menu', array( $this, 'discord_oauth_config_menu' ) );
		add_action( 'wp_ajax_discord_unlink_account', array( $this, 'handle_discord_unlink_account' ) );
		add_action( 'show_user_profile', array( $this, 'render_discord_user_settings' ) );
		add_action( 'edit_user_profile', array( $this, 'render_discord_user_settings' ) );
	}

	/**
	 * Generates the Discord login button to display on the login form.
	 *
	 * @return void
	 */
	public function discord_oauth_login_button() {
		$discord_login_url = 'https://discord.com/oauth2/authorize?client_id=' . $this->discord_client_id . '&redirect_uri=' . rawurlencode( $this->discord_redirect_uri ) . '&response_type=code&scope=identify email';
		echo '<p id="discord-login-button"><a class="button" style="margin: 0 6px 16px 0; width: 100%; text-align: center;" href="' . esc_url( $discord_login_url ) . '">Log in with Discord</a></p>';
	}

	/**
	 * Handles any callbacks from Discord.
	 *
	 * @return void
	 */
	public function handle_discord_oauth_callback() {

		if ( isset( $_GET['code'] ) ) {
			$token_url  = 'https://discord.com/api/oauth2/token';
			$token_data = array(
				'client_id'     => $this->discord_client_id,
				'client_secret' => $this->discord_client_secret,
				'grant_type'    => 'authorization_code',
				'code'          => sanitize_text_field( $_GET['code'] ),
				'redirect_uri'  => $this->discord_redirect_uri,
				'scope'         => 'identify email',
			);

			$response   = wp_safe_remote_post( $token_url, array( 'body' => $token_data ) );
			$body       = wp_remote_retrieve_body( $response );
			$token_info = json_decode( $body, true );

			if ( isset( $token_info['access_token'] ) ) {
				$user_info_url = 'https://discord.com/api/v10/users/@me';
				$headers       = array(
					'Authorization' => 'Bearer ' . $token_info['access_token'],
				);

				$user_response = wp_safe_remote_get( $user_info_url, array( 'headers' => $headers ) );
				$user_body     = wp_remote_retrieve_body( $user_response );
				$user_info     = json_decode( $user_body, true );

				$user_id = $this->get_user_id_by_discord_id( $user_info['id'] );

				if ( $user_id ) {
					wp_set_auth_cookie( $user_id );
				} else {
					if ( is_user_logged_in() ) {
						update_user_meta( get_current_user_id(), 'discord_id', $user_info['id'] );
					} else {
						$username = sanitize_user( $user_info['username'] );
						$email    = sanitize_email( $user_info['email'] );
						$user_id  = wp_create_user( $username, wp_generate_password(), $email );

						update_user_meta( $user_id, 'discord_id', $user_info['id'] );

						wp_set_auth_cookie( $user_id );
					}
				}
			}
		}

		wp_safe_redirect( home_url() );
		exit;
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

			if ( isset( $_POST['discord_redirect_uri'] ) ) {
				$this->discord_redirect_uri = sanitize_text_field( wp_unslash( $_POST['discord_redirect_uri'] ) );
				update_option( 'discord_redirect_uri', $this->discord_redirect_uri );
			}
		}
		?>
		<div class="wrap">
			<h2>Discord OAuth Settings</h2>
			<form method="post" action="">
				<?php
					wp_nonce_field( 'discord_oauth_nonce', 'discord_oauth_nonce' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="discord_client_id">Client ID</label></th>
						<td>
							<input type="text" name="discord_client_id" id="discord_client_id" value="<?php echo esc_attr( $this->discord_client_id ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="discord_client_secret">Client Secret</label></th>
						<td>
							<input type="hidden" name="discord_client_secret" id="discord_client_secret" value="<?php echo esc_attr( $this->discord_client_secret ); ?>" class="regular-text">
							<b>Currently streaming, you wish you could see this on YouTube!</b><br />
							This will be removed and replaced with a normal text box when I am done steaming.
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="discord_redirect_uri">Redirect URI</label></th>
						<td><input type="text" readonly="true" name="discord_redirect_uri" id="discord_redirect_uri" value="<?php echo esc_attr( $this->discord_redirect_uri ); ?>" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button( 'Save Settings', 'primary', 'discord_oauth_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the plugin settings on the user's profile page.
	 *
	 * @param WP_User $user The current WP_User object.
	 * @return void
	 */
	public function render_discord_user_settings( $user ) {
		if ( get_user_meta( $user->ID, 'discord_id', true ) ) {
			if ( current_user_can( 'edit_user', $user->ID ) && isset( $_GET['unlink_discord'] ) && intval( sanitize_text_field( wp_unslash( $_GET['unlink_discord'] ) ) ) === $user->ID ) {
				delete_user_meta( $user->ID, 'discord_id' );
			}
		}

		if ( get_user_meta( $user->ID, 'discord_id', true ) ) {
			?>
			<h3>Unlink Discord Account</h3>
			<table class="form-table">
				<tr>
					<th></th>
					<td>
						<a href="<?php echo esc_url( add_query_arg( 'unlink_discord', $user->ID ) ); ?>" class="button">Unlink Discord Account</a>
						<p class="description">Click this button to unlink your Discord account.</p>
					</td>
				</tr>
			</table>
			<?php
		} else {
			if ( $this->discord_client_id && $this->discord_redirect_uri ) {
				$discord_authorize_url = "https://discord.com/oauth2/authorize?client_id={$this->discord_client_id}&redirect_uri=" . urlencode( $this->discord_redirect_uri ) . '&response_type=code&scope=identify email';
				?>
			<h3>Link Discord Account</h3>
			<table class="form-table">
				<tr>
					<th></th>
					<td>
						<a href="<?php echo esc_url( $discord_authorize_url ); ?>" class="button">Link Discord Account</a>
						<p class="description">Click this button to link your Discord account.</p>
					</td>
				</tr>
			</table>
				<?php
			}
		}
	}
}
