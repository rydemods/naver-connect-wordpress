<?php

/*
Plugin Name: Naver Connect
Plugin URI: http://www.seoulwebdesign.com/
Description: This plugins helps you create naver login and register buttons. The login and register process only takes one click.
Version: 1.0.0
Author: Seoulwebdesign
License: GPL2
*/

/* 

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



global $new_naver_settings;
define('NEW_NAVER_LOGIN', 1);
define( 'NAVER_OAUTH_URL', "https://nid.naver.com/oauth2.0/" );

if (!defined('NEW_NAVER_LOGIN_PLUGIN_BASENAME')) define('NEW_NAVER_LOGIN_PLUGIN_BASENAME', plugin_basename(__FILE__));

$new_naver_settings = maybe_unserialize(get_option('naver_connect'));

if(!function_exists('cbx_uniqid')){
    function cbx_uniqid(){
        if(isset($_COOKIE['cbx_uniqid'])){
            if(get_site_transient('n_'.$_COOKIE['cbx_uniqid']) !== false){
                return $_COOKIE['cbx_uniqid'];
            }
        }
        $_COOKIE['cbx_uniqid'] = uniqid('naver', true);
        setcookie('cbx_uniqid', $_COOKIE['cbx_uniqid'], time() + 3600, '/');
        set_site_transient('n_'.$_COOKIE['cbx_uniqid'], 1, 3600);
        
        return $_COOKIE['cbx_uniqid'];
    }
}

/*
Loading style for buttons
*/

function naver_connect_stylesheet() {

  wp_register_style('naver_connect_stylesheet', plugins_url('buttons/naver-btn.css', __FILE__));
  wp_enqueue_style('naver_connect_stylesheet');
}

if (!isset($new_naver_settings['naver_load_style'])) $new_naver_settings['naver_load_style'] = 1;


if ($new_naver_settings['naver_load_style']) {
  add_action('wp_enqueue_scripts', 'naver_connect_stylesheet');
  add_action('login_enqueue_scripts', 'naver_connect_stylesheet');
  add_action('admin_enqueue_scripts', 'naver_connect_stylesheet');
}

/*
Creating the required table on installation
*/

function naver_connect_install() {

  global $wpdb;
  $table_name = $wpdb->prefix . "naversocial_users";
  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
    `ID` int(11) NOT NULL,
    `type` varchar(20) NOT NULL,
    `identifier` varchar(100) NOT NULL,
    KEY `ID` (`ID`,`type`)
  );";
  require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}
register_activation_hook(__FILE__, 'naver_connect_install');

/*
Adding query vars for the WP parser
*/

function new_naver_add_query_var() {

  global $wp;
  $wp->add_query_var('editProfileRedirect');
  $wp->add_query_var('loginNaver');
  $wp->add_query_var('loginNaverdoauth');
}
add_filter('init', 'new_naver_add_query_var');

/* -----------------------------------------------------------------------------
Main function to handle the Sign in/Register/Linking process
----------------------------------------------------------------------------- */

/*
Compatibility for older versions
*/

function new_naver_login_compat() {

  global $wp;
  if ($wp->request == 'loginNaver' || isset($wp->query_vars['loginNaver'])) {
    new_naver_login_action();
  }
}
add_action('parse_request', 'new_naver_login_compat');

/*
For login page
*/

function new_naver_login() {

  if (isset($_REQUEST['loginNaver']) && intval($_REQUEST['loginNaver']) == '1') {
    new_naver_login_action();
  }
}
add_action('login_init', 'new_naver_login');

//few naver api function written as procedural form

function get_naver_create_login_url(){
    global $new_naver_settings;

    //$redirect_url = new_naver_redirect_url();
    $redirect_url = new_naver_login_url();
    //$redirect_url = trim(urlencode($redirect_url));

    return NAVER_OAUTH_URL.'authorize?client_id='.$new_naver_settings['naver_appid'].'&response_type=code&redirect_uri='.$redirect_url.'&state='.get_naver_generate_state();
}

