<?php
/*
Plugin Name: EA WP bbPress Invision v4 Converter
Plugin URI: https://github.com/EnhancedAthlete/bbPress-Invision-v4-Converter
Description: Converts Invision v4 forums to bbPress. NB: Plugin must remain active to allow imported users to log in.
Version: 0.9
Author: BrianHenryIE
Author URI: http://BrianHenry.IE
License: GPL2
*/

// TODO: Add a UI for this
$ipb_uploads_url = 'https://forum.enhancedathlete.com/uploads';
update_option('bbpress_converter_ipb_uploads_url', $ipb_uploads_url );

/**
 * Add this converter to bbPress's list of available converters
 *
 * converter.php : 54
 * return (array) apply_filters( 'bbp_get_converters', $files );
 *
 * @param string[] $files
 *
 * @return string[]
 */
function add_invision_converter( $files ) {

	$converter_path = __DIR__ . '/' . 'InvisionV4.php';

	$files[ 'InvisionV4' ] = $converter_path;

	ksort($files);

	return $files;
}
add_filter('bbp_get_converters', 'add_invision_converter' );


/**
 * Add a link on the plugins page to the importer
 *
 * @param string[] $links
 *
 * @return string[]
 */
function add_action_links ($links){

	$importer_url = admin_url('/tools.php?page=bbp-converter');

	$new_links = array('<a href="' . $importer_url . '">Open Importer</a>',);

	return array_merge( $links, $new_links );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );