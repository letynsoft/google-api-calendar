<?php
add_filter('gac_auth_config', function ($config) {
  $config['redirect_uris'] = array(add_query_arg('gac_return', 'true', get_admin_url(null, 'options-general.php')));
  return $config;
});
add_action('admin_init', function () {
  if(!empty($_REQUEST['gac_return']) && $_REQUEST['gac_return'] === 'true' && isset($_REQUEST['code'])) {
    //TODO: Handle errors?
    $url = null;
    $client = gac_getClient(null, $url, $_REQUEST['code']);
    delete_transient('gac_calendar_user_colors');
    wp_redirect(get_admin_url(null, 'options-general.php'));
    die();
  } else if (!empty($_REQUEST['gac_clear_token'])) {
    if(isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'gac_clear_token')) {
      $client = gac_getClient(null);
      if($client) {
        $state = $client -> revokeToken($client->getAccessToken());
        if($state) {
          //TODO: Add a notice?
          update_option('gac_auth_token', null);
          wp_redirect(get_admin_url(null, 'options-general.php'));
          die();
        } else {
          //FIXME: Add a notice!
        }
      }
    }
  }
});
function google_api_client_fix_api_key($val) {
  return (string)$val;
}
add_action("admin_init", function () {
  add_settings_section( 'google_calendar_client_id', __('Google Calendar', 'ls-google-api-client'), function() { echo '<div id="theme_tracked_contact_form"></div>'; }, 'general' );
  register_setting("google_calendar_client_id", "google_calendar_client_id", 'google_api_client_fix_api_key');
  add_settings_field("google_calendar_client_id", __("Client ID", 'ls-google-api-client'), function () {
    $google_calendar_client_id = get_option('google_calendar_client_id', '');
    $i = 0;
    echo gac_only_field(array('type' => 'text', 'name' => 'google_calendar_client_id', 'id' => 'google_calendar_client_id'), array('google_calendar_client_id' => $google_calendar_client_id));
    echo ' <a href="'.'https://console.developers.google.com/flows/enableapi?apiid=calendar&keyType=CLIENT_SIDE&reusekey=true'.'">'.__('Add calendar API to exiting Google project / Create new').'</a>';
    //FIXME: Add help!
  }, "general", 'google_calendar_client_id');
  register_setting("google_calendar_client_secret", "google_calendar_client_secret", 'google_api_client_fix_api_key');
  add_settings_field("google_calendar_client_secret", __("Client Secret", 'ls-google-api-client'), function () {
    $google_calendar_client_secret = get_option('google_calendar_client_secret', '');
    $i = 0;
    echo gac_only_field(array('type' => 'text', 'name' => 'google_calendar_client_secret'), array('google_calendar_client_secret' => $google_calendar_client_secret));
    if(!empty($google_calendar_client_secret)) {
      $authUrl = null;
      if(!gac_getClient(null, $authUrl)) {
        echo ' <a href="'.htmlspecialchars($authUrl, ENT_QUOTES).'">Authenticate</a>';
      } else {
        echo ' <a href="'.wp_nonce_url(add_query_arg('gac_clear_token', 'true', get_admin_url(null, 'options-general.php')), 'gac_clear_token').'">Deauthenticate</a>';
      }
    }
  }, "general", 'google_calendar_client_id');
});
add_filter('whitelist_options', function ($opts) {
  $opts['general'][] = 'google_calendar_client_id';
  $opts['general'][] = 'google_calendar_client_secret';
  return $opts;
});


?>
