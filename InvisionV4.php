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

	private $rest_server;

	private $forums_relative_url;

	private $ipb_uploads_url;

	private $redirection_group_name = 'bbPress';
	private $redirection_group_id = null;

	private $wp_user_avatar_user_meta_key = null;

	/**
	 * Main Constructor
	 *
	 */
	public function __construct() {
		parent::__construct();

		$wordpress_install_url = get_option('home');
		$wordpress_install_url = trailingslashit($wordpress_install_url);

		$forums_root = get_option( '_bbp_root_slug');
		$forums_url = $wordpress_install_url . $forums_root;

		$url = wp_parse_url($forums_url);
		$this->forums_relative_url = trailingslashit( $url['path'] );

		$this->ipb_uploads_url = 'https://forum.enhancedathlete.com/uploads';

		$this->rest_server = rest_get_server();

		$this->redirection_init();
		$this->wp_user_avatar_init();

		add_action( "added_user_meta", array( $this, 'set_user_role'), 20, 4 );
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
			'to_type'         => 'tags',
			'to_fieldname'    => 'name'
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

		// User avatar thumbnail.
		$this->field_map[] = array(
			'from_tablename'  => 'core_members',
			'from_fieldname'  => 'pp_thumb_photo',
			'to_type'         => 'user',
			'to_fieldname'    => '_ipb_user_thumb_photo'
		);

		// User avatar.
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
		));

		foreach( $new_posts as $announcement ) {
			set_post_type( $announcement->ID, 'topic' );

			$ipb_announce_active = get_post_meta( $announcement->ID, '_ipb_announce_active', true );

			if( $ipb_announce_active != 1 ) {

				// TODO:
				error_log(__FUNCTION__ . ' This is reporting success, but the topic is still approved.');
				error_log( '$ipb_announce_active ' . $announcement->ID . ' : ' . $ipb_announce_active );

				$result = bbp_unapprove_topic( $announcement->ID );

				error_log( 'bbp_unapprove_topic ' . $announcement->ID . ' : ' . $result );
			}

			delete_post_meta( $announcement->ID, '_ipb_announce_active' );

			bbp_stick_topic( $announcement->ID, true );

			bbp_close_topic( $announcement->ID );

		}

		return $success;
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

		$invision_markup = $this->invision_member_links( $invision_markup );

		$invision_markup = $this->invision_emoticons( $invision_markup );

		$invision_markup = $this->import_infusion_media( $invision_markup );

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
	 * href="<___base_url___>/profile/5299-banhammer/"
	 * /forums/users/banhammer
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

		// <img (alt=".*?").*?src="(<fileStore.core_Emoticons>.*?)" srcset="(<fileStore.core_Emoticons>.*?)" (title=".*?")

		$output_array = array();

		if( false != preg_match_all('/<img (alt=".*?").*?src="<fileStore.core_Emoticons>\/emoticons\/(.*?)" srcset="<fileStore.core_Emoticons>\/emoticons\/(.*?) 2x" (title=".*?")/', $invision_markup, $output_array) ) {

			$ipb_uploads_url = untrailingslashit( $this->ipb_uploads_url );
			$upload_dir = wp_upload_dir();
			wp_mkdir_p($upload_dir['basedir'] . '/ipb_emoticons/');

			$invision_markup = preg_replace('/alt=".*?"/', '', $invision_markup );
			$invision_markup = preg_replace('/title=".*?"/', '', $invision_markup );

			// $output_array[2] // angry.png
			$local_file = $upload_dir['basedir'] . '/ipb_emoticons/' . $output_array[2][0];
			if( !file_exists( $local_file ) ) {

				copy( $ipb_uploads_url . '/emoticons/' . $output_array[2][0], $local_file );
			}
			$invision_markup = str_replace( '<fileStore.core_Emoticons>/emoticons/' . $output_array[2][0], $upload_dir['baseurl'] . '/ipb_emoticons/' . $output_array[2][0], $invision_markup );


			$local_file = $upload_dir['basedir'] . '/ipb_emoticons/' . $output_array[3][0];
			if( !file_exists( $local_file ) ) {

				copy( $ipb_uploads_url . '/emoticons/' . $output_array[3][0], $local_file );
			}

			$invision_markup = str_replace( '<fileStore.core_Emoticons>/emoticons/' . $output_array[3][0], $upload_dir['baseurl'] . '/ipb_emoticons/' . $output_array[3][0], $invision_markup );

		}

		return $invision_markup;
	}

	public function import_infusion_media( $invision_markup ) {

		if( null == $this->ipb_uploads_url ) {
			return $invision_markup;
		}

		$ipb_uploads_url = untrailingslashit( $this->ipb_uploads_url );

		$files_found = array();

		if( false != preg_match_all('/<fileStore.core_Attachment>(.*?)"/', $invision_markup, $files_found) ) {

			foreach($files_found[1] as $index => $file_path) {

				$remote_file_url = $ipb_uploads_url . $file_path;

				$year_month_filename = array();

				if( false != preg_match('/monthly_(\d{4})_(\d{2})\/(.*)/', $file_path, $year_month_filename ) ) {

					$year = $year_month_filename[1];
					$month = $year_month_filename[2];
					$filename = $year_month_filename[3];

					$upload_dir = wp_upload_dir($year . '/' . $month );

					$local_file_destination_path = $upload_dir['path'] . '/' . $filename;

					copy( $remote_file_url, $local_file_destination_path );

					$local_file_url = $upload_dir['url'] . '/' . $filename;

					$string_to_replace = $files_found[0][$index];

					$invision_markup = str_replace( $string_to_replace, $local_file_url . '"', $invision_markup );

				}
			}
		}

		return $invision_markup;

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

		delete_post_meta( $post_ID, $meta_key );
	}

	private function create_redirect( $from, $to_post_id ) {

		// This should never happen since the calling method is added by an action set inside a check for the plugin.s
		if( null == $this->redirection_group_id ) {
			return;
		}

		$request = new WP_REST_Request( 'POST', '/redirection/v1/redirect' );
		$request->set_query_params(
			array(
				'url'         => $from,
				'action_data' => array (
					'url' => "?p=$to_post_id"
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

		$old_avatar_meta_key = '_ipb_user_photo';

		if( $meta_key != $old_avatar_meta_key ) {
			return;
		}

		$old_avatar_thumb_meta_key = '_ipb_user_thumb_photo';

		$avatar_thumb = get_user_meta( $user_id, $old_avatar_thumb_meta_key, true );

		delete_user_meta( $user_id, $old_avatar_thumb_meta_key );

		// The thumbnail should be fetched and stored first
		// Thumbnails only exist for user uploaded photos
		if( empty( $avatar_thumb ) ) {
			return;
		}

		$ipb_uploads_url = trailingslashit( $this->ipb_uploads_url );

		$url = $ipb_uploads_url . $_meta_value;

		$attachment_id = $this->image_upload( $url );

		update_user_meta($user_id, $this->wp_user_avatar_user_meta_key, $attachment_id);

		delete_user_meta( $user_id, $old_avatar_meta_key );
	}


	/**
	 * @param $url
	 *
	 * @return int|WP_Error attachment id
	 */
	function image_upload( $url ) {

		// Gives us access to the download_url() and wp_handle_sideload() functions
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		// Download file to temp dir
		$timeout_seconds = 10;
		$temp_file = download_url( $url, $timeout_seconds );

		if ( !is_wp_error( $temp_file ) ) {

			$info = new SplFileInfo($url);

			// Array based on $_FILE as seen in PHP file uploads
			$file = array(
				'name'     => basename($url), // ex: wp-header-logo.png
				'type'     => 'image/' . $info->getExtension(),
				'tmp_name' => $temp_file,
				'error'    => 0,
				'size'     => filesize($temp_file),
			);

			$overrides = array(
				// Tells WordPress to not look for the POST form
				// fields that would normally be present as
				// we downloaded the file from a remote server, so there
				// will be no form fields
				// Default is true
				'test_form' => false,

				// Setting this to false lets WordPress allow empty files, not recommended
				// Default is true
				'test_size' => true,
			);

			// Move the temporary file into the uploads directory
			$results = wp_handle_sideload( $file, $overrides );

			if ( !empty( $results['error'] ) ) {
				// Insert any error handling here
			} else {

				$filename  = $results['file']; // Full path to the file
				$local_url = $results['url'];  // URL to the file in the uploads dir
				$type = $results['type']; // MIME type of the file
				$wp_upload_dir = wp_upload_dir(); // Get the path to the upload directory.

				$attachment = array (
					'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
					'post_mime_type' => $type,
					'post_status' => 'inherit',
					'post_content' => '',
				);

				$img_id = wp_insert_attachment( $attachment, $filename  );

				return $img_id;
			}
		}
	}

	/***
	 * Once the _ipb_user_group user meta key is set, this function uses its value to set the appropriate bbPress
	 * role for that user.
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

		if( $_meta_value != 3) {
			error_log( 'setting role ' . $_meta_value . ' for user ' . $user_id );
		}
		delete_user_meta( $user_id, $meta_key, $_meta_value );

		// 3: IPB Members => bbPress Participant
		if( 3 == $_meta_value ) {
			return;
		}

		// 2: IPB Guests => bbPress Spectators
		if( 2 == $_meta_value ) {
			bbp_set_user_role( $user_id, 'bbp_spectator' );
		}

		// 4: IPB administrators => bbPress Keymasters
		if( 4 == $_meta_value ) {
			bbp_set_user_role( $user_id, 'bbp_keymaster' );
		}

		// 6: IPB moderators => bbPress Moderators
		if( 6 == $_meta_value ) {
			bbp_set_user_role( $user_id, 'bbp_moderator' );
		}
	}
}