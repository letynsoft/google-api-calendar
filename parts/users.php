<?php
add_filter('gac_calendar_post_types', function ($post_types) {
  $post_types[] = array(
    'slug' => 'gac_users',
    'name' => __('Calendar Users', 'ls-google-api-client'),
    'singular_name' => __('Calendar User', 'ls-google-api-client'),
    'public' => false,
    'show_in_admin_bar' => false,
    'hierarchical' => false,
    'has_archive' => false,
    'supports' => array('title')
  );
  return $post_types;
});
add_action('add_meta_boxes', function () {
  $screens = ['gac_users'];
  foreach ($screens as $screen) {
    add_meta_box(
      'gac_metabox',           // Unique ID
      __('Calendar', 'ls-google-api-client'),  // Box title
      'gac_users_metabox_render',  // Content callback, must be of type callable
      $screen
    );
  }
});
add_action( 'save_post', function ( $post_id ) {
  if ( in_array(get_post_type($post_id), array('gac_users'), true) ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $parent_id = wp_is_post_revision( $post_id ) ) {
      $post_id = $parent_id;
    }
    update_post_meta( $post_id, '_calendarId', $_POST['_calendarId']);
    update_post_meta( $post_id, '_time_from', $_POST['_time_from']);
    update_post_meta( $post_id, '_time_to', $_POST['_time_to']);
    foreach(array('_calendarId_exclude') as $key) {
      $meta = get_post_meta( $post_id, $key );
      $categories = isset($_REQUEST[$key]) ? array_filter($_REQUEST[$key]) : null;
      if(!is_null($categories)) {
        $last_i = -1;
        foreach($categories as $k => $v) {
          $last_i = $k;
          if(isset($meta[$k])) {
            update_post_meta($post_id, $key, $v, $meta[$k]);
          } else {
            add_post_meta($post_id, $key, $v);
          }
        }
        for($i = $last_i + 1; $i < count($meta); $i++) {
          delete_post_meta($post_id, $key, $meta[$i]);
        }
      }
    }
  }
});
function gac_users_metabox_render() {
  $_user_id = get_the_id();
  $days = array(
    array(1, __('Monday', 'ls-google-api-client')),
    array(2,  __('Tuesday', 'ls-google-api-client')),
    array(3,  __('Wednesday', 'ls-google-api-client')),
    array(4,  __('Thursday', 'ls-google-api-client')),
    array(5,  __('Friday', 'ls-google-api-client')),
    array(6,  __('Saturday', 'ls-google-api-client')),
    array(7,  __('Sunday', 'ls-google-api-client')),
  );

  $key1 = '_time_from';
  $key2 = '_time_to';
  $time_from = get_post_meta($_user_id, $key1, true);
  $time_to = get_post_meta($_user_id, $key2, true);
  for($i = 1; $i <= 7; $i ++) {
    echo '<h3>'.$days[$i - 1][1].'</h3>';
    echo '<label>'.__('Start Time', 'ls-google-api-client');
    echo '<input type="time" name="'.htmlspecialchars($key1, ENT_QUOTES).'['.($i - 1).']" value="'.htmlspecialchars($time_from[$i - 1], ENT_QUOTES).'" /></label>';
    echo '<label>'.__('End Time', 'ls-google-api-client');
    echo '<input type="time" name="'.htmlspecialchars($key2, ENT_QUOTES).'['.($i - 1).']" value="'.htmlspecialchars($time_to[$i - 1], ENT_QUOTES).'" /></label>';
  }

  $service = gac_getClient();
  if(empty($service)) {
    return;
  }

  $current = get_post_meta(get_the_id(), '_calendarId', true);
  echo '<h3><label for="_calendarId">'.__('Calendar Name', 'ls-google-api-client').'</label></h3>';
  echo '<select name="_calendarId" id="_calendarId">';
  echo '<option value="">-- select --</option>';
  gac_get_data(function ($opts) use ($service) {
    return $service->calendarList->listCalendarList($opts);
  }, function ($item) use ($current) {
    echo '<option value="'.htmlspecialchars($item->getId(), ENT_QUOTES).'"'.($current === $item->getId() ? ' selected="selected"' : 'ls-google-api-client').'>'.htmlspecialchars($item->getSummary()).'</option>';
  });
  echo '</select>';
  $current = get_post_meta(get_the_id(), '_calendarId_exclude');
  echo '<h3><label for="_calendarId_exclude">'.__('Calendar Name - Exclude', 'ls-google-api-client').'</label></h3>';
  echo '<select name="_calendarId_exclude[]" multiple="multiple" size="5" id="_calendarId_exclude">';
  gac_get_data(function ($opts) use ($service) {
    return $service->calendarList->listCalendarList($opts);
  }, function ($item) use ($current) {
    echo '<option value="'.htmlspecialchars($item->getId(), ENT_QUOTES).'"'.(in_array($item->getId(), $current) ? ' selected="selected"' : 'ls-google-api-client').'>'.htmlspecialchars($item->getSummary()).'</option>';
  });
  echo '</select>';
}

?>