function get_naver_generate_state() {
    $mt = microtime();
    $rand = mt_rand();
    //$this->state = md5( $mt . $rand );
    return md5( $mt . $rand );
}

function naver_getAccessToken(){
    global $new_naver_settings;


    $data = array(); // variable initialization was missing
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, NAVER_OAUTH_URL.'token?client_id='.$new_naver_settings['naver_appid'].'&client_secret='.$new_naver_settings['naver_secret'].'&grant_type=authorization_code&code='.$_GET['code'].'&state='.$_GET['state']);
    curl_setopt($curl, CURLOPT_POST, 1); 
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true); 
    $retVar = curl_exec($curl); 
    curl_close($curl);
    $NHNreturns = json_decode($retVar);

    
    if(isset($NHNreturns->access_token)){

      return $NHNreturns;
    
    }

    return false;
    
   

  }


function naver_getUserProfile($retType = "JSON"){
    global $new_naver_settings;
    
    $access_token = get_site_transient( cbx_uniqid().'_naver_at');

    //if($this->getConnectState()){
    if($access_token){
      $data = array();
      $data['Authorization'] =$access_token->token_type.' '.$access_token->access_token;

      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, 'https://apis.naver.com/nidlogin/nid/getUserProfile.xml');
      curl_setopt($curl, CURLOPT_POST, 1); 
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Authorization: '.$data['Authorization']
      ));

      curl_setopt($curl, CURLOPT_RETURNTRANSFER,true); 
      $retVar = curl_exec($curl); 
      curl_close($curl);


      if($retType == "JSON"){
        $xml = new SimpleXMLElement($retVar);
    
        $xmlJSON = array();
        $xmlJSON['result']['resultcode'] = (string) $xml->result[0]->resultcode[0];
        $xmlJSON['result']['message'] = (string) $xml->result[0]->message[0];

        if($xml->result[0]->resultcode == '00'){
          foreach($xml->response->children() as $response => $k){
            $xmlJSON['response'][(string)$response] = (string) $k;
          }
        }

        return json_encode($xmlJSON);
      }else{
        return $retVar;
      }
    }else{
      return false;
    }
}

