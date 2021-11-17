<?php
/**
 * Plugin Name: Rollout Tool
 * Plugin URI: http://exploremytown.com
 * Description: Uses query techniques to update websites on-the-fly.
 * Version: 1.0
 * Author: N Palmer
*/

function siup_include_admin_scripts() { // Include admin css/js
    $pluginUrl = plugin_dir_url( __FILE__ );
	wp_enqueue_script('siup_admin_functions', $pluginUrl  . 'admin-functions.js');
	wp_localize_script('siup_admin_functions', 'SiupAdminVars', array(
	    'pluginPath' => plugin_dir_url( __FILE__ ),
	));
    wp_enqueue_style( 'siup_admin_css', $pluginUrl  . 'admin-style.css' );
}
add_action('admin_enqueue_scripts', 'siup_include_admin_scripts');

include_once(plugin_dir_path( __FILE__ ) . 'options.php');