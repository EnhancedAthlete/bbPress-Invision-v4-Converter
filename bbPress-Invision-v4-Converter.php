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

/**
 * Files source setting field
 *
 * For importing images and attachments from old forum.
 */
function bbp_converter_setting_callback_original_forum_url() {
	?>

	<input name="_bbp_converter_original_forum_url" id="_bbp_converter_original_forum_url" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_original_forum_url', '' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_original_forum_url' ); ?> />
	<p class="description"><?php printf( esc_html__( '~%s. Used to determine if images & attachments are local and need attention (as opposed to URLs which won\'t be affected by the converter)', 'bbpress' ), '<code>http://oldforum.url/</code>' ); ?></p>

	<?php
}

/**
 * Files source setting field
 *
 * For importing images and attachments from old forum.
 */
function bbp_converter_setting_callback_files_source() {
	?>

	<input name="_bbp_converter_files_source" id="_bbp_converter_files_source" type="text" class="code" value="<?php bbp_form_option( '_bbp_converter_files_source', '' ); ?>" <?php bbp_maybe_admin_setting_disabled( '_bbp_converter_files_source' ); ?> />
	<p class="description"><?php printf( esc_html__( 'Maybe %s, can be any http accessible location of Invisions /uploads/ folder. ', 'bbpress' ), '<code>http://oldforum.url/uploads/</code>' ); ?></p>

	<?php
}

/**
 * Add files source settings field
 *
 * @see bbPress/includes/admin/settings.php l102  bbp_admin_get_settings_fields()
 */
function bbp_admin_original_forum_url_settings_field( $bbp_admin_get_settings_fields ) {

	$original_forum_url = array(
		'title'             => esc_html__( 'Original Invision Forum URL', 'bbpress' ),
		'callback'          => 'bbp_converter_setting_callback_original_forum_url',
		'sanitize_callback' => 'sanitize_text_field',
		'args'              => array( 'label_for' => '_bbp_converter_original_forum_url' )
	);

	$bbp_admin_get_settings_fields['bbp_converter_connection']['_bbp_converter_original_forum_url'] = $original_forum_url;

	return $bbp_admin_get_settings_fields;
}
function bbp_admin_files_source_settings_field( $bbp_admin_get_settings_fields ) {

	$uploads_location_setting = array(
		'title'             => esc_html__( 'Invision Files Source URL', 'bbpress' ),
		'callback'          => 'bbp_converter_setting_callback_files_source',
		'sanitize_callback' => 'sanitize_text_field',
		'args'              => array( 'label_for'=> '_bbp_converter_files_source' )
	);


	$bbp_admin_get_settings_fields['bbp_converter_connection']['_bbp_converter_files_source'] = $uploads_location_setting;

	return $bbp_admin_get_settings_fields;
}

add_filter( 'bbp_admin_get_settings_fields', 'bbp_admin_original_forum_url_settings_field' );

add_filter( 'bbp_admin_get_settings_fields', 'bbp_admin_files_source_settings_field' );


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
function bbp_add_invision_converter( $files ) {

	$converter_path = __DIR__ . '/' . 'InvisionV4.php';

	$files[ 'InvisionV4' ] = $converter_path;

	ksort($files);

	return $files;
}
add_filter('bbp_get_converters', 'bbp_add_invision_converter' );


/**
 * Add a link on the plugins page to the importer
 *
 * @param string[] $links
 *
 * @return string[]
 */
function bbp_invision_converter_add_action_links ($links){

	$importer_url = admin_url('/tools.php?page=bbp-converter');

	$new_links = array('<a href="' . $importer_url . '">Open Importer</a>',);

	return array_merge( $new_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'bbp_invision_converter_add_action_links' );