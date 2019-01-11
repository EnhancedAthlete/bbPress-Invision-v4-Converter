<?php

/**
 * bbPress Invision v4 Converter
 *
 * @package bbPress
 * @subpackage Converters
 */

/**
 * Implementation of Invision Community (Power Board) v4.x forum converter.
 *
 * @since 2.6-rc-6 bbPress (r6854)
 *
 * @link https://github.com/EnhancedAthlete/bbPress-Invision-v4-Converter
 */
class InvisionV4 extends BBP_Converter_Base {

	public const ANNOUNCEMENT_POST_TYPE = "announcement-temp";
	public const ATTACHMENT_POST_TYPE   = "attachment-temp";

	private $rest_server;

	private $forums_relative_url;

	private $redirection_group_name = 'bbPress';
	private $redirection_group_id = null;

	private $wp_user_avatar_user_meta_key = null;

	private $ipb_uploads_url;

	// No trailing slashes
	private $wp_uploads_dir_path;

	// begins with /
	private $wp_uploads_url_path;

	private $wp_emoticons_dir_path;
	private $wp_emoticons_url_path;

	private $ipb_emoticons_url;

	/**
	 * Main Constructor
	 *
	 * Runs long after WordPress init
	 */
	public function __construct() {
		parent::__construct();

		$this->configureUploadsPaths();

		$this->rest_server = rest_get_server();

		$this->redirection_init();

		$this->wp_user_avatar_init();

		add_action( "added_user_meta", array( $this, 'set_user_role'), 20, 4 );

		add_action( "added_user_meta", array( $this, 'set_user_ban'), 25, 4 );

		add_action( "added_post_meta", array( $this, 'set_post_hidden'), 20, 4 );

		add_action( "added_post_meta", array( $this, 'set_post_closed'), 20, 4 );

		add_action( "added_post_meta", array( $this, 'process_has_url_attachment'), 20, 4 );
	}

	/**
	 * Adding trailing slash to all paths
	 */
	private function configureUploadsPaths() {

		$wordpress_install_url = get_option('home');
		$wordpress_install_url = trailingslashit($wordpress_install_url);

		$forums_root = get_option( '_bbp_root_slug');
		$forums_url = $wordpress_install_url . $forums_root;

		$url = wp_parse_url($forums_url);
		$this->forums_relative_url = trailingslashit( $url['path'] );

		$this->ipb_uploads_url = trailingslashit( get_option( 'bbpress_converter_ipb_uploads_url' ) );

		$upload_dir      = wp_upload_dir();

		$this->wp_uploads_dir_path = trailingslashit( $upload_dir['basedir'] );

		$this->wp_uploads_url_path = str_replace( get_site_url(), '', $upload_dir['baseurl'] . '/' );

		$wp_emoticons_dir_path = $this->wp_uploads_dir_path . 'ipb_emoticons/';
		wp_mkdir_p( $wp_emoticons_dir_path );
		$this->wp_emoticons_dir_path = $wp_emoticons_dir_path;

		$this->wp_emoticons_url_path = $this->wp_uploads_url_path . 'ipb_emoticons/';

		$this->ipb_emoticons_url = $this->ipb_uploads_url . 'emoticons/';

	}
	/**
	 * The WordPress Redirection plugin can be used to avoid 404 links for users
	 *
	 * Check is the plugin active.
	 * Retrieve the group id or create it if absent.
	 *
	 * @see https://wordpress.org/plugins/redirection/
	 */
	private function redirection_init() {

		if( !class_exists( 'Redirection_Api' ) ) {
			return;
		}

		$request = new WP_REST_Request( 'GET', '/redirection/v1/group' );

		$response = rest_do_request( $request );
		$data = $this->rest_server->response_to_data( $response, false );

		$redirection_group_exists = false;

		foreach( $data['items'] as $item ) {
			if( $item['name'] === $this->redirection_group_name ) {
				$this->redirection_group_id = $item['id'];
				$redirection_group_exists = true;
				break;
			}
		}

		if( !$redirection_group_exists ) {

			$request = new WP_REST_Request( 'POST', '/redirection/v1/group' );
			$request->set_query_params( array(
				'name' => $this->redirection_group_name,
				'moduleId' => WordPress_Module::MODULE_ID
				)
			);

			$response = rest_do_request( $request );
			$data = $this->rest_server->response_to_data( $response, false );

			if( !array_key_exists( 'items', $data ) ) {

				error_log( 'redirection_init REST error' );
				error_log( json_encode( $data ) );
			}

			foreach( $data['items'] as $item ) {
				if( $item['id'] === $this->redirection_group_name ) {
					$this->redirection_group_id = $item['id'];
				}
			}
		}

		add_action( "added_post_meta", array( $this, 'add_404_redirect'), 20, 4 );

	}

	public function wp_user_avatar_init() {

		if( !class_exists( 'WP_User_Avatar_Setup' ) ) {
			return;
		}

		/** @var int $wpdb */
		global $blog_id;

		/** @var wpdb $wpdb */
		global $wpdb;

		$this->wp_user_avatar_user_meta_key = $wpdb->get_blog_prefix( $blog_id ) . 'user_avatar';

		add_action( "added_user_meta", array( $this, 'add_wp_user_avatar'), 20, 4 );
	}

	/**
	 * Sets up the field mappings
	 */
	public function setup_globals() {

		// Setup smiley URL & path
		$this->bbcode_parser_properties = array(
			'smiley_url' => false,
			'smiley_dir' => false
		);

		/** Forum Section *****************************************************/

		// Old forum id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'id',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_id'
		);

