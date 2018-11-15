# bbPress Invision v4 Converter

A (*work-in-progress*) converter to migrate from [Invision Community (Power Board) v4](https://invisioncommunity.com/) to [bbPress](https://bbpress.org/) (forums software for WordPress). 

Tested from Invision Community 4.3.6 to [bbPress 2.6-rc-6](https://bbpress.org/download/) on WordPress 4.9.8 and PHP 7.2.

## Installation and Use

1. Drop `InvisionV4.php` into `wp-content/plugins/bbpress/includes/admin/converters/`
2. Navigate to WordPress Admin Dashboard / Tools / Forums / Import Forums at `/wp-admin/tools.php?page=bbp-converter`
3. Fill in the source database details and hit `Start`

## TODO

There is still work to complete before considering this production quality and submitting to bbPress core. The forum users and content does import, for the most part.

### Forum Titles

Forum titles are stored in the `core_sys_lang_words` table with the forum number concatenated into a string, e.g. `forums_forum_1`, SQL: `SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1'`

`SELECT CONCAT(prefix, id) as concat FROM (SELECT 'forums_forum_' as prefix, ipbforum.forums_forums.id FROM ipbforum.forums_forums) AS t`

`SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = CONCAT('forums_forum_', 1)`

For now, the forum slug, which is built from the forum name, is being used.

### Forum Desciptions

Forum descriptions are stored in the `core_sys_lang_words` table with the forum number concatenated into a string, e.g. `forums_forum_1_desc`, SQL: `SELECT word_default FROM ipbforum.core_sys_lang_words WHERE word_app = 'forums' AND word_key = 'forums_forum_1_desc'`

HTML then needs to be stripped from this value.

### Post Voices / Forum Last Post

* The voices count (per thread) isn't set after posts are imported
* Forum Last Posts all appear as "No Topics"

Both these are fixed by running Tools / Repair Forums : Recalculate last activity in each topic and forum.

How to run this automatically?

### Files

Files need to be imported into the wp-content/uploads directory and their links/embeds updated.

Line 616 currently links to the embedded file

`preg_replace( '/\[media\]/', '',   $invision_markup );`

``SELECT * FROM ipbforum.core_sys_conf_settings WHERE `conf_key` = 'site_address';` - seems the correct place to store the site address in IPB but it was null in our installation. I don't see where it is set in the UI and when I changed it in MySQL I didn't see that reflected anywhere.

It is set in `/conf_global.php` as `$INFO['base_url']` but we only have a database connection and the converter/importer UI doesn't ask for the site's address.

I'm not sure if there's real value importing into the WordPress media library rather than just filesystem.

### V3 FAQ & Known Issues

Since this is based off the v3 importer, the same unimplemented features from v3 probably still apply: [Invision IPB v3.1x, v3.2x, v3.3x & v3.4x Importer for bbPress](https://codex.bbpress.org/getting-started/importing-data/import-forums/invision/) (although this seems outdated anyway).

### Widgets

`core_widgets`

### Announcements

Announcements act as special pinned posts that appear above the list of forums. How to pin to top of index forums list?

`core_announcements`

for the first three posts everyone sees


### Core Settings

```SELECT * FROM ipbforum.core_sys_conf_settings WHERE conf_key = 'board_name';```

Probably best not to overwrite the WordPress site title.

'board_name' - WordPress site title

smtp_host
smtp_pass
smtp_port
smtp_protocol
smtp_user

site_address - null

### More Settings

// Registration Terms & Rules


## BuddyPress

bbPress doesnt support PMs, afaik.

Status updates (`core_member_status_updates`) & replies (`core_member_status_replies`).

## 404 Redirection

The [WordPress Redirection plugin](https://wordpress.org/plugins/redirection/) should be used to ensure no 404s exist after the import.

e.g. `https://invisionforum.domain.com/forum/6-supplementation/`

which seems to be a concatentation of
`"/forum/"``forums_forums.id``"-"``forums.forums.name_seo`

and should redirect to ?p=12345 as HTTP 301 moved permenantly.

This seesm to be calculated based on settings in ipb / system / advanced configuration / friendly urls 
AND
Seach Engine Optimization / Friendly URLs


Maybe save it as post-meta anyway for users that don't have the plugin installed.


```
// Create a redirection group for the forums "Invision"
$invision_group_name = 'Invision';

$groups = Red_Group::get_all();
$invision_group = null;
foreach( $groups as $group ) {
	if($invision_group_name == $group['name']) {
		$invision_group = $group;
		break;
	}
}

if( null == $invision_group ) {
	$group_module_id = WordPress_Module.MODULE_ID;
	$invision_group = Red_Group::create( $invision_group_name, $group_module_id );
}

$details['url'] = 'old url';
$details['action_data'] = 'new url';
$details['action_code'] = 301
$details['action_type'] = 'url'; // the actual string "url"
$details['match_type'] = 'url'; // the actual string "url"

$details['group_id'] = $invision_group['id'];

$new_redirect = Red_Item::create( $details );

if ( is_wp_error( $data ) ) {
}
```


## Acknowledgements

Built for [Enhanced Athlete](https://www.enhancedathlete.com) by [Brian Henry](http://github.com/brianhenryie/), thanks to the contributors of the [Invision v3 converter](https://bbpress.trac.wordpress.org/log/trunk/src/includes/admin/converters/Invision.php).