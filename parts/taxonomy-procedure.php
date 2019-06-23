<?php
add_filter('gac_calendar_taxonomies', function ($taxonomies) {
  $taxonomies[] = array(
    'name' => __('Procedures', 'ls-google-api-client'),
    'singular_name' => __('Procedure', 'ls-google-api-client'),
    'slug' => 'gac_procedure',
    'hierarchical' => true,
    'show_admin_column' => false,
    'public' => false,
    'post_types' => array('gac_users'),
  );
  return $taxonomies;
});
add_action('created_term', 'gac_procedure_save_term');
add_action('edit_term', 'gac_procedure_save_term');
add_action('delete_term', 'gac_procedure_delete_term');
add_action('gac_procedure_edit_form_fields', 'gac_procedure_render_function', 99);
add_action('gac_procedure_add_form_fields', 'gac_procedure_render_function', 99);
function gac_procedure_delete_term($id) {
  delete_term_meta($id, 'job_listing_category_icon');
}
function gac_procedure_save_term($id) {
  if($_POST['taxonomy'] === 'gac_procedure' && isset($_REQUEST['_length'])) {
    if(!empty($_REQUEST['_length'])) {
      update_term_meta($id, '_length', $_REQUEST['_length']);
    } else {
      delete_term_meta($id, '_length');
    }
  }
  if($_POST['taxonomy'] === 'gac_procedure' && isset($_REQUEST['_color'])) {
    if(!empty($_REQUEST['_color'])) {
      update_term_meta($id, '_color', $_REQUEST['_color']);
    } else {
      delete_term_meta($id, '_color');
    }
  }
}
function gac_procedure_render_function($tag) {
  gac_wrap_term_form(gac__procedure_render_function($tag), '_length', __('Length', 'ls-google-api-client'));
  $service = gac_getClient();
  $_colors = $service->colors->get()->getEvent();
  $colors = array_combine(array_keys($_colors), array_column(array_map('get_object_vars', $_colors), 'background'));
  gac_wrap_term_form(gac__procedure_render_function($tag, '_color', $colors), '_color', __('Color', 'ls-google-api-client'));

}
function gac__procedure_render_function($tag, $key = '_length', $options = null) {
  global $category, $wp_version;
  if ($wp_version >= '3.0') {
    $category_id = (is_object($tag))?$tag->term_id:null;
  } else {
    $category_id = $category;
  }

  if (is_object($category_id)) {
    $category_id = $category_id->term_id;
  }
  $classes = array();
  $current = get_term_meta($category_id, $key, true);

  if(is_null($options)) {
    return '<input type="number" name="'.htmlspecialchars($key, ENT_QUOTES).'" step="5" value="'.htmlspecialchars($current, ENT_QUOTES).'" /> '.__('mins.', 'ls-google-api-client');
  } else {
    $_options = array();
    foreach($options as $_key => $value) {
      $_options[] = array($_key, $value);
    }
    return '<select name="'.htmlspecialchars($key, ENT_QUOTES).'"><option value="">'.__('-- no color (calendar default) --', 'ls-google-api-client').'</option>'.implode('', array_map(function ($item) use($current) {
      return '<option style="background-color: '.$item[1].'" value="'.htmlspecialchars($item[0], ENT_QUOTES).'"'.(''.$current === ''.$item[0] ? ' selected="selected"' : 'ls-google-api-client').'>'.htmlspecialchars($item[1]).'</option>';
    }, $_options)).'</select>';
  }
}




?>
