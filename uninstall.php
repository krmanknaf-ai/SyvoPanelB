<?php
declare(strict_types=1);
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
if (!(bool)get_option('syvo_bd_delete_data_on_uninstall',false)) exit;
global $wpdb;
$tables=['locations','directory_pages','jobs','business_index','ai_logs','rate_limits'];
foreach($tables as $name){$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}syvo_{$name}");}
$posts=get_posts(['post_type'=>'syvo_business','post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
foreach($posts as $id)wp_delete_post((int)$id,true);
$terms=get_terms(['taxonomy'=>'syvo_bd_category','hide_empty'=>false,'fields'=>'ids']);if(!is_wp_error($terms))foreach($terms as $id)wp_delete_term((int)$id,'syvo_bd_category');
$terms=get_terms(['taxonomy'=>'syvo_bd_service','hide_empty'=>false,'fields'=>'ids']);if(!is_wp_error($terms))foreach($terms as $id)wp_delete_term((int)$id,'syvo_bd_service');
$pages=get_option('syvo_bd_core_pages',[]);foreach((array)$pages as $id)wp_delete_post((int)$id,true);
$role=get_role('syvo_business_owner');if($role)remove_role('syvo_business_owner');
foreach(['syvo_bd_version','syvo_bd_db_version','syvo_bd_core_pages','syvo_bd_map_config','syvo_bd_location_import_status','syvo_bd_location_dataset_version','syvo_bd_delete_data_on_uninstall'] as $o)delete_option($o);
