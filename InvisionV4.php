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

	private $rest_server;

	private $ipb_uploads_url;

	private $redirection_group_name = 'bbPress';
	private $redirection_group_id = null;

	/**
	 * Main Constructor
	 *
	 */
	public function __construct() {
		parent::__construct();

		$this->rest_server = rest_get_server();

		$this->redirection_init();
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

		if( class_exists( 'Redirection_Api' ) ) {

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
		}
	}

	private function create_redirect( $from, $to_post_id ) {

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

		$invision_markup = $this->import_infusion_media( $invision_markup );

		// Now that Invision custom HTML has been stripped put the cleaned HTML back in $field
		$field = $invision_markup;

		// Parse out any bbCodes in $field with the BBCode 'parser.php'
		return parent::callback_html( $field );

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
}
