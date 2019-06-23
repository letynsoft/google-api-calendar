<?php
//FIXME: Add the confirmation backends!
add_filter('gac_calendar_post_types', function($post_types) {
  $post_types[] = array(
    'slug' => 'gac_booking',
    'name' => __('Bookings', 'ls-google-api-client'),
    'singular_name' => __('Booking', 'ls-google-api-client'),
    'public' => false,
    'show_in_admin_bar' => false,
    'hierarchical' => false,
    'has_archive' => false,
    'supports' => array('title', 'custom-fields', 'comments')
  );
  return $post_types;
});




?>
