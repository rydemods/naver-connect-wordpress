<?php
/*
Naver Connect Setting page
*/

$updatednaver = "normal";

if(isset($_POST['naver_update_options'])) {
	if($_POST['naver_update_options'] == 'Y') {
    foreach($_POST AS $k => $v){
      $_POST[$k] = stripslashes($v);
    }
		update_option("naver_connect", maybe_serialize($_POST));
		$updatednaver = 'update_success';
	}
}

if(!class_exists('CBXNaverSettings')) {
    class CBXNaverSettings {
        static function naver_Options_Page() {
          $domain = get_option('siteurl');
          $domain = str_replace(array('http://', 'https://'), array('',''), $domain);
          $domain = str_replace('www.', '', $domain);
          $a = explode("/",$domain);
          $domain = $a[0];
            ?>

            <div class="wrap">
            <div id="newnaver-options">
            <div id="newnaver-title"><h2>Naver Connect Settings</h2></div>
            <?php
            global $updatednaver;
            if($updatednaver == 'update_success')
                $message =__('Configuration updated', 'naver-connect') . "<br />";
            else if($updatednaver == 'update_failed')
                $message =__('Error while saving options', 'naver-connect') . "<br />";
            else
                $message = '';

            if($message != "") {
            ?>
                <div class="updated"><strong><p><?php
                echo $message;
                ?></p></strong></div><?php
            } ?>

          <?php
          if (!function_exists('curl_init')) {
          ?>
            <div class="error"><strong><p><?php _e('Naver needs the CURL PHP extension. Contact your server adminsitrator!', 'naver-connect'); ?></p></strong></div>
          <?php
          }else{
            $version = curl_version();
            $ssl_supported= ($version['features'] & CURL_VERSION_SSL);
            if(!$ssl_supported){
            ?>
              <div class="error"><strong><p><?php _e('Protocol https not supported or disabled in libcurl. Contact your server adminsitrator!', 'naver-connect'); ?></p></strong></div>
            <?php
            }
          }
          if (!function_exists('json_decode')) {
            ?>
              <div class="error"><strong><p><?php _e('Naver needs the JSON PHP extension. Contact your server adminsitrator!', 'naver-connect'); ?></p></strong></div>
            <?php
          }
          ?>





            </div></div></div>
            <!--right end-->

            <!--left-->
            <div class="postbox-container" style="float:left;width: 100%;">
            <div class="metabox-holder">
            <div class="meta-box-sortabless">

            <!--setting-->
            <div id="newfb-setting" class="postbox">
            <h3 class="hndle"><?php _e('Settings', 'naver-connect'); ?></h3>
            <?php $naver_connect = maybe_unserialize(get_option('naver_connect')); ?>

            <form method="post" action="<?php echo get_bloginfo("wpurl"); ?>/wp-admin/options-general.php?page=naver-connect">
                <input type="hidden" name="naver_update_options" value="Y">

                <table class="form-table">
                    <tr>
                    <th scope="row"><?php _e('Naver App Client ID:', 'naver-connect'); ?></th>
                    <td>
                    <input type="text" name="naver_appid" value="<?php echo $naver_connect['naver_appid']; ?>" />
                    </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('Naver App Client Secret:', 'naver-connect'); ?></th>
                    <td>
                    <input type="text" name="naver_secret" value="<?php echo $naver_connect['naver_secret']; ?>" />
                    </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('New user prefix:', 'naver-connect'); ?></th>
                    <td>
                        <?php if(!isset($naver_connect['naver_user_prefix'])) $naver_connect['naver_user_prefix'] = 'Naver - '; ?>
                    <input type="text" name="naver_user_prefix" value="<?php echo $naver_connect['naver_user_prefix']; ?>" />
                    </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('Fixed redirect url for login:', 'naver-connect'); ?></th>
                    <td>
                        <?php if(!isset($naver_connect['naver_redirect'])) $naver_connect['naver_redirect'] = 'auto'; ?>
                    <input type="text" name="naver_redirect" value="<?php echo $naver_connect['naver_redirect']; ?>" />
                    </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('Fixed redirect url for register:', 'naver-connect'); ?></th>
                        <td>
                            <?php if(!isset($naver_connect['naver_redirect_reg'])) $naver_connect['naver_redirect_reg'] = 'auto'; ?>
                             <input type="text" name="naver_redirect_reg" value="<?php echo $naver_connect['naver_redirect_reg']; ?>" />
                        </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('Load button stylesheet:', 'naver-connect'); ?></th>
                    <td>
                        <?php if(!isset($naver_connect['naver_load_style'])) $naver_connect['naver_load_style'] = 1; ?>
                        <input name="naver_load_style" id="naver_load_style_yes" value="1" type="radio" <?php if(isset($naver_connect['naver_load_style']) && $naver_connect['naver_load_style']){?> checked <?php } ?>> Yes  &nbsp;&nbsp;&nbsp;&nbsp;
                        <input name="naver_load_style" id="naver_load_style_no" value="0" type="radio" <?php if(isset($naver_connect['naver_load_style']) && $naver_connect['naver_load_style'] == 0){?> checked <?php } ?>> No
                    </td>
                    </tr>

                <tr>
                    <th scope="row"><?php _e('Login button:', 'naver-connect'); ?></th>
                    <td>
                  <?php if(!isset($naver_connect['naver_login_button'])) $naver_connect['naver_login_button'] = '<span class="naver-login">'.__('Connect Naver', 'naver-connect').'</span>'; ?>
                      <textarea cols="83" rows="3" name="naver_login_button"><?php echo $naver_connect['naver_login_button']; ?></textarea>
                    </td>
                    </tr>

                <tr>
                    <th scope="row"><?php _e('Link account button:', 'naver-connect'); ?></th>
                    <td>
                  <?php if(!isset($naver_connect['naver_link_button'])) $naver_connect['naver_link_button'] = '<span class="naver-login">'.__('Connect Naver', 'naver-connect').'</span>'; ?>
                      <textarea cols="83" rows="3" name="naver_link_button"><?php echo $naver_connect['naver_link_button']; ?></textarea>
                    </td>
                    </tr>

                <tr>
                    <th scope="row"><?php _e('Unlink account button:', 'naver-connect'); ?></th>
                    <td>
                  <?php if(!isset($naver_connect['naver_unlink_button'])) $naver_connect['naver_unlink_button'] = '<span class="naver-login-disconnect">'.__('Disconnect Naver', 'naver-connect').'</span>'; ?>
                      <textarea cols="83" rows="3" name="naver_unlink_button"><?php echo $naver_connect['naver_unlink_button']; ?></textarea>
                    </td>
                    </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                         <p class="submit">
                        <input style="margin-left: 10%;" type="submit" name="Submit" value="<?php _e('Save Changes', 'naver-connect'); ?>" />
                      </p>
                    </td>
                    </tr>
                </table>

                 
            </form>
            </div>
            <!--setting end-->

            <!--others-->
            <!--others end-->

            </div></div></div>
            <!--left end-->

            </div>
            </div>
            <?php
        }

        //shows menus in setting and renders setting for this plugin
        function Naver_Menu() {
            add_options_page(__('Naver Connect'), __('Naver Connect'), 'manage_options', 'naver-connect', array(__CLASS__,'naver_Options_Page'));
        }

    } //end class CBXNaverSettings
}//check if class exists
?>
