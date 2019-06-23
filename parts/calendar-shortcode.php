<?php
add_shortcode('gac_calendar', function ($atts, $content = null, $name = '') {
  try {
    static $cnt = 0;
    gac_setup_date_functions();
    $atts = shortcode_atts(array(
      'module_id' => 'gac-calendar-'.($cnt++),
      'module_class' => '',
      'id' => '',
      'time_offset' => '',
      'show_color' => '1',
    ), $atts, $name);
    $ignore_color = $atts['show_color'] === 'off';
    add_action('wp_footer', function () {
      echo '<style type="text/css">div.loop.main-loop { padding-left: 0px; padding-right: 0px; }</style>';
    });
    if(empty($atts['id'])) {
      return '';
    }
    $p = get_post($atts['id']);
    if(empty($p)) {
      return '';
    }
    $users = get_post_meta($p->ID, '_gac_users');
    if(empty($users)) {
      return '';
    }

    $arrs = array_map(function ($u) {
      $red = gac_read_time( get_post_meta($u, '_time_from', true) );
      return array_map(function ($i) { return $i + 1; }, array_keys(array_filter($red)));
    }, $users);
    $days = array_unique(call_user_func_array('array_merge', $arrs));

    $time = time();
    if(isset($_REQUEST['time'])) {
      $time = time() + (min(12, max(-12, (int)$_REQUEST['time'])) * WEEK_IN_SECONDS);
    }
    $optParams = array(
      'timeMin' => gmdate('c', strToTime('sunday midnight', $time) - DAY_IN_SECONDS * 6),
      'timeMax' => gmdate('c', strToTime('sunday midnight', $time) + DAY_IN_SECONDS - 1),
    );
    $enable_create = get_post_meta($p->ID, '_enable_create', true) === '1';

    $colors = get_transient('gac_calendar_user_colors');
    if(empty($colors)) {
      $colors = gac_getClient()->colors->get()->getEvent();
      set_transient('gac_calendar_user_colors', $colors, 12 * HOUR_IN_SECONDS);
    }
    $items = array();
    $config = compact('ignore_color', 'enable_create');
    $itemsExclude = array();
    $userOpenTimes = array();
    foreach($users as $userId) {
      $calendarId = get_post_meta($userId, '_calendarId', true);
      if(empty($calendarId)) {
        continue;
      }
      $userOpenTimeFrom[$userId] = gac_read_time( get_post_meta($userId, '_time_from', true) );
      $userOpenTimeTo[$userId] = gac_read_time( get_post_meta($userId, '_time_to', true) );
      $calendarExclude = get_post_meta($userId, '_calendarId_exclude');
      foreach($calendarExclude as $cid) {
        gac_get_data(array(function () { return gac_getClient(); }, 'events.listEvents', [$cid, $optParams]), function ($item) use (&$itemsExclude, $userId) {
          if(!isset($itemsExclude[$userId])) $itemsExclude[$userId] = array();
          $itemsExclude[$userId][] = array('start' => gac_getGoogleTime($item->getStart()), 'end' => gac_getGoogleTime($item->getEnd()));
        });
      }
      gac_get_data(array(function () { return gac_getClient(); }, 'events.listEvents', [$calendarId, $optParams]), function ($item) use (&$items, $colors, $userId) {
        $clr = null;
        if(isset($colors[$item->getColorId()])) {
          $color = $colors[$item->getColorId()];
          $clr = array('foreground' => $color->getForeground(), 'background' => $color->getBackground());
        }
        $start = gac_getGoogleTime($item->getStart());
        $end = gac_getGoogleTime($item->getEnd());
        $i = 0;
        if(!isset($items[$userId])) {
          $items[$userId] = array();
        }
        $items[$userId][] = array(
          'start' => $start,
          'end' => $end,
          'colorid' => !empty($item->getColorId()) ? $item->getColorId() : -1,
          'color' => $clr,
        );
      });
    }
    $min_from = min(array_map('min', array_filter(array_map(function ($item) { return array_filter((array)$item); }, $userOpenTimeFrom))));
    $max_to = max(array_map('max', array_filter(array_map(function ($item) { return array_filter((array)$item); }, $userOpenTimeTo))));
    $first = strToTime('sunday midnight', $time) - DAY_IN_SECONDS * 6;
    $str = '<div id="'.$atts['module_id'].'"  class="type-gac-calendar-container '.$atts['module_class'].'">';
    if(!empty($_REQUEST['registrationdone'])) {
      $str .= '<p>'.__('Your registration needs email confirmation, please go to your email and confirm it is correct.', 'ls-google-api-client').'</p>';
    }
    $str .= '<ul class="type-gac-calendar">';
    $date_start_offset = $min_from;
    $date_end_offset = $max_to;
    for ($time = $first; $time < $first + DAY_IN_SECONDS * 7; $time += DAY_IN_SECONDS) {
      $today = date('w', $time);
      if(in_array($today, $days)) {
        if(gac_is_anybody_in($time, $p)) {
          $str .= '<li><ul><li class="header">'.(min($days) == $today && (!isset($_REQUEST['time']) || $_REQUEST['time'] > -12) ? '<a href="?time='.((isset($_REQUEST['time']) ? $_REQUEST['time'] : 0) - 1).'">&laquo;</a>' : '').date('d.m.', $time+$date_start_offset).(max($days) == $today && (!isset($_REQUEST['time']) || $_REQUEST['time'] < 12) ? '<a href="?time='.((isset($_REQUEST['time']) ? $_REQUEST['time'] : 0) + 1).'">&raquo;</a>' : '').'</li><li><ul class="one-day-users">';
          foreach($users as $uid) {
            $top = $userOpenTimeFrom[$uid][$today - 1] - $date_start_offset;
            if(gac_user_off_whole_day($itemsExclude[$uid], $time)) {
            } else {
              $str .= '<li style="padding-top: '.($top/gac_time_div()).'px"><ul>'.gac_print_day($time+$userOpenTimeFrom[$uid][$today - 1], $items[$uid], $itemsExclude[$uid], $userOpenTimeTo[$uid][$today - 1] - $userOpenTimeFrom[$uid][$today - 1], $config).'</ul></li>';
            }
          }
          $str .= '</ul></li></ul></li>';
        } else {
          $str .= '<li class="calendar-off"><ul><li class="header">'.date('d.m.', $time+$date_start_offset).'</li><li class="bank-holidays"><span>'.__('Bank holidays', 'ls-google-api-client').'</span></li></ul></li>';
        }
      }
    }

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    return $str.'</ul><div class="modal">'.gac_book_form($p, $min_from, $max_to).'</div></div>';
  } catch (Exception $ex) {
    trigger_error('Exception: '.$ex->getMessage(), E_USER_WARNING);
    return __('Failed to communicate with backend, please try again later.', 'ls-google-api-client');
  }
});
function gac_read_time($days) {
  if(!is_array($days)) {
    return array();
  }
  foreach($days as $key => $val) {
    if(preg_match('/^([0-9]+):([0-9]+)$/', $val, $preg)) {
      $days[$key] = ($preg[1] * HOUR_IN_SECONDS) + ($preg[2] * MINUTE_IN_SECONDS);
    } else {
      $days[$key] = null;
    }
  }
  return $days;
}
function gac_print_day($time_start, $items, $itemsExclude, $max_time = DAY_IN_SECONDS, $config = array()) {
  if(empty($items)) {
    $items = array();
  }
  $items = array_filter((array)$items + (array)$itemsExclude, function ($item) use ($time_start, $max_time) {
    return $item['start'] >= $time_start && $item['end'] <= $time_start + $max_time;
  });
  uasort($items, function ($a, $b) {
    return $a['start'] - $b['start'];
  });
  $overlaping = array();
  foreach($items as $key1 => $item1) {
    $check = false;
    foreach($items as $key2 => $item2) {
      if($key1 === $key2) {
        $check = true;
      } else if ($check) {
        if ($item1['start'] <= $item2['start'] && $item1['end'] > $item2['start']) {
          $overlaping[] = array($key1, $key2);
        } else if ($item2['start'] <= $item1['start'] && $item2['end'] >= $item1['start']) {
          $overlaping[] = array($key1, $key2);
        }
      }
    }
  }
  $last_start = $time_start;
  $str = '';
  $in_overlaping = false;
  $last_item = null;
  $printed_items = array();
  //echo date('Y-m-d H:i:s', $time_start).": ".count($itemsExclude)." (".count($items).")\n";
  foreach($items as $key => $item) {
    if(in_array($key, $printed_items, true)) continue;
    $is_overlaping = array_filter($overlaping, function ($item) use ($key) {
      return in_array($key, $item);
    });
    if(!empty($is_overlaping)) {
      $keys = array_unique(gac_array_flatten($is_overlaping));
      $my_items = array_combine($keys, array_map(function ($k) use ($items) { return $items[$k]; }, $keys));
      uasort($my_items, function ($a, $b) {
        return $a['start'] - $b['start'];
      });
      $start = min(array_column($my_items, 'start'));
      $top = max($start - $last_start, 0);
      if($start !== $last_start && $config['enable_create']) {
        $available = array();
        $str .= '<li class="book" data-date="'.date('Y-m-d', $last_start).'" data-time="'.date('H:i:s', $last_start).'" style="height: '.($top/gac_time_div()).'px;"><a data-available-procedures="'.json_encode($available).'" href="#">'.__('Book here', 'ls-google-api-client').'</a></li>';
        $top = 0;
      }
      $str .= '<li class="overlap" style="margin-top: '.($top/gac_time_div()).'px"><ul>';
      $byColor = array();
      foreach($my_items as $_key => $_item) {
        $byColor[$_item['colorid']][$_key] = $_item;
      }
      ksort($byColor);
      foreach($byColor as $clr=>$_items) {
        $str .= '<li class="clr"><ul>';
        $_start = $start;
        foreach($_items as $_key => $_item) {
          $printed_items[] = $_key;
          $str .= gac_show_item($_item, $_start, $_key, $config, $itemsExclude);
          $_start = $_item['end'];
        }
        $str .= '</ul></li>';
      }
      $last_start = max(array_column($my_items, 'end'));
      $str .= '</ul></li>';
      continue;
    }

    $printed_items[] = $key;
    $str .= gac_show_item($item, $last_start, $key, $config, $itemsExclude);
    $last_start = $item['end'];
    $last_item = $key;
  }
  if($in_overlaping) {
    $str .= '</ul></li>';
  }
  if($last_start < $time_start + $max_time && $config['enable_create']) {
    $str .= '<li class="book" data-date="'.date('Y-m-d', $last_start).'" data-time="'.date('H:i:s', $last_start).'"  style="height: '.(($time_start + $max_time - $last_start) / gac_time_div()).'px"><a href="#">'.__('Book here', 'ls-google-api-client').'</a></li>';
  }
  return $str;
}
function gac_show_item($item, $last_start, $key, $config = array(), $is_exclude = array()) {
  $str = '';
  $top = max($item['start'] - $last_start, 0);
  if(!empty($top) && $top > 0 && $config['enable_create']) {
    $str .= '<li class="book" data-date="'.date('Y-m-d', $last_start).'" data-time="'.date('H:i:s', $last_start).'" style="height: '.($top/gac_time_div()).'px;"><a href="#">'.__('Book here', 'ls-google-api-client').'</a></li>';
    $top = 0;
  }
  $str .= '<li id="'.$key.'" class="event-item" data-start="'.date('Y-m-d H:i:s', $item['start']).'" data-end="'.date('Y-m-d H:i:s', $item['end']).'" style="'.($config['ignore_color'] ? '' : (!empty($item['color']['foreground']) ? 'color: '.$item['color']['foreground'].'; ':'').(!empty($item['color']['background']) ? 'background-color: '.$item['color']['background'].'; ':'')).'margin-top: '.($top / gac_time_div()).'px; height: '.(($item['end'] - $item['start']) / gac_time_div()).'px">'.date('H:i', $item['start']).' -'.($item['end'] - $item['start'] > HOUR_IN_SECONDS ? '<br />' : '').'&nbsp;'.date('H:i', $item['end']).'</li>';
  return $str;
}
function gac_is_anybody_in($time, $calendarPost) {
  $optParams = array(
    'timeMin' => date('c', strToTime('midnight', $time)),
    'timeMax' => date('c', strToTime('midnight', $time) + DAY_IN_SECONDS),
  );
  $calendarExclude = get_post_meta($calendarPost->ID, '_calendarId_exclude');
  $itemsExclude = array();
  foreach($calendarExclude as $cid) {
    $itemsExclude += gac_get_data(array(function () { return gac_getClient(); }, 'events.listEvents', [$cid, $optParams]), function ($item) {
      return array('start' => gac_getGoogleTime($item->getStart()), 'end' => gac_getGoogleTime($item->getEnd()));
    });
  }
  foreach($itemsExclude as $item) {
    if($item['start'] >= $time && $item['end'] <= $time + DAY_IN_SECONDS
      || $item['start'] <= $time && $item['end'] > $time) {
      return false;
    }
  }
  return true;
}

?>
