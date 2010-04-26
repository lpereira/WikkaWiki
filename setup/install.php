<?php

// Start session
session_set_cookie_params(0, '/');
session_name(md5('WikkaWiki'));
session_start();

require_once('setup/inc/functions.inc.php');

// Copy POST params from SESSION, then destroy SESSION
if(isset($_SESSION['post']))
{
	$_POST = array_merge($_POST, $_SESSION['post']);
}
$_SESSION=array();
if(isset($_COOKIE[session_name()]))
{
	setcookie(session_name(), '', time()-42000, '/');
}
session_destroy();

/*
foreach($_POST as $key=>$value)
{
	print $key.":".$value."<br/>";
}
foreach($_POST['config'] as $key=>$value)
{
	print $key.":".$value."<br/>";
}
exit;
*/

// i18n section
if (!defined('ADDING_CONFIG_ENTRY')) define('ADDING_CONFIG_ENTRY', 'Adding a new option to the wikka.config file: %s'); // %s - name of the config option
if (!defined('DELETING_COOKIES')) define('DELETING_COOKIES', 'Deleting wikka cookies since their name has changed.');

// initialization
$config = array(); //required since PHP5, to avoid warning on array_merge #94
// fetch configuration
$config = $_POST["config"];

/*
print "\$config:<br/>";
foreach($config as $key=>$value)
{
	print $key.":".$value."<br/>";
}
exit;
*/

// if the checkbox was not checked, $_POST['config']['enable_version_check'] would not be defined. We must explicitly set it to "0" to overwrite any value already set (if exists).
if (!isset($config["enable_version_check"]))
{
	$config["enable_version_check"] = "0";
}
// merge existing configuration with new one
$config = array_merge($wakkaConfig, $config);

/*
print "\$config:<br/>";
foreach($config as $key=>$value)
{
	print $key.":".$value."<br/>";
}
exit;
*/

// test configuration
print("<h2>Testing Configuration</h2>\n");
test("Testing MySQL connection settings...", $dblink = @mysql_connect($config["mysql_host"], $config["mysql_user"], $config["mysql_password"]));
test("Looking for database...", @mysql_select_db($config["mysql_database"], $dblink), "The database you configured was not found. Remember, it needs to exist before you can install/upgrade Wakka!\n\nPress the Back button and reconfigure the settings.");
print("<br />\n");

// do installation stuff
if (!$version = trim($wakkaConfig["wakka_version"])) $version = "0";

// set upgrade note to be used when overwriting default pages
$upgrade_note = 'Upgrading from '.$version.' to '.WAKKA_VERSION;

$lang_defaults_path = WIKKA_LANG_PATH.DIRECTORY_SEPARATOR.'defaults'.DIRECTORY_SEPARATOR;
test('Checking availability of default pages...', is_dir($lang_defaults_path), 'default pages not found at '.$lang_defaults_path, 1);

