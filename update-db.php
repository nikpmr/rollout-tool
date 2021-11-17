<?php
require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
if(!is_user_logged_in()) exit(http_response_code(401));
global $wpdb;
$dry_run = false;

$target_sites = $_POST['target_sites'];
$items = $_POST['items'];
$source_site_prefix = $wpdb->prefix;

$sites = wp_get_sites();
foreach($sites as $site){
	if( !in_array($site['blog_id'], $target_sites) ) continue;
	switch_to_blog($site['blog_id']);
	
	$target_table = str_replace($source_site_prefix, $wpdb->prefix, $_POST['source_table']);

	if($_POST['method'] == 'query_update'){
		$data = stripslashes($_POST['data']);
		$where = stripslashes($_POST['where']);
		if(!$dry_run){
			$wpdb->show_errors();
			$result = $wpdb->query( "UPDATE $target_table SET $data WHERE $where " );
			if(!$result) echo("An error occurred while running the query.\n{$wpdb->last_query}\n");
		}
		else echo "UPDATE $target_table SET\n$data \nWHERE $where \n";
	}
	if($_POST['method'] == 'query_delete'){
		$where = stripslashes($_POST['where']);
		if(!$dry_run){
			$wpdb->show_errors();
			$result = $wpdb->query( "DELETE FROM $target_table WHERE $where " );
			if(!$result) echo("An error occurred while running the query.\n{$wpdb->last_query}\n");
		}
		else echo "DELETE $target_table \nWHERE $where \n";
	}
	else{
		foreach($items as $item){
			if(is_string($item['settingValue'])) $item['settingValue'] = stripslashes($item['settingValue']);
			if($item['isSerialized'] == 'true' && $item['subSettingName'] != ''){
				$foundSettingValue = unserialize($wpdb->get_var( sprintf(
					"SELECT %s FROM %s WHERE %s = \"%s\"",
					$_POST['column_value'], $target_table, $_POST['column_name'], $item['settingName']
				) ));
				$foundSettingValue[$item['subSettingName']] = $item['subSettingValue'];
				$item['settingValue'] = $foundSettingValue;
			}
			$item['settingValue'] = ($item['isSerialized'] == 'true') ? serialize($item['settingValue']) : $item['settingValue'];
			if(!$dry_run){
				$wpdb->update( 
					$target_table, // table
					array($_POST['column_value'] => $item['settingValue']), // data
					array( $_POST['column_name'] => $item['settingName'] ) // where
				);
			}
			else echo "update " . $target_table . "\n" . $_POST['column_value'] . "\nto " . $item['settingValue'] 
			. "\nwhere " . $_POST['column_name']  . "\n= " . $item['settingName'] . "\n\n";
		}
		restore_current_blog();
	}
}
if($dry_run) echo('Dry run completed.');
else echo('Changes written to DB.');