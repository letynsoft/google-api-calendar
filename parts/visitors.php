<?php

add_filter('login_redirect', function ( $url, $request, $user ){
  if( $user && is_object( $user ) && is_a( $user, 'WP_User' ) ) {
    if( $user->has_cap( 'subscriber')) {
      $url = home_url('/objednavkovy-kalendar/');
    }
  }
  return $url;
}, 10, 3 );
?>
