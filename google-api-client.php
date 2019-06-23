<?php
/**
 * Plugin Name:       Google Calendar
 * Description:       Book an appointment for an emploees, based on their google calendar (bank holidays, employee-off, ...).
 * Version:           2.0.0
 * Author:            LetynSOFT
 * Author URI:        http://letynsoft.com
 * Text Domain:       ls-google-api-client
 * Domain Path:       /languages
*/
//FIXME: Change the calendar behavior, removing the need to setup days and procedures for calendar
//TODO: gac_field: Implement checkboxes, radio
//TODO: Refactor all the code to use gac_field
//TEST: Add caching of all the requests to google! (flush them appropriately)
include_once(dirname(__FILE__).'/vendor/autoload.php');
foreach(array('authentication.php', 'booking.php', 'calendar-admin.php', 'calendar-form.php', 'calendar-shortcode.php', 'taxonomy-procedure.php', 'users.php', 'visitors.php') as $fn) {
  require(dirname(__FILE__).'/parts/'.$fn);
}
$myUpdateChecker = \Puc_v4_Factory::buildUpdateChecker(
  'https://letynsoft.com/wp_plugins/google-api-client/details.json',
  __FILE__,
  'google-api-client'
);
add_action( 'plugins_loaded', function () {
  load_plugin_textdomain( 'ls-google-api-client', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
});
function gac_setup_date_functions() {
  $lang = get_locale();
  if(!setlocale(LC_ALL, $lang)) {
    setlocale(LC_ALL, $lang.'.utf8');
  }
  $timezone = get_option('timezone_string', '');
  if(!empty($timezone)) {
    date_default_timezone_set($timezone);
  }
}
add_action('init', function () {
  $items = apply_filters('gac_calendar_post_types', array( ));
  foreach($items as $item) {
    $o = register_post_type( $item['slug'],
      apply_filters( "register_post_type_divi_child_".$item['slug'], array(
        'labels' => array(
          'name'          => $item['name'],
          'singular_name'     => $item['singular_name'],
          'menu_name'             => $item['name'],
          'all_items'             => sprintf( __( 'All %s', 'ls-google-api-client' ), $item['name'] ),
          'add_new'         => __( 'Add New', 'ls-google-api-client' ),
          'add_new_item'      => sprintf( __( 'Add %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'edit'          => __( 'Edit', 'ls-google-api-client' ),
          'edit_item'       => sprintf( __( 'Edit %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'new_item'        => sprintf( __( 'New %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'view'          => sprintf( __( 'View %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'view_item'       => sprintf( __( 'View %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'search_items'      => sprintf( __( 'Search %s', 'ls-google-api-client' ), $item['name'] ),
          'not_found'       => sprintf( __( 'No %s found', 'ls-google-api-client' ), $item['name'] ),
          'not_found_in_trash'  => sprintf( __( 'No %s found in trash', 'ls-google-api-client' ), $item['name'] ),
          'parent'        => sprintf( __( 'Parent %s', 'ls-google-api-client' ), $item['singular_name'] )
        ),
        //'description' => ,
        'public' => !empty($item['public']),
        'show_ui' => true,
        'show_in_admin_bar' => !empty($item['show_in_admin_bar']),
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => !empty($item['hierarchical']),
        'rewrite' => !empty($item['rewrite']) ? (!empty($item['rewrite_name']) ? array('slug' => $item['rewrite_name'], 'with_front' => false) : array('slug' => $item['slug'], 'with_front' => false)) : false,
        'query_var' => !empty($item['public']),
        'supports' => $item['supports'],
        'has_archive' => !empty($item['has_archive']),
        'show_in_nav_menus' => !empty($item['show_in_nav_menus']),
      ) )
    );
  }
  $taxonomies = apply_filters('gac_calendar_taxonomies', array());
  foreach($taxonomies as $item) {
    register_taxonomy($item['slug'], $item['post_types'],
      apply_filters( "register_post_type_divi_child_".$item['slug'], array(
        'labels' => array(
          'name'          => $item['name'],
          'singular_name'     => $item['singular_name'],
          'menu_name'             => $item['name'],
          'all_items'             => sprintf( __( 'All %s', 'ls-google-api-client' ), $item['name'] ),
          'add_new'         => __( 'Add New', 'ls-google-api-client' ),
          'add_new_item'      => sprintf( __( 'Add %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'edit'          => __( 'Edit', 'ls-google-api-client' ),
          'edit_item'       => sprintf( __( 'Edit %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'new_item'        => sprintf( __( 'New %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'view'          => sprintf( __( 'View %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'view_item'       => sprintf( __( 'View %s', 'ls-google-api-client' ), $item['singular_name'] ),
          'search_items'      => sprintf( __( 'Search %s', 'ls-google-api-client' ), $item['name'] ),
          'not_found'       => sprintf( __( 'No %s found', 'ls-google-api-client' ), $item['name'] ),
          'not_found_in_trash'  => sprintf( __( 'No %s found in trash', 'ls-google-api-client' ), $item['name'] ),
          'parent'        => sprintf( __( 'Parent %s', 'ls-google-api-client' ), $item['singular_name'] )
        ),
        'public' => !empty($item['public']),
        'show_ui' => true,
        'show_in_nav_menus' => !empty($item['show_in_nav_menus']),
        'show_admin_column' => !empty($item['show_admin_column']),
        'hierarchical' => !empty($item['hierarchical']),
      )
    ));
  }
});
add_action( 'wp_enqueue_scripts', function () {
  wp_enqueue_script( 'gac-functions', plugins_url('/js/functions.js', __FILE__), array( 'jquery' ), '1.00', true );
  wp_enqueue_style( 'gac-styles', plugins_url('/css/style.css', __FILE__) );
});
function gac_wrap_term_form($html, $key, $title) {
  $begin = '<tr id="'.$key.'" class="'.implode(' ', $classes).'"><th>'.htmlspecialchars($title).'</th><td>';
  $end = '</td></tr>';
  if(!isset($_GET['tag_ID'])) {
    $begin = '<div class="form-field term-'.$key.'-wrap '.implode(' ', $classes).'"><label>'.htmlspecialchars($title).'</label>';
    $end = '</div>';
  }
  echo $begin.$html.$end;
}

function gac_getGoogleTime($time) {
  $t = $time->getDate();
  if($t) {
    return strToTime($t);
  }
  $t = $time->getDateTime();
  if($t) {
    return strToTime($t.' '.$time->getTimeZone());
  }
  return -1;
}
function gac_array_flatten($array) {
  if (!is_array($array)) {
    return false;
  }
  $result = array();
  foreach ($array as $key => $value) {
    if (is_array($value)) {
      $result = array_merge($result, gac_array_flatten($value));
    } else {
      $result[$key] = $value;
    }
  }
  return $result;
}
function gac_time_div() {
  return 60;
}
function gac_findCaller($service, $functionPath) {
  $path = explode('.', $functionPath);
  if(is_callable($service)) {
    $service = $service();
  }
  $current = $service;
  foreach($path as $item) {
    if(isset($current->$item)) {
      $current = $current->$item;
    } else if (is_callable(array($current, $item))) {
      return array($current, $item);
    }
  }
  return null;
}
function gac_cache_prefix($calendarId, $params = array()) {
  $md5 = '';
  if(!empty($params)) {
    $md5 = '_'.md5(implode(',', array_keys($params)).'_'.implode(',', $params));
  }
  return 'gac_calendar_'.$calendarId.$md5;
}
function gac_clear_cache($calendarIds, $time = null) {
  global $wpdb;
  foreach(array_filter((array)$calendarIds) as $calendarId) {
    $wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_gac_calendar_".esc_sql($calendarId)."%')" );
  }
}
function gac_disable_cache() {
  return false;
}
function gac_get_data($list_callback, $item_callback) {
  if(is_array($list_callback)) {
    $list_callback = function ($opts) use ($list_callback) {
      if(!isset($list_callback[2])) $list_callback[2] = array('', array());
      $cachePrefix = gac_cache_prefix($list_callback[2][0], $list_callback[2][1]);
      if(!gac_disable_cache()) {
        $cache = get_transient($cachePrefix);
        if($cache) {
          return $cache;
        }
      }
      if(!empty($opts)) $list_callback[2][1] = array_merge($opts, $list_callback[2][1]);
      $caller = gac_findCaller($list_callback[0], $list_callback[1]);
      if($caller) {
        $o = call_user_func_array($caller, $list_callback[2]);
        set_transient($cachePrefix, $o, 12 * HOUR_IN_SECONDS);
        return $o;
      }
      return null;
    };
  }
  $result = array();
  $list = call_user_func($list_callback, array());
  while($list) {
    try {
      foreach ($list->getItems() as $item) {
        $o = call_user_func($item_callback, $item);
        if ($o) {
          $result[] = $o;
        }
      }
    } catch (Exception $ex) {
      var_dump($ex);
    }
    $pageToken = $list->getNextPageToken();
    if ($pageToken) {
      $optParams = array('pageToken' => $pageToken);
      $list = call_user_func($list_callback, $optParams);
    } else {
      break;
    }
  }
  return $result;
}
/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function gac_getClient($class = 'Google_Service_Calendar', &$authUrl = null, $code = null, $ignore_cache = false) {
  static $cache = array(), $client = null;

  if(!$ignore_cache) {
    if(isset($cache[$class])) {
      return $cache[$class];
    } else if (!empty($client)) {
      return new $class($client);
    }
  }
  $httpClient = new GuzzleHttp\Client([
    'timeout' => 10,
    'connect_timeout' => 10
  ]);
  $client = new Google_Client();
  $client->setHttpClient($httpClient);
  $client->setApplicationName('Google Calendar API PHP Quickstart');
  $client->setScopes(array(Google_Service_Calendar::CALENDAR_EVENTS, Google_Service_Calendar::CALENDAR_READONLY));
  $cid = get_option('google_calendar_client_id', null);
  $sec = get_option('google_calendar_client_secret', null);
  if(empty($cid) || empty($sec)) {
    return null;
  }
  $config = apply_filters('gac_auth_config', array('client_id' => $cid, 'client_secret' => $sec));
  $client->setAuthConfig($config);
  $client->setAccessType('offline');
  $client->setPrompt('select_account consent');

  // Load previously authorized token from a file, if it exists.
  // The file token.json stores the user's access and refresh tokens, and is
  // created automatically when the authorization flow completes for the first
  // time.
  $token = get_option('gac_auth_token', null);
  if (!empty($token)) {
    $client->setAccessToken($token);
  }

  // If there is no previous token or it's expired.
  if ($client->isAccessTokenExpired()) {
    // Refresh the token if possible, else fetch a new one.
    if ($client->getRefreshToken()) {
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    } else if (!empty($code)) {
      // Request authorization from the user.

      // Exchange authorization code for an access token.
      $accessToken = $client->fetchAccessTokenWithAuthCode($code);
      // Check to see if there was an error.
      if (array_key_exists('error', $accessToken)) {
        $authUrl = $client->createAuthUrl();
        //TODO: Throw an exception insted
        return null;
      }
      $client->setAccessToken($accessToken);

    } else {
      $authUrl = $client->createAuthUrl();
      //TODO: Throw an exception insted
      return null;
    }
    // Save the token to a file.
    update_option('gac_auth_token', $client->getAccessToken());
  }
  if(!empty($class)) {
    $cache[$class] = new $class($client);
    return $cache[$class];
  }
  return $client;
}
function gac_field($field, $values, $name_prefix = '') {
  $str = '';
  $str .=  '<li class="field-'.$field['name'].' type-'.$field['type'].'">';
  if(!in_array($field['type'], apply_filters('gac_field_disable_label', array('checkboxes', 'radio')))) {
    $str .= '<label>';
  }
  if(isset($field['label'])) {
    $str .= '<span class="label-container">'.$field['label'].'</span>';
  }
  $str .= gac_only_field($field, $values, $name_prefix);
  if(!in_array($field['type'], apply_filters('gac_field_disable_label', array('checkboxes', 'radio')))) {
    $str .= '</label>';
  }
  if(!empty($field['note'])) {
    $str .= '<p class="note">'.implode('</p><p class="note">', (array)$field['note']).'</p>';
  }
  $str .= '</li>';
  return $str;
}
function gac_only_field($field, $values, $name_prefix = '') {
  $str = '';
  if(isset($field['if'])) {
    $field['data-if'] = !empty($name_prefix) ? array_combine(array_map(function ($key) use ($name_prefix) {
      return $name_prefix.'['.$key.']';
    }, array_keys($field['if'])), $field['if']) : $field['if'];
    unset($field['if']);
    if(!isset($field['data-if']['_condition_'])) {
      $field['data-if']['_condition_'] = 'and';
    }
  }
  if(!isset($field['classes'])) {
    $field['classes'] = array();
  } else if(!is_array($field['classes'])) {
    $field['classes'] = (array)$field['classes'];
  }
  $field['classes'][] = 'gac-form-control';
  $field['class'] = implode(' ', $field['classes']);
  unset($field['classes']);
  $name_w_prefix = !empty($name_prefix) ? $name_prefix.'['.$field['name'].']' : $field['name'];
  switch($field['type']) {
    case 'checkbox':
      if($values[$field['name']] === $field['value']) {
        $field['checked'] = true;
      }
      $values[$field['name']] = $field['value'];
    case 'user_meta':
      if($field['type'] === 'user_meta') {
        if(!empty($field['meta_key'])) {
          $current_user_id = get_current_user_id();
          $values[$field['name']] = get_user_meta($current_user_id, $field['meta_key'], true);
        }
      }
    case 'text':
    case 'email':
    case 'number':
    case 'url':
    case 'tel':
    case 'time':
    case 'submit':
    case 'date':
      $str .= '<input '.gac_metabox_form_fields_print_args(
        array_merge($field, array(
          'type' => $field['type'],
          'name' => $name_w_prefix,
          'value' => isset($values[$field['name']]) ? htmlspecialchars($values[$field['name']], ENT_QUOTES) : '',
        )),
        array('id', 'class', 'title', 'type', 'name', 'value', 'required', 'readonly', 'disabled', 'checked', 'size', 'maxlength', 'placeholder', 'step', 'min', 'max', 'data-*')
      ).' />';
      break;
    case 'terms':
      if($field['type'] === 'terms') {
        $field['options'] = array('' => __('-- select --')) + array_column(array_map(function ($taxonomy) {
          return array($taxonomy->term_id, $taxonomy->name);
        }, get_terms(array('taxonomy' => $field['taxonomy']))), 1, 0);
        var_dump($field['options']);
      }
    case 'taxonomy':
      if($field['type'] === 'taxonomy') {
        $field['options'] = array_merge(array('' => __('-- select --')), array_column(array_map(function ($taxonomy) {
          return array($taxonomy->name, $taxonomy->label);
        }, get_taxonomies(array(), 'objects')), 1, 0));
      }
    case 'select':
      $str .= '<select '.gac_metabox_form_fields_print_args(
        array_merge($field, array(
          'name' => $name_w_prefix)),
          array('id', 'class', 'title', 'name', 'data-*', 'multiple', 'readonly', 'disabled', 'size')
          ).'>';
      foreach($field['options'] as $key => $option) {
        $str .= '<option value="'.htmlspecialchars($key, ENT_QUOTES).'"'.(isset($values[$field['name']]) && ((!empty($field['multiple']) && in_array($key, $values[$field['name']])) || (empty($field['multiple']) && $values[$field['name']] === $key)) ? ' selected="selected"' : '').'>'.$option.'</option>';
      }
      $str .= '</select>';
      break;
    case 'radio':
    case 'checkboxes':
      $type = $field['type'] === 'radio' ? 'radio' : 'checkbox';
      foreach($field['options'] as $key => $label) {
        $str .= '<label><input '.gac_metabox_form_fields_print_args(
          array_merge($field, array(
            'name' => $name_w_prefix.($type === 'checkbox' ? '[]' : ''),
            'value' => $key,
            'type' => $type,
            'checked' => in_array($key, (array)$values[$field['name']]),
          )),
          array('class', 'type', 'name', 'value', 'data-*', 'readonly', 'disabled', 'checked')).' />'.$label.'</label>';
      }
      break;
    default:
      $str .= apply_filters('gac_calendar_field_type_'.$field['type'], '', $field, $values, $name_w_prefix);
      break;
  }
  return $str;
}
function gac_metabox_form_fields_print_args($data, $fields) {
  $out = array();
  foreach($fields as $field) {
    if(strpos($field, '*') !== false) {
      foreach($data as $key => $val) {
        if(preg_match('/^'.implode('.*', array_map('preg_quote', explode('*', $field))).'/', $key)) {
          if(!is_bool($data[$key])) {
            $out[] = htmlspecialchars($key).'="'.htmlspecialchars(''.gac_metabox_form_fields_print_arg($val), ENT_QUOTES).'"';
          } else if (is_bool($data[$key])) {
            $out[] = htmlspecialchars($key);
          }
        }
      }
    } else if (!empty($data[$field]) || (isset($data[$field]) && ($data[$field] === 0 || $data[$field] === '0'))) {
      if(!is_bool($data[$field])) {
        $out[] = htmlspecialchars($field).'="'.htmlspecialchars(''.gac_metabox_form_fields_print_arg($data[$field]), ENT_QUOTES).'"';
      } else if (is_bool($data[$field])) {
        $out[] = htmlspecialchars($field);
      }
    } else if (isset($data[$field])) {
      if(is_string($data[$field])) {
        $out[] = htmlspecialchars($field);
      } else if (is_bool($data[$field])) {
        //is false, ignore
      }
    }
  }
  return implode(' ', $out);
}
function gac_metabox_form_fields_print_arg($arg) {
  if(is_scalar($arg)) {
    return $arg;
  }
  return json_encode($arg);
}
function gac_fields_update($pid, $data) {
  $fields = get_post_meta($pid, '_form_field', true);
  $user = wp_get_current_user();
  foreach($fields as $field) {
    if(isset($data[$field['name']]) && $field['type'] === 'user_meta') {
      if(!empty($data[$field['name']])) {
        update_user_meta($user->ID, $field['meta_key'], $data[$field['name']]);
      } else {
        delete_user_meta($user->ID, $field['meta_key']);
      }
    } else if(isset($data[$field['name']]) && $field['type'] === 'user_detail') {
      if(!empty($data[$field['name']])) {
        wp_update_user(array('ID' => $user->ID,  $field['detail_key'] => $data[$field['name']]));
      } else {
        //ignoring removing the field here!
      }
    }
  }
}
