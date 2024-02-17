<?php

/**
 * The file that defines the base OAuth class.
 *
 * A class definition that includes base attributes and functions used for
 * authenticating WordPress users with an OAuth provider.
 *
 * @link       https://www.xershade.ca
 * @since      1.2.0
 *
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 */

/**
 * The OAuth base class.
 *
 * This is used to define base attributes and functions used for
 * authenticating WordPress users with an OAuth provider.
 *
 * @since      1.2.0
 * @package    Xershade_Discord_Integration
 * @subpackage Xershade_Discord_Integration/includes
 * @author     XerShade <xershade.ca@gmail.com>
 */

class OAuthBase {
    protected $service;
    protected $service_slug;
    protected $client_id;
    protected $client_secret;
    protected $redirect_uri;
    protected $authorize_url;
    protected $token_url;

    public function __construct($authorize_url, $token_url) {
        $this->service = str_replace( 'OAuth', '', get_class( $this ) );
        $this->service_slug = strtolower( $this->service );
        $this->client_id = get_option($this->service_slug . '_client_id');
        $this->client_secret = get_option($this->service_slug . '_client_secret');
        $this->redirect_uri = site_url( '/wp-admin/profile.php?oauth=' . $this->service_slug );
        $this->authorize_url = $authorize_url;
        $this->token_url = $token_url;
    }

    protected function makeRequest($url, $method, $params = array(), $headers = array()) {    
        $response = wp_remote_request($url, array(
            'method'  => $method,
            'body'    => $params,
            'headers' => $headers,
        ));
    
        if (is_wp_error($response)) {
            return false;
        }
    
        return wp_remote_retrieve_body($response);
    }

    public function getAuthorizationUrl() {
        $state = wp_create_nonce($this->service_slug . '_login');
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'state' => $state,
        );

