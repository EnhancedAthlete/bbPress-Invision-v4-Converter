# bbPress Invision v4 Converter

A (*work-in-progress*) converter to migrate from [Invision Community (Power Board) v4](https://invisioncommunity.com/) to [bbPress](https://bbpress.org/) (forums software for WordPress), built as a WordPress plugin.

Tested converting from Invision Community 4.3.6 to [bbPress 2.6-rc-7](https://bbpress.org/download/) on WordPress 4.9.9 and PHP 7.2.

## Installation and Use

* (optional) Install and activate [Redirection](https://wordpress.org/plugins/redirection/) plugin to ensure old links continue to work.
* (optional) Install and activate and [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/) plugin.

1. Install the plugin files into `wp-content/plugins/bbPress-Invision-v4-Converter/` and activate.
4. Navigate to WordPress Admin Dashboard / Tools / Forums / Import Forums (at `wp-admin/tools.php?page=bbp-converter`).
4. Fill in the source database details as usual with every bbPress converter.
5. Fill in the `Original Invision Forum URL` and `Invision Files Source URL`.
6. Press Start. (and be patient).

This plugin must remain active in order for users' Invision passwords to continue to work with WordPress (each user needs to log in once for the password to be saved natively).

## Completed

* Users: passwords, roles, avatars (if [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/) active), banning.
* Forums: importing.
* Topics: importing, unapproving, closing.
* Super-stickies (announcements): importing, unapproving.
* Replies: importing, unapproving, emoticons, images.
* Images / Attachments
* Broken link redirection (if [Redirection](https://wordpress.org/plugins/redirection/) active).

## TODO

* Users: meta-data (social accounts...), old user profile URL redirects*.
* Topics: unapproving reasons.
* Favorites.
* Subscriptions.
* Redirection: urls with trailing slashes don't redirect, .jpg doesn't redirect.
* Forums reset:(media files remain and redirects remain).


Favorites: `core_follow` table


`core_deletion_log` maybe not addressed, thus undeleting topics



. Redirects can be deleted in a single click. Imported attachments are marked `_bbp_attachment`. No action seems to be called during the bbPress reset function.


Use wp-cli to text search for hyperlinks and see if there are any weird ones remaining (see `convert_link...` tables).


Add table of IPB tables in this document and comment what has been converted and what has not


Avatar not showing on:
https://forum-staging.gv1md4q4-liquidwebsites.com/forums/users/roro/
(But is for other users)

User stats on user page not correct, but replies etc do list. (need to run another repair)

https://forum-staging.gv1md4q4-liquidwebsites.com/forums/topic/ 
Redirects to:
https://forum-staging.gv1md4q4-liquidwebsites.com/forums/topic/topical-dnp/
(seems to be 

### Users

`ipbforum.core_members` values to consider importing:

| Invision  | Values/sample  |   |   |   |
|---|---|---|---|---|
| `restrict_post` | `[0, -1]` `-1` means view only? | `bbp_set_user_role( $user_id, 'bbp_spectator' );`  |   |   |
| `mod_posts` | `[0, -1]` `-1` means hold posts for moderation? |   |   |   |
| `last_activity` | unix time | `update_user_meta( 'last_activity', '2019-01-05 20:26:47' );`  |   |   |
| `timezone` |   |   |   |   |
| `allow_admin_mails` | [0, 1] |  |  |  |
| `members_disable_pm` | [0, 1] |  |  |  |
| `msg_show_notification` | [0, 1] |  |  |  |
| `auto_track` | `{"content":1,"comments":1,"method":"daily"}` |  |  |  |
| `pp_cover_photo` | file path below /uploads/ |  |  |  |
| `bday_day`, `bday_month`, `bday_year` |  |  |  |  |
| `members_profile_views` | int |  |  |  |
| `pp_reputation_points` | int |  | http://www.rewweb.co.uk/bbp-user-ranking/ |  |
| `signature` |  |  |  |  |

IPB temporary bans (`temp_ban` unix time > 0) are lifted immediately. (could be implemented by cron).

Ban warnings.

### Topics

Unapproving, reasons stored in `ipbforum.core_soft_delete_log;`

* Up/down voting

	- `ipbforum.forums_question_ratings` has individual votes stored.

### Posts

`ipbforum.forums_posts.queued` [-1, 0, 2] I think means moderation queue. 0 are normal posts. I don't know the difference between -1 and 2 or what it might be so hiding everything not 0. 

`ipbforum.core_soft_delete_log` is the time and reason for unapproving the post/topic.

### Subscriptions

#### Forum Subscriptions

Step 6

`SELECT * FROM ipbforum.core_follow`

#### Topic Subscriptions

Step 13

### Favorites

Step 14

### Images/Attachments Source/UI

Need to distinguish between the url location of ipb uploads folder for importing, vs. where it was when live.

uploads folder can be calculated from `SELECT * FROM ipbforum.core_file_storage;` `Filesystem` `{"dir":"{root}\/uploads","url":"uploads"}`

Maybe problems if WordPress is not installed in root (/)... 

With the converter as-is, you need to edit `bbPress-Invision-v4-Converter.php` PHP ~line 13 `$ipb_uploads_url` to set a http source for the Invision uploads url.

A ticket exists for a general implementation of this on bbPress Trac: [Add support for bbPress converter to import attachments](https://bbpress.trac.wordpress.org/ticket/2596).

Ideally a new form input would be added on the Import Forums page for the location of the old uploads folder. Http is fine, but local file path should work with PHP's `copy` function too.

### V3 FAQ & Known Issues

Since this is based off the v3 importer, the same unimplemented features from v3 probably still apply: [Invision IPB v3.1x, v3.2x, v3.3x & v3.4x Importer for bbPress](https://codex.bbpress.org/getting-started/importing-data/import-forums/invision/) (although this seems outdated anyway).

### Core/WordPress Settings

Some settings from Invision to consider importing to WordPress. See `ipbforum.core_sys_conf_settings` for more.

Probably best not to overwrite these settings if they're not at the WordPress defaults.

| Invision  | Values/sample  |   |   |   |
|---|---|---|---|---|
| `allow_gravatars` | `[0, 1]` |  |  |  | 
| `board_name` | string | WordPress site title |  |  | 
| `email_in` |  | Post via email |  |  | 
| `email_out` |  |  |  |  | 
| `ipb_bruteforce_attempts` |  |  |  |  | 
| `ipb_bruteforce_period` |  |  |  |  | 
| `ipb_bruteforce_unlock` |  |  |  |  | 
| `mail_method` |  |  |  |  | 
| `pop3_password` |  |  Post via email  |  |  | 
| `pop3_port` |  |  |  |  | 
| `pop3_server` |  |  |  |  | 
| `pop3_tls` |  |  |  |  | 
| `pop3_user` |  |  |  |  | 
| `profile_birthday_type` |  |  |  |  | 
| `profile_comment_approval` |  |  |  |  | 
| `profile_comments` |  |  |  |  | 
| `smtp_host` |  |  |  |  | 
| `smtp_pass` |  |  |  |  |
| `smtp_port` |  |  |  |  |
| `smtp_protocol` |  |  |  |  |
| `smtp_user` |  |  |  |  |
| `forums_rss` |  |  |  |  | 
| `forums_topics_per_page` |  |  |  |  | 


// Registration Terms & Rules

### Messages

bbPress doesnt support PMs, afaik. BuddyPress would be the appropriate plugin to add if that is needed.

Status updates (`core_member_status_updates`) & replies (`core_member_status_replies`).

### Redirection

Redirect plugin isn't invoked for .jpg files, only bare URLs.

Redirect paths with/without trailing slash. (add a regex to remove it).

## Completed Notes

### Announcements

Announcements uses a temporary post type `announcement-temp` which is only registered in the converter and the posts are immediately updated to become topics and their properties set.

### Images / Attachments

The import step `convert_anonymous_reply_authors()` is overridden to import attachments. (since I don't see a way to add a custom step and this one didn't seem to be in use).

Missing files logged in error log.

`core_attachments` is joined with `core_attachments_map` and details are copied into a temporary custom post type (`attachment-temp`). Once the data is in WordPress, the posts are looped through, the files downloaded from the remote server, the attachment created in the media library for the correct user, and the link in the original post updated to the new attachment location.

A 404 redirection from the old path to the new is created, but needs to be exported from Redirection plugin to Apache/Nginx.

A meta key is added to each attachment, `_bbp_attachment`, for use with [GD bbPress Attachments](https://wordpress.org/plugins/gd-bbpress-attachments/) plugin.

This will work for forums attachments only, i.e. does not import messaging attachments or announcements.

As posts are imported, their content is scanned for old attachments (local but not mentioned in the database) and a meta key is set which once saved to WordPress fires the action to download the file and store it in the WordPress Media Library.

Attachment thumbnails as `<img ` in the html don't preserve the size they had been, rather they WordPress `medium` size is used.

### Emoticons

It seems the parent class callback_html() function was replacing bbcodes inside alt="" and title="" tags and not correctly replacing the image's url.

```
<img alt="&gt;:(" data-emoticon="" height="20" src="<fileStore.core_Emoticons>/emoticons/angry.png" srcset="<fileStore.core_Emoticons>/emoticons/angry@2x.png 2x" title="&gt;:(" width="20" />

<img alt="&lt;img src=" width="" height="" />:(" title="&gt;:(" class="bbcode_smiley" /&gt;" data-emoticon="" height="20" src="/emoticons/angry.png" srcset="/emoticons/angry@2x.png 2x" title="<img class="bbcode_smiley" title="&gt;:(" src="/angry.gif" alt="&gt;:(" width="" height="" />" width="20" /&gt;
```

This converter now copies over the original emoticon to wp-content/uploads/emoticons/, replaces the url in the post content, and removes the troublesome tags so the parent class doesn't mess them up.

### User Profile Pictures

If [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/) plugin is active on the site, the user's avatar will be added to the WordPress Media Library and used as their avatar.

### 404 Redirection

If the [WordPress Redirection plugin](https://wordpress.org/plugins/redirection/) is installed, this converter will add 404 redirects from old forum and topic URLs to the new bbPress posts.

It does this by saving the `forums_forums.name_seo` and `forums_topics.title_seo` into WordPress post meta, and having an action on their save to use that data to build the old url, e.g. `/forum/6-forum-seo-name/`, and uses the Redirection REST API internally to add the entry pointing to `/?p=123`, so permalinks can be changed later, to a group named `bbPress`, then deletes the new meta key.

## Invision v4 Schema

Beyond what has been addressed by this plugin, further data and functionality in Invision remains to be addressed. Much of it is not directly applicable to bbPress, and much of what may be relevant to you was not to me. NA = Not Directly Applicable to bbPress. 

Here are _some_ notes on what I know:

| Table  | Purpose | Convereted | Notes | In my case |
|---|---|---|---|---|
| `calendar_calendars` | | NA
| `calendar_event_comments` |
| `calendar_event_reminders` |
| `calendar_event_reviews` |
| `calendar_event_rsvp` |
| `calendar_events` |
| `calendar_import_feeds` |
| `calendar_import_map` |
| `calendar_venues` |
| `convert_app_sessions` | Seems to be related to importing TO Invision. | NA
| `convert_apps` |
| `convert_bbcode_mediatag` |
| `convert_custom_bbcode` |
| `convert_link` |
| `convert_link_pms` |
| `convert_link_posts` |
| `convert_link_topics` |
| `convert_logs` |
| `core_acp_search_index` | ACP = Admin Control Panel | NA 
| `core_acp_tab_order` |
| `core_acronyms` |
| `core_admin_login_logs` |
| `core_admin_logs` | Logs – might be useful to add as metadata to users and topics.
| `core_admin_permission_rows` |
| `core_advertisements` | NA
| `core_announcements` | Imported as Super Stickies.
| `core_api_keys` |
| `core_api_logs` |
| `core_applications` | List of Invision plugins.
| `core_attachments` | | Forums attachments imported to Media Library | `WHERE core_attachments_map.location_key = "forums_Forums"`
| `core_attachments_map` | 
| `core_banfilters` | | | | empty
| `core_bulk_mail` | maybe the table of an add-on of ours
| `core_cache` | | NA | | empty
| `core_clubs` | | NA | BuddyPress relevant? | empty
| `core_clubs_fields` |
| `core_clubs_fieldvalues` |
| `core_clubs_memberships` |
| `core_clubs_node_map` |
| `core_content_meta` | | | | empty
| `core_deletion_log` | | | **Maybe important** are topics marked deleted here accounted for elsewhere?
| `core_dev` | | | | empty
| `core_edit_history` | | | | empty
| `core_email_templates` | | | BuddyPress relevant?
| `core_emoticons` | | emoticons were imported when their direct URL was seen. Maybe a more thorough way would've been to use this table, to also add users' ability to use their familiar typed code :P 
| `core_error_logs` | | | | empty
| `core_file_logs` | Seems maybe to be deletion logs
| `core_file_storage` | A single entry specifying the uploads directory, both path and URL.
| `core_files` | | | | empty
| `core_files_temp` | | | | empty
| `core_follow` | | **TODO** | also applicable to BuddyPress
| `core_geoip_cache` | | | | empty
| `core_googleauth_used_codes` | | | | empty
| `core_group_promotions` | | | | empty
| `core_groups` | admin/member/mod groups | Imported, but assumed default Invision settings, and only for default bbPress groups.
| `core_hooks` |
| `core_ignored_users` | | NA | would need a bbPress plugin | empty
| `core_image_proxy` | | | | empty
| `core_incoming_emails` | | | | empty
| `core_ipsconnect_queue` | Auth/SSO
| `core_ipsconnect_slaves` | Auth/SSO
| `core_item_markers` | Records which topics/posts/etc have been read by each user. | | would need a bbPress plugin
| `core_javascript` | .js files stored in DB
| `core_leaders` | ??
| `core_leaders_groups` |
| `core_log` |
| `core_login_handlers` | | NA | worth looking at to decide what WordPress plugins are needed
| `core_mail_error_logs` | | | | empty
| `core_member_history` |
| `core_member_ranks` | | | | empty
| `core_member_status_replies` | | BuddyPress relevant?
| `core_member_status_updates` | | BuddyPress relevant?
| `core_members` | | Converted to `WP_User`s. Not all data converted.
| `core_members_feature_seen` |
| `core_members_known_devices` |
| `core_members_known_ip_addresses` |
| `core_members_warn_actions` | |
| `core_members_warn_logs` |
| `core_members_warn_reasons` |
| `core_menu` |
| `core_message_posts` | Private messages | NA / BuddyPress relevant
| `core_message_topic_user_map` |
| `core_message_topics` |
| `core_moderator_logs` |
| `core_moderators` |
| `core_modules` | | | Worth looking at for plugins needed.
| `core_notification_defaults` | System notificaion settings
| `core_notification_preferences` | Individual notification settings (beyond system set)
| `core_notifications` |
| `core_permission_index` |
| `core_pfields_content` |
| `core_pfields_data` |
| `core_pfields_groups` |
| `core_plugins` | | | surprisingly empty | empty
| `core_polls` | | | | empty
| `core_profanity_filters` | | | | empty
| `core_profile_steps` | | | | empty
| `core_question_and_answer` |
| `core_queue` | | | | empty
| `core_ratings` | | | | empty
| `core_rc_comments` | | | "Updated the status of the report to Complete"
| `core_rc_index` |
| `core_rc_reports` |
| `core_reactions` | Reactions similar to Facebook's Like, Angry, Laughing... | 
| `core_reputation_index` |
| `core_reputation_leaderboard_history` |
| `core_reputation_levels` |
| `core_rss_export` |
| `core_search_index` | seems to cut out irrelevant words for content to make search more efficient | NA
| `core_search_index_item_map` |
| `core_search_index_tags` |
| `core_security_answers` | Hashed set of answers.
| `core_security_questions` |
| `core_seo_meta` | | | | empty
| `core_sessions` |
| `core_share_links` |
| `core_sitemap` |
| `core_social_promote` | | | | empty
| `core_social_promote_content` | | | | empty
| `core_social_promote_sharers` |
| `core_soft_delete_log` | **NOT addressed in posts**
| `core_spam_service_log` | | | | empty
| `core_statistics` | Daily analytics?
| `core_store` |
| `core_streams` |
| `core_sys_conf_settings` | settings, e.g. forums_topics_per_page | **NOT imported** 
| `core_sys_cp_sessions` | | | | empty
| `core_sys_lang` |
| `core_sys_lang_words` | Public facing terminology | Imported only for forum titles
| `core_sys_social_group_members` | | | | empty
| `core_sys_social_groups` | | | | empty
| `core_tags` | | Imported but **uncertain** – it seems to have worked.
| `core_tags_cache` |
| `core_tags_perms` |
| `core_tasks` |
| `core_tasks_log` |
| `core_theme_conflict` |
| `core_theme_content_history` |
| `core_theme_css` |
| `core_theme_resources` |
| `core_theme_settings_fields` |
| `core_theme_settings_values` |
| `core_theme_templates` |
| `core_themes` |
| `core_upgrade_history` |
| `core_validating` |
| `core_view_updates` |
| `core_voters` |
| `core_widget_areas` |
| `core_widget_trash` |
| `core_widgets` |
| `forums_answer_ratings` | | Plugin required
| `forums_archive_posts` | | | | empty
| `forums_archive_rules` |
| `forums_forums` | The forums themselves | Imported. Position not imported. I password protected, not addressed. ....
| `forums_posts` | | probably mostly imported
| `forums_question_ratings` |
| `forums_rss_import` | | | | empty
| `forums_rss_imported` | | | | empty
| `forums_topic_mmod` | | | | empty
| `forums_topics` | | Mostly imported. Views not imported.
| `forums_view_method` |
| `masspm_messages`

## Acknowledgements

Built for [Enhanced Athlete](https://www.enhancedathlete.com) by [Brian Henry](http://github.com/BrianHenryIE/), thanks to the contributors of the [Invision v3 converter](https://bbpress.trac.wordpress.org/log/trunk/src/includes/admin/converters/Invision.php).