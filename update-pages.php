<?php
require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
// if(!is_user_logged_in()) exit(http_response_code(401));

include_once('phpQuery.php');
global $wpdb;
$dry_run = false;

$items = $_POST['items'];
$target_sites = $_POST['target_sites'];
// if($dry_run) print_r( $items );

$sites = wp_get_sites();
foreach($sites as $site){
	if( !in_array($site['blog_id'], $target_sites) ) continue;

	switch_to_blog($site['blog_id']);
	
	foreach($items as $item){
		// Pull correct page from site
		$item['pageTitle']	= trim($item['pageTitle']);
		$item['element']	= stripslashes($item['element']);
		$item['content']	= htmlentities(stripslashes($item['content']));
		$item['selector']	= preg_replace('/\#(.*?)($| )/', '[module_id="$1"]', $item['selector']);
		$item['selector']	= preg_replace('/\.(.*?)($| )/', '[module_class="$1"]', $item['selector']);

		// $page = get_page_by_title($item['pageName']);
		$page = $wpdb->get_row("
			SELECT * FROM {$wpdb->prefix}posts 
			WHERE post_name = '{$item['pageName']}'
			AND post_type = '{$item['pageType']}'
		", ARRAY_A);
		
		// Parse page to HTML and get element specified by selector
		$content = $page['post_content'];

		$content = htmlspecialchars($content, ENT_NOQUOTES);
		$content = str_replace('[', '<', $content);
		$content = str_replace(']', '>', $content);
		$content_document = phpQuery::newDocument($content);
		$element = pq($item['selector']);

		// Perform the specified action
		switch($item['action']){
			case 'replace_all': // Replace entire element. PHPQuery doesn't support replaceWith() very well, so we'll have to use a workaround.
				$element->after($item['element']);
				$element->remove();
				$element = phpQuery::newDocument($item['element']);
				$element = $element->find($item['selector']);
			break;
			case 'replace_content': // Replace content only
				$element->html($item['content']);
			break;
			case 'replace_attr' : // Replace all attributes
			case 'replace_attr_custom': // Replace selected attributes
			case 'delete_attr': // Delete selected attributes
				$attributes_key = ($item['action'] == 'replace_attr') ? 'attributesAll' : 'attributes';
				foreach($item[$attributes_key] as $attribute){
					$attr_name = explode(':', $attribute)[0];
					$attr_value = urldecode(explode(':', $attribute)[1]);
					if ($item['action'] == 'delete_attr') $element->removeAttr($attr_name);
					else $element->attr($attr_name, $attr_value);
				}
			break;
			case 'append': // Append to existing parent
			case 'prepend': // Prepend to existing parent
				$parent_selector = explode(' > ', $item['selector']);
				array_pop($parent_selector);
				$parent_selector = implode( ' > ', $parent_selector );
				$parent_element = $content_document->find($parent_selector);
				if($item['action'] == 'append') $parent_element->append($item['element']);
				else $parent_element->prepend($item['element']);
			break;
			case 'delete': // Delete element
				$element->remove();
			break;
		}

		// Edit the global module
		if( !empty($item['globalModule']) && !empty($element->htmlOuter()) ){
			$gmod_id_target = $content_document->find($item['selector'])->attr('global_module'); // Global module ID of target element
			$gmod_id_source = $item['globalModule']; // Global module ID of source element
			$gmod_element = $element->clone();
			$gmod_element->removeAttr('global_module'); // Global modules don't have the global_module attribute
			$gmod_content = $gmod_element->htmlOuter();

			// Write global module to DB
			$gmod_content = str_replace('<', '[', $gmod_content);
			$gmod_content = str_replace('>', ']', $gmod_content);
			$gmod_content = htmlspecialchars_decode($gmod_content);
			if($dry_run) echo("update global module {$item['globalModule']} in {$wpdb->prefix}posts with\n\n" . $gmod_content . "\n\n");
			else{
				$update_result = wp_update_post(array(
					'ID' 			=> 	$gmod_id_source,
					'post_content' 	=> 	$gmod_content
				));
				if(!$update_result){ // If the receiving site doesn't already use a global module, attempt to create it
					echo("Error updating global module " . $gmod_id_source . " on " . $site['domain'] . ", attempting to create...\n");
					$random_string = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
					$update_result = wp_insert_post(array(
						'import_id'		=> $gmod_id_source,
						'post_content' 	=> $gmod_content,
						'post_title' 	=> "Global Module " . $random_string,
						'post_status'	=> "publish",
						'post_type'		=> "et_pb_layout",
					));
					if($update_result) pq($item['selector'])->attr('global_module', $update_result); // Update element with newly created global module ID
				}
				if(!$update_result) echo("Failed to create global module.\n");
			}
		}

		// Write page to DB
		$modified_content = $content_document->htmlOuter();
		$modified_content = str_replace('<', '[', $modified_content);
		$modified_content = str_replace('>', ']', $modified_content);
		$modified_content = htmlspecialchars_decode($modified_content);
		if($dry_run) echo("update page {$page['ID']} in {$wpdb->prefix}posts with\n" . $modified_content . "\n");
		else{
			$update_post_result = wp_update_post(array(
				'ID' 			=> 	$page['ID'],
				'post_content' 	=> 	$modified_content
			));
			if(!$update_post_result) echo("Error updating page " . $item['pageTitle'] . " on " . $site['domain'] . "\n");
		}
	}

	restore_current_blog();
}
if(!$dry_run) echo('Changes written to DB.');