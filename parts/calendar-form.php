<?php
add_action('wp_head', function () {
  echo '<script type="text/javascript">var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
});
function gac_book_form($p, $min_time, $max_time) {
  $user = wp_get_current_user();
  if($user->ID === 0) {
    $str = '<p>'.sprintf(__('You need to <a href="%s">register first</a>', 'ls-google-api-client'), site_url('/wp-login.php?action=register&redirect_to=' . urlencode(add_query_arg('registrationdone', 'true', get_permalink())))).'</p>';
    return $str;
  }
  $str = '<a href="#" class="close">X</a>';
  $fields = get_post_meta($p->ID, '_form_field', true);
  $str .= '<form class="booking-form" data-p="'.$p->ID.'" data-wpnonce="'.wp_create_nonce('ajax_booking').'"><ul>';
  foreach($fields as $field) {
    $str .= gac_field($field, $values);
  }
  $str .= gac_field(array('type' => 'submit', 'name' => 'sbmt', 'classes' => 'button'), array('sbmt' => __('Book', 'ls-google-api-client')));
  $str .= '</ul></form>';
  return $str;
}
add_action('wp_ajax_gac_book', function () {
  header('Content-Type: application/json');
  if(!empty($_REQUEST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], 'ajax_booking' ) ) {
    gac_setup_date_functions();
    //Ok, we can push an update to the calendar (preemptive?)?
    if(empty($_REQUEST['form_data'])) {
      die(gac_encode_response(array('error' => __('We don\'t have data!', 'ls-google-api-client'))));
    }
    parse_str($_REQUEST['form_data'], $data);
    if(empty($data)) {
      die(gac_encode_response(array('error' => __('We don\'t have data!', 'ls-google-api-client'))));
    }
    $post = get_post($_REQUEST['p']);
    if(!$post || empty($data['date']) || empty($data['time']) || empty($data['procedure'])) {
      die(gac_encode_response(array('error' => __('Missing params!', 'ls-google-api-client'), 'data' => $data)));
    }
    if(empty($data['phone'])) {
      die(gac_encode_response(array('error' => __('Missing phone', 'ls-google-api-client'), 'data' => $data)));
    }
    $start_time = strToTime($data['date'].' '.$data['time']);
    if($start_time < time()) {
      die(gac_encode_response(array('error' => __('We cannot start in the past!', 'ls-google-api-client'))));
    }
    $procedure = $data['procedure'];
    $time_spent = gac_time_for_procedure($procedure);
    if(!$time_spent) {
      die(gac_encode_response(array('error' => __('Procedure doesn\'t have time!', 'ls-google-api-client'))));
    }
    $employees = gac_check_employee_at_work($start_time, $time_spent, $procedure);
    if(!$employees) {
      //First check the member is at work
      die(gac_encode_response(array('error' => __('No employee available at this time', 'ls-google-api-client'))));
    } else if(!gac_check_employee_available($start_time, $time_spent, $employees)) {
      //Check there is a free space in the calendar
      die(gac_encode_response(array('error' => __('This time is already taken', 'ls-google-api-client'))));
    }
    //this time appears to be free, create the announcement post
    $user = wp_get_current_user();

    //TODO: Find out who to assing to somewhat smarter?
    foreach ($employees as $employee){
      $calendarId = get_post_meta($employee->ID, '_calendarId', true);
      if(!empty($calendarId)) {
        break;
      }
    }
    $colorId = get_term_meta($procedure, '_color', true);
    $calendarId = get_post_meta($employee->ID, '_calendarId', true);
    $data['gac_users'] = array_map(function ($emp) { return $emp->ID; }, $employees);

    //update the current user fields!
    gac_fields_update($post->ID, $data);

    $title = sprintf(__('%1$s at %2$s', 'ls-google-api-client'), $user->display_name, date(get_option('date_format').' '.get_option('time_format'), $start_time));
    $gac_booking_id = wp_insert_post(array('post_type' => 'gac_booking', 'post_status' => 'publish', 'post_title' => $title, 'meta_input' => $data, 'post_parent' => $post->ID));
    if(get_post_meta($post->ID, '_send_email', true) === 'on') {
      wp_mail($user->user_email, __('Booking Created', 'ls-google-api-client'), sprintf(__('Your booking for %s on %s at %s was accepted', 'ls-google-api-client'), get_term_by('term_id', $procedure, 'gac_procedure')->name, iconv('cp1250', 'utf-8', strftime('%e. %B', $start_time)), iconv('cp1250', 'utf-8', strftime('%k:%M', $start_time))));
    }
    if(!empty($gac_booking_id) && !is_wp_error($gac_booking_id)) {
      $publish = get_post_meta($post->ID, '_push_immediately', 'off');
      if(in_array($publish, array('on', 'confirm'))) {
        //If _push_immediately is enabled, push to the calendar with special tag?
        $service = gac_getClient();
        if($service) {
        //FIXME: Confirm?
          $proc = get_term_by('term_id', $data['procedure'], 'gac_procedure');
          $description = array($proc->name, $data['phone']);
          if(!empty($data['coupon'])) {
            $description[] = sprintf(__('Coupon: %s'), $data['coupon']);
          }
          $eventDetails = array(
            'summary' => $title,
            'description' => implode(' ', $description),
            'start' => array(
              'dateTime' => gmdate('Y-m-d\TH:i:s', $start_time),
              'timeZone' => 'GMT',
            ),
            'end' => array(
              'dateTime' => gmdate('Y-m-d\TH:i:s', $start_time + $time_spent),
              'timeZone' => 'GMT',
            ),
          );
          if(!empty($colorId)) {
            $eventDetails['colorId'] = $colorId;
          }
          $event = new Google_Service_Calendar_Event($eventDetails);
          if($publish === 'confirm') {
            $event->setVisibility('private');
            wp_insert_comment(array(
              'comment_post_ID' => $gac_booking_id,
              'comment_author' => 'system',
              'comment_content' => sprintf(__('The event was created, but it <a href="%s">needs a confirmation</a>.', 'ls-google-api-client'), add_query_arg('gac_booking_confirm', 'true', get_edit_post_link($gac_booking_id))),
            ));
          }
          $event = $service->events->insert($calendarId, $event);
          gac_clear_cache($calendarId, $start_time);
        }
      } else {
        wp_insert_comment(array(
          'comment_post_ID' => $gac_booking_id,
          'comment_author' => 'system',
          'comment_content' => sprintf(__('The event was not created, you can <a href="%s">do this here</a>.', 'ls-google-api-client'), add_query_arg('gac_booking_create', 'true', get_edit_post_link($gac_booking_id))),
        ));
      }
      die(gac_encode_response(array('success' => true, 'message' => __('Booking accepted', 'ls-google-api-client'))));
    } else {
      die(gac_encode_response(array('error' => __('Failed to create the booking', 'ls-google-api-client'))));
    }
  }
  die(gac_encode_response(array('error' => __('Unknown error occurred', 'ls-google-api-client'))));
});
function gac_encode_response($out) {
  return json_encode(apply_filters('gac_encode_response', $out));
}
function gac_user_off_whole_day($exclude, $time) {
  foreach($exclude as $item) {
    if($item['start'] <= $time && $item['end'] >= $time) {
      return true;
    }
  }
  return false;
}
function gac_time_for_procedure($procedure) {
  $length = get_term_meta($procedure, '_length', true);
  if(!empty($length)) {
    return (int)$length * MINUTE_IN_SECONDS;
  }
  return HOUR_IN_SECONDS;
}
function gac_check_employee_available($start_time, $time_spent, &$employees) {
  $service = gac_getClient();
  $optParams = array(
    'timeMin' => date('c', strToTime('midnight', $start_time)),
    'timeMax' => date('c', strToTime('midnight', $start_time) + DAY_IN_SECONDS),
  );
  //echo date('Y-m-d H:i:s', $start_time)." + $time_spent\n";
  foreach($employees as $key => $employee) {
    $calendar = get_post_meta($employee->ID, '_calendarId', true);
    $items = array();
    gac_get_data(function ($opts) use ($service, $calendar, $optParams) {
      return $service->events->listEvents($calendar, array_merge($optParams, $opts));
    }, function ($item) use (&$items) {
      $items[] = array('start' => gac_getGoogleTime($item->getStart()), 'end' => gac_getGoogleTime($item->getEnd()));
    });
    foreach($items as $item) {
      //echo date('Y-m-d H:i:s', $item['start'])." - ".date('Y-m-d H:i:s', $item['end'])."\n";
      if($item['start'] >= $start_time && $item['end'] <= $start_time + $time_spent) {
        unset($employees[$key]);
        continue 2;
      } else if ($item['start'] <= $start_time && $item['end'] > $start_time) {
        unset($employees[$key]);
        continue 2;
      }
    }
  }
  return count($employees);
}
function gac_check_employee_at_work($start_time, $time_spent, $procedure) {
  $employees = get_posts(array('post_type' => 'gac_users', 'tax_query' => array(array('taxonomy' => 'gac_procedure', 'field' => 'term_id', 'terms' => (array)$procedure)), 'posts_per_page' => 999));
  $service = gac_getClient();
  $optParams = array(
    'timeMin' => date('c', strToTime('midnight', $start_time)),
    'timeMax' => date('c', strToTime('midnight', $start_time) + DAY_IN_SECONDS),
  );
  $dow = date('w', $start_time);
  foreach($employees as $key => $employee) {
    $time_from = get_post_meta($employee->ID, '_time_from', true);
    if($start_time < strToTime($time_from[$dow - 1], $start_time)) {
      unset($employees[$key]);
      continue;
    }
    $time_to = get_post_meta($employee->ID, '_time_to', true);
    if($start_time + $time_spent > strToTime($time_to[$dow - 1], $start_time)) {
      unset($employees[$key]);
      continue;
    }
    $calendarExclude = get_post_meta($employee->ID, '_calendarId_exclude');
    $itemsExclude = array();
    foreach($calendarExclude as $cid) {
      gac_get_data(function ($opts) use ($service, $cid, $optParams) {
        return $service->events->listEvents($cid, array_merge($optParams, $opts));
      }, function ($item) use (&$itemsExclude) {
        $itemsExclude[] = array('start' => gac_getGoogleTime($item->getStart()), 'end' => gac_getGoogleTime($item->getEnd()));
      });
    }
    foreach($itemsExclude as $item) {
      if($item['start'] > $start_time && $item['end'] < $start_time + $time_spent
        || $item['start'] < $start_time && $item['end'] > $start_time) {
        unset($employees[$key]);
        continue 2;
      }
    }
  }
  return $employees;
}

?>
