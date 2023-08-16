<?php
/**
 * Plugin Name: XerShade's OAuth (Discord)
 * Description: A simple OAuth plugin for WordPress that allows users to login and create new accounts with discord.
 * Version: 1.0.0
 * Author: XerShade
 */

// Register the Discord OAuth login button
add_action( 'login_form', 'discord_oauth_login_button' );
function discord_oauth_login_button() {
	// Get the current URL to use as the return URL
	$return_url = urlencode( home_url( $_SERVER['REQUEST_URI'] ) );

	// Discord OAuth settings
	$discord_client_id    = get_option( 'discord_client_id' );
	$discord_redirect_uri = get_option( 'discord_redirect_uri' );

	$discord_login_url = 'https://discord.com/oauth2/authorize?client_id=' . $discord_client_id . '&redirect_uri=' . urlencode( $discord_redirect_uri ) . '&response_type=code&scope=identify email&redirect_to=' . $return_url;
	echo '<p id="discord-login-button"><a class="button" style="margin: 0 6px 16px 0; width: 100%; text-align: center;" href="' . esc_url( $discord_login_url ) . '">Log in with Discord</a></p>';
}

// Handle Discord OAuth callback
add_action( 'wp_ajax_discord_oauth_callback', 'handle_discord_oauth_callback' );
add_action( 'wp_ajax_nopriv_discord_oauth_callback', 'handle_discord_oauth_callback' );
function handle_discord_oauth_callback() {
	// Discord OAuth settings
	$discord_client_id     = get_option( 'discord_client_id' );
	$discord_client_secret = get_option( 'discord_client_secret' );
	$discord_redirect_uri  = get_option( 'discord_redirect_uri' );

	if ( isset( $_GET['code'] ) ) {
		$token_url  = 'https://discord.com/api/oauth2/token';
		$token_data = array(
			'client_id'     => $discord_client_id,
			'client_secret' => $discord_client_secret,
			'grant_type'    => 'authorization_code',
			'code'          => $_GET['code'],
			'redirect_uri'  => $discord_redirect_uri,
			'scope'         => 'identify email',
		);

		// Get access token
		$response   = wp_safe_remote_post( $token_url, array( 'body' => $token_data ) );
		$body       = wp_remote_retrieve_body( $response );
		$token_info = json_decode( $body, true );

		if ( isset( $token_info['access_token'] ) ) {
			$user_info_url = 'https://discord.com/api/v10/users/@me';
			$headers       = array(
				'Authorization' => 'Bearer ' . $token_info['access_token'],
			);

			// Get user's Discord profile
			$user_response = wp_safe_remote_get( $user_info_url, array( 'headers' => $headers ) );
			$user_body     = wp_remote_retrieve_body( $user_response );
			$user_info     = json_decode( $user_body, true );

			// Check if the user exists in WordPress
			$user_id = get_user_id_by_discord_id( $user_info['id'] );

			if ( $user_id ) {
				// User is already linked, log them in
				wp_set_auth_cookie( $user_id );
			} else {
				// Check if the user is logged in
				if ( is_user_logged_in() ) {
					// Link Discord ID to user's meta
					update_user_meta( get_current_user_id(), 'discord_id', $user_info['id'] );
				} else {
					// New user, create a WordPress account
					$username = sanitize_user( $user_info['username'] );
					$email    = sanitize_email( $user_info['email'] );
					$user_id  = wp_create_user( $username, wp_generate_password(), $email );

					// Link Discord ID to user's meta
					update_user_meta( $user_id, 'discord_id', $user_info['id'] );

					// Log in the newly created user
					wp_set_auth_cookie( $user_id );
				}
			}
		}
	}

	// Handle error case
	if ( isset( $_GET['redirect_to'] ) ) {
		$redirect_to = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		wp_safe_redirect( $redirect_to );
	} else {
		wp_safe_redirect( home_url() );
	}
	exit;
}

// Handle account unlinking
add_action( 'wp_ajax_discord_unlink_account', 'handle_discord_unlink_account' );
function handle_discord_unlink_account() {
	if ( is_user_logged_in() ) {
		delete_user_meta( get_current_user_id(), 'discord_id' );
		wp_redirect( home_url() );
		exit;
	}
}

// Helper function to get user ID by Discord ID
function get_user_id_by_discord_id( $discord_id ) {
	$users = get_users(
		array(
			'meta_key'   => 'discord_id',
			'meta_value' => $discord_id,
			'number'     => 1,
		)
	);

	return ! empty( $users ) ? $users[0]->ID : 0;
}

// Add the configuration menu item to the admin menu
add_action( 'admin_menu', 'discord_oauth_config_menu' );
function discord_oauth_config_menu() {
	add_menu_page(
		'Discord OAuth Settings',
		'Discord OAuth',
		'manage_options',
		'discord-oauth-settings', // Use a different slug to avoid conflicts
		'discord_oauth_config_page',
		'dashicons-shield',
		30
	);
}

// Render the configuration page
function discord_oauth_config_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Save settings when the form is submitted
	if ( isset( $_POST['discord_oauth_submit'] ) ) {
		update_option( 'discord_client_id', sanitize_text_field( $_POST['discord_client_id'] ) );
		update_option( 'discord_client_secret', sanitize_text_field( $_POST['discord_client_secret'] ) );
		update_option( 'discord_redirect_uri', sanitize_text_field( $_POST['discord_redirect_uri'] ) );
	}

	$discord_client_id     = get_option( 'discord_client_id', '' );
	$discord_client_secret = get_option( 'discord_client_secret', '' );
	$discord_redirect_uri  = get_option( 'discord_redirect_uri', site_url( '/wp-admin/admin-ajax.php?action=discord_oauth_callback' ) );
	?>
	<div class="wrap">
		<h2>Discord OAuth Settings</h2>
		<form method="post" action="">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="discord_client_id">Client ID</label></th>
					<td><input type="text" name="discord_client_id" id="discord_client_id" value="<?php echo esc_attr( $discord_client_id ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="discord_client_secret">Client Secret</label></th>
					<td><input type="text" name="discord_client_secret" id="discord_client_secret" value="<?php echo esc_attr( $discord_client_secret ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="discord_redirect_uri">Redirect URI</label></th>
					<td><input type="text" readonly="true" name="discord_redirect_uri" id="discord_redirect_uri" value="<?php echo esc_attr( $discord_redirect_uri ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings', 'primary', 'discord_oauth_submit' ); ?>
		</form>
	</div>
	<?php
}

// Add Unlink Account button to user profile
add_action( 'show_user_profile', 'unlink_discord_account_button' );
add_action( 'edit_user_profile', 'unlink_discord_account_button' );
function unlink_discord_account_button( $user ) {
	if ( get_user_meta( $user->ID, 'discord_id', true ) ) {
		if ( current_user_can( 'edit_user', $user->ID ) && isset( $_GET['unlink_discord'] ) && $_GET['unlink_discord'] == $user->ID ) {
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
		$discord_client_id    = get_option( 'discord_client_id' );
		$discord_redirect_uri = get_option( 'discord_redirect_uri' );

		if ( $discord_client_id && $discord_redirect_uri ) {
			$discord_authorize_url = "https://discord.com/oauth2/authorize?client_id={$discord_client_id}&redirect_uri=" . urlencode( $discord_redirect_uri ) . '&response_type=code&scope=identify email';
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