switch ($version)
{
// new installation
case "0":
	print("<h2>Installing Stuff</h2>");
	test("Creating page table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."pages (".
			"id int(10) unsigned NOT NULL auto_increment,".
			"tag varchar(75) NOT NULL default '',".
			"time datetime NOT NULL default '0000-00-00 00:00:00',".
			"body mediumtext NOT NULL,".
			"owner varchar(75) NOT NULL default '',".
			"user varchar(75) NOT NULL default '',".
			"latest enum('Y','N') NOT NULL default 'N',".
			"note varchar(100) NOT NULL default '',".
			"PRIMARY KEY  (id),".
			"KEY idx_tag (tag),".
			"FULLTEXT KEY body (body),".
			"KEY idx_time (time),".
			"KEY idx_owner (owner), ".
			"KEY idx_latest (latest)".
			") TYPE=MyISAM;", $dblink), "Already exists?", 0);
	test("Creating ACL table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."acls (".
			"page_tag varchar(75) NOT NULL default '',".
			"read_acl text NOT NULL,".
			"write_acl text NOT NULL,".
			"comment_read_acl text NOT NULL,".
			"comment_post_acl text NOT NULL,".
			"PRIMARY KEY  (page_tag)".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);
	test("Creating link tracking table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."links (".
			"from_tag varchar(75) NOT NULL default '',".
			"to_tag varchar(75) NOT NULL default '',".
			"UNIQUE KEY from_tag (from_tag,to_tag),".
			"KEY idx_to (to_tag)".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);
	test("Creating referrer table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."referrers (".
			"page_tag varchar(75) NOT NULL default '',".
			"referrer varchar(300) NOT NULL default '',".
			"time datetime NOT NULL default '0000-00-00 00:00:00',".
			"KEY idx_page_tag (page_tag),".
			"KEY idx_time (time)".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);
	test("Creating referrer blacklist table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."referrer_blacklist (".
			"spammer varchar(300) NOT NULL default '',".
			"KEY idx_spammer (spammer)".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);
	test("Creating user table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."users (".
			"name varchar(75) NOT NULL default '',".
			"password varchar(32) NOT NULL default '',".
			"email varchar(50) NOT NULL default '',".
			"revisioncount int(10) unsigned NOT NULL default '20',".
			"changescount int(10) unsigned NOT NULL default '50',".
			"doubleclickedit enum('Y','N') NOT NULL default 'Y',".
			"signuptime datetime NOT NULL default '0000-00-00 00:00:00',".
			"show_comments enum('Y','N') NOT NULL default 'N',".
			"default_comment_display enum ('date_asc', 'date_desc', 'threaded') NOT NULL default 'threaded',".
			"status enum('invited','signed-up','pending','active','suspended','banned','deleted'),".
			"theme varchar(50) default '',".
			"challenge varchar(8) default '00000000',".
			"PRIMARY KEY  (name),".
			"KEY idx_signuptime (signuptime)".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);
	test("Creating comment table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."comments (".
			"id int(10) unsigned NOT NULL auto_increment,".
			"page_tag varchar(75) NOT NULL default '',".
			"time datetime NOT NULL default '0000-00-00 00:00:00',".
			"comment text NOT NULL,".
			"user varchar(75) NOT NULL default '',".
			"parent int(10) unsigned default NULL,". 
			"status enum('deleted') default NULL,".
			"deleted char(1) default NULL,".
			"PRIMARY KEY  (id),".
			"KEY idx_page_tag (page_tag),".
			"KEY idx_time (time)".
			") TYPE=MyISAM;", $dblink), "Already exists?", 0);
	test("Creating session tracking table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."sessions (".
			"sessionid char(32) NOT NULL,".
			"userid varchar(75) NOT NULL,".
			"PRIMARY KEY (sessionid, userid),".
			"session_start datetime NOT NULL".
			") TYPE=MyISAM", $dblink), "Already exists?", 0);

	update_default_page(array(
	'_rootpage', 
	'AdminPages',
	'AdminUsers',
	'CategoryAdmin',
	'CategoryCategory', 
	'CategoryWiki', 
	'DatabaseInfo',
	'FormattingRules', 
	'HighScores', 
	'InterWiki', 
	'MyChanges', 
	'MyPages', 
	'OrphanedPages', 
	'OwnedPages', 
	'PageIndex', 
	'PasswordForgotten', 
	'RecentChanges', 
	'RecentlyCommented', 
	'SandBox', 
	'SysInfo',
	'TableMarkup',
	'TableMarkupReference',
	'TextSearch', 
	'TextSearchExpanded', 
	'UserSettings', 
	'WantedPages', 
	'WikiCategory', 
	'WikkaDocumentation', 
	'WikkaReleaseNotes'), $dblink, $config, $lang_defaults_path, $lang_defaults_fallback_path); 

	test('Building links table...', 1);
	/**
	 * Script for (re)building links table.
	 */
	include('links.php');

	// @@@	?? *default* ACLs are in the configuration file; settings on UserSettings page are irrelevant for default ACLs!
	//		use page-specific "ACL" files to create page-specific ACLs (in update_default_page()!).
	// @@@	use test() function to report actual results instead of assuming success!
	test("Setting default ACL...", 1);
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'UserSettings', read_acl = '*', write_acl = '+', comment_read_acl = '*', comment_post_acl = '+'", $dblink);
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminUsers', read_acl = '!*', write_acl = '!*', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink);
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminPages', read_acl = '!*', write_acl = '!*', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink);
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'DatabaseInfo', read_acl = '!*', write_acl = '!*', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink);

	// Register admin user
	$challenge = dechex(crc32(time()));
	$pass_val = md5($challenge.(mysql_real_escape_string($_POST['password'])));
	// Delete existing admin user in case installer was run twice
	@mysql_query('delete from '.$config['table_prefix'].'users where name = \''.$config['admin_users'].'\'', $dblink);
    test(__('Adding admin user').'...',
	        @mysql_query("insert into ".$config["table_prefix"]."users
			set name = '".$config["admin_users"]."', password = '".$pass_val."', email = '".$config["admin_email"]."', signuptime = now(), challenge='".$challenge."'", $dblink), "Hmm!", 0);

	// Auto-login wiki admin
	// Set default cookie path
	test("Setting initial session cookies for auto-login...", 1);
	$base_url_path = preg_replace('/wikka\.php/', '', $_SERVER['SCRIPT_NAME']);
	$wikka_cookie_path = ('/' == $base_url_path) ? '/' : substr($base_url_path,0,-1);

	// Set cookies
	SetCookie('user_name@wikka', $config['admin_users'], time() + PERSISTENT_COOKIE_EXPIRY, $wikka_cookie_path); 
	$_COOKIE['user_name'] = $config['admin_users']; 
	SetCookie('pass@wikka', $pass_val, time() + PERSISTENT_COOKIE_EXPIRY, $wikka_cookie_path); 
	$_COOKIE['pass'] = $pass_val; 

	break;

// The funny upgrading stuff. Make sure these are in order! //
// And yes, there are no breaks here. This is on purpose.  //

// from 0.1 to 0.1.1
case "0.1":
	print("<strong>Wakka 0.1 to 0.1.1</strong><br />\n");
	test("Just very slightly altering the pages table...",
		@mysql_query("alter table ".$config['table_prefix']."pages add body_r text not null default '' after body", $dblink), "Already done? Hmm!", 0);
	test("Claiming all your base...", 1);

// from 0.1.1 to 0.1.2
case "0.1.1":
	print("<strong>Wakka 0.1.1 to 0.1.2</strong><br />\n");
	test("Keep rolling...", 1);

// from 0.1.2 to 0.1.3-dev (will be 0.1.3)
case "0.1.2":
	print("<strong>Wakka 0.1.2 to 0.1.3-dev</strong><br />\n");
	test("Keep rolling...", 1);

case "0.1.3-dev":
	print("<strong>Wakka 0.1.3-dev to Wikka 1.0.0 changes:</strong><br />\n");
	test("Adding note column to the pages table...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages ADD note varchar(50) NOT NULL default '' after latest", $dblink), "Failed.", 1);
	test("Just slightly altering the pages table...",
		@mysql_query("alter table ".$config['table_prefix']."pages DROP COLUMN body_r", $dblink), "Already done? Hmm!", 0);
	test("Just slightly altering the users table...",
		@mysql_query("alter table ".$config['table_prefix']."users DROP COLUMN motto", $dblink), "Already done? Hmm!", 0);
case "1.0":
case "1.0.1":
case "1.0.2":
case "1.0.3":
case "1.0.4":
// from 1.0.4 to 1.0.5
	print("<strong>1.0.4 to 1.0.5 changes:</strong><br />\n");
	test(sprintf(ADDING_CONFIG_ENTRY, 'double_doublequote_html'), 1);
	$config["double_doublequote_html"] = 'safe';
case "1.0.5":
case "1.0.6":
	print("<strong>1.0.6 to 1.1.0 changes:</strong><br />\n");
	test("Creating comment table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."comments (".
			"id int(10) unsigned NOT NULL auto_increment,".
			"page_tag varchar(75) NOT NULL default '',".
			"time datetime NOT NULL default '0000-00-00 00:00:00',".
			"comment text NOT NULL,".
			"user varchar(75) NOT NULL default '',".
			"PRIMARY KEY  (id),".
			"KEY idx_page_tag (page_tag),".
			"KEY idx_time (time)".
			") TYPE=MyISAM", $dblink), "Already done? Hmm!", 1);
	test("Copying comments from the pages table to the new comments table...",
		@mysql_query("INSERT INTO ".$config['table_prefix']."comments (page_tag, time, comment, user) SELECT comment_on, time, body, user FROM ".$config['table_prefix']."pages WHERE comment_on != '';", $dblink), "Already done? Hmm!", 1);
	test("Deleting comments from the pages table...",
		@mysql_query("DELETE FROM ".$config['table_prefix']."pages WHERE comment_on != ''", $dblink), "Already done? Hmm!", 1);
	test("Removing comment_on field from the pages table...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages DROP comment_on", $dblink), "Already done? Hmm!", 1);
	test("Removing comment pages from the ACL table...",
		@mysql_query("DELETE FROM ".$config['table_prefix']."acls WHERE page_tag like 'Comment%'", $dblink), "Already done? Hmm!", 1);
case "1.1.0":
	print("<strong>1.1.0 to 1.1.2 changes:</strong><br />\n");
	test("Dropping current ACL table structure...",
		@mysql_query("DROP TABLE ".$config['table_prefix']."acls", $dblink), "Already done? Hmm!", 0);
	test("Creating new ACL table structure...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."acls (".
			"page_tag varchar(75) NOT NULL default '',".
			"read_acl text NOT NULL,".
			"write_acl text NOT NULL,".
			"comment_acl text NOT NULL,".
			"PRIMARY KEY  (page_tag)".
			") TYPE=MyISAM", $dblink), "Already exists?", 1);
case "1.1.2":
case "1.1.3":
	print("<strong>1.1.3 to 1.1.3.1 changes:</strong><br />\n");
	test("Altering pages table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages CHANGE tag tag varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering pages table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages CHANGE user user varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering pages table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages CHANGE owner owner varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering pages table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."pages CHANGE note note varchar(100) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering user table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."users CHANGE name name varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering comments table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."comments CHANGE page_tag page_tag varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering comments table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."comments CHANGE user user varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering acls table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."acls CHANGE page_tag page_tag varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering links table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."links CHANGE from_tag from_tag varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering links table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."links CHANGE to_tag to_tag varchar(75) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Altering referrers table structure...",
		@mysql_query("ALTER TABLE ".$config['table_prefix']."referrers MODIFY referrer varchar(150) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test("Creating referrer_blacklist table...",
		@mysql_query(
			"CREATE TABLE ".$config['table_prefix']."referrer_blacklist (".
			"spammer varchar(150) NOT NULL default '',".
			"KEY idx_spammer (spammer)".
			") TYPE=MyISAM", $dblink), "Already exists? Hmm!", 1);
	test("Altering a pages table index...",
		@mysql_query("alter table ".$config['table_prefix']."pages DROP INDEX tag", $dblink), "Already done? Hmm!", 0);
	test("Altering a pages table index...",
		@mysql_query("alter table ".$config['table_prefix']."pages ADD FULLTEXT body (body)", $dblink), "Already done? Hmm!", 0);
	test("Altering a users table index...",
		@mysql_query("alter table ".$config['table_prefix']."users DROP INDEX idx_name", $dblink), "Already done? Hmm!", 0);
case "1.1.3.1":
case "1.1.3.2":
	print("<strong>1.1.3.2 to 1.1.3.3 changes:</strong><br />\n");
	test(sprintf(ADDING_CONFIG_ENTRY, 'wikiping_server'), 1);
	$config["wikiping_server"] = '';
case "1.1.3.3":
case "1.1.3.4":
case "1.1.3.5":
case "1.1.3.6":
case "1.1.3.7":
case "1.1.3.8":
case "1.1.3.9":
case "1.1.4.0":
case "1.1.5.0":
case "1.1.5.1":
case "1.1.5.2":
case "1.1.5.3":
	test("Adding WikkaReleaseNotes page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'WikkaReleaseNotes', body = '{{wikkachanges}}{{nocomments}}\n\n\n----\nCategoryWiki', owner = '(Public)', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Adding WikkaDocumentation page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'WikkaDocumentation' , body = '=====Wikka Documentation=====\n\nComprehensive and up-to-date documentation on Wikka Wiki can be found on the [[http://docs.wikkawiki.org/ Wikka Documentation server]].', owner = '(Public)', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	// cookie names have changed -- logout user and delete the old cookies
	test(DELETING_COOKIES, 1);
	DeleteCookie("name");
	DeleteCookie("password");
	// delete files removed from previous version
	@unlink('actions/wakkabug.php');
	// delete directories that have been moved
	rmdirr("freemind");
	rmdirr("safehtml");
	rmdirr("wikiedit2");
	rmdirr("xml");
case "1.1.6.0":
case "1.1.6.1":
	test(sprintf(ADDING_CONFIG_ENTRY, 'grabcode_button' ), 1);
	$config["grabcode_button"] = '1';
	test(sprintf(ADDING_CONFIG_ENTRY, 'wiki_suffix'), 1);
	$config["wiki_suffix"] = '_wikka';
	test(sprintf(ADDING_CONFIG_ENTRY, 'require_edit_note'), 1);
	$config["require_edit_note"] = '0';
	test(sprintf(ADDING_CONFIG_ENTRY, 'public_sysinfo'), 1);
	$config["public_sysinfo"] = '0';
	// cookie names have changed -- logout user and delete the old cookies
	test(DELETING_COOKIES, 1);
	DeleteCookie("wikka_user_name");
	DeleteCookie("wikka_pass");
	//adding SysInfo page
	test("Adding SysInfo page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'SysInfo', body = '===== System Information =====\n\n~-Wikka version: ##{{wikkaversion}}##\n~-PHP version: ##{{phpversion}}##\n~-\"\"MySQL\"\" version: ##{{mysqlversion}}##\n~-\"\"GeSHi\"\" version: ##{{geshiversion}}##\n~-Server:\n~~-Host: ##{{system show=\"host\"}}##\n~~-Operative System: ##{{system show=\"os\"}}##\n~~-Machine: ##{{system show=\"machine\"}}##\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."',  user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
case "1.1.6.2-alpha":
case "1.1.6.2-beta":
case "1.1.6.2":
case "1.1.6.3":
	test(sprintf(ADDING_CONFIG_ENTRY, 'allow_user_registration' ), 1);
	$config['allow_user_registration'] = '1';
	test(sprintf(ADDING_CONFIG_ENTRY, 'wikka_template_path' ), 1);
	$config["wikka_template_path"] = 'templates';
	test("Adding HighScores page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'HighScores', body = '{{highscores}}\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Adding CategoryAdmin page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'CategoryAdmin', body = '=====Wiki Administration Category=====\nThis category links to pages for wiki administration.\n\n\n----\n\n{{category}}\n\n\n----\n[[CategoryCategory List of all categories]]', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Adding DatabaseInfo page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'DatabaseInfo', body = '{{dbinfo}}\n\n----\nCategoryAdmin', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Setting ACL for DatabaseInfo...",
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'DatabaseInfo', read_acl = '!*', write_acl = '!*', comment_acl = '!*'", $dblink), "Already done? OK!", 0);
	test("Adding AdminUsers page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'AdminUsers', body = '{{checkversion}}\n{{adminusers}}\n\n----\nCategoryAdmin', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Setting ACL for AdminUsers...",
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminUsers', read_acl = '!*', write_acl = '!*', comment_acl = '!*'", $dblink), "Already done? OK!", 0);
	test("Adding AdminPages page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'AdminPages', body = '{{checkversion}}\n{{adminpages}}\n\n----\nCategoryAdmin', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Setting ACL for AdminPages...",
	mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminPages', read_acl = '!*', write_acl = '!*', comment_acl = '!*'", $dblink), "Already done? OK!", 0);	
	test("Archiving latest SysInfo revision...", 
	mysql_query("update ".$config["table_prefix"]."pages set latest = 'N' where tag = 'SysInfo'"), "Already done? OK!", 0);
	test("Updating SysInfo page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'SysInfo', body = '{{checkversion}}\n===== System Information =====\n\n~-Wikka version: ##{{wikkaversion}}##\n~-PHP version: ##{{phpversion}}##\n~-\"\"MySQL\"\" version: ##{{mysqlversion}}##\n~-\"\"GeSHi\"\" version: ##{{geshiversion}}##\n~-Server:\n~~-Host: ##{{system show=\"host\"}}##\n~~-Operative System: ##{{system show=\"os\"}}##\n~~-Machine: ##{{system show=\"machine\"}}##\n\n{{wikkaconfig}}\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Archiving latest WikiCategory revision...", 
	mysql_query("update ".$config["table_prefix"]."pages set latest = 'N' where tag = 'WikiCategory'"), "Already done? OK!", 0);
	test("Updating WikiCategory page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'WikiCategory', body = '=====How to use categories=====\nThis wiki is using a very flexible but simple categorizing system to keep everything properly organized.\n\n====1. Adding a page to an existing category====\nTo \'\'add a page to an existing category\'\' simply add a link to the relevant category page. For example, to mark page ##\"\"MyPage\"\"## as a child of category ##\"\"MyCategory\"\"##, just add a link to ##\"\"MyCategory\"\"## from ##\"\"MyPage\"\"##. This will automatically add ##\"\"MyPage\"\"## to the list of pages belonging to that category. Category links are put by convention at the end of the page, but the position of these links does not affect their behavior.\n\n====2. Adding a subcategory to an existing category====\nTo \'\'create a hierarchy of categories\'\', you can follow the same instructions to add pages to categories. For example, to mark category ##\"\"Category2\"\"## as a child (or subcategory) of another category ##\"\"Category1\"\"##, just add a link to ##\"\"Category1\"\"## in ##\"\"Category2\"\"##. This will automatically add ##\"\"Category2\"\"## to the list of ##\"\"Category1\"\"##\'s children.\n\n====3. Creating new categories====\nTo \'\'start a new category\'\' just create a page containing ##\"\"{{category}}\"\"##. This will mark the page as a special //category page// and will output a list of pages belonging to the category. Category page names start by convention with the word ##Category## but you can also create categories without following this convention. To add a new category to the master list of categories just add a link from it to CategoryCategory.\n\n====4. Browsing categories====\nTo \'\'browse the categories\'\' available on your wiki you can start from CategoryCategory. If all pages and subcategories are properly linked as described above, you will be able to browse the whole hierarchy of categories starting from this page.\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Adding status field to users table...",
	mysql_query("alter table ".$config['table_prefix']."users add column status enum ('invited','signed-up','pending','active','suspended','banned','deleted')"), "Already done? OK!", 0); 
	test("Adding sessions tracking table...",
	mysql_query("create table ".$config['table_prefix']."sessions (sessionid char(32) NOT NULL, userid varchar(75) NOT NULL, PRIMARY KEY (sessionid, userid), session_start datetime NOT NULL)"),	"Already done? OK!", 0); 
	test('Dropping obsolete index `from_tag`...',
	mysql_query('alter table '.$config['table_prefix'].'links drop index `idx_from`'), 'Already done?  OK!', 0);
case "1.1.6.4":
case "1.1.6.5":
case "1.1.6.6":
case "1.1.6.7":
case "1.2":
	test(sprintf(ADDING_CONFIG_ENTRY, 'enable_user_host_lookup' ), 1);
	$config['enable_user_host_lookup'] = '1';
	test("Archiving latest FormattingRules revision...", 
	mysql_query("update ".$config["table_prefix"]."pages set latest = 'N' where tag = 'FormattingRules'"), "Already done? OK!", 0);
	test("Updating FormattingRules page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'FormattingRules', body = '======Wikka Formatting Guide======\n\n<<**Note:** Anything between 2 sets of double-quotes is not formatted.<<::c::\nOnce you have read through this, test your formatting skills in the SandBox.\n----\n===1. Text Formatting===\n\n~##\"\"**I\'m bold**\"\"##\n~**I\'m bold **\n\n~##\"\"//I\'m italic text!//\"\"##\n~//I\'m italic text!//\n\n~##\"\"And I\'m __underlined__!\"\"##\n~And I\'m __underlined__!\n\n~##\"\"##monospace text##\"\"##\n~##monospace text##\n\n~##\"\"\'\'highlight text\'\'\"\"## (using 2 single-quotes)\n~\'\'highlight text\'\'\n\n~##\"\"++Strike through text++\"\"##\n~++Strike through text++\n\n~##\"\"Press #%ANY KEY#%\"\"##\n~Press #%ANY KEY#%\n\n~##\"\"@@Center text@@\"\"##\n~@@Center text@@\n\n===2. Headers===\n\nUse between six ##=## (for the biggest header) and two ##=## (for the smallest header) on both sides of a text to render it as a header.\n\n~##\"\"====== Really big header ======\"\"##\n~====== Really big header ======\n  \n~##\"\"===== Rather big header =====\"\"##\n~===== Rather big header =====\n\n~##\"\"==== Medium header ====\"\"##\n~==== Medium header ====\n\n~##\"\"=== Not-so-big header ===\"\"##\n~=== Not-so-big header ===\n\n~##\"\"== Smallish header ==\"\"##\n~== Smallish header ==\n\n===3. Horizontal separator===\n~##\"\"----\"\"##\n----\n\n===4. Forced line break===\n~##\"\"---\"\"##\n---\n\n===5. Lists and indents===\n\nYou can indent text using a **~**, a **tab** or **4 spaces** (which will auto-convert into a tab).\n\n##\"\"~This text is indented<br />~~This text is double-indented<br />&nbsp;&nbsp;&nbsp;&nbsp;This text is also indented\"\"##\n\n~This text is indented\n~~This text is double-indented\n	This text is also indented\n\nTo create bulleted/ordered lists, use the following markup (you can always use 4 spaces instead of a ##**~**##):\n\n**Bulleted lists**\n##\"\"~- Line one\"\"##\n##\"\"~- Line two\"\"##\n\n	- Line one\n	- Line two\n\n**Numbered lists**\n##\"\"~1) Line one\"\"##\n##\"\"~1) Line two\"\"##\n\n	1) Line one\n	1) Line two\n\n**Ordered lists using uppercase characters**\n##\"\"~A) Line one\"\"##\n##\"\"~A) Line two\"\"##\n\n	A) Line one\n	A) Line two\n\n**Ordered lists using lowercase characters**\n##\"\"~a) Line one\"\"##\n##\"\"~a) Line two\"\"##\n\n	a) Line one\n	a) Line two\n\n**Ordered lists using roman numerals**\n##\"\"~I) Line one\"\"##\n##\"\"~I) Line two\"\"##\n\n	I) Line one\n	I) Line two\n\n**Ordered lists using lowercase roman numerals**\n##\"\"~i) Line one\"\"##\n##\"\"~i) Line two\"\"##\n\n	i) Line one\n	i) Line two\n\n===6. Inline comments===\n\nTo format some text as an inline comment, use an indent ( **~**, a **tab** or **4 spaces**) followed by a **\"\"&amp;\"\"**.\n\n**Example:**\n\n##\"\"~&amp; Comment\"\"##\n##\"\"~~&amp; Subcomment\"\"##\n##\"\"~~~&amp; Subsubcomment\"\"##\n\n~& Comment\n~~& Subcomment\n~~~& Subsubcomment\n\n===7. Images===\n\nTo place images on a Wiki page, you can use the ##image## action.\n\n**Example:**\n\n~##\"\"{{image class=\"center\" alt=\"DVD logo\" title=\"An Image Link\" url=\"images/dvdvideo.gif\" link=\"RecentChanges\"}}\"\"##\n~{{image class=\"center\" alt=\"dvd logo\" title=\"An Image Link\" url=\"images/dvdvideo.gif\" link=\"RecentChanges\"}}\n\nLinks can be external, or internal Wiki links. You don\'t need to enter a link at all, and in that case just an image will be inserted. You can use the optional classes ##left## and ##right## to float images left and right. You don\'t need to use all those attributes, only ##url## is required while ##alt## is recommended for accessibility.\n\n===8. Links===\n\nTo create a **link to a wiki page** you can use any of the following options: ---\n~1) type a ##\"\"WikiName\"\"##: --- --- ##\"\"FormattingRules\"\"## --- FormattingRules --- ---\n~1) add a forced link surrounding the page name by ##\"\"[[\"\"## and ##\"\"]]\"\"## (everything after the first space will be shown as description): --- --- ##\"\"[[SandBox Test your formatting skills]]\"\"## --- [[SandBox Test your formatting skills]] --- --- ##\"\"[[SandBox &#27801;&#31665;]]\"\"## --- [[SandBox &#27801;&#31665;]] --- ---\n~1) add an image with a link (see instructions above).\n\nTo **link to external pages**, you can do any of the following: ---\n~1) type a URL inside the page: --- --- ##\"\"http://www.example.com\"\"## --- http://www.example.com --- --- \n~1) add a forced link surrounding the URL by ##\"\"[[\"\"## and ##\"\"]]\"\"## (everything after the first space will be shown as description): --- --- ##\"\"[[http://example.com/jenna/ Jenna\'s Home Page]]\"\"## --- [[http://example.com/jenna/ Jenna\'s Home Page]] --- --- ##\"\"[[mail@example.com Write me!]]\"\"## --- [[mail@example.com Write me!]] --- ---\n~1) add an image with a link (see instructions above);\n~1) add an interwiki link (browse the [[InterWiki list of available interwiki tags]]): --- --- ##\"\"WikiPedia:WikkaWiki\"\"## --- WikiPedia:WikkaWiki --- --- ##\"\"Google:CSS\"\"## --- Google:CSS --- --- ##\"\"Thesaurus:Happy\"\"## --- Thesaurus:Happy --- ---\n\n===9. Tables===\n\n<<The ##table## action has been deprecated as of Wikka version 1.2 and has been replaced with the syntax that follows. Please visit the [[Docs:TableActionInfo Wikka documentation server]] for information about the older ##table## action.<<::c::\nTables can be created using two pipe (##\"\"||\"\"##) symbols. Everything in a single line is rendered as a table row.\n\n**Example:**\n\n##\"\"||Cell 1||Cell 2||\"\"##\n\n||Cell 1||Cell 2||\n\nHeader cells can be rendered by placing an equals sign between the pipes.\n\n**Example:**\n\n##\"\"|=|Header 1|=|Header 2||\"\"##\n##\"\"||Cell 1||Cell 2||\"\"##\n\n|=|Header 1|=|Header 2||\n||Cell 1||Cell 2||\n\nRow and column spans are specified with ##x:## and ##y:## in parentheses just after the pipes.\n\n**Example:**\n\n##\"\"|=| |=|(x:2)Columns||\"\"##\n##\"\"|=|(y:2) Rows||Cell 1||Cell 2||\"\"##\n##\"\"||Cell 3||Cell 4||\"\"##\n\n|=| |=|(x:2)Columns||\n|=|(y:2) Rows||Cell 1||Cell 2||\n||Cell 3||Cell 4||\n\nMany additional features are available using table markup. A more comprehensive table markup guide is available on this server\'s TableMarkup page. A complete syntax reference is available on this server\'s TableMarkupReference page.\n\n===10. Colored Text===\n\nColored text can be created using the ##color## action:\n\n**Example:**\n\n~##\"\"{{color c=\"blue\" text=\"This is a test.\"}}\"\"##\n~{{color c=\"blue\" text=\"This is a test.\"}}\n\nYou can also use hex values:\n\n**Example:**\n\n~##\"\"{{color hex=\"#DD0000\" text=\"This is another test.\"}}\"\"##\n~{{color hex=\"#DD0000\" text=\"This is another test.\"}}\n\nAlternatively, you can specify a foreground and background color using the ##fg## and ##bg## parameters (they accept both named and hex values):\n\n**Examples:**\n\n~##\"\"{{color fg=\"#FF0000\" bg=\"#000000\" text=\"This is colored text on colored background\"}}\"\"##\n~{{color fg=\"#FF0000\" bg=\"#000000\" text=\"This is colored text on colored background\"}}\n\n~##\"\"{{color fg=\"yellow\" bg=\"black\" text=\"This is colored text on colored background\"}}\"\"##\n~{{color fg=\"yellow\" bg=\"black\" text=\"This is colored text on colored background\"}}\n\n\n===11. Floats===\n\nTo create a **left floated box**, use two ##<## characters before and after the block.\n\n**Example:**\n\n~##\"\"&lt;&lt;Some text in a left-floated box hanging around&lt;&lt; Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler.\"\"##\n\n<<Some text in a left-floated box hanging around<<Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler.\n\n::c::To create a **right floated box**, use two ##>## characters before and after the block.\n\n**Example:**\n\n~##\"\">>Some text in a right-floated box hanging around>> Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler.\"\"##\n\n   >>Some text in a right-floated box hanging around>>Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler. Some more text as a filler.\n\n::c:: Use ##\"\"::c::\"\"##  to clear floated blocks.\n\n===12. Code formatters===\n\nYou can easily embed code blocks in a wiki page using a simple markup. Anything within a code block is displayed literally. \nTo create a **generic code block** you can use the following markup:\n\n~##\"\"%% This is a code block %%\"\"##. \n\n%% This is a code block %%\n\nTo create a **code block with syntax highlighting**, you need to specify a //code formatter// (see below for a list of available code formatters). \n\n~##\"\"%%(\"\"{{color c=\"red\" text=\"php\"}}\"\")<br />&lt;?php<br />echo \"Hello, World!\";<br />?&gt;<br />%%\"\"##\n\n%%(php)\n<?php\necho \"Hello, World!\";\n?>\n%%\n\nYou can also specify an optional //starting line// number.\n\n~##\"\"%%(php;\"\"{{color c=\"red\" text=\"15\"}}\"\")<br />&lt;?php<br />echo \"Hello, World!\";<br />?&gt;<br />%%\"\"##\n\n%%(php;15)\n<?php\necho \"Hello, World!\";\n?>\n%%\n\nIf you specify a //filename//, this will be used for downloading the code.\n\n~##\"\"%%(php;15;\"\"{{color c=\"red\" text=\"test.php\"}}\"\")<br />&lt;?php<br />echo \"Hello, World!\";<br />?&gt;<br />%%\"\"##\n\n%%(php;15;test.php)\n<?php\necho \"Hello, World!\";\n?>\n%%\n\n**List of available code formatters:**\n{{table columns=\"6\" cellpadding=\"1\" cells=\"LANGUAGE;FORMATTER;LANGUAGE;FORMATTER;LANGUAGE;FORMATTER;ABAP;abap;Actionscript;actionscript;ADA;ada;Apache Log;apache;AppleScript; applescript;ASM;asm;ASP;asp;AutoIT;autoit;Axapta/Dynamics Ax X++;xpp;Bash;bash;BlitzBasic;blitzbasic;BNF;bnf;C;c;C for Macs;c_mac;c#;csharp;C++;cpp;C++ (QT extensions);cpp-qt;CAD DCL;caddcl;CadLisp;cadlisp;CFDG;cfdg;ColdFusion;cfm; CSS;css;D;d;Delphi;delphi;Diff-Output;diff;DIV; div;DOS;dos;dot;dot;Eiffel;eiffel;Fortran;fortran;FOURJ\'s Genero 4GL;genero;FreeBasic;freebasic;GML;gml;Groovy;groovy;Haskell;haskell;HTML;html4strict;INI;ini;IO;io;Inno Script;inno;Java 5;java5;Java;java;Javascript;javascript;LaTeX;latex;Lisp;lisp;Lua;lua;Matlab;matlab;Microchip Assembler;mpasm;Microsoft Registry;reg;mIRC;mirc;Motorola 68000 Assembler;m68k;MySQL;mysql;NSIS;nsis;Objective C;objc;OpenOffice BASIC;oobas;Objective Caml;ocaml;Objective Caml (brief);ocaml-brief;Oracle 8;oracle8;Pascal;pascal;Per (forms);per;Perl;perl;PHP;php;PHP (brief);php-brief;PL/SQL;plsql;Python;phyton;Q(uick)BASIC;qbasic;robots.txt;robots;Ruby;ruby;Ruby on Rails;rails;SAS;sas;Scheme;scheme;sdlBasic;sdlbasic;SmallTalk;smalltalk;Smarty;smarty;SQL;sql;TCL/iTCL;tcl;T-SQL;tsql;Text;text;thinBasic;thinbasic;Unoidl;idl;VB.NET;vbnet;VHDL;vhdl;Visual BASIC;vb;Visual Fox Pro;visualfoxpro;WinBatch;winbatch;XML;xml;ZiLOG Z80;z80;###\"}}\n\n===13. Mindmaps===\n\nWikka has native support for [[Docs:FreeMind mindmaps]]. There are two options for embedding a mindmap in a wiki page.\n\n**Option 1:** Upload a \"\"FreeMind\"\" file to a webserver, and then place a link to it on a wikka page:\n  ##\"\"http://yourdomain.com/freemind/freemind.mm\"\"##\nNo special formatting is necessary.\n\n**Option 2:** Paste the \"\"FreeMind\"\" data directly into a wikka page:\n~- Open a \"\"FreeMind\"\" file with a text editor.\n~- Select all, and copy the data.\n~- Browse to your Wikka site and paste the Freemind data into a page. \n\n===14. Embedded HTML===\n\nYou can easily paste HTML in a wiki page by wrapping it into two sets of doublequotes. \n\n~##&quot;&quot;[html code]&quot;&quot;##\n\n**Examples:**\n\n~##&quot;&quot;y = x<sup>n+1</sup>&quot;&quot;##\n~\"\"y = x<sup>n+1</sup>\"\"\n\n~##&quot;&quot;<acronym title=\"Cascade Style Sheet\">CSS</acronym>&quot;&quot;##\n~\"\"<acronym title=\"Cascade Style Sheet\">CSS</acronym>\"\"\n\nBy default, some HTML tags are removed by the \"\"SafeHTML\"\" parser to protect against potentially dangerous code.  The list of tags that are stripped can be found on the [[Docs:SafeHTML SafeHTML]] documentation page.\n\nIt is possible to allow //all// HTML tags to be used, see Docs:UsingHTML for more information.\n\n----\nCategoryWiki', owner = '(Public)', user = 'WikkaInstaller', note='".$upgrade_note."', time = now(), latest = 'Y'", $dblink), "Already done? OK!", 0);
	test("Adding TableMarkup page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'TableMarkup', body = '======Wikka Table Markup Guide======\n>>==See also:==\n~-For earlier Wikka versions, check the [[Docs:TableActionInfo table]] action\n~-Updated versions of this page can be found on the [[Docs:TableMarkup Wikka documentation server]] \n~-For a more formal description, check this server\'s TableMarkupReference page.\n>>\nAs of ##1.2##, Wikka introduces a flexible markup for data tables. Any kind of tables allowed by XHTML can be created using this markup, from the most basic examples (e.g. simple rows of cells) to complex tables with full support for accessibility options.::c::\n\n====1. Table basics: cells, rows, columns====\n\nThe most basic element of a table is a **cell**. Single cells can be created using the standard delimiter ##\"\"||\"\"##, e.g.:\n\n##\"\"||Hello||\"\"##\n\n||Hello||\n\nNote that a cell must always be open and closed by delimiters. \n\n**Rows** can be created by adding on the same line multiple cells:\n\n##\"\"||Cell 1||Cell 2||Cell 3||\"\"##\n\n||Cell 1||Cell 2||Cell 3||\n\n**Columns** can be created by adding rows on separate lines:\n\n##\"\"||Cell 1||\"\"##\n##\"\"||Cell 2||\"\"##\n##\"\"||Cell 3||\"\"##\n\n||Cell 1||\n||Cell 2||\n||Cell 3||\n\nBy now you should be able to create simple tables with **multiple rows and columns**.\n\n##\"\"||Cell 1||Cell 2||Cell 3||\"\"##\n##\"\"||Cell 4||Cell 5||Cell 6||\"\"##\n##\"\"||Cell 7||Cell 8||Cell 9||\"\"##\n\n||Cell 1||Cell 2||Cell 3||\n||Cell 4||Cell 5||Cell 6||\n||Cell 7||Cell 8||Cell 9||\n\n====2. Headings====\n\nAs soon as you create slightly more complex data tables, you will need to specify column and row **headings**. Headings are special cells that specify what kind of data rows and columns contain. The most basic way of creating a heading is by using ##\"\"|=|\"\"## as a delimiter.\n\nThe following is an example of a simple table with **column headings**:\n\n##\"\"|=|Apples|=|Pears|=|\"\"##\n##\"\"||300Kg||480Kg||\"\"##\n\n|=|Apples|=|Pears|=|\n||300Kg||480Kg||\n\n**Row headings** are created in the same way. Since they are usually followed by normal cells, they must be terminated with the ##\"\"||\"\"## standard delimiter if the next element in the row is a simple cell:\n\n##\"\"|=|Apples||300Kg||\"\"##\n##\"\"|=|Pears||480Kg||\"\"##\n\n|=|Apples||300Kg||\n|=|Pears||480Kg||\n\nYou should be able by now to create simple tables with row and column headings:\n\n##\"\"|=|       |=|Apples|=|Pears|=|\"\"##\n##\"\"|=|Mary||300Kg||320Kg||\"\"##\n##\"\"|=|John||400Kg||630Kg||\"\"##\n\n|=| |=|Apples|=|Pears|=|\n|=|Mary||300Kg||320Kg||\n|=|John||400Kg||630Kg||\n\nWe will describe later how to add accessibility parameters for row and column headings.\n\n====3. Captions====\n\nUsually tables are introduced with a caption that describes what the table contains. A caption element is introduced with a ##\"\"|?|\"\"## delimiter and terminated with a standard delimiter ##\"\"||\"\"##.\n\n##\"\"|?|Fruit production in 2006||\"\"##\n##\"\"|=|       |=|Apples|=|Pears|=|\"\"##\n##\"\"|=|Mary||300Kg||320Kg||\"\"##\n##\"\"|=|John||400Kg||630Kg||\"\"##\n\n|?|Fruit production in 2006||\n|=| |=|Apples|=|Pears|=|\n|=|Mary||300Kg||320Kg||\n|=|John||400Kg||630Kg||\n\n====4. Spans====\n\n**Spans** are used to combine multiple cells or multiple headings vertically or horizontally and are created using the following [[TableMarkupReference attribute parameters]]:\n\n##\"\"||\"\"(\'\'span options\'\')Element content\"\"||\"\"##\n\nA **cell spanning multiple columns** is generated by prefixing the cell content with a ##(x:\'\'n\'\')## parameter, where ##\'\'n\'\'## is the number of columns to be spanned. The following example shows how to create a cell spanning two columns:\n\n##\"\"||(x:2)Cell spanning 2 columns||Cell 3||\"\"##\n##\"\"||Cell 4||Cell 5||Cell 6||\"\"##\n##\"\"||Cell 7||Cell 8||Cell 9||\"\"##\n\n||(x:2)Cell spanning 2 columns||Cell 3||\n||Cell 4||Cell 5||Cell 6||\n||Cell 7||Cell 8||Cell 9||\n\nSpans can also be applied to rows. A **cell spanning multiple rows** is generated by prefixing the cell content with a ##(y:\'\'n\'\')## parameter, where  ##\'\'n\'\'##  is the number of rows to be spanned. The following example shows how to create a cell spanning two rows:\n\n##\"\"||(y:2)Cell spanning 2 rows||Cell 2||Cell 3||\"\"##\n##\"\"||Cell 5||Cell 6||\"\"##\n##\"\"||Cell 7||Cell 8||Cell 9||\"\"##\n\n||(y:2)Cell spanning 2 rows||Cell 2||Cell 3||\n||Cell 5||Cell 6||\n||Cell 7||Cell 8||Cell 9||\n\nSpans are particularly useful to create **subheadings**:\n\n##\"\"|?|Fruit production in the last two years||\"\"##\n##\"\"|=|       |=|(x:2)Apples|=|(x:2)Pears|=|\"\"##\n##\"\"|=|       |=|2005|=|2006|=|2005|=|2006|=|\"\"##\n##\"\"|=|Mary||300Kg||320Kg||400kg||280Kg||\"\"##\n##\"\"|=|John||400Kg||630Kg||210Kg||300Kg||\"\"##\n\n|?|Fruit production in the last two years||\n|=|       |=|(x:2)Apples|=|(x:2)Pears|=|\n|=|       |=|2005|=|2006|=|2005|=|2006|=|\n|=|Mary||300Kg||320Kg||400kg||280Kg||\n|=|John||400Kg||630Kg||210Kg||300Kg||\n\nColumn and row spans can be combined to created funky table layouts:\n\n##\"\"||(x:2;y:2)2x2||(x:2)2x1||(y:2)1x2||\"\"##\n##\"\"||(y:2)1x2||1x1||\"\"##\n##\"\"||1x1||1x1||(x:2)2x1||\"\"##\n\n||(x:2;y:2)2x2||(x:2)2x1||(y:2)1x2||\n||(y:2)1x2||1x1||\n||1x1||1x1||(x:2)2x1||\n\n\n====5. Formatting text within tables====\n\nYou can use any kind of basic [[TextFormatting Wikka markup]] to render text within tables.\nThe following example adds basic formatting to cell content:\n\n##\"\"|?|Using text formatting within tables||\"\"##\n##\"\"||##Monospaced##||//Italics//||**Bold**||__Underlined__||\"\"##\n##\"\"||\'\'Highlighted\'\'||++Strikethrough++||(x:2)**//Bold italics//**||\"\"##\n\n|?|Using text formatting within tables||\n||##Monospaced##||//Italics//||**Bold**||__Underlined__||\n||\'\'Highlighted\'\'||++Strikethrough++||(x:2)**//Bold italics//**||\n\n====6. Adding actions and images within tables====\n\nSimple, content-generating [[Docs:UsingActions actions]] (including [[Docs:AddingImages images]]) can be added within table cells and headings.\n\n##\"\"|?|Using actions within tables||\"\"##\n##\"\"||This wiki contains {{countpages}} pages||\"\"##\n##\"\"||{{image url=\"images/wikka_logo.jpg\" class=\"center\" alt=\"a w\" title=\"w image\"}}||\"\"##\n##\"\"||{{color c=\"red\" text=\"some colored text\"}}||\"\"##\n\n|?|Using actions within tables||\n||This wiki contains {{countpages}} pages||\n||{{image url=\"images/wikka_logo.jpg\" class=\"center\" alt=\"a w\" title=\"w image\"}}||\n||{{color c=\"red\" text=\"some colored text\"}}||\n\n====7. Adding links within tables====\n\nAll the available options to create [[Docs:AddingLinks links]] can be used within table cells or headings:\n\n##\"\"|?|Adding links within tables||\"\"##\n##\"\"||Camelcase links: SandBox||\"\"##\n##\"\"||Escaped camelcase links: &quot;&quot;SandBox&quot;&quot; escaped||\"\"##\n##\"\"||Forced links: [[HomePage main]]||\"\"##\n##\"\"||Interwiki links: Wikipedia:Wikka||\"\"##\n##\"\"||Forced interwiki links: [[Wikipedia:Wikka Wikka article on Wikipedia]]||\"\"##\n##\"\"||External links: http://www.example.com ||\"\"##\n##\"\"||Forced external links: [[http://www.example.com Example.com]]||\"\"##\n##\"\"||Image links: {{image url=\"images/wizard.gif\" alt=\"wizard\" title=\"Display an index of pages on this wiki\" link=\"PageIndex\"}}||\"\"##\n\n|?|Adding links within tables||\n||Camelcase links: SandBox||\n||Escaped camelcase links: \"\"SandBox escaped\"\"||\n||Forced links: [[HomePage main]]||\n||Interwiki links: Wikipedia:Wikka||\n||Forced interwiki links: [[Wikipedia:Wikka Wikka article on Wikipedia]]||\n||External links: http://www.example.com ||\n||Forced external links: [[http://www.example.com Example.com]]||\n||Image links: {{image url=\"images/wizard.gif\" alt=\"wizard\" title=\"Display an index of pages on this wiki\" link=\"PageIndex\"}}||\n\n====8. Adding HTML within tables====\n\nYou can also use [[Docs:UsingHTML embedded HTML]] in table elements:\n\n##\"\"|?|Embedding HTML within tables||\"\"##\n##\"\"||Here\'s some superscript: &quot;&quot;a&lt;sup&gt;2+1&lt;/sup&gt;&quot;&quot;||\"\"##\n##\"\"||And here\'s some subscript too: &quot;&quot;a&lt;sub&gt;2k&lt;/sub&gt;&quot;&quot;||\"\"##\n##\"\"||I love acronyms: &quot;&quot;&lt;acronym title=\"What You See Is What You Get\"&gt;WYSIWYG&lt;/acronym&gt;&quot;&quot;||\"\"##\n\n|?|Embedding HTML within tables||\n||Here\'s some superscript: \"\"a<sup>2+1</sup>\"\"||\n||And here\'s some subscript too: \"\"a<sub>2k</sub>\"\"||\n||I love acronyms: \"\"<acronym title=\"What You See Is What You Get\">WYSIWYG</acronym>\"\"||\n\n====9. Adding a touch of style====\n\nThe table markup introduces a new [[TableMarkupReference style selector]]. CSS style options can be added to any element by enclosing them within **single braces**, right before the element content, e.g.:\n##\"\"||\"\"{\'\'style options\'\'}Element content\"\"||\"\"##\n\nFor example, to render a cell with **red background** and **white text color**, you can do the following:\n\n##\"\"||{background-color:red; color:white}Hello||\"\"##\n\n||{background-color:red; color:white}Hello||\n\nYou can play with **font size** and **text alignment**:\n\n##\"\"|?|Adding some more style||\"\"##\n##\"\"||{font-size:190%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:170%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:150%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:130%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:110%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:90%; text-align:right}Please scale me!||\"\"##\n##\"\"||{font-size:70%; text-align:right}Please scale me!||\"\"##\n\n|?|Adding some more style||\n||{font-size:190%; text-align:right}Please scale me!||\n||{font-size:170%; text-align:right}Please scale me!||\n||{font-size:150%; text-align:right}Please scale me!||\n||{font-size:130%; text-align:right}Please scale me!||\n||{font-size:110%; text-align:right}Please scale me!||\n||{font-size:90%; text-align:right}Please scale me!||\n||{font-size:70%; text-align:right}Please scale me!||\n\nYou can also apply style to **headings** and **captions**:\n\n##\"\"|?|{border:1px dotted red; color:red}Style can be applied anywhere||\"\"##\n##\"\"|=|{color:#000; font-size:150%; font-style:italic; font-family:Georgia, Hoefler Text, Georgia, serif; font-weight:normal; line-height:150%}Emphemeral Quibus|=|\"\"##\n##\"\"||Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Duis auctor auctor pede.||\"\"##\n\n|?|{border:1px dotted red; color:red}Style can be applied anywhere||\n|=|{color:#000; font-size:150%; font-style:italic; font-family:Georgia, Hoefler Text, Georgia, serif; font-weight:normal; line-height:150%}Emphemeral Quibus|=|\n||Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Duis auctor auctor pede.||\n\nPlease note that **style parameters should always follow attribute parameters**, when both are specified for an element (see the [[TableMarkupReference table markup reference]]):\n\n##\"\"|?|Give priority||\"\"##\n##\"\"||(x:2;y:2){background-color:pink}2x2||(x:2){background-color:lightblue}2x1||(y:2){background-color:lightyellow}1x2||\"\"##\n##\"\"||(y:2){background-color:lightyellow}1x2||{background-color:#333;color:white}1x1||\"\"##\n##\"\"||{background-color:lightblue}1x1||{background-color:#333;color:white}1x1||(x:2){background-color:pink}2x1||\"\"##\n\n|?|Give priority||\n||(x:2;y:2){background-color:pink}2x2||(x:2){background-color:lightblue}2x1||(y:2){background-color:lightyellow}1x2||\n||(y:2){background-color:lightyellow}1x2||{background-color:#333;color:white}1x1||\n||{background-color:lightblue}1x1||{background-color:#333;color:white}1x1||(x:2){background-color:pink}2x1||\n\n====10. Adding style through classes====\n\nYou can apply existing classes from your stylesheet to any element using the class parameter ##(c:\'\'class\'\')##. Note that custom style declarations specified through braced markup override class attributes.\n\nThe following example applies to table cells two class selectors defined in the stylesheet. The third row shows how to override a class selector with custom style attributes:\n\n##\"\"|?|Using class selectors to add style to table elements||\"\"##\n##\"\"||(c:highlight)This cell uses the ##.highlight## class||\"\"##\n##\"\"||(c:smaller)This cell uses the ##.smaller## class||\"\"##\n##\"\"||(c:smaller){font-size:150%}This cell uses the ##.smaller## class overridden by custom style settings||\"\"##\n\n|?|Using class selectors to add style to table elements||\n||(c:highlight)This cell uses the ##.highlight## class||\n||(c:smaller)This cell uses the ##.smaller## class||\n||(c:smaller){font-size:150%}This cell uses a ##.smaller## class overridden by custom style settings||\n\n====11. Global table attributes====\n\nTable-level attributes can be specified by adding at the beginning of the table the following element: ##\"\"|!|   ||\"\"##, which is used as a container for global table attributes. For example, you can specify **global style options** for a table by adding them to this element:\n\n##\"\"|!|{border:3px solid blue; background-color: black; color: white; width: 300px; text-align: center}||\"\"##\n##\"\"||Cell 1||\"\"##\n##\"\"||Cell 2||\"\"##\n##\"\"||Cell 3||\"\"##\n\n|!|{border:3px solid blue; background-color: black; color: white; width: 300px; text-align: center}||\n||Cell 1||\n||Cell 2||\n||Cell 3||\n\n====12. Referring to elements: the ##id## attribute====\n\n##id## attributes are used to refer to unique elements in a page and to provide an anchor for styling and linking. You can specify an ##id## for any table element by using the ##(i:\'\'id\'\')## parameter.\n\nFor example, the following markup creates a table with the ##id## \"main_table\" containing two cells with ##id##\'s \"cell_1\" and \"cell_2\"\n\n##\"\"|!|(i:main_table)||\"\"##\n##\"\"|?|Using id to refer to table elements||\"\"##\n##\"\"||(i:cell_1)This cell can be referred to by using the ##cell_1## id||\"\"##\n##\"\"||(i:cell_2)This cell can be referred to by using the ##cell_2## id||\"\"##\n\n|!|(i:main_table)||\n|?|Using id to refer to table elements||\n||(i:cell_1)This cell can be referred to by using the ##cell_1## id||\n||(i:cell_2)This cell can be referred to by using the ##cell_2## id||\n\n====13. Accessibility options: adding titles====\n\nAny table element can be given a ##title## attribute to enhance its accessibility. Titles are typically displayed in graphical browsers by hovering over the corresponding element and are useful to display unobtrusive descriptions about specific elements. You can specify a ##title## for any table element by using the ##(t:\'\'title\'\')## parameter.\n\nThe following example adds titles to several table elements (you can hover over the table to display them):\n\n##\"\"|!|(t:Comparative figures for fruit production in the last year){width: 350px}||\"\"##\n##\"\"|?|Fruit production in 2006||\"\"##\n##\"\"|=|       |=|(t:yearly production of apples)Apples|=|(t:yearly production of pears)Pears|=|\"\"##\n##\"\"|=|(t:Mary\'s contribution to 2006 production)Mary||(t:Mary\'s production of apples in 2006){text-align:center}300Kg||(t:Mary\'s production of pears in 2006){text-align:center}320Kg||\"\"##\n##\"\"|=|(t:John\'s contribution to 2006 production)John||(t:John\'s production of apples in 2006){text-align:center}400Kg||(t:John\'s production of pears in 2006){text-align:center}630Kg||\"\"##\n\n|!|(t:Comparative figures for fruit production in the last year){width: 350px}||\n|?|Fruit production in 2006||\n|=|       |=|(t:yearly production of apples)Apples|=|(t:yearly production of pears)Pears|=|\n|=|(t:Mary\'s contribution to 2006 production)Mary||(t:Mary\'s production of apples in 2006){text-align:center}300Kg||(t:Mary\'s production of pears in 2006){text-align:center}320Kg||\n|=|(t:John\'s contribution to 2006 production)John||(t:John\'s production of apples in 2006){text-align:center}400Kg||(t:John\'s production of pears in 2006){text-align:center}630Kg||\n\n====14. Accessibility options: adding a summary====\n\nTables can take an optional ##summary## attribute to describe the purpose and/or structure of the table. The description provided by the summary attribute is particularly helpful to users of non-visual browsers. You can specify a summary by adding a ##(u:\'\'Summary\'\')## parameter in the table global attributes.\n\nFor example, the following line:\n##\"\"|!|(u:This is a summary)||\"\"##\nwill add to the table a ##summary## attribute with the value ##This is a summary##.\n\n====15. Accessibility options: table head, table body and table foot====\n\nRows in a table can be grouped in a table head, table body and table foot. This division enables browsers to support scrolling of table bodies independently of the table head and foot. When long tables are printed, the table head and foot information may be repeated on each page that contains table data. The table head and table foot should contain information about the table\'s columns. The table body should contain rows of table data.\n\nWikka allows you to create groups of rows with special markers:\n~- The ##\"\"|[|\"\"## marker groups the rows it precedes as a **table head** block;\n~- The ##\"\"|]|\"\"## marker groups the rows it precedes as a **table foot** block;\n~- The ##\"\"|#|\"\"## marker groups the rows it precedes as a **table body**;\n\nThe following example shows how to use these elements to create row groups. Note that Wikka uses different backgrounds to differentiate column headings in the table head and foot from row headings in the table body:\n\n##\"\"|!|(u:A table with summary, caption, head, foot and body){width: 400px}||\"\"##\n##\"\"|?|Here\'s how you can group rows||\"\"##\n##\"\"|[|\"\"##\n##\"\"|=|Name|=|Place|=|Telephone||\"\"##\n##\"\"|]|\"\"##\n##\"\"|=|Name|=|Place|=|Telephone||\"\"##\n##\"\"|#|\"\"##\n##\"\"|=|John Smith||New York||555-1234||\"\"##\n##\"\"|=|Jane Smith||Los Angeles||555-2345||\"\"##\n##\"\"|=|John Doe||Unknown||Unknown||\"\"##\n##\"\"|=|Jane Doe||Unknown||Unknown||\"\"##\n\n|!|(u:A table with summary, caption, head, foot and body){width: 400px}||\n|?|Here\'s how you can group rows||\n|[|\n|=|Name|=|Place|=|Telephone||\n|]|\n|=|Name|=|Place|=|Telephone||\n|#|\n|=|John Smith||New York||555-1234||\n|=|Jane Smith||Los Angeles||555-2345||\n|=|John Doe||Unknown||Unknown||\n|=|Jane Doe||Unknown||Unknown||\n\n====16. Accessibility options: heading scope====\n\nTo be semantically correct and accessible to users with non-visual browsers, headings should contain scope attributes describing the cell range they refer to. \n~-Column heading scopes can be specified using the ##(o:col)## parameter in the corresponding column heading;\n~-Row heading scopes can be specified using the ##(o:row)## parameter in the corresponding row heading;\n\nThe following example shows how to correctly add column and row scopes to a table to make it accessible:\n\n##\"\"|!|(u:The number of employees and the foundation year of some imaginary companies.)||\"\"##\n##\"\"|?|Table 1: Company data||\"\"##\n##\"\"|[|\"\"##\n##\"\"|||=|(o:col)Employees|=|(o:col)Founded||\"\"##\n##\"\"|#|\"\"##\n##\"\"|=|(o:row)ACME Inc||1000||1947||\"\"##\n##\"\"|=|(o:row)XYZ Corp||2000||1973||\"\"##\n\n|!|(u:The number of employees and the foundation year of some imaginary companies.)||\n|?|Table 1: Company data||\n|[|\n|||=|(o:col)Employees|=|(o:col)Founded||\n|#|\n|=|(o:row)ACME Inc||1000||1947||\n|=|(o:row)XYZ Corp||2000||1973||\n\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), 'Already done?  OK!', 0);
	test("Adding TableMarkupReference page...",
	mysql_query("insert into ".$config['table_prefix']."pages set tag = 'TableMarkupReference', body = '=====Wikka Table Markup Reference=====\n>>==See also:==\n~-For an informal introduction to this markup and several examples consult this server\'s TableMarkup page.\n~-Updated versions of this page can be found on the [[Docs:TableMarkupReference Wikka documentation server]].\n>>\n==== 1. Table Markup Scheme ====\n\nThe generic markup for table elements follows this scheme:\n\n~**##{{color fg=\"#F00\" text=\"|*|\"}}{{color fg=\"blue\" text=\"(attribute parameters)\"}}{{color fg=\"green\" text=\"{style parameters}\"}}content{{color fg=\"#F00\" text=\"||\"}}##**\n\n==Example:==\n\n~##\"\"|=|(i:main_heading){text-size: 120%}This is the main heading||\"\"##\n\n===Understanding the Table Markup Scheme===\n\n~1)**Opening delimiter** --- **##{{color fg=\"#F00\" text=\"|*|\"}}##** is any of the delimiters described in the //elements table// below.\n~1)**Attributes** --- **##{{color fg=\"blue\" text=\"(attribute parameters)\"}}##** is an optional series of ##parameter:value## declarations enclosed in brackets. Valid parameters are described in the //attribute table// below. Multiple parameter declarations can be separated with a semicolon (**##;##**).\n~1)**Style** --- **##{{color fg=\"green\" text=\"{style parameters}\"}}##** is an optional series of CSS style declarations enclosed in braces. Multiple style declarations can be separated with a semicolon (**##;##**).\n~1)**Content** --- **##content##** can be any valid content for that element (including [[TextFormatting formatted text]]).\n~1)**Closing delimiter** --- **##{{color fg=\"#F00\" text=\"||\"}}##** is the standard delimiter.\n\n==Note:==\nSome elements are //self closing// and do not accept any attributes, style parameters or content. See the notes in the //elements table// below.\n\n==== 2. Elements ====\n\n|!|{width: 80%}||\n|?|Table Elements||\n|=|\"\"XHTML Elements\"\"|=|Delimiter|=|Notes||\n||##<table>##||##\"\"|!|\"\"##||Optional, only useful for adding attributes. **Must** be first in table markup if used. Should be on a line by itself.||\n||##<caption>##||##\"\"|?|\"\"##||||\n||##<colgroup>##||##\"\"|_|\"\"##||||\n||##<col />##||##\"\"|-|\"\"##||Selfclosing - must not be closed!||\n||##<thead>##||##\"\"|[|\"\"##||||\n||##<tfoot>##||##\"\"|]|\"\"##||||\n||##<tbody>##||##\"\"|#|\"\"##||||\n||##<tr>##||none||Will be opened for each row of table cells.||\n||##<th>##||##\"\"|=|\"\"##||||\n||##<td>##||##\"\"||\"\"##||||\n\n==== 3. Attributes ====\n\n|?|Table Attributes||\n|[|\n|=|Attribute|=|Markup key||\n|]|\n|=|Attribute|=|Markup key||\n|#|\n|=|(x:2)Core||\n||##id##||##i##||\n||##title##||##t##||\n||##class##||##c##||\n||##style##||##s##||\n|=|(x:2)i18n||\n||##xml:lang##||##l##||\n||##dir##||##d##||\n|=|(x:2)Table cells||\n||##colspan##||##x##||\n||##rowspan##||##y##||\n||##scope##||##o##||\n||##headers##||##h##||\n||##abbr##||##a##||\n||##axis##||##z##||\n|=|(x:2)Other Table elements||\n||##span##||##p##||\n||##summary##||##u##||\n\n\n----\nCategoryWiki', owner = '(Public)', note='".$upgrade_note."', user = 'WikkaInstaller', time = now(), latest = 'Y'", $dblink), 'Already done?  OK!', 0);
	test("Adding theme field to user preference table...",
	@mysql_query("ALTER TABLE ".$config['table_prefix']."users ADD
	theme varchar(50) default ''", $dblink), "Already done? OK!", 0);
case "1.3":
	// Dropping obsolete "handler" field from pages table, refs #452
	test('Removing handler field from the pages table...',
	@mysql_query("ALTER TABLE ".$config["table_prefix"]."pages DROP handler", $dblink), __('Already done? Hmm!'), 1);
	// Support for threaded comments
	test("Adding fields to comments table to enable threading...",  
	mysql_query("alter table ".$config["table_prefix"]."comments add parent int(10) unsigned default NULL", $dblink), "Already done? OK!", 0);
	test("Adding fields to comments table to enable threading...",
	mysql_query("alter table ".$config["table_prefix"]."users add default_comment_display enum('date_asc', 'date_desc', 'threaded') NOT NULL default 'threaded'", $dblink), "Already done? OK!", 0);
	test("Adding fields to comments table to enable threading...",  
	mysql_query("alter table ".$config["table_prefix"]."comments add status enum('deleted') default NULL", $dblink), "Already done? OK!", 0);
	// Create new fields for comment_read_acl and comment_post_acl, 
	// and copy existing comment_acl values to these new fields 
	test('Creating new comment_read_acl field...', 
	@mysql_query("alter table ".$config['table_prefix']."acls add comment_read_acl text not null", $dblink), __('Already done?  OK!'), 0); 
	test('Creating new comment_post_acl field...', 
	@mysql_query("alter table ".$config['table_prefix']."acls add comment_post_acl text not null", $dblink), __('Already done?  OK!'), 0); 
	test('Copying existing comment_acls to new fields...', 
	@mysql_query("update ".$config['table_prefix']."acls as a inner join(select page_tag, comment_acl from ".$config['table_prefix']."acls) as b on a.page_tag = b.page_tag set a.comment_read_acl=b.comment_acl, a.comment_post_acl=b.comment_acl", $dblink), __('Failed').'. ?', 1);
	test("Setting default UserSettings ACL...",
	@mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'UserSettings', comment_read_acl = '*', comment_post_acl = '+'", $dblink), __('Already done? OK!'), 0);
	test("Setting default AdminUsers ACL...",
	@mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminUsers', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink), __('Already done? OK!'), 0);
	test("Setting default AdminPages ACL...",
	@mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'AdminPages', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink), __('Already done? OK!'), 0);
	test("Setting default DatabaseInfo ACL...",
	@mysql_query("insert into ".$config['table_prefix']."acls set page_tag = 'DatabaseInfo', comment_read_acl = '!*', comment_post_acl = '!*'", $dblink), __('Already done? OK!'), 0);
	test(__('Creating index on owner column').'...', 
	@mysql_query('alter table '.$config['table_prefix'].'pages add index `idx_owner` (`owner`)', $dblink), __('Already done?  OK!'), 0); 
  	test(__('Altering referrers table structure').'...',
		@mysql_query("ALTER TABLE ".$config['table_prefix']."referrers MODIFY referrer varchar(300) NOT NULL default ''", $dblink), "Failed. ?", 1);
	test(__('Altering referrer blacklist table structure').'...',
		@mysql_query("ALTER TABLE ".$config['table_prefix']."referrer_blacklist MODIFY spammer varchar(300) NOT NULL default ''", $dblink), "Failed. ?", 1);
}

// #600: Force reloading of stylesheet.
// #6: Append this to individual theme stylesheets
$config['stylesheet_hash'] = substr(md5(time()),1,5);
?>

<p>
In the next step, the installer will try to write the updated configuration file, <tt><?php echo $wakkaConfigLocation ?></tt>.
Please make sure the web server has write access to the file, or you will have to edit it manually.
Once again, see <a href="http://docs.wikkawiki.org/WikkaInstallation" target="_blank">WikkaInstallation</a> for details.
</p>

<form action="<?php echo myLocation(); ?>?installAction=writeconfig" method="post">
<input type="hidden" name="config" value="<?php echo Wakka::hsc_secure(serialize($config)) ?>" /><?php /* #427 */ ?>
<input type="submit" value="Continue" />
</form>
