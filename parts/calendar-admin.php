<?php
add_filter('gac_calendar_post_types', function ($post_types) {
  $post_types[] = array(
    'slug' => 'gac_calendar',
    'name' => __('Calendars', 'ls-google-api-client'),
    'singular_name' => __('Calendar', 'ls-google-api-client'),
    'public' => false,
    'show_in_admin_bar' => false,
    'hierarchical' => false,
    'has_archive' => false,
    'supports' => array('title', 'editor')
  );
  return $post_types;
});
add_action( 'save_post', function ($post_id) {
  if ( in_array(get_post_type($post_id), array('gac_calendar'), true) ) {
    update_post_meta( $post_id, '_push_immediately', isset($_POST['_push_immediately']) ? $_POST['_push_immediately'] : 'off');
    update_post_meta( $post_id, '_send_email', isset($_POST['_send_email']) ? $_POST['_send_email'] : 'off');
    update_post_meta( $post_id, '_enable_create', isset($_POST['_enable_create']) ? $_POST['_enable_create'] : 'off');
    if(!empty($_REQUEST['_form_field'])) {
      $keys = array();
      foreach($_REQUEST['_form_field'] as $key => &$field) {
        if(empty($field['name']) || in_array($field['name'], $keys, true)) {
          unset($_REQUEST['_form_field'][$key]);
          continue;
        }
        $keys[] = $field['name'];
        if($field['name'] === 'date') {
          $field['type'] = 'date';
          $field['required'] = '1';
        } else if ($field['name'] === 'time') {
          $field['type'] = 'time';
          $field['required'] = '1';
        } else if ($field['name'] === 'procedure') {
          $field['type'] = 'terms';
          $field['taxonomy'] = 'gac_procedure';
          $field['required'] = '1';
        }
      }
      unset($field);
    }
    update_post_meta( $post_id, '_form_field', isset($_REQUEST['_form_field']) ? array_values($_REQUEST['_form_field']) : array());
    foreach(array('_days', '_calendarId_exclude', '_gac_users') as $key) {
      $meta = get_post_meta( $post_id, $key );
      $categories = isset($_REQUEST[$key]) ? array_filter($_REQUEST[$key]) : null;
      if(!is_null($categories)) {
        $last_i = -1;
        foreach(array_values($categories) as $k => $v) {
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
} );
add_action('add_meta_boxes', function () {
  add_meta_box( 'gac_metabox', __( 'Calendar', 'ls-google-api-client' ), 'gac_metabox', 'gac_calendar' );
  add_meta_box( 'gac_metabox_form_fields', __( 'Order Form Fields', 'ls-google-api-client' ), 'gac_metabox_form_fields', 'gac_calendar' );
});
add_action('admin_init', function () {
  if(!empty($_REQUEST['gac_clear_calendar']) && !empty($_REQUEST['post'])) {
    $id = (int)$_REQUEST['post'];
    gac_clear_cache(get_post_meta($id, '_calendarId_exclude'));
    array_map(function ($user) {
      gac_clear_cache(get_post_meta($user->ID, '_calendarId', true));
      gac_clear_cache(get_post_meta($user->ID, '_calendarId_exclude'));
    }, get_posts(array('post_id__in' => get_post_meta($id, '_gac_users'), 'post_type' => 'gac_users')));
  }
});
add_action( 'admin_enqueue_scripts', function ($hook) {
  wp_register_style( 'gac_calendar_admin_edit_css', plugins_url( 'css/style-admin.css', dirname(__FILE__) ), false, '1.0.0' );
  wp_enqueue_style( 'gac_calendar_admin_edit_css' );
  if ( 'post.php' != $hook ) {
    return;
  }
  wp_enqueue_script( 'gac_calendar_admin_edit', plugins_url( 'js/admin-edit.js', dirname(__FILE__) ) );
} );
function gac_metabox_form_fields($object, $box) {
  $fields = get_post_meta($object->ID, '_form_field', true);
  $key = -1;
  $frozen_fields_orig = $frozen_fields = array(
    'date' => array(
      'type' => 'date',
      '_label' => __('Date'),
    ),
    'time' => array(
      'type' => 'time',
      '_label' => __('Time'),
    ),
    'procedure' => array(
      'type' => 'terms',
      'taxonomy' => 'gac_procedure',
      '_label' => __('Procedure'),
    ),
  );

  echo '<ul class="gac-order-form-fields">';
  foreach($fields as $key => $field) {
    if(isset($frozen_fields[$field['name']]['type'])) {
      if($frozen_fields[$field['name']]['type'] === $field['type']) {
        unset($frozen_fields[$field['name']]);
      } else {
        unset($field[$key]);
      }
    }
  }
  foreach($frozen_fields as $key => $field) {
    $fields[] = array_merge(array(
      'name' => $key,
      'label' => $field['_label'],
      'required' => '1',
    ), $field);
  }
  foreach($fields as $key => $field) {
    echo '<li class="gac-form-field"><div class="draggable-handle"></div>'.gac_metabox_form_fields_print_field($key, $field, '_form_field', isset($frozen_fields_orig[$field['name']])).'</li>';
  }
  echo '<li class="gac-form-field"><div class="draggable-handle"></div>'.gac_metabox_form_fields_print_field($key + 1, array(), '_form_field').'</li>';
  echo '</ul>';
}
function gac_metabox_form_fields_print_field($key, $values = array(), $prefix = '', $disable = false) {
  $str = '<ul>';
  if(!empty($values) && !$disable) {
    $str .= '<a href="#" class="gac-form-delete-group">'.__('Delete').'</a>';
  } else {
    //$str .= '<a href="#" class="gac-form-clone-group">'.__('New Field').'</a>';
  }
  $name_prefix = $prefix.'['.$key.']';
  foreach(array(
    array('type' => 'text', 'name' => 'name', 'placeholder' => __('eg. firstname'), 'label' => __('Programatical name'), 'readonly' => $disable),
    array('type' => 'text', 'name' => 'label', 'placeholder' => __('eg. Firstname'), 'label' => __('Display name')),
    array('type' => 'select', 'name' => 'type', 'label' => __('Field Type'), 'disabled' => $disable, 'options' => apply_filters('gac_field_types', array(
      '' => __('-- select --', 'ls-google-api-client'),
      'text' => __('Text', 'ls-google-api-client'),
      'email' => __('Email', 'ls-google-api-client'),
      'number' => __('Number', 'ls-google-api-client'),
      'checkbox' => __('Checkbox', 'ls-google-api-client'),
      'url' => __('URL', 'ls-google-api-client'),
      'tel' => __('Telephone', 'ls-google-api-client'),
      'date' => __('Date', 'ls-google-api-client'),
      'time' => __('Time', 'ls-google-api-client'),
      'textarea' => __('Textarea', 'ls-google-api-client'),
      'select' => __('Select', 'ls-google-api-client'),
      'radio' => __('Radio', 'ls-google-api-client'),
      'checkboxes' => __('Checkbox', 'ls-google-api-client'),
      'terms' => __('Terms', 'ls-google-api-client'),
      'user_detail' => __('User Detail', 'ls-google-api-client'),
      'user_meta' => __('User Meta', 'ls-google-api-client'),
      'taxonomy' => __('Taxonomy', 'ls-google-api-client'),
    ))),
    array('type' => 'checkbox', 'name' => 'required', 'label' => __('Required'), 'value' => '1'),
    array('type' => 'taxonomy', 'name' => 'taxonomy', 'label' => __('Taxonomy'), 'if' => array('type' => ['terms']), 'disabled' => $disable),
    array('type' => 'select', 'name' => 'detail_key', 'label' => __('User Detail Type'), 'if' => array('type' => ['user_detail']), 'options' => array(
      '' => __('-- select --'),
      'user_nicename' => __('Nice Name'),
      'user_email' => __('Email'),
      'user_url' => __('URL'),
      'user_registered' => __('Registered'),
      'user_status' => __('Status'),
      'display_name' => __('Display Name')
    )),
    array('type' => 'text', 'name' => 'meta_key', 'label' => __('User Meta Key'), 'placeholder' => __('eg. _phone'), 'title' => __('Should start with \'_\'.'), 'if' => array('type' => ['user_meta'])),
    array('type' => 'checkbox', 'name' => 'put_google_event_description', 'label' => __('Put to Google Calendar Event detail'), 'value' => '1'),
    //TODO: Add copyable option for select, radio and checkboxes

  ) as $field) {
    $str .= gac_field($field, $values, $name_prefix);
  }
  $str .= '</ul>';
  return $str;
}
function gac_metabox($object, $box) {
  //echo '';
  $authUrl = null;
  try {
    $service = gac_getClient('Google_Service_Calendar', $authUrl);
    if(empty($service)) {
      echo sprintf(__('You must first <a href="%s">Authenticate</a>', 'ls-google-api-client'), htmlspecialchars($authUrl, ENT_QUOTES));
      return;
    }
    echo '<a href="'.add_query_arg('gac_clear_calendar', 'true', get_edit_post_link(get_the_id())).'">'.__('Clear calendar cache', 'ls-google-api-client').'</a>';
  } catch (Exception $ex) {
    trigger_error('Exception: '.$ex->getMessage(), E_USER_WARNING);
    echo __('Failed to communicate with backend, please try again later.', 'ls-google-api-client');
    return;
  }
  echo '<ul class="calendar-postbox">';
  $values = array(
    //'_days' => get_post_meta(get_the_id(), '_days'),
    '_gac_users' => get_post_meta(get_the_id(), '_gac_users'),
    '_calendarId_exclude' => get_post_meta(get_the_id(), '_calendarId_exclude'),
    '_enable_create' => get_post_meta(get_the_id(), '_enable_create', true),
    '_send_email' => get_post_meta(get_the_id(), '_send_email', true),
    '_push_immediately' => get_post_meta(get_the_id(), '_push_immediately', true),
  );
  $calendars = gac_get_data(function ($opts) use ($service) {
    return $service->calendarList->listCalendarList($opts);
  }, function ($item) use ($current) {
    return array($item->getId(), $item->getSummary());
  });

  foreach(array(
    /*array('type' => 'checkboxes', 'label' => __('Days', 'ls-google-api-client'), 'name' => '_days', 'options' => array(
      1 => __('Monday', 'ls-google-api-client'),
      2 => __('Tuesday', 'ls-google-api-client'),
      3 => __('Wednesday', 'ls-google-api-client'),
      4 => __('Thursday', 'ls-google-api-client'),
      5 => __('Friday', 'ls-google-api-client'),
      6 => __('Saturday', 'ls-google-api-client'),
      7 => __('Sunday', 'ls-google-api-client'),
    )),*/
    array('type' => 'checkboxes', 'label' => __('Calendar Users', 'ls-google-api-client'), 'name' => '_gac_users', 'options' => array_column(array_map(function ($u) {
      return array($u->ID, $u->post_title);
    }, get_posts(array('post_type' => 'gac_users'))), 1, 0)),
    array('type' => 'select', 'label' => __('Calendar Name - Exclude', 'ls-google-api-client'), 'name' => '_calendarId_exclude', 'multiple' => true, 'options' => array_column($calendars, 1, 0)),
    array('type' => 'checkbox', 'label' => __('Enable Create Events', 'ls-google-api-client'), 'value' => '1', 'name' => '_enable_create', 'note' => __('Allow new events to be created in this calendar', 'ls-google-api-client')),
    array('type' => 'checkbox', 'label' => __('Send email to the customer', 'ls-google-api-client'), 'value' => '1', 'name' => '_send_email'),
    array('type' => 'radio', 'name' => '_push_immediately', 'options' => array(
      'on' => __('Push the booking to the calendar immediately', 'ls-google-api-client'),
      'confirm' => __('Create, but confirm', 'ls-google-api-client'),
      'off' => __('Require Review', 'ls-google-api-client'),
    ), 'note' => array(
      __('If Enabled, the booking will have special status, but noone else would be able to book that time anymore.', 'ls-google-api-client'),
      __('If Disabled, the booking needs to be approved first, so there may be multiple bookings for the same time.', 'ls-google-api-client')
    )),
  ) as $field) {
    echo gac_field($field, $values);
  }
  echo '</ul>';

//   $current = get_post_meta(get_the_id(), '_calendarId_exclude');
//   echo '<h3><label for="_calendarId_exclude">'.__('Calendar Name - Exclude', 'ls-google-api-client').'</label></h3>';
//   echo '<select name="_calendarId_exclude[]" multiple="multiple" size="5" id="_calendarId_exclude">';
//   gac_get_data(function ($opts) use ($service) {
//     return $service->calendarList->listCalendarList($opts);
//   }, function ($item) use ($current) {
//     echo '<option value="'.htmlspecialchars($item->getId(), ENT_QUOTES).'"'.(in_array($item->getId(), $current) ? ' selected="selected"' : '').'>'.htmlspecialchars($item->getSummary()).'</option>';
//   });
//   echo '</select>';
//   $current = get_post_meta(get_the_id(), '_enable_create', true);
//   echo '<h3><label><input type="checkbox" name="_enable_create" value="on"'.($current==='on' ? ' checked="checked"' : '').' />'.__('Enable Create Events', 'ls-google-api-client').'</label></h3>';
//   echo '<p class="note">'.__('Allow new events to be created in this calendar', 'ls-google-api-client').'</p>';
//   $current = get_post_meta(get_the_id(), '_send_email', true);
//   echo '<h3><label><input type="checkbox" name="_send_email" value="on"'.($current==='on' ? ' checked="checked"' : '').' />'.__('Send email to the customer', 'ls-google-api-client').'</label></h3>';
//   $key = '_push_immediately';
//   $current = get_post_meta(get_the_id(), $key, true);
//   echo '<h3><input type="radio" id="'.$key.'" name="'.$key.'" value="on"'.($current==='on' ? ' checked="checked"' : '').' /><label for="'.$key.'">'.__('Push the booking to the calendar immediately', 'ls-google-api-client').'</label><label><input type="radio" name="'.$key.'" value="confirm"'.($current==='confirm' ? ' checked="checked"' : '').' />'.__('Create, but confirm', 'ls-google-api-client').'</label><label for="'.$key.'"><input type="radio" id="'.$key.'" name="'.$key.'" value="off"'.($current==='off' ? ' checked="checked"' : '').' />'.__('Require Review', 'ls-google-api-client').'</label></h3>';
//   echo '<p class="note">'.__('If Enabled, the booking will have special status, but noone else would be able to book that time anymore.', 'ls-google-api-client').'</p>';
//   echo '<p class="note">'.__('If Disabled, the booking needs to be approved first, so there may be multiple bookings for the same time.', 'ls-google-api-client').'</p>';
}


?>
