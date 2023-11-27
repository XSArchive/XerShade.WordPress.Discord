<?php
/**
 * The file that defines the Discord OAuth class.
 *
 * A class definition that includes base attributes and functions used for
 * authenticating WordPress users with Discord.
 *
 * @link       https://www.xershade.ca
 * @since      1.2.0
 *
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xershade-oauth-base.php';

/**
 * The Discord OAuth class.
 *
 * This is used to define base attributes and functions used for
 * authenticating WordPress users with Discord.
 *
 * @since      1.2.0
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 * @author     XerShade <xershade.ca@gmail.com>
 */
class DiscordOAuth extends OAuthBase {
    public function __construct() {
        $authorize_url = 'https://discord.com/api/oauth2/authorize';
        $token_url = 'https://discord.com/api/oauth2/token';

        parent::__construct($authorize_url, $token_url);
    }
    
    public function getAuthorizationUrl() {
        $state = wp_create_nonce($this->service_slug . '_login');
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
        );

        return $this->authorize_url . '?' . http_build_query($params);
    }

    public function getAccessToken($code) {
        $params = array(
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'authorization_code',
            'code'          => sanitize_text_field( wp_unslash( $_GET['code'] ) ),
            'redirect_uri'  => $this->redirect_uri,
            'scope'         => 'identify email',
        );

        $response = $this->makeRequest($this->token_url, 'POST', $params);

        $token_info = json_decode($response, true);

        if (isset($token_info['access_token'])) {
            // Fetch user information
            $user_info = $this->getUserInfo($token_info['access_token'], $params);

            // Merge user information with token information
            $token_info = array_merge($token_info, $user_info);
        }

        return $token_info;
    }

    protected function getUserInfo($access_token, $params) {
        $url = 'https://discord.com/api/v10/users/@me';
        $headers       = array(
            'Authorization' => 'Bearer ' . $access_token,
        );
    
        // Make the request to Discord API
        $response = $this->makeRequest($url, 'GET', $params, $headers);
    
        // Decode the JSON response
        $user_info = json_decode($response, true);
    
        return $user_info;
    }    
    
}