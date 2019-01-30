# bbPress Invision v4 Converter

A (*work-in-progress*) converter to migrate from [Invision Community (Power Board) v4](https://invisioncommunity.com/) to [bbPress](https://bbpress.org/) (forums software for WordPress), built as a WordPress plugin.

Tested converting from Invision Community 4.3.6 to [bbPress 2.6-rc-7](https://bbpress.org/download/) on WordPress 4.9.9 and PHP 7.2.

## Installation and Use

* (optional) Install and activate [Redirection](https://wordpress.org/plugins/redirection/) plugin to ensure old links continue to work.
* (optional) Install and activate and [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/) plugin.

1. Edit `bbPress-Invision-v4-Converter.php` line 13 to add the source server uploads url for file import. If the old forum is offline, copy its uploads folder somewhere http accessible and use that path.
2. Install the plugin files into `wp-content/plugins/bbPress-Invision-v4-Converter/` and activate.
3. Navigate to WordPress Admin Dashboard / Tools / Forums / Import Forums (at `wp-admin/tools.php?page=bbp-converter`).
4. Fill in the source database details and hit `Start`.
5. Manually set your forum titles and descriptions.

This plugin must remain active in order for users' Invision passwords to continue to work with WordPress (each user needs to log in once for the password to be saved natively).

## Completed

* Users: passwords, roles, avatars (if [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/) active), banning.
* Forums: importing*.
* Topics: importing, unapproving, closing.
* Super-stickies (announcements): importing, unapproving.
* Replies: importing, unapproving, emoticons, images.
* Images / Attachments
* Broken link redirection (if [Redirection](https://wordpress.org/plugins/redirection/) active).

## TODO

* Users: meta-data (social accounts...).
* Forums: titles, descriptions.
* Topics: unapproving reasons
* Favorites
* Subscriptions
* Redirection: urls with trailing slashes don't redirect, .jpg doesn't redirect.
* Images/Attachments import config UI.


Forums reset doesn't work... i.e. media files remain and redirects remain. Redirects can be deleted in a single click. Imported attachments are marked `_bbp_attachment`.


Use wp-cli to text search for hyperlinks and see if there are any weird ones remaining (see `convert_link...` tables).


Add table of IPB tables in this document and comment what has been converted and what has not


Avatar not showing on:
https://forum-staging.gv1md4q4-liquidwebsites.com/forums/users/roro/
(But is for other users)

User stats on user page not correct, but replies etc do list. (need to run another repair)

https://forum-staging.gv1md4q4-liquidwebsites.com/forums/topic/ 
Redirects to:
https://forum-staging.gv1md4q4-liquidwebsites.com/forums/topic/topical-dnp/

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

### Forums

#### Titles

Forum titles are stored in the `core_sys_lang_words` table with the forum number concatenated into a string, e.g. `forums_forum_1`, SQL: `SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1'`

`SELECT CONCAT(prefix, id) as concat FROM (SELECT 'forums_forum_' as prefix, ipbforum.forums_forums.id FROM ipbforum.forums_forums) AS t`

`SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = CONCAT('forums_forum_', 1)`

For now, the forum slug, which is built from the forum name, is being used.

#### Desciptions

Forum descriptions are stored in the `core_sys_lang_words` table with the forum number concatenated into a string, e.g. `forums_forum_1_desc`, SQL: `SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1_desc'`

HTML then needs to be stripped from this value.

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

| Table  | Purpose | Convereted |   |   |
|---|---|---|---|---|
| `calendar_calendars` |
| `calendar_event_comments` |
| `calendar_event_reminders` |
| `calendar_event_reviews` |
| `calendar_event_rsvp` |
| `calendar_events` |
| `calendar_import_feeds` |
| `calendar_import_map` |
| `calendar_venues` |
| `convert_app_sessions` |
| `convert_apps` |
| `convert_bbcode_mediatag` |
| `convert_custom_bbcode` |
| `convert_link` |
| `convert_link_pms` |
| `convert_link_posts` |
| `convert_link_topics` |
| `convert_logs` |
| `core_acp_search_index` |
| `core_acp_tab_order` |
| `core_acronyms` |
| `core_admin_login_logs` |
| `core_admin_logs` |
| `core_admin_permission_rows` |
| `core_advertisements` |
| `core_announcements` |
| `core_api_keys` |
| `core_api_logs` |
| `core_applications` |
| `core_attachments` |
| `core_attachments_map` |
| `core_banfilters` |
| `core_bulk_mail` |
| `core_cache` |
| `core_clubs` |
| `core_clubs_fields` |
| `core_clubs_fieldvalues` |
| `core_clubs_memberships` |
| `core_clubs_node_map` |
| `core_content_meta` |
| `core_deletion_log` |
| `core_dev` |
| `core_edit_history` |
| `core_email_templates` |
| `core_emoticons` |
| `core_error_logs` |
| `core_file_logs` |
| `core_file_storage` |
| `core_files` |
| `core_files_temp` |
| `core_follow` |
| `core_geoip_cache` |
| `core_googleauth_used_codes` |
| `core_group_promotions` |
| `core_groups` |
| `core_hooks` |
| `core_ignored_users` |
| `core_image_proxy` |
| `core_incoming_emails` |
| `core_ipsconnect_queue` | Auth/SSO
| `core_ipsconnect_slaves` | Auth/SSO
| `core_item_markers` | Records which topics/posts/etc have been read by each user.
| `core_javascript` |
| `core_leaders` |
| `core_leaders_groups` |
| `core_log` |
| `core_login_handlers` |
| `core_mail_error_logs` |
| `core_member_history` |
| `core_member_ranks` |
| `core_member_status_replies` |
| `core_member_status_updates` |
| `core_members` |
| `core_members_feature_seen` |
| `core_members_known_devices` |
| `core_members_known_ip_addresses` |
| `core_members_warn_actions` |
| `core_members_warn_logs` |
| `core_members_warn_reasons` |
| `core_menu` |
| `core_message_posts` |
| `core_message_topic_user_map` |
| `core_message_topics` |
| `core_moderator_logs` |
| `core_moderators` |
| `core_modules` |
| `core_notification_defaults` |
| `core_notification_preferences` |
| `core_notifications` |
| `core_permission_index` |
| `core_pfields_content` |
| `core_pfields_data` |
| `core_pfields_groups` |
| `core_plugins` |
| `core_polls` |
| `core_profanity_filters` |
| `core_profile_steps` |
| `core_question_and_answer` |
| `core_queue` |
| `core_ratings` |
| `core_rc_comments` |
| `core_rc_index` |
| `core_rc_reports` |
| `core_reactions` |
| `core_reputation_index` |
| `core_reputation_leaderboard_history` |
| `core_reputation_levels` |
| `core_rss_export` |
| `core_search_index` |
| `core_search_index_item_map` |
| `core_search_index_tags` |
| `core_security_answers` |
| `core_security_questions` |
| `core_seo_meta` |
| `core_sessions` |
| `core_share_links` |
| `core_sitemap` |
| `core_social_promote` |
| `core_social_promote_content` |
| `core_social_promote_sharers` |
| `core_soft_delete_log` |
| `core_spam_service_log` |
| `core_statistics` |
| `core_store` |
| `core_streams` |
| `core_sys_conf_settings` |
| `core_sys_cp_sessions` |
| `core_sys_lang` |
| `core_sys_lang_words` | Forum titles
| `core_sys_social_group_members` |
| `core_sys_social_groups` |
| `core_tags` |
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
| `forums_answer_ratings` |
| `forums_archive_posts` |
| `forums_archive_rules` |
| `forums_forums` |
| `forums_posts` |
| `forums_question_ratings` |
| `forums_rss_import` |
| `forums_rss_imported` |
| `forums_topic_mmod` |
| `forums_topics` |
| `forums_view_method` |
| `masspm_messages`



## Acknowledgements

Built for [Enhanced Athlete](https://www.enhancedathlete.com) by [Brian Henry](http://github.com/BrianHenryIE/), thanks to the contributors of the [Invision v3 converter](https://bbpress.trac.wordpress.org/log/trunk/src/includes/admin/converters/Invision.php).