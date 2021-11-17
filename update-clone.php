<?php
require($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
// if(!is_user_logged_in()) exit(http_response_code(401));
global $wpdb;
$dry_run = false; 

$target_sites = $_POST['target_sites'];
$items = $_POST['items'];
$source_site_prefix = $wpdb->prefix;
$source_table = $_POST['source_table'];
$table_suffix = str_replace($source_site_prefix, '', $source_table);
$key_column_name = $_POST['key_column_name'];

$sites = wp_get_sites();
foreach($sites as $site){
    if( !in_array($site['blog_id'], $target_sites) ) continue;
    switch_to_blog($site['blog_id']);
    
    $target_site_prefix = $wpdb->prefix;
    $target_table = str_replace($source_site_prefix, $target_site_prefix, $source_table);

    foreach($items as $item){
        $setting_id = $item['settingId'];

        if($item['action'] == 'clone'){
            $setting_row = clone_setting($setting_id);
            $target_setting_id = $setting_row['target_id'];

            if($table_suffix == 'posts'){ // If cloning a post
                // Insert postmeta associated with post
                $postmeta = clone_postmeta($setting_id, $target_setting_id);
                
                // If cloning a template, add additional required postmeta
                if($setting_row['post_type'] == 'et_template'){ 
                    // Update the Theme Builder postmeta with the new template's post ID
                    $et_theme_builder_id = $wpdb->get_var( 
                        "SELECT ID FROM $target_table WHERE post_type = 'et_theme_builder'"
                    );
                    $data = array(
                        "post_id"       => $et_theme_builder_id,
                        "meta_key"      => '_et_template',
                        "meta_value"    => $target_setting_id
                    );
                    if(!$dry_run) $wpdb->insert( $target_site_prefix . "postmeta", $data);
                    else echo "insert into ${target_site_prefix}postmeta \n" . print_r($data, true) . "\n\n";

                    // Import header, body and footer template posts
                    clone_template_layout('header', $postmeta['_et_header_layout_id']);
                    clone_template_layout('body', $postmeta['_et_body_layout_id']);
                    clone_template_layout('footer', $postmeta['_et_footer_layout_id']);
                }
            }

            if($table_suffix == 'terms'){ // If cloning a term (category, tag)
                // Insert new taxonomy entry
                $taxonomy_row_result = $wpdb->get_row( // Grab the row from the source table
                    "SELECT * FROM ${source_site_prefix}term_taxonomy WHERE term_id = $setting_id"
                , ARRAY_A);
                array_shift($taxonomy_row_result); // Remove the first (primary) element
                $taxonomy_row_result['term_id'] = $target_setting_id;
                if(!$dry_run) $wpdb->insert($target_site_prefix . 'term_taxonomy', $taxonomy_row_result);
                else echo "insert into ${target_site_prefix}term_taxonomy \n" . print_r($taxonomy_row_result, true) . "\n\n";
            }

            if($setting_row['post_type'] == 'nav_menu_item'){ // If cloning a nav menu item
                $relationship_row_result = $wpdb->get_row( // Grab the row from the source table
                    "SELECT * FROM ${source_site_prefix}term_relationships WHERE object_id = $setting_id"
                , ARRAY_A);
                $relationship_row_result['object_id'] = $target_setting_id;
                if(!$dry_run) $wpdb->insert($target_site_prefix . 'term_relationships', $relationship_row_result);
                else echo "insert into ${target_site_prefix}term_relationships \n" . print_r($relationship_row_result, true) . "\n\n";
            }
            
            if(false){ // This section is available for updating additional rows as needed
                $wpdb->update( 
                    $target_site_prefix . 'options', // table
                    array('option_value' => $target_setting_id), // data
                    array('option_name' => 'page_for_posts') // where
                );
            }
        }
        else if($item['action'] == 'delete'){
            $column_name = $_POST['column_name'];
            $setting_name = $item['settingName'];

            if(!$dry_run){
                $wpdb->delete( 
                    $target_table, // table
                    array( $column_name => $setting_name ) // where
                );
            }
            else echo "delete from $target_table \nwhere $column_name \n= $setting_name \n\n";
        }
    }
    restore_current_blog();
}
if($dry_run) echo('Dry run completed.');
else echo('Changes written to DB.');


function clone_setting($setting_id){
    global $wpdb, $dry_run, $source_table, $key_column_name, $target_table;

    $row_result = $wpdb->get_row( // Grab the row from the source table
        "SELECT * FROM $source_table WHERE $key_column_name = $setting_id"
    , ARRAY_A);
    $source_setting_id = array_shift($row_result); // Remove the first (primary) element
    if(!$dry_run){ // Paste the row into the target table and save the resulting ID
        $wpdb->insert($target_table, $row_result);
        $target_setting_id = $wpdb->insert_id;
    }
    else echo "insert into $target_table \n" . print_r($row_result, true) . "\n\n";

    $row_result['target_id'] = $target_setting_id;
    return $row_result;
}

function clone_postmeta($source_setting_id, $target_setting_id){
    global $wpdb, $dry_run, $source_site_prefix, $target_site_prefix;
                    
    $postmeta = array();
    $postmeta_results = $wpdb->get_results( // Grab all postmeta for this post from the source table
        "SELECT * FROM ${source_site_prefix}postmeta WHERE post_id = $source_setting_id"
    , ARRAY_A);
    foreach($postmeta_results as $result){ // Paste each postmeta row into the target table
        $postmeta[$result['meta_key']] = $result['meta_value']; // Record postmeta to an array
        $data = array(
            "post_id"       => $target_setting_id,
            "meta_key"      => $result['meta_key'],
            "meta_value"    => $result['meta_value']
        );
        if(!$dry_run) $wpdb->insert($target_site_prefix . "postmeta", $data);
        else echo "insert into ${target_site_prefix}postmeta \n" . print_r($data, true) . "\n\n";
    }
    if($dry_run) echo 'POSTMETA: ' . print_r($postmeta, true);
    return $postmeta;
}

function clone_template_layout($type, $layout_id){ // $type = 'header', 'body', or 'footer'
    global $wpdb, $dry_run, $target_setting_id, $target_site_prefix;
    
    if($layout_id != 0){
        $layout_row = clone_setting($layout_id); // First clone the layout itself

        // The postmeta of the previously cloned template needs to be updated to reflect the new layout IDs
        $data = array('meta_value' => $layout_row['target_id']);
        $where = array(
            'post_id' => $target_setting_id, 
            'meta_key' => '_et_' . $type . '_layout_id'
        );
        if(!$dry_run) $wpdb->update($target_site_prefix . "postmeta", $data, $where);
        else echo "insert into ${target_site_prefix}postmeta \n" . print_r($data, true) . "\n where" . print_r($where, true) . "\n\n";

        clone_postmeta($layout_id, $layout_row['target_id']); // Clone all postmeta of layout as well
    }
}