function new_naver_login_action() {
    global $wp, $wpdb, $new_naver_settings;

    
    if (isset($_GET['action']) && $_GET['action'] == 'unlink') {
        $user_info = wp_get_current_user();
        if ($user_info->ID) {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'naversocial_users
          WHERE ID = %d
          AND type = \'naver\'', $user_info->ID));
            set_site_transient($user_info->ID.'_new_naver_admin_notice',__('Your Naver profile is successfully unlinked from your account.', 'naver-connect'), 3600);
        }
        new_naver_redirect();
    }
    

    //var_dump($new_naver_settings);
    //exit();

    //var_dump(get_naver_create_login_url());

    //exit();

   

    //timestamp
    if (isset($_GET['state']) && isset($_GET['code'])) {

        
        //exit(); //we will check this state later

        if (isset($new_naver_settings['naver_redirect']) && $new_naver_settings['naver_redirect'] != '' && $new_naver_settings['naver_redirect'] != 'auto') {
            $_GET['redirect'] = $new_naver_settings['naver_redirect'];
        }

        if(isset($_GET['redirect'])){
          set_site_transient( cbx_uniqid().'_naver_r', $_GET['redirect'], 3600);
        }
        //echo 'get access toekn here';

       
        //$client->authenticate();
        //get access token
        $access_token = naver_getAccessToken();

        //var_dump($access_token);
        
        //set access token in db
        set_site_transient( cbx_uniqid().'_naver_at', $access_token, 3600);

        header('Location: ' . filter_var(new_naver_login_url() , FILTER_SANITIZE_URL));
        exit();


        

    }

    //get access token from db
    $access_token = get_site_transient( cbx_uniqid().'_naver_at');

    if ($access_token !== false) {
        //$client->setAccessToken($access_token);
        ////not sure what to do here
    }

    if (isset($_REQUEST['logout'])) {
        delete_site_transient( cbx_uniqid().'_naver_at');
        //$client->revokeToken();
        //have to apply naver base logout here 
    }

    //echo 'start3';
    
    //already have the access token, we will play to create the account and take action as need
    //if ($client->getAccessToken()) {
    if ($access_token) {

        //$u = $oauth2->userinfo->get();
        $u = naver_getUserProfile();

        $profile = json_decode($u);
        $u       = $profile->response; 


        //set_site_transient( cbx_uniqid().'_naver_at', $client->getAccessToken(), 3600);

        // These fields are currently filtered through the PHP sanitize filters.

        // See http://www.php.net/manual/en/filter.filters.sanitize.php


        $email = filter_var($u->email, FILTER_SANITIZE_EMAIL); //get email from user data

        $ID = $wpdb->get_var($wpdb->prepare('SELECT ID FROM ' . $wpdb->prefix . 'naversocial_users WHERE type = "naver" AND identifier = "%s"', $u->enc_id));

        if (!get_user_by('id', $ID)) {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . 'naversocial_users WHERE ID = "%s"', $ID));

            $ID = null;
        }

        if (!is_user_logged_in()) {
            if ($ID == NULL) { // Register

                $ID = email_exists($email);
                //non logged in and naver connected before
                if ($ID == false) { // Real register

                    require_once (ABSPATH . WPINC . '/registration.php');
                    $random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);
                    
                    if (!isset($new_naver_settings['naver_user_prefix'])) $new_naver_settings['naver_user_prefix'] = 'Naver - ';
                    $sanitized_user_login = sanitize_user($new_naver_settings['naver_user_prefix'] . $u->nickname);
                    
                    if (!validate_username($sanitized_user_login)) {
                        $sanitized_user_login = sanitize_user('naver' . $user_profile['id']);
                    }
                    
                    $defaul_user_name = $sanitized_user_login;
                    
                    $i = 1;
                    
                    while (username_exists($sanitized_user_login)) {
                        $sanitized_user_login = $defaul_user_name . $i;
                        $i++;
                    }

                    $ID = wp_create_user($sanitized_user_login, $random_password, $email);
                    if (!is_wp_error($ID)) {
                        wp_new_user_notification($ID, $random_password);
                        $user_info = get_userdata($ID);
                        wp_update_user(array(
                            'ID' => $ID,
                            'display_name'  => $u->nickname,
                            'first_name'    => $u->nickname,
                            'last_name'     => $u->nickname
                        ));

                        update_user_meta($ID, 'new_naver_default_password', $user_info->user_pass);
                        update_user_meta($ID, 'naver_profile_picture', $u->profile_image);
                        
                        do_action('cbx_naver_user_registered', $ID, $u);
                    } else {
                        return;
                    }
                }

                //logged in but connecting first time or again.
                if ($ID) {
                    $wpdb->insert($wpdb->prefix . 'naversocial_users', array(
                        'ID'            => $ID,
                        'type'          => 'naver',
                        'identifier'    => $u->enc_id
                    ) , array(
                        '%d',
                        '%s',
                        '%s'
                    ));
                }

                if (isset($new_naver_settings['naver_redirect_reg']) && $new_naver_settings['naver_redirect_reg'] != '' && $new_naver_settings['naver_redirect_reg'] != 'auto') {
                    set_site_transient( cbx_uniqid().'_naver_r', $new_naver_settings['naver_redirect_reg'], 3600);
                }
            } //end register, non logged in 

            //let's process if logged in user is connecting naver  
            if ($ID) { // Login

                $secure_cookie = is_ssl();
                $secure_cookie = apply_filters('secure_signon_cookie', $secure_cookie, array());
                global $auth_secure_cookie; // XXX ugly hack to pass this to wp_authenticate_cookie

                $auth_secure_cookie = $secure_cookie;
                wp_set_auth_cookie($ID, true, $secure_cookie);
                $user_info = get_userdata($ID);
                do_action('wp_login', $user_info->user_login, $user_info);
                do_action('cbx_naver_user_logged_in', $ID, $u);

                update_user_meta($ID, 'naver_profile_picture', $u->profile_image);
            }
        } else {
            if (new_naver_is_user_connected()) { // It was a simple login


            } elseif ($ID === NULL) { // Let's connect the account to the current user!

                $current_user = wp_get_current_user();
                $wpdb->insert($wpdb->prefix . 'naversocial_users', array(
                    'ID' => $current_user->ID,
                    'type' => 'naver',
                    'identifier' => $u->enc_id
                ) , array(
                    '%d',
                    '%s',
                    '%s'
                ));

                do_action('cbx_naver_user_account_linked', $ID, $u);
                $user_info = wp_get_current_user();
                set_site_transient($user_info->ID.'_new_naver_admin_notice',__('Your Naver profile is successfully linked with your account. Now you can sign in with Naver easily.', 'naver-connect'), 3600);
            } else {
                $user_info = wp_get_current_user();
                set_site_transient($user_info->ID.'_new_naver_admin_notice',__('This Naver profile is already linked with other account. Linking process failed!', 'naver-connect'), 3600);
            }
        }
    } else {
    
        


        if (isset($new_naver_settings['naver_redirect']) && $new_naver_settings['naver_redirect'] != '' && $new_naver_settings['naver_redirect'] != 'auto') {
            $_GET['redirect'] = $new_naver_settings['naver_redirect'];
        }

        if (isset($_GET['redirect'])) {
            set_site_transient( cbx_uniqid().'_naver_r', $_GET['redirect'], 3600);
        }

        $redirect = get_site_transient( cbx_uniqid().'_naver_r');

        if ($redirect || $redirect == new_naver_login_url()) {
            $redirect = site_url();
            set_site_transient( cbx_uniqid().'_naver_r', $redirect, 3600);
            //var_dump($redirect);
        }

        //var_dump(get_naver_create_login_url());
        header('LOCATION: ' . get_naver_create_login_url());
        exit;
    }

    //echo 'hello5';
    new_naver_redirect();
}