        return $this->authorize_url . '?' . http_build_query($params);
    }

    public function getAccessToken($code) {
        $params = array(
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        );

        $response = $this->makeRequest($this->token_url, 'POST', $params);

        return json_decode($response, true);
    }

    function handle_oauth_callback() {
        if (isset($_GET['oauth']) && $_GET['oauth'] === $this->service_slug) {
            $code = isset($_GET['code']) ? $_GET['code'] : '';
            $state = isset($_GET['state']) ? $_GET['state'] : '';

            // Verify the state to prevent CSRF attacks
            if (wp_verify_nonce($state, $this->service_slug . '_login')) {    
                // Get user information along with the access token
                $token_info = $this->getAccessToken($code);
    
                if (isset($token_info['id'])) {
                    // Check if the user with Discord ID exists
                    $users = get_users(array(
                        'meta_key' => $this->service_slug . '_id',
                        'meta_value' => $token_info['id'],
                    ));
                    $user = (!empty($users)) ? $users[0] : false;
                    
                    if ($user) {
                        // User exists, log in
                        wp_set_auth_cookie($user->ID);
            
                        // Redirect to the admin area or any other page after authentication
                        wp_redirect(admin_url());
                        exit;
                    } elseif (is_user_logged_in()) {
                        // User is logged in, link Discord account
                        $user_id = get_current_user_id();
                        update_user_meta($user_id, $this->service_slug . '_id', $token_info['id']);
            
                        // Redirect to the profile page or any other page after linking
                        wp_redirect(admin_url('profile.php'));
                        exit;
                    } else {
                        // User doesn't exist, register a new user
                        $user_id = wp_create_user($this->service_slug . '_user_' . $token_info['id'], wp_generate_password(), $token_info['email']);
            
                        // Update user meta with Discord ID
                        update_user_meta($user_id, $this->service_slug . '_id', $token_info['id']);
            
                        // Log in the user
                        wp_set_auth_cookie($user_id);
            
                        // Redirect to the admin area or any other page after authentication
                        wp_redirect(site_url());
                        exit;
                    }
                }
            }
        }
    }

    function handle_oauth_linking() {
        $user_id = get_current_user_id();
    
        if (isset($_GET['unlink_' . $this->service_slug]) && $_GET['unlink_' . $this->service_slug] === '1') {
            delete_user_meta($user_id, $this->service_slug . '_id');
            wp_redirect(admin_url('profile.php'));
            exit;
        } elseif (isset($_GET['link_' . $this->service_slug]) && $_GET['link_' . $this->service_slug] === '1') {
            wp_redirect($this->getAuthorizationUrl());
            exit;
        }
    }

    function add_oauth_user_settings() {
        $user_id = get_current_user_id();
        $oauth_id = get_user_meta($user_id, $this->service_slug . '_id', true);
    
        if ($oauth_id) {
            echo '<p class="' . $this->service_slug . '-settings-link">Linked ' . $this->service . ' Account: ' . esc_html($oauth_id) . ' | <a href="' . esc_url(add_query_arg('unlink_' . $this->service_slug, '1')) . '">Unlink ' . $this->service . ' Account</a></p>';
        } else {
            echo '<p class="' . $this->service_slug . '-settings-link"><a href="' . esc_url(add_query_arg('link_' . $this->service_slug, '1')) . '">Link ' . $this->service . ' Account</a></p>';
        }
    }

    public function add_login_button() {
        echo '<p class="oauth-login-button ' . $this->service_slug . '-login-button"><a href="' . esc_url($this->getAuthorizationUrl()) . '">Login with ' . $this->service . '</a></p>';
    }

    // Add the menu item and page
    function add_settings_page() {
        add_menu_page(
            $this->service . ' OAuth Settings',
            $this->service . ' OAuth',
            'manage_options',
            $this->service_slug . '-oauth-settings',
            array( $this, 'add_settings_page_content' )
        );
    }

    // Settings page content
    function add_settings_page_content() {
        ?>
        <div class="wrap">
            <h2><?php echo $this->service; ?> OAuth Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->service_slug . '_oauth_settings');
                do_settings_sections($this->service_slug . '_oauth_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    function oauth_register_settings() {
        register_setting($this->service_slug . '_oauth_settings', $this->service_slug . '_client_id');
        register_setting($this->service_slug . '_oauth_settings', $this->service_slug . '_client_secret');
    
        add_settings_section(
            $this->service_slug . '_oauth_section',
            'OAuth Settings',
            array( $this, 'oauth_section_callback' ),
            $this->service_slug . '_oauth_settings'
        );
    
        add_settings_field(
            $this->service_slug . '_client_id',
            'Client ID',
            array( $this, 'oauth_client_id_callback'),
            $this->service_slug . '_oauth_settings',
            $this->service_slug . '_oauth_section'
        );
    
        add_settings_field(
            $this->service_slug . '_client_secret',
            'Client Secret',
            array( $this, 'oauth_client_secret_callback'),
            $this->service_slug . '_oauth_settings',
            $this->service_slug . '_oauth_section'
        );
    
        add_settings_field(
            $this->service_slug . '_redirect_uri',
            'Redirect Uri',
            array( $this, 'oauth_redirect_uri_callback'),
            $this->service_slug . '_oauth_settings',
            $this->service_slug . '_oauth_section'
        );
    }
    
    function oauth_section_callback() {
        echo '<p>Enter your OAuth settings below:</p>';
    }
    
    function oauth_client_id_callback() {
        echo "<input type='text' name='" . $this->service_slug . "_client_id' value='$this->client_id' />";
    }
    
    function oauth_client_secret_callback() {
        echo "<input type='text' name='" . $this->service_slug . "_client_secret' value='$this->client_secret' />";
    }
    
    function oauth_redirect_uri_callback() {
        echo "<input type='text' name='" . $this->service_slug . "_redirect_uri' value='$this->redirect_uri' readonly=true />";
    }

}