		// Forum parent id (If no parent, then 0, Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'parent_id',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_old_forum_parent_id'
		);

		// Forum topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums_forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_topic_count'
		);

		// Forum reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums_forums',
			'from_fieldname' => 'posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_reply_count'
		);

		// Forum total topic count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums_forums',
			'from_fieldname' => 'topics',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_topic_count'
		);

		// Forum total reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename' => 'forums_forums',
			'from_fieldname' => 'posts',
			'to_type'        => 'forum',
			'to_fieldname'   => '_bbp_total_reply_count'
		);

		// Forum title.
		// TODO pass id to a callback and get actual name from:
		// TODO: now it just uses the [seo] slug
		// SELECT * FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums'
		// SELECT * FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key LIKE 'forums_forum_%'
		// SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1'
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'name_seo',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_title'
		);

		// Forum slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'name_seo',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Forum description.
		// TODO pass id to a callback and get actual name from:
		// TODO: now it just uses the [seo] slug
		// SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1_desc'
		// Strip HTML from description
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'name_seo',
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_null'
		);


		// Forum display order (Starts from 1)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'position',
			'to_type'         => 'forum',
			'to_fieldname'    => 'menu_order'
		);

		// Forum type (Forum = 0 or Category = -1, Stored in postmeta)
		// parent_id returns 0 or -1 and the callback translates that to forum or category
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'parent_id',
			'to_type'         => 'forum',
			'to_fieldname'    => '_bbp_forum_type',
			'callback_method' => 'callback_forum_type'
		);

		// Forum status (Set a default value 'open', Stored in postmeta)
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => '_bbp_status',
			'default'      => 'open'
		);

		// Forum dates.
		// I don't see anywhere IPB records the forum creation dates, so it defaults to now.
		// Later figure out the oldest post in the forum.
		$this->field_map[] = array(
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_date',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_date_gmt',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_modified',
			'default' => date('Y-m-d H:i:s')
		);
		$this->field_map[] = array(
			'to_type'         => 'forum',
			'to_fieldname'    => 'post_modified_gmt',
			'default' => date('Y-m-d H:i:s')
		);

		// Store the forum url fragment for creating a redirect link later
		$this->field_map[] = array(
			'from_tablename'  => 'forums_forums',
			'from_fieldname'  => 'name_seo',
			'to_type'         => 'forum',
			'to_fieldname'    => '_ipb_forum_name_seo'
		);

		/** Topic Section *****************************************************/

		// Old topic id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'tid',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_topic_id'
		);

		// Topic reply count (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'posts',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_reply_count',
			'callback_method' => 'callback_topic_reply_count'
		);

		// Topic parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_forumid'
		);

		// Topic author.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'starter_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Topic content.
		// Note: We join the posts table because topics do not have content.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post',
			'join_tablename'  => 'forums_topics',
			'join_type'       => 'INNER',
			'join_expression' => 'ON(forums_topics.tid = forums_posts.topic_id) WHERE forums_posts.new_topic = 1',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Topic post id
		// Because invision topic content is pulled from the forums_post table, the original post id is lost, then
		// isn't available in the `wp_bbp_converter_translator` table for lookup.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Post has URL attachment (as opposed to an attachment recorded in the IPB database).
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post',
			'to_type'         => 'topic',
			'to_fieldname'    => '_has_url_attachment',
			'callback_method' => 'callback_has_url_attachment'
		);

		// Topic title.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'title',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_title'
		);

		// Topic slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'title_seo',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Topic parent forum id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'forum_id',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_forumid'
		);

		// Sticky status (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'pinned',
			'to_type'         => 'topic',
			'to_fieldname'    => '_bbp_old_sticky_status_id',
			'callback_method' => 'callback_sticky_status'
		);

		// Topic dates.
		// unix time
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'start_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'start_date',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'last_post',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'last_post',
			'to_type'         => 'topic',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'forums_topics',
			'from_fieldname' => 'last_post',
			'to_type'        => 'topic',
			'to_fieldname'   => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		// Store the title URL fragment for later creating the 404 redirect
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'title_seo',
			'to_type'         => 'topic',
			'to_fieldname'    => '_ipb_topic_title_seo'
		);

		// approved 1 : visible. -1 : not visible
		// from: SELECT DISTINCT(approved) FROM ipbforum.forums_topics
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'approved',
			'to_type'         => 'topic',
			'to_fieldname'    => '_ipb_topic_approved'
		);

		// SELECT DISTINCT(state) FROM ipbforum.forums_topics
		$this->field_map[] = array(
			'from_tablename'  => 'forums_topics',
			'from_fieldname'  => 'state',
			'to_type'         => 'topic',
			'to_fieldname'    => '_ipb_topic_closed'
		);


		/** Announcements Section *****************************************************/

		// Changes IPB announcements to super stickies with no parent forum

		$this->field_map[] = array(
			'to_type'      => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname' => 'post_type',
			'default'      => self::ANNOUNCEMENT_POST_TYPE
		);

		$this->field_map[] = array(
			'to_type'      => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);

		// Old announcement id (stored negative to avoid clashing with topic ids)
		// I'm afraid to use another meta key, assuming it's referred to widely
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_id',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_bbp_old_topic_id',
			'callback_method' => '__return_negative'
		);
		// Storing it properly here for use in 404 redirection below
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_id',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_bbp_old_announcement_id',
		);

		// Announcement reply count
		// IPB announcements don't have replies
		// TODO: from_fieldname is doing nothing here. Is __return_zero callback the best way to do this?
		$this->field_map[] = array(
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_bbp_reply_count',
			'default'         => 0
		);

		// Announcement parent forum id is always 0
		$this->field_map[] = array(
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_bbp_forum_id',
			'default'         => 0
		);

		// Announcement author.
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_member_id',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Announcement content.
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_content',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Post has URL attachment (as opposed to an attachment recorded in the IPB database).
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_content',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_has_url_attachment',
			'callback_method' => 'callback_has_url_attachment'
		);

		// Announcement title.
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_title',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_title'
		);

		// Announcement slug (Clean name to avoid conflicts)
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_seo_title',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_name',
			'callback_method' => 'callback_slug'
		);

		// Always set announcement parent post id to 0
		$this->field_map[] = array(
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_parent',
			'default'         => 0
		);

		// Announcement dates.
		// unix time
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_start',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_start',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_start',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_start',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename' => 'core_announcements',
			'from_fieldname' => 'announce_start',
			'to_type'        => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'   => '_bbp_last_active_time',
			'callback_method' => 'callback_datetime'
		);

		// Store the title URL fragment for later creating the 404 redirect
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_seo_title',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_ipb_announcement_title_seo'
		);

		// Close bbPress topic because it's an announcement (and IPB announcements didn't have replies)
		$this->field_map[] = array(
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_ipb_topic_closed',
			'default'         => 'closed'
		);

		// 0 for hidden, 1 for active
		// SELECT DISTINCT(announce_active) FROM ipbforum.core_announcements;
		$this->field_map[] = array(
			'from_tablename'  => 'core_announcements',
			'from_fieldname'  => 'announce_active',
			'to_type'         => self::ANNOUNCEMENT_POST_TYPE,
			'to_fieldname'    => '_ipb_announce_active'
		);


		/** Tags Section ******************************************************/

		// Topic id.
		$this->field_map[] = array(
			'from_tablename'  => 'core_tags',
			'from_fieldname'  => 'tag_meta_id',
			'to_type'         => 'tags',
			'to_fieldname'    => 'objectid',
			'callback_method' => 'callback_topicid'
		);

		// Term text.
		$this->field_map[] = array(
			'from_tablename'  => 'core_tags',
			'from_fieldname'  => 'tag_text',
			'from_expression' => 'WHERE tag_text IS NOT NULL',
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
		);

		$this->field_map[] = array(
			'to_type'         => 'tags',
			'to_fieldname'    => 'description',
			'default'         => ''
		);

		$this->field_map[] = array(
			'from_tablename'  => 'core_tags',
			'from_fieldname'  => 'tag_text',
			'to_type'         => 'tags',
			'to_fieldname'    => 'slug',
			'callback_method' => 'callback_slug'
		);


		/** Reply Section *****************************************************/

		// Old reply id (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'pid',
			'from_expression' => 'WHERE new_topic = 0',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_old_reply_id'
		);

		// Reply parent forum id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_forum_id',
			'callback_method' => 'callback_topicid_to_forumid'
		);

		// Reply parent topic id (If no parent, then 0. Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_topic_id',
			'callback_method' => 'callback_topicid'
		);

		// Reply author ip (Stored in postmeta)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'ip_address',
			'to_type'         => 'reply',
			'to_fieldname'    => '_bbp_author_ip'
		);

		// Reply author.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'author_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Reply content.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_content',
			'callback_method' => 'callback_html'
		);

		// Post has URL attachment (as opposed to an attachment recorded in the IPB database).
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post',
			'to_type'         => 'reply',
			'to_fieldname'    => '_has_url_attachment',
			'callback_method' => 'callback_has_url_attachment'
		);

		// Reply parent topic id (If no parent, then 0)
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'topic_id',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_topicid'
		);

		// Reply dates.
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'post_date',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'edit_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'edit_time',
			'to_type'         => 'reply',
			'to_fieldname'    => 'post_modified_gmt',
			'callback_method' => 'callback_datetime'
		);

		$this->field_map[] = array(
			'from_tablename'  => 'forums_posts',
			'from_fieldname'  => 'queued',
			'to_type'         => 'reply',
			'to_fieldname'    => '_ipb_post_queued',
		);


		/** Attachment Section ******************************************************/

		$this->field_map[] = array(
			'to_type'      => self::ATTACHMENT_POST_TYPE,
			'to_fieldname' => 'post_type',
			'default'      => self::ATTACHMENT_POST_TYPE
		);

		// Attachment Id. Stored in post menu_order field (the post is temporary)
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_id',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'menu_order',
		);

		// Attachment Filename.
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_file',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_title',
		);

		// Attachment extension.
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_ext',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_mime_type',
		);

		// Attachment URL path (relative)
		// The relative path to the file including the filename, from the /uploads/ url, with no leading /
		// e.g. monthly_2017_09/IMG_20170914_063031.jpg.2a66d5cfaa07c6b051bac09452b83c19.jpg
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_location',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_content',
		);

		// Attachment User Id.
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_member_id',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_author',
			'callback_method' => 'callback_userid'
		);

		// Attachment Topic Id.
		// id1 is ipb toic id, id2 is ipb post id.
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments_map',
			'from_fieldname'  => 'id2',
			'join_tablename'  => 'core_attachments',
			'join_type'       => 'INNER',
			'join_expression' => 'ON(core_attachments.attach_id = core_attachments_map.attachment_id) WHERE core_attachments_map.location_key = "forums_Forums"',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_parent',
			'callback_method' => 'callback_reply_to'
		);

		// Attachment Date.
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_date',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_date',
			'callback_method' => 'callback_datetime'
		);
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_date',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => 'post_date_gmt',
			'callback_method' => 'callback_datetime'
		);

		// Attachment thumbnail
		$this->field_map[] = array(
			'from_tablename'  => 'core_attachments',
			'from_fieldname'  => 'attach_thumb_location',
			'to_type'         => self::ATTACHMENT_POST_TYPE,
			'to_fieldname'    => '_attachment_thumbnail_location',
		);


		/** User Section ******************************************************/

		// Store old user id (Stored in usermeta)
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'member_id',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_old_user_id'
		);

		// Store old user password (Stored in usermeta serialized with salt)
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'members_pass_hash',
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_password',
			'callback_method' => 'callback_savepass'
		);

		// Store old user salt (This is only used for the SELECT row info for the above password save)
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'members_pass_salt',
			'to_type'         => 'user',
			'to_fieldname'    => ''
		);

		// User password verify class (Stored in usermeta for verifying password)
		$this->field_map[] = array(
			'to_type'         => 'user',
			'to_fieldname'    => '_bbp_class',
			'default' => 'InvisionV4'
		);

		// User name.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'name',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_login'
		);

		// User nice name.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'name',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_nicename'
		);

		// User email.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'email',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_email'
		);

		// User registered.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'joined',
			'to_type'         => 'user',
			'to_fieldname'    => 'user_registered',
			'callback_method' => 'callback_datetime'
		);

		// User display name.
		$this->field_map[] = array(
			'from_tablename' => 'core_members',
			'from_fieldname' => 'name',
			'to_type'        => 'user',
			'to_fieldname'   => 'display_name'
		);

		// User avatar update date.
		// Used to check the user had set a custom avatar
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'photo_last_update',
			'to_type'         => 'user',
			'to_fieldname'    => '_ipb_user_photo_update_date'
		);

		// User avatar.
		// Placed after thumbnail import so that meta key is set first
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'pp_main_photo',
			'to_type'         => 'user',
			'to_fieldname'    => '_ipb_user_photo'
		);


		// User group / role.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'member_group_id',
			'to_type'         => 'user',
			'to_fieldname'    => '_ipb_user_group'
		);

		// User ban
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'temp_ban',
			'to_type'         => 'user',
			'to_fieldname'    => '_ipb_user_ban'
		);
	}


	/**
	 * This method allows us to indicates what is or is not converted for each
	 * converter.
	 *
	 * Doesn't seem to be in use in any of the included converters
	 */
	public function info() {
		return '';
	}


	/**
	 * Imports announcements as super-stickies.
	 *
	 * Temporarily registers a post type for importing which is then changed to topic.
	 *
	 * @param int $start
	 *
	 * @return bool
	 */
	public function convert_topic_super_stickies( $start = 1 ) {

		register_post_type( InvisionV4::ANNOUNCEMENT_POST_TYPE );

		$success = $this->convert_table( InvisionV4::ANNOUNCEMENT_POST_TYPE, $start );

		/** @var WP_Post[] $new_posts */
		$new_posts = get_posts( array(
			'post_type' => InvisionV4::ANNOUNCEMENT_POST_TYPE,
			'numberposts' => -1,
		));

		foreach( $new_posts as $announcement ) {
			set_post_type( $announcement->ID, 'topic' );
			bbp_stick_topic( $announcement->ID, true );

			bbp_close_topic( $announcement->ID );

			$ipb_announce_active = get_post_meta( $announcement->ID, '_ipb_announce_active', true );

			if( $ipb_announce_active != 1 ) {

				bbp_unapprove_topic( $announcement->ID );
				bbp_unstick_topic( $announcement->ID );
			}

			delete_post_meta( $announcement->ID, '_ipb_announce_active' );
		}

		return $success;
	}

	/**
	 * Import attachments.
	 *
	 * In testing, there never were any anonymous reply authors. This function is being overridden for importing
	 * attachments.
	 *
	 * @param int $start
	 *
	 * @return bool
	 */
	public function convert_anonymous_reply_authors( $start = 1 ) {

		register_post_type( InvisionV4::ATTACHMENT_POST_TYPE );

		// Pull in the attachments as WordPress posts with custom post type
		$result = $this->convert_table( InvisionV4::ATTACHMENT_POST_TYPE, $start );

		/** @var WP_Post[] $new_posts */
		$new_posts = get_posts( array(
			'post_type' => InvisionV4::ATTACHMENT_POST_TYPE,
			'numberposts' => -1,
			'post_status' => 'draft'
		));

		// Download them and add them to the media library
		foreach( $new_posts as $attachment_post ) {

			$ipb_attachment_id          = $attachment_post->menu_order;
			$filename                   = $attachment_post->post_title;
			$file_ext                   = $attachment_post->post_mime_type;

			// https://forum.enhancedathlete.com/uploads/monthly_2016_08/IMG_20160818.jpg.6873547ba2dc84ed4b08c362ae2657c7.jpg
			$ipb_attachment_direct_url  = $this->ipb_uploads_url . $attachment_post->post_content;

			// /uploads/monthly_2016_08/IMG_20160818.jpg.6873547ba2dc84ed4b08c362ae2657c7.jpg
			$ipb_attachment_direct_url_relative =  wp_parse_url( $ipb_attachment_direct_url )[ 'path' ];

			// https://forum.enhancedathlete.com/uploads/applications/core/interface/file/attachment.php?id=839
			$ipb_attachment_php_url     = $this->ipb_uploads_url . 'applications/core/interface/file/attachment.php?id=' . $ipb_attachment_id;

			// /uploads/applications/core/interface/file/attachment.php?id=839
			$ipb_attachment_php_url_relative = wp_parse_url( $ipb_attachment_php_url )[ 'path' ];

			$attachment_user_id         = $attachment_post->post_author;
			$attachment_date            = $attachment_post->post_date;
			$parent_post_id             = $attachment_post->post_parent;

			// $attachment_post->post_content
			// monthly_2016_08/IMG_20160818.jpg.6873547ba2dc84ed4b08c362ae2657c7.jpg
			$upload_folder = null;
			$year_month_array = array();
			if( false != preg_match('/monthly_(\d{4})_(\d{2}).*/', $attachment_post->post_content, $year_month_array) ) {
				$upload_folder = $year_month_array[1] . '/' . $year_month_array[2];
			}

			$wp_attachment_id = $this->upload_to_media_library( $ipb_attachment_direct_url, $upload_folder, $parent_post_id, $attachment_date, $file_ext, $filename );

			if( is_wp_error( $wp_attachment_id ) ) {
				// The file did not upload successfully
				error_log( 'ipb attachment: ' . $ipb_attachment_id . ', wp user: ' . $attachment_user_id . ', wp parent post: ' . $parent_post_id );
				wp_delete_post( $attachment_post->ID );
				continue;
			}

			wp_update_post( array(
				'ID'                => $wp_attachment_id,
				'post_title'        => $filename,
				'post_author'       => $attachment_user_id,
				'post_date'         => $attachment_date,
				'post_date_gmt'     => $attachment_date,
			));


			// Add redirects from old urls to the WordPress URL

			// https://forum.enhancedathlete.com/wp-content/uploads/2019/01/asd.jpg
			$new_attachment_url = wp_get_attachment_url( $wp_attachment_id );

			// /wp-content/uploads/2019/01/asd.jpg
			$new_attachment_url_relative = wp_parse_url( $new_attachment_url )[ 'path' ];

			$this->create_redirect_to_local_url( $ipb_attachment_php_url_relative,    $new_attachment_url_relative );
			$this->create_redirect_to_local_url( $ipb_attachment_direct_url_relative, $new_attachment_url_relative );


			// Update the URL used in the post itself
			// `<a href="/uploads/applications/core/interface/file/attachment.php?id=839" data-fileid="839" rel="">`

			/** @var WP_Post $parent_post */
			$parent_post = get_post( $parent_post_id );

			if( null == $parent_post ) {

				wp_delete_post( $attachment_post->ID );
				continue;
			}

			$post_content = $parent_post->post_content;

			$post_content = str_replace( $ipb_attachment_php_url_relative, $new_attachment_url_relative, $post_content );
			$post_content = str_replace( $ipb_attachment_direct_url_relative, $new_attachment_url_relative, $post_content );

			// Update the thumbnail
			// <a class="ipsAttachLink ipsAttachLink_image" href="<fileStore.core_Attachment>/monthly_2017_09/Screenshot_20170910-152038.png.6f296e278cac3bc3d100c7c1e47a05d4.png" data-fileid="1633" rel=""><img class="ipsImage ipsImage_thumbnailed" data-fileid="1633" src="<fileStore.core_Attachment>/monthly_2017_09/Screenshot_20170910-152038.thumb.png.10c5324e28bcb06995193ac45425f4fa.png" alt="Screenshot_20170910-152038.thumb.png.10c5324e28bcb06995193ac45425f4fa.png" /></a>

			// /monthly_2017_09/Screenshot_20170910-152038.thumb.png.10c5324e28bcb06995193ac45425f4fa.png
			$ipb_thumbnail = get_post_meta( $attachment_post->ID, '_attachment_thumbnail_location', true );

			if( !empty( $ipb_thumbnail ) ) {

				// https://forum.enhancedathlete.com/uploads/monthly_2017_09/Screenshot_20170910-152038.thumb.png.10c5324e28bcb06995193ac45425f4fa.png
				$ipb_thumbnail_url = $this->ipb_uploads_url . $ipb_thumbnail;

				// /uploads/monthly_2017_09/Screenshot_20170910-152038.thumb.png.10c5324e28bcb06995193ac45425f4fa.png
				$ipb_thumbnail_url_path = wp_parse_url( $ipb_thumbnail_url )[ 'path' ];

				$image_arr = wp_get_attachment_image_src( $wp_attachment_id, 'medium' );
				if ( false != $image_arr ) {

					// https://forum-staging.gv1md4q4-liquidwebsites.com/wp-content/uploads/2019/01/Screen-Shot-2019-01-02-at-3.34.13-PM-176x300.png
					$wp_thumbnail_url = $image_arr[0];

					// /wp-content/uploads/2019/01/Screen-Shot-2019-01-02-at-3.34.13-PM-176x300.png
					$wp_thumbnail_url_path = wp_parse_url( $wp_thumbnail_url )[ 'path' ];

					$post_content = str_replace( $ipb_thumbnail_url_path, $wp_thumbnail_url_path, $post_content );
				}
			}

			wp_update_post( array(
				'ID' => $parent_post_id,
				'post_content' => $post_content
			));

			// GD bbPress Attachments
			update_post_meta( $wp_attachment_id, '_bbp_attachment', '1' );

			wp_delete_post( $attachment_post->ID );
		}

		return $result;
	}

	/**
	 * This is the last step in the conversion so is being overridden to do some cleanup
	 *
	 * Run repairs after import to recalculate metadata
	 *
	 * @return bool
	 */
	public function convert_reply_to_parents( $start = 1 ) {

		$success = parent::convert_reply_to_parents( $start );

		require_once ( get_home_path() . '/wp-content/plugins/bbpress/includes/admin/tools/repair.php' );

		// Forum Last Posts were appearing as "No Topics"
		bbp_admin_repair_freshness();

		// Voice count was not being calculated after import
		bbp_admin_repair_topic_voice_count();

		$this->calculateSetReturnForumDateFromOldestPost();

		return $success;
	}

	/**
	 * Traverses forums setting the forum creation date to match its oldest topic or the oldest topic in its subforums
	 *
	 * @param int $forum_id
	 *
	 * @return WP_Post
	 */
	function calculateSetReturnForumDateFromOldestPost( $forum_id = 0 ) {

		/** @var WP_Post[] $oldest_topics */
		$oldest_topics = array();

		/** @var WP_Post[] $oldest_topics_in_forum */
		$oldest_topics_in_forum = get_posts( array(
			'post_type'      => 'topic',
			'post_parent'      => $forum_id,
			'order_by'       => 'publish_date',
			'order'          => 'ASC',
			'posts_per_page' => 1
		) );

		$oldest_topics = array_merge( $oldest_topics, $oldest_topics_in_forum );

		$subforum_ids = get_posts( array(
			'post_type' => 'forum',
			'fields'    => 'ids',
			'post_parent'  => $forum_id,
		) );

		foreach($subforum_ids as $subforum_id) {

			$oldest_topics[] = $this->calculateSetReturnForumDateFromOldestPost( $subforum_id );
		}

		/** @var WP_Post $oldest_topic */
		$oldest_topic = null;

		foreach( $oldest_topics as $topic ) {

			if( $oldest_topic == null ) {

				$oldest_topic = $topic;
				continue;
			}

			if( strtotime( $topic->post_date ) <  strtotime( $oldest_topic->post_date ) ) {

				$oldest_topic = $topic;
			}
		}

		if( $oldest_topic != null && $forum_id != 0 ) {

			wp_update_post(
				array(
					'ID'            => $forum_id,
					'post_date'     => $oldest_topic->post_date,
					'post_date_gmt' => $oldest_topic->post_date
				)
			);
		}

		return $oldest_topic;
	}

	public function __return_negative( $value ) {
		return -1 * abs( $value );
	}

	/**
	 * Translate the forum type from Invision numerics to WordPress's strings.
	 *
	 * @param int $status Invision numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_forum_type( $status = 0 ) {
		if ( $status == -1 ) {
			$status = 'category';
		} else {
			$status = 'forum';
		}
		return $status;
	}

	/**
	 * Translate the topic sticky status type from Invision numerics to WordPress's strings.
	 *
	 * @param int $status Invision numeric forum type
	 * @return string WordPress safe
	 */
	public function callback_sticky_status( $status = 0 ) {
		switch ( $status ) {
			case 1 :
				$status = 'sticky';       // Invision Pinned Topic 'pinned = 1'
				break;

			case 0  :
			default :
				$status = 'normal';       // Invision Normal Topic 'pinned = 0'
				break;
		}
		return $status;
	}

	/**
	 * Verify the topic reply count.
	 *
	 * @param int $count Invision reply count
	 * @return string WordPress safe
	 */
	public function callback_topic_reply_count( $count = 1 ) {
		$count = absint( (int) $count - 1 );
		return $count;
	}

	/**
	 * This method is to save the salt and password together.  That
	 * way when we authenticate it we can get it out of the database
	 * as one value. Array values are auto sanitized by WordPress.
	 */
	public function callback_savepass( $field, $row ) {
		return array( 'hash' => $field, 'salt' => $row['members_pass_salt'] );
	}

	/**
	 * This method is to take the pass out of the database and compare
	 * to a pass the user has typed in.
	 *
	 * Hash function found in IPB Member.php line 2507 encryptedPassword()
	 */
	public function authenticate_pass( $password, $serialized_pass ) {
		$pass_array = unserialize( $serialized_pass );

		return ( $pass_array['hash'] == crypt( $password, '$2a$13$' . $pass_array['salt'] ) );
	}

	/**
	 * This callback processes any custom BBCodes with parser.php
	 */
	protected function callback_html( $field ) {

		// Strips Invision custom HTML first from $field before parsing $field to parser.php
		$invision_markup = $field;
		$invision_markup = html_entity_decode( $invision_markup );

		// Replace '[html]' with '<pre><code>'
		$invision_markup = preg_replace( '/\[html\]/', '<pre><code>',     $invision_markup );
		// Replace '[/html]' with '</code></pre>'
		$invision_markup = preg_replace( '/\[\/html\]/', '</code></pre>', $invision_markup );
		// Replace '[sql]' with '<pre><code>'
		$invision_markup = preg_replace( '/\[sql\]/', '<pre><code>',      $invision_markup );
		// Replace '[/sql]' with '</code></pre>'
		$invision_markup = preg_replace( '/\[\/sql\]/', '</code></pre>',  $invision_markup );
		// Replace '[php]' with '<pre><code>'
		$invision_markup = preg_replace( '/\[php\]/', '<pre><code>',      $invision_markup );
		// Replace '[/php]' with '</code></pre>'
		$invision_markup = preg_replace( '/\[\/php\]/', '</code></pre>',  $invision_markup );
		// Replace '[xml]' with '<pre><code>'
		$invision_markup = preg_replace( '/\[xml\]/', '<pre><code>',      $invision_markup );
		// Replace '[/xml]' with '</code></pre>'
		$invision_markup = preg_replace( '/\[\/xml\]/', '</code></pre>',  $invision_markup );
		// Replace '[CODE]' with '<pre><code>'
		$invision_markup = preg_replace( '/\[CODE\]/', '<pre><code>',     $invision_markup );
		// Replace '[/CODE]' with '</code></pre>'
		$invision_markup = preg_replace( '/\[\/CODE\]/', '</code></pre>', $invision_markup );

		// Replace '[quote:XXXXXXX]' with '<blockquote>'
		$invision_markup = preg_replace( '/\[quote:(.*?)\]/', '<blockquote>',                            $invision_markup );
		// Replace '[quote="$1"]' with '<em>@$1 wrote:</em><blockquote>'
		$invision_markup = preg_replace( '/\[quote="(.*?)":(.*?)\]/', '<em>@$1 wrote:</em><blockquote>', $invision_markup );
		// Replace '[/quote:XXXXXXX]' with '</blockquote>'
		$invision_markup = preg_replace( '/\[\/quote:(.*?)\]/', '</blockquote>',                         $invision_markup );

		// Replace '[twitter]$1[/twitter]' with '<a href="https://twitter.com/$1">@$1</a>"
		$invision_markup = preg_replace( '/\[twitter\](.*?)\[\/twitter\]/', '<a href="https://twitter.com/$1">@$1</a>', $invision_markup );

		// Replace '[member='username']' with '@username"
		$invision_markup = preg_replace( '/\[member=\'(.*?)\'\]/', '@$1 ', $invision_markup );

		// Replace '[media]' with ''
		$invision_markup = preg_replace( '/\[media\]/', '',   $invision_markup );
		// Replace '[/media]' with ''
		$invision_markup = preg_replace( '/\[\/media\]/', '', $invision_markup );

		// Replace '[list:XXXXXXX]' with '<ul>'
		$invision_markup = preg_replace( '/\[list\]/', '<ul>',                    $invision_markup );
		// Replace '[list=1:XXXXXXX]' with '<ul>'
		$invision_markup = preg_replace( '/\[list=1\]/', '<ul>',                  $invision_markup );
		// Replace '[*:XXXXXXX]' with '<li>'
		$invision_markup = preg_replace( '/\[\*\](.*?)\<br \/\>/', '<li>$1</li>', $invision_markup );
		// Replace '[/list:u:XXXXXXX]' with '</ul>'
		$invision_markup = preg_replace( '/\[\/list\]/', '</ul>',                 $invision_markup );

		// Replace '[hr]' with '<hr>"
		$invision_markup = preg_replace( '/\[hr\]/', '<hr>',     $invision_markup );

		// Replace '[font=XXXXXXX]' with ''
		$invision_markup = preg_replace( '/\[font=(.*?)\]/', '', $invision_markup );
		// Replace '[/font]' with ''
		$invision_markup = preg_replace( '/\[\/font\]/', '',     $invision_markup );

		// Replace any Invision smilies from path '/sp-resources/forum-smileys/sf-smily.gif' with the equivelant WordPress Smilie
		$invision_markup = preg_replace( '/\<img src=(.*?)EMO\_DIR(.*?)bbc_emoticon(.*?)alt=\'(.*?)\' \/\>/', '$4', $invision_markup );
		$invision_markup = preg_replace( '/\:angry\:/',    ':mad:',     $invision_markup );
		$invision_markup = preg_replace( '/\:mellow\:/',   ':neutral:', $invision_markup );
		$invision_markup = preg_replace( '/\:blink\:/',    ':eek:',     $invision_markup );
		$invision_markup = preg_replace( '/B\)/',          ':cool:',    $invision_markup );
		$invision_markup = preg_replace( '/\:rolleyes\:/', ':roll:',    $invision_markup );
		$invision_markup = preg_replace( '/\:unsure\:/',   ':???:',     $invision_markup );

		$invision_markup = $this->invision_emoticons( $invision_markup );

		$invision_markup = $this->invision_member_links( $invision_markup );

		// Update attachment links to relative URLs
		// The files themselves will be imported later and redirections set up
		// href="<fileStore.core_Attachment>/monthly_2017_09/Screenshot_20170910-152038.png.6f296e278cac3bc3d100c7c1e47a05d4.png"
		// TODO: the explicit "/uploads" here is liable to become incorrect
		$invision_markup = str_replace( "<fileStore.core_Attachment>", '/uploads', $invision_markup );

		// iFrame embeds
		$invision_markup = str_replace( "<___base_url___>/index.php?app=core&module=system&controller=embed&url=", '', $invision_markup );

		// Any instances remaining of base url should be in local hyperlinks which should be addressed with Redirection
		// /forum /discussion /topic (the affects the broken /applicattions...attachments URLs, but probably positively)
		$invision_markup = str_replace( "<___base_url___>", '', $invision_markup );

		// Now that Invision custom HTML has been stripped put the cleaned HTML back in $field
		$field = $invision_markup;

		// Parse out any bbCodes in $field with the BBCode 'parser.php'
		return parent::callback_html( $field );
	}


	/**
	 * Replaces links to IPB profiles with bbPress profile links
	 *
	 * Uses the IPB username rather than calculating the WordPress username from IPB user id.
	 *
	 * href="<___base_url___>/profile/5299-brian/"
	 * href="/forums/users/brian"
	 *
	 * @param $invision_markup
	 *
	 * @return string
	 */
	private function invision_member_links( $invision_markup ) {

		$output_array = array();

		if( false != preg_match_all('/<a.*?href="<___base_url___>\/profile\/\d+-(.*?)\/".*?>/', $invision_markup, $output_array) ) {

			foreach( $output_array[0] as $index => $anchor ) {

				$bbpress_user_url = $this->forums_relative_url . 'users/' . $output_array[1][$index];

				$bbpress_user_anchor = '<a href="' . $bbpress_user_url . '">';

				$invision_markup = str_replace( $anchor, $bbpress_user_anchor, $invision_markup );

			}
		}

		$output_array = array();

		// <a href="<___base_url___>/profile/BrianHenryIE" rel="">https://forum.enhancedathlete.com/profile/BrianHenryIE</a> you are absolutely right

		if( false != preg_match_all('/<___base_url___>\/profile\/(\w*)/', $invision_markup, $output_array) ) {

			foreach( $output_array[0] as $index => $anchor ) {

				$bbpress_user_url = $this->forums_relative_url . 'users/' . $output_array[1][$index];

				$invision_markup = str_replace( $anchor, $bbpress_user_url, $invision_markup );

			}
		}

		return $invision_markup;
	}


	/**
	 * I think $bbcode->Parse( $field ) was parsing bbCode in alt="&gt;:(" and title="&gt;:(" and replacing them
	 * with image tags.
	 *
	 * This function copies IPB emoticons to WordPress, replaces the URLs in the post, and removes the alt and title tags
	 *
	 * @param string $invision_markup
	 *
	 * @return string
	 */
	private function invision_emoticons( $invision_markup ) {

		if ( null == $this->ipb_uploads_url ) {
			return $invision_markup;
		}

		// sample string <img alt=":)" data-emoticon="" height="20" src="<fileStore.core_Emoticons>/emoticons/smile.png" srcset="<fileStore.core_Emoticons>/emoticons/smile@2x.png 2x" title=":)" width="20" /></p>

		$output_array = array();

		if( false != preg_match_all('/<img.*?src="<fileStore.core_Emoticons\/?>\/emoticons\/(.*?.png).*?\/emoticons\/(.*?2x.png).*?>/', $invision_markup, $output_array) ) {

			foreach($output_array[0] as $key => $replace) {

				$local_file = $this->wp_emoticons_dir_path . $output_array[1][$key];
				if( !file_exists( $local_file ) ) {
					$remote_file = $this->ipb_emoticons_url . $output_array[1][$key];
					if( !@copy( $remote_file, $local_file ) ) {
						error_log( "Remote emoticon not found: " . $remote_file );
					}
				}

				$local_file = $this->wp_emoticons_dir_path . $output_array[2][$key];
				if( !file_exists( $local_file ) ) {
					$remote_file = $this->ipb_emoticons_url . $output_array[2][$key];
					if( !@copy( $remote_file, $local_file ) ) {
						error_log( "Remote emoticon not found: " . $remote_file );
					}
				}
			}

			$invision_markup = str_replace('<fileStore.core_Emoticons/>/emoticons/', $this->wp_emoticons_url_path, $invision_markup );
			$invision_markup = str_replace('<fileStore.core_Emoticons>/emoticons/', $this->wp_emoticons_url_path, $invision_markup );
		}

		//  sample string <img src="<___base_url___>/uploads/emoticons/ohmy.png" alt=":o" />!</p>

		$output_array = array();

		if( false != preg_match_all('/.*?<___base_url___>\/uploads\/emoticons\/(.*?)"/', $invision_markup, $output_array) ) {

			foreach ( $output_array[1] as $key => $emoticon ) {

				$emoticon = str_replace(' 2x', '', $emoticon );
				$local_file = $this->wp_uploads_dir_path . $emoticon;
				if ( ! file_exists( $local_file ) ) {
					$remote_file = $this->ipb_emoticons_url . $emoticon;
					if( !@copy( $remote_file, $local_file ) ) {
						error_log( "Remote emoticon not found: " . $remote_file );
					}

				}

				$invision_markup       = str_replace( '<___base_url___>/uploads/emoticons/', $this->wp_emoticons_url_path, $invision_markup );
			}
		}

		return $invision_markup;
	}

	/**
	 * Used to check if the forum post contains a local url link that won't be addressed by emoticons or attachments.
	 *
	 * A meta key is set on the post, '_has_url_attachment', then `add_action` is used on meta data to catch when it
	 * is applied, then the full post has been inserted into WordPress and can be manipulated to properly import the
	 * attachment and assign to the post and correct user.
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function callback_has_url_attachment( $content ) {

		if( false != stristr( $content, '<___base_url___>/uploads/')
		    && false == stristr( $content, '<___base_url___>/uploads/emoticons') ) {

			return 'yes';
		}
	}


	/***
	 * When a post mentions a local URL that is not in the IPB4 database, a meta key ~`_announcement_has_url_attachment`
	 * is added with the value `yes` to mark it for processing here. When the meta is saved, the WordPress action
	 * calls this method.
	 *
	 * It is done at this stage because the user_id and post_id are not available in the html callback.
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $post_ID  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function process_has_url_attachment( $mid, $post_ID, $meta_key, $_meta_value ) {

		if( '_has_url_attachment' != $meta_key || 'yes' != $_meta_value) {
			return;
		}

		$post = get_post( $post_ID );

		$post_content = $post->post_content;

		// By this stage /uploads/emoticions should have been addressed

		// sample string <img src="<___base_url___>/uploads/editor/tk/74fbai1mvij3.jpg" alt="74fbai1mvij3.jpg" /></p>
		// has already been parsed above to
		// sample string <img src="/uploads/editor/tk/74fbai1mvij3.jpg" alt="74fbai1mvij3.jpg" /></p>

		$output_array = array();

		if( false != preg_match_all('/<img.*?src="\/uploads\/(.*?)".*?alt="(.*?)".*?>/', $post_content, $output_array) ) {

			$post_year  = date("Y", strtotime( $post->post_date ) );
			$post_month = date("m", strtotime( $post->post_date ) );

			$wp_uploads_subdir = $post_year . '/' . $post_month;

			foreach ( $output_array[1] as $key => $remote_path_name ) {

				$remote_file_url = $this->ipb_uploads_url . $remote_path_name;

				$attachment_id = $this->upload_to_media_library( $remote_file_url, $wp_uploads_subdir, $post->ID, $post->post_date );

				if( is_wp_error( $attachment_id ) ) {

					error_log( 'Failed to fetch file : ' . $remote_file_url );

					continue;
				}

				$filename = $output_array[2][$key];

				wp_update_post( array(
					'ID'                => $attachment_id,
					'post_title'        => $filename,
					'post_author'       => $post->post_author,
					'post_date'         => $post->post_date,
					'post_date_gmt'     => $post->post_date,
				));

				$new_attachment_url          = wp_get_attachment_url( $attachment_id );
				$new_attachment_url_relative = wp_parse_url( $new_attachment_url )[ 'path' ];

				// Add redirect
				$this->create_redirect_to_local_url( '/uploads/' . $remote_path_name, $new_attachment_url_relative );

				// Replace full size image with linked thumbnail

				$image_arr = wp_get_attachment_image_src( $attachment_id, 'medium' );
				if ( false != $image_arr ) {

					// https://forum-staging.gv1md4q4-liquidwebsites.com/wp-content/uploads/2019/01/Screen-Shot-2019-01-02-at-3.34.13-PM-176x300.png
					$thumbnail_url = $image_arr[0];

					// /wp-content/uploads/2019/01/Screen-Shot-2019-01-02-at-3.34.13-PM-176x300.png
					$thumbnail_url_path = wp_parse_url( $thumbnail_url )[ 'path' ];

					// TODO: Don't do this if there's already a link around it.

					$img_link = '<a href="' . $new_attachment_url_relative . '"><img src="' . $thumbnail_url_path .'" alt="' . $filename . '" /></a>';

					$post_content = str_replace( $output_array[0][$key], $img_link, $post_content );
				} else {
					$post_content = str_replace( '/uploads/' . $remote_path_name, $new_attachment_url_relative, $post_content );
				}

				update_post_meta( $attachment_id, '_bbp_attachment', '1' );
			}

			wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => $post_content
			));
		}

		delete_meta( $mid );
	}

	/***
	 * Builds and saves URLs for 404 redirects.
	 *
	 * This is called everytime post meta is added.
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $post_ID  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function add_404_redirect( $mid, $post_ID, $meta_key, $_meta_value ) {

		$old_seo_meta_key = array(
			'forum' => '_ipb_forum_name_seo',
			'topic' => '_ipb_topic_title_seo',
			'announcement' => '_ipb_announcement_title_seo'
		);

		$post_type = array_search ($meta_key, $old_seo_meta_key);

		if( false == $post_type ) {
			return;
		}

		$old_id = get_post_meta( $post_ID, "_bbp_old_{$post_type}_id", true );

		if( false === $old_id ) {
			return;
		}

		$old_url = "/$post_type/$old_id-$_meta_value";

		// Can't auto test if it is a valid URL on private forums.

		$this->create_redirect( $old_url, $post_ID );

		delete_meta( $mid );
	}

	private function create_redirect( $from, $to_post_id ) {
		return $this->create_redirect_to_local_url( $from, "?p=$to_post_id" );
	}

	// Don't preceed with a slash?
	private function create_redirect_to_local_url( $from, $to_url ) {
		// This should never happen since the calling method is added by an action set inside a check for the plugin.s
		if( null == $this->redirection_group_id ) {
			return;
		}

		// Strip the domain from input URL
		$from = preg_replace('/https?:\/\/.*?\//', '/', $from);

		$request = new WP_REST_Request( 'POST', '/redirection/v1/redirect' );
		$request->set_query_params(
			array(
				'url'         => $from,
				'action_data' => array (
					'url' => $to_url
				),
				'action_type' => 'url',
				'group_id'    => $this->redirection_group_id,
				'action_code' => 301,
				'match_type'  => 'url'
			)
		);

		rest_do_request( $request );
	}

	/***
	 *
	 *
	 * This is called everytime user meta is added.
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $user_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function add_wp_user_avatar( $mid, $user_id, $meta_key, $_meta_value ) {

		$ipb_avatar_meta_key = '_ipb_user_photo';

		if( $meta_key != $ipb_avatar_meta_key ) {
			return;
		}

		$user_avatar_update_date_meta_key = '_ipb_user_photo_update_date';

		// unix time
		$user_avatar_update_date = get_user_meta( $user_id, $user_avatar_update_date_meta_key, true );

		delete_user_meta( $user_id, $user_avatar_update_date_meta_key );
		delete_user_meta( $user_id, $ipb_avatar_meta_key );

		// The thumbnail should be fetched and stored first
		// Thumbnails only exist for user uploaded photos
		if( empty( $user_avatar_update_date ) ) {
			return;
		}

		$url = $this->ipb_uploads_url . $_meta_value;

		$post_year  = date("Y", strtotime( $user_avatar_update_date ) );
		$post_month = date("m", strtotime( $user_avatar_update_date ) );

		$upload_folder = $post_year . '/' . $post_month;

		$postdate = date("Y-m-d H:i:s", $user_avatar_update_date );

		$attachment_id = $this->upload_to_media_library( $url, $upload_folder, null, $postdate );

		if( is_wp_error( $attachment_id ) ) {
			error_log('Failed to fetch file : ' . $url );
			return;
		}
		update_user_meta( $user_id, $this->wp_user_avatar_user_meta_key, $attachment_id );

		// Set the owner of the user's image to themself
		wp_update_post( array(
			'ID'                => $attachment_id,
			'post_author'       => $user_id,
			'post_date'         => $postdate,
			'post_date_gmt'     => $postdate,
		) );


	}


	/**
	 * @param string $url
	 * @param string $year_month     "yyyy/mm" to move to a specific folder. (Time formatted in 'yyyy/mm')
	 * @param int    $parent_post_id id of the post to attach the file to
	 * @param string $post_date      MySQL date for 'post_updated'
	 *
	 * @return int|WP_Error attachment id
	 */
	function upload_to_media_library( $url, $year_month = null, $parent_post_id = null, $post_date = null, $file_ext = null, $proper_filename = null ) {

		// Gives us access to the download_url() and wp_handle_sideload() functions
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		if( null != $year_month ) {
			// Make sure the folder exists. This may be addressed inside WP anyway?
			wp_mkdir_p( $this->wp_uploads_dir_path . $year_month );
		}

		// Download file to temp dir
		$timeout_seconds = 20;
		$temp_file = download_url( $url, $timeout_seconds );

		if ( !is_wp_error( $temp_file ) ) {

			$name = is_null( $proper_filename ) ? basename( $url ) : $proper_filename;

			$mime_type = mime_content_type( $temp_file );

			if( is_null( $file_ext ) ) {
				$mimes    = get_allowed_mime_types();
				$file_ext = array_search( $mime_type, $mimes );
				$file_ext = explode( '|', $file_ext )[0];
			}

			// ... because IPB adds a cache/unique string at the end of filenames
			// either cut it off, or
			// Add the file extension to its name if it's not already there.
			$regex_output_array = array();
			if( false != preg_match('/(.*?\.' . $file_ext . ')/i', $name, $regex_output_array) ) {
				$name = $regex_output_array[1];
			} elseif( substr($name , ( -1 * strlen( $file_ext ) ) ) != $file_ext ) {
				$name = "$name.$file_ext";
			}

			// Array based on $_FILE as seen in PHP file uploads
			$file = array(
				'name'     => $name, // ex: wp-header-logo.png
				'type'     => $mime_type,
				'tmp_name' => $temp_file,
				'error'    => 0,
				'size'     => filesize( $temp_file ),
			);

			if( $file_ext ) {
				$file['ext'] = $file_ext;
			}

			$overrides = array(
				// Tells WordPress to not look for the POST form
				// fields that would normally be present as
				// we downloaded the file from a remote server, so there
				// will be no form fields
				// Default is true
				'test_form' => false,

				// Added since this is only run in the plugin, and assumes IPB had reasonable file upload restrictions
				// 'test_type' => false,

				// Setting this to false lets WordPress allow empty files, not recommended
				// Default is true
				'test_size' => true,
			);

			// Move the temporary file into the uploads directory
			$results = wp_handle_sideload( $file, $overrides, $year_month );

			if ( !empty( $results['error'] ) ) {
				// Insert any error handling here
				error_log('Error adding file to media library : ' . $url );
				error_log( $name );
				error_log( json_encode( $results ) );

				return new WP_Error( '321', $results['error'] );

			} else {

				$filename  = $results['file']; // Full path to the file
				$type      = $results['type']; // MIME type of the file

				$attachment = array (
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
					'post_mime_type' => $type,
					'post_status'    => 'inherit',
					'post_content'   => '',
				);

				if( $post_date != null ) {
					$attachment['post_date'] = $post_date;
				}

				$img_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );

				// Generate thumbnails
				$attach_data = wp_generate_attachment_metadata( $img_id, $filename );
				wp_update_attachment_metadata( $img_id,  $attach_data );

				return $img_id;
			}
		} else {
			return $temp_file;
		}
	}

	/***
	 * Once the _ipb_user_group user meta key is set, this function uses its value to set the appropriate bbPress
	 * role for that user.
	 *
	 * IPB defaults are found in install/done.php lines 47 - 62
	 *
	 * SELECT DISTINCT(member_group_id) FROM ipbforum.core_members
	 * Only [3, 4, 6] in our db.
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $user_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function set_user_role( $mid, $user_id, $meta_key, $_meta_value ) {

		$user_group_meta_key = '_ipb_user_group';

		if( $meta_key != $user_group_meta_key ) {
			return;
		}

		switch ( $_meta_value ) {
			case 2:
				// 2: IPB Guests => bbPress Spectators
				bbp_set_user_role( $user_id, 'bbp_spectator' );
				break;
			case 3:
				// 3: IPB Members => bbPress Participant
				// => the default
				break;
			case 4:
				// 4: IPB administrators => bbPress Keymasters
				bbp_set_user_role( $user_id, 'bbp_keymaster' );
				break;
			case 6:
				// 6: IPB moderators => bbPress Moderators
				bbp_set_user_role( $user_id, 'bbp_moderator' );
				break;
		}

		delete_user_meta( $user_id, $meta_key, $_meta_value );
	}

	/***
	 * Ban users who have temp_ban = -1 in ipbforum.core_members.temp_ban
	 *
	 * -1 is permanent ban. 0 is not banned. Unix timestamp is for temporary ban.
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $user_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function set_user_ban( $mid, $user_id, $meta_key, $_meta_value ) {

		$user_ban_meta_key = '_ipb_user_ban';

		if( $meta_key != $user_ban_meta_key ) {
			return;
		}

		if( -1 == $_meta_value ) {

			bbp_set_user_role( $user_id, 'bbp_blocked' );
		}

		delete_user_meta( $user_id, $meta_key, $_meta_value );
	}


	/***
	 * Unapprove topics that were hidden in IPB
	 *
	 * SELECT DISTINCT(announce_active) FROM ipbforum.core_announcements [0, 1]
	 * SELECT DISTINCT(approved) FROM ipbforum.forums_topics [-1, 1]
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $post_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function set_post_hidden( $mid, $post_id, $meta_key, $_meta_value ) {

		$ipb_hidden_meta_key = array(
			'topic'         => '_ipb_topic_approved',
			'post'          => '_ipb_post_queued'
		);

		$post_type = array_search( $meta_key, $ipb_hidden_meta_key );

		if( false == $post_type ) {
			return;
		}

		switch ( $post_type ) {
			case 'topic':
				if( $_meta_value != 1 ) {
					bbp_unapprove_topic( $post_id );
				}
				break;
			case 'post':
				if( $_meta_value != 0 ) {
					bbp_unapprove_reply( $post_id );
				}
				break;
		}

		delete_meta( $mid );
	}

	/***
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $post_id    Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function set_post_closed( $mid, $post_id, $meta_key, $_meta_value ) {

		if( '_ipb_topic_closed' != $meta_key) {
			return;
		}

		if ( 'closed' == $_meta_value ) {
			bbp_close_topic( $post_id );
		}

		delete_meta( $mid );
	}

}