/*
Is the current user connected the Facebook profile?
*/

function new_naver_is_user_connected() {

  global $wpdb;
  $current_user = wp_get_current_user();
  $ID = $wpdb->get_var($wpdb->prepare('
    SELECT identifier FROM ' . $wpdb->prefix . 'naversocial_users WHERE type = "naver" AND ID = "%d"
  ', $current_user->ID));
  if ($ID === NULL) return false;
  return $ID;
}

function new_naver_get_user_access_token($id) {

  return get_user_meta($id, 'naver_user_access_token', true);
}

/*
Connect Field in the Profile page
*/

function new_add_naver_connect_field() {

  global $new_is_social_header;

  //if(new_naver_is_user_connected()) return;
  if ($new_is_social_header === NULL) {
?>
    <h3>Naver connect</h3>
    <?php
    $new_is_social_header = true;
  }
?>
  <table class="form-table">
    <tbody>
      <tr>	
        <th>
        </th>	
        <td>
          <?php
              if (new_naver_is_user_connected()) {
                echo '<p>'.__('Naver is connected, click to disconnect', 'naver-connect').'</p>';
                echo '<p>'.new_naver_unlink_button().'</p>';
              } else {
                echo '<p>'.__('Naver is not connected, click to connect', 'naver-connect').'</p>';
                echo '<p>'.new_naver_link_button().'</p>';
              }
            ?>
        </td>
      </tr>
    </tbody>
  </table>
  <?php
}


add_action('profile_personal_options', 'new_add_naver_connect_field');

function new_add_naver_login_form() {

?>
  <script>
  if(jQuery.type(has_social_form) === "undefined"){
    var has_social_form = false;
    var socialLogins = null;
  }
  jQuery(document).ready(function(){
    (function($) {
      if(!has_social_form){
        has_social_form = true;
        var loginForm = $('#loginform,#registerform,#front-login-form, #bp-login-widget-form, #naver-login-widget-form');
        socialLogins = $('<div class="newsociallogins" style="text-align: center;"><div style="clear:both;"></div></div>');
        if(loginForm.find('input').length > 0)
          loginForm.prepend("<h3 style='text-align:center;'>OR</h3>");
        loginForm.prepend(socialLogins);
      }
      if(!window.naver_added){
        socialLogins.prepend('<?php echo addslashes(preg_replace('/^\s+|\n|\r|\s+$/m', '', new_naver_sign_button())); ?>');
        window.naver_added = true;
      }
    }(jQuery));
  });
  </script>
  <?php
}

add_action('login_form', 'new_add_naver_login_form');
add_action('bp_before_login_widget_loggedout','new_add_naver_login_form');
add_action('naver_before_login_widget_loggedout','new_add_naver_login_form'); //used in custom naver login widget

add_action('register_form', 'new_add_naver_login_form');
add_action('bp_sidebar_login_form', 'new_add_naver_login_form');
add_filter('get_avatar', 'new_naver_insert_avatar', 5, 5);

function new_naver_insert_avatar($avatar = '', $id_or_email, $size = 96, $default = '', $alt = false) {

  $id = 0;
  if (is_numeric($id_or_email)) {
    $id = $id_or_email;
  } else if (is_string($id_or_email)) {
    $u = get_user_by('email', $id_or_email);
    $id = $u->id;
  } else if (is_object($id_or_email)) {
    $id = $id_or_email->user_id;
  }
  if ($id == 0) return $avatar;
  $pic = get_user_meta($id, 'naver_profile_picture', true);
  if (!$pic || $pic == '') return $avatar;
  $avatar = preg_replace('/src=("|\').*?("|\')/i', 'src=\'' . $pic . '\'', $avatar);
  return $avatar;
}

add_filter('bp_core_fetch_avatar', 'new_naver_bp_insert_avatar', 3, 5); //buddypress integrate

function new_naver_bp_insert_avatar($avatar = '', $params, $id) {
    if(!is_numeric($id) || strpos($avatar, 'gravatar') === false) return $avatar;
    $pic = get_user_meta($id, 'naver_profile_picture', true);
    if (!$pic || $pic == '') return $avatar;
    $avatar = preg_replace('/src=("|\').*?("|\')/i', 'src=\'' . $pic . '\'', $avatar);
    return $avatar;
}

/*
Options Page
*/
require_once (trailingslashit(dirname(__FILE__)) . "naver-settings.php");
if (class_exists('CBXNaverSettings')) {

  $naversettings = new CBXNaverSettings();
    //var_dump($naversettings);
    //exit();
  //if (isset($cbxfbsettings)) {
    //  exit();
    add_action('admin_menu', array(&$naversettings, 'Naver_Menu'  ) , 1);
  //}
}

//add setting link in plugin listing page
add_filter('plugin_action_links', 'new_naver_plugin_action_links', 10, 2);

function new_naver_plugin_action_links($links, $file) {



  if ($file != NEW_NAVER_LOGIN_PLUGIN_BASENAME) return $links;
    //var_dump($file);
    //var_dump(NEW_NAVER_LOGIN_PLUGIN_BASENAME);
    //var_dump(menu_page_url('naver-connect', false));
  $settings_link = '<a href="' . menu_page_url('naver-connect', false) . '">' . esc_html(__('Naver Settings', 'naver-connect')) . '</a>';
  array_unshift($links, $settings_link);
  return $links;
}

/* -----------------------------------------------------------------------------
Miscellaneous functions
----------------------------------------------------------------------------- */

/**
 * Shortcode to show Naver login button
 *
 * @param $atts
 */
function shortcode_new_naver_sign_button( $atts ) {
    return  new_naver_sign_button();
}
add_shortcode( 'naverlogin', 'shortcode_new_naver_sign_button' );


/**
 * Shortcode to show naver unlink button
 *
 * @param $atts
 */
function shortcode_new_naver_linkunlink_button( $atts ) {
    //new_add_naver_connect_field();
    $output = '';
    if (new_naver_is_user_connected()) {
        $output .= '<p>'.__('Naver is connected, click to disconnect', 'naver-connect').'</p>';
        $output .= '<p>'.new_naver_unlink_button().'</p>';
    } else {
        $output .= '<p>'.__('Naver is not connected, click to connect', 'naver-connect').'</p>';
        $output .= '<p>'.new_naver_link_button().'</p>';
    }

    return $output;

}

add_shortcode( 'naverlinkunlink', 'shortcode_new_naver_linkunlink_button' );



function new_naver_sign_button() {

  global $new_naver_settings;
  return '<a href="' . new_naver_login_url() . (isset($_GET['redirect_to']) ? '&redirect=' . $_GET['redirect_to'] : '') . '" rel="nofollow">' . $new_naver_settings['naver_login_button'] . '</a><br />';
}

function new_naver_link_button() {

  global $new_naver_settings;
  return '<a href="' . new_naver_login_url() . '&redirect=' . new_naver_curPageURL() . '">' . $new_naver_settings['naver_link_button'] . '</a><br />';
}

function new_naver_unlink_button() {

  global $new_naver_settings;
  return '<a href="' . new_naver_login_url() . '&action=unlink&redirect=' . new_naver_curPageURL() . '">' . $new_naver_settings['naver_unlink_button'] . '</a><br />';
}




function new_naver_curPageURL() {

  $pageURL = 'http';
  if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
    $pageURL.= "s";
  }
  $pageURL.= "://";
  if ($_SERVER["SERVER_PORT"] != "80") {
    $pageURL.= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
  } else {
    $pageURL.= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
  }
  return $pageURL;
}

function new_naver_login_url() {

  return site_url('wp-login.php') . '?loginNaver=1';
}

function new_naver_redirect_url() {

    $redirect = get_site_transient( cbx_uniqid().'_naver_r');

    if (!$redirect || $redirect == '' || $redirect == new_naver_login_url()) {
        if (isset($_GET['redirect'])) {
            $redirect = $_GET['redirect'];
        } else {
            $redirect = site_url();
        }
    }
    //header('LOCATION: ' . $redirect);
    //delete_site_transient( cbx_uniqid().'_naver_r');
    //exit;

    return $redirect;
}

function new_naver_redirect() {
  
  $redirect = get_site_transient( cbx_uniqid().'_naver_r');

  if (!$redirect || $redirect == '' || $redirect == new_naver_login_url()) {
    if (isset($_GET['redirect'])) {
      $redirect = $_GET['redirect'];
    } else {
      $redirect = site_url();
    }
  }
  header('LOCATION: ' . $redirect);
  delete_site_transient( cbx_uniqid().'_naver_r');
  exit;
}

function new_naver_edit_profile_redirect() {

  global $wp;
  if (isset($wp->query_vars['editProfileRedirect'])) {
    if (function_exists('bp_loggedin_user_domain')) {
      header('LOCATION: ' . bp_loggedin_user_domain() . 'profile/edit/group/1/');
    } else {
      header('LOCATION: ' . self_admin_url('profile.php'));
    }
    exit;
  }
}
add_action('parse_request', 'new_naver_edit_profile_redirect');

function new_naver_jquery() {

  wp_enqueue_script('jquery');
}
add_action('login_form_login', 'new_naver_jquery');
add_action('login_form_register', 'new_naver_jquery');

/*
Session notices used in the profile settings
*/

function new_naver_admin_notice() {
  $user_info = wp_get_current_user();
  $notice = get_site_transient($user_info->ID.'_new_naver_admin_notice');
  if ($notice !== false) {
    echo '<div class="updated">
       <p>' . $notice . '</p>
    </div>';
    delete_site_transient($user_info->ID.'_new_naver_admin_notice');
  }
}

add_action('admin_notices', 'new_naver_admin_notice');
add_action('widgets_init', create_function('', 'return register_widget("Naver_Login_Widget");') );

/**
 * Naver Custom Login Widget customized from buddypress
 *
 * @since BuddyPress (1.9.0)
 */
class Naver_Login_Widget extends WP_Widget {

    /**
     * Constructor method.
     */
    public function __construct() {
        parent::__construct(
            false,
            _x( '(Naver) Log In', 'Title of the login widget', 'naverlogin' ),
            array(
                'description' => __( 'Show a Log In form to logged-out visitors, and a Log Out link to those who are logged in.', 'naverlogin' ),
                'classname' => 'widget_naver_login_widget widget',
            )
        );
    }

    /**
     * Display the login widget.
     *
     * @see WP_Widget::widget() for description of parameters.
     *
     * @param array $args Widget arguments.
     * @param array $instance Widget settings, as saved by the user.
     */
    public function widget( $args, $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : '';
        $title = apply_filters( 'widget_title', $title );

        echo $args['before_widget'];

        echo $args['before_title'] . esc_html( $title ) . $args['after_title']; ?>

        <?php if ( is_user_logged_in() ) : ?>

            <?php do_action( 'naver_before_login_widget_loggedin' ); ?>



            <div class="naver-login-widget-user-links">
                <div class="naver-login-widget-user-link"><?php echo '<a href="' . admin_url() . '">' . __('Site Admin') . '</a>' ; ?></div>
                <div class="naver-login-widget-user-logout"><a class="logout" href="<?php echo wp_logout_url( ); ?>"><?php _e( 'Log Out', 'naverlogin' ); ?></a></div>
            </div>

            <?php do_action( 'naver_after_login_widget_loggedin' ); ?>

        <?php else : ?>

            <?php do_action( 'naver_before_login_widget_loggedout' ); ?>

            <form name="naver-login-form" id="naver-login-widget-form" class="standard-form" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
                <label for="naver-login-widget-user-login"><?php _e( 'Username', 'naverlogin' ); ?></label>
                <input type="text" name="log" id="naver-login-widget-user-login" class="input" value="" />

                <label for="naver-login-widget-user-pass"><?php _e( 'Password', 'naverlogin' ); ?></label>
                <input type="password" name="pwd" id="naver-login-widget-user-pass" class="input" value=""  />

                <div class="forgetmenot"><label><input name="rememberme" type="checkbox" id="naver-login-widget-rememberme" value="forever" /> <?php _e( 'Remember Me', 'naverlogin' ); ?></label></div>

                <input type="submit" name="wp-submit" id="naver-login-widget-submit" value="<?php esc_attr_e( 'Log In', 'naverlogin' ); ?>" />

                <?php if ( get_option('users_can_register') ) : ?>

                    <span class="naver-login-widget-register-link">
                        <?php echo  '<a href="' . esc_url( wp_registration_url() ) . '">' . __('Register') . '</a>' ; ?>
                    </span>

                <?php endif; ?>

            </form>

            <?php do_action( 'naver_after_login_widget_loggedout' ); ?>

        <?php endif;

        echo $args['after_widget'];
    }

    /**
     * Update the login widget options.
     *
     * @param array $new_instance The new instance options.
     * @param array $old_instance The old instance options.
     * @return array $instance The parsed options to be saved.
     */
    public function update( $new_instance, $old_instance ) {
        $instance             = $old_instance;
        $instance['title']    = isset( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';

        return $instance;
    }

    /**
     * Output the login widget options form.
     *
     * @param $instance Settings for this widget.
     */
    public function form( $instance = array() ) {

        $settings = wp_parse_args( $instance, array(
            'title' => '',
        ) ); ?>

        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'naverlogin' ); ?>
                <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $settings['title'] ); ?>" /></label>
        </p>

    <?php
    }
}
