<?php //display a particular thread’s contents
/* ====================================================================================================================== */
/* NoNonsense Forum v11 © Copyright (CC-BY) Kroc Camen 2011
   licenced under Creative Commons Attribution 3.0 <creativecommons.org/licenses/by/3.0/deed.en_GB>
   you may do whatever you want to this code as long as you give credit to Kroc Camen, <camendesign.com>
*/

//bootstrap the forum; you should read that file first
require_once './shared.php';

//get the post message, the other fields (name / pass) are retrieved automatically in 'shared.php'
define ('TEXT', safeGet (@$_POST['text'], SIZE_TEXT));

//which thread to show
$FILE = (preg_match ('/^[^.\/]+$/', @$_GET['file']) ? $_GET['file'] : '') or die ('Malformed request');
//load the thread (have to read lock status from the file)
$xml  = @simplexml_load_file ("$FILE.rss") or die ('Malformed XML');

//access rights for the current user
define ('CAN_REPLY', FORUM_ENABLED && (
	//- if you are a moderator (doesn’t matter if the forum or thread is locked)
	IS_MOD ||
	//- if you are a member, the forum lock doesn’t matter, but you can’t reply to locked threads (only mods can)
	(!(bool) $xml->channel->xpath ("category[text()='locked']") && IS_MEMBER) ||
	//- if you are neither a mod nor a member, then as long as: 1. the thread is not locked, and
	//  2. the forum is such that anybody can reply (unlocked or thread-locked), then you can reply
	(!(bool) $xml->channel->xpath ("category[text()='locked']") && (!FORUM_LOCK || FORUM_LOCK == 'threads'))
));

/* ====================================================================================================================== */

//was the submit button clicked? (and is the info valid?)
if (CAN_REPLY && AUTH && TEXT) {
	//get a read/write lock on the file so that between now and saving, no other posts could slip in
	//normally we could use a write-only lock 'c', but on Windows you can't read the file when write-locked!
	$f = fopen ("$FILE.rss", 'r+'); flock ($f, LOCK_EX);
	
	//we have to read the XML using the file handle that's locked because in Windows, functions like
	//`get_file_contents`, or even `simplexml_load_file`, won't work due to the lock
	$xml = simplexml_load_string (fread ($f, filesize ("$FILE.rss")), 'DXML') or die ('Malformed XML');
	
	if (!(
		//ignore a double-post (could be an accident with the back button)
		NAME == $xml->channel->item[0]->author &&
		formatText (TEXT) == $xml->channel->item[0]->description &&
		//can’t post if the thread is locked
		!$xml->channel->xpath ("category[text()='locked']")
	)) {
		//where to?
		//(we won’t use `page=last` here as we are effecitvely handing the user a permalink here)
		$page = ceil (count ($xml->channel->item) / FORUM_POSTS);
		$url  = FORUM_URL.PATH_URL.$FILE.($page > 1 ? "?page=$page" : '').'#'.base_convert (microtime (), 10, 36);
		
		//add the comment to the thread
		$item = $xml->channel->item[0]->insertBefore ('item');
		//add the "RE:" prefix, and reply number to the title
		//(see 'theme.config.php', if it exists, otherwise 'theme.config.deafult.php',
		//in the theme's folder for the definition of `THEME_RE`)
		$item->addChild ('title',	safeHTML (sprintf (THEME_RE,
			count ($xml->channel->item)-1,	//number of the reply
			$xml->channel->title		//thread title
		)));
		$item->addChild ('link',	$url);
		$item->addChild ('author',	safeHTML (NAME));
		$item->addChild ('pubDate',	gmdate ('r'));
		$item->addChild ('description',	safeHTML (formatText (TEXT)));
		
		//write the file: first move the write-head to 0, remove the file's contents, and then write new ones
		rewind ($f); ftruncate ($f, 0); fwrite ($f, $xml->asXML ());
	} else {
		//if a double-post, link back to the previous post
		$url = $xml->channel->item[0]->link;
	}
	
	//close the lock / file
	flock ($f, LOCK_UN); fclose ($f);
	
	//regenerate the forum / sub-forums's RSS file
	indexRSS ();
	
	//refresh page to see the new post added
	header ("Location: $url", true, 303);
	exit;
}

/* ====================================================================================================================== */

//lock / unlock the thread? (only a moderator can un/lock a thread)
if (isset ($_GET['lock']) && IS_MOD && AUTH) {
	//get a write lock on the file so that between now and saving, no other posts could slip in
	$f   = fopen ("$FILE.rss", 'r+'); flock ($f, LOCK_EX);
	$xml = simplexml_load_string (fread ($f, filesize ("$FILE.rss")), 'DXML') or die ('Malformed XML');
	
	if ((bool) $xml->channel->xpath ("category[text()='locked']")) {
		//if there’s a "locked" category, remove it
		//note: for simplicity this removes *all* channel categories as NNF only uses one atm,
		//      in the future the specific "locked" category needs to be removed
		unset ($xml->channel->category);
		//when unlocking, go to the thread
		$url = FORUM_URL.PATH_URL."$FILE?page=last#reply";
	} else {
		//if no "locked" category, add it
		$xml->channel->category[] = 'locked';
		//if locking return to the index
		//(todo: could return to the particular page in the index the thread is on--complex!)
		$url = FORUM_URL.PATH_URL;
	}
	
	//commit the data
	rewind ($f); ftruncate ($f, 0); fwrite ($f, $xml->asXML ());
	//close the lock / file
	flock ($f, LOCK_UN); fclose ($f);
	
	//regenerate the folder's RSS file
	indexRSS ();
	
	header ("Location: $url", true, 303);
	exit;
}

/* ====================================================================================================================== */

//info for the site header
$HEADER = array (
	'TITLE'		=> safeHTML ($xml->channel->title),
	'RSS'		=> PATH_URL."$FILE.rss",
	'LOCKED'	=> (bool) $xml->channel->xpath ("category[text()='locked']"),
	'LOCK_URL'	=> PATH_URL."$FILE?lock"
);

/* original post
   ---------------------------------------------------------------------------------------------------------------------- */
//take the first post from the thread (removing it from the rest)
$thread = $xml->channel->xpath ('item');
$post   = array_pop ($thread);

//prepare the first post, which on this forum appears above all pages of replies
$POST = array (
	'TITLE'		=> safeHTML ($xml->channel->title),
	'AUTHOR'	=> safeHTML ($post->author),
	'DATETIME'	=> gmdate ('r', strtotime ($post->pubDate)),
	'TIME'		=> date (DATE_FORMAT, strtotime ($post->pubDate)),
	'DELETE_URL'	=> FORUM_PATH . 'action.php?delete&amp;path='.safeURL (PATH)."&amp;file=$FILE",
	'APPEND_URL'	=> FORUM_PATH . 'action.php?append&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
			  .substr (strstr ($post->link, '#'), 1).'#append',
	'TEXT'		=> $post->description,
	'MOD'		=> isMod ($post->author),
	'ID'		=> substr (strstr ($post->link, '#'), 1)
);

//remember the original poster’s name, for marking replies by the OP
$author = (string) $post->author;

/* replies
   ---------------------------------------------------------------------------------------------------------------------- */
//determine the page number (for threads, the page number can be given as "last")
define ('PAGE',
	@$_GET['page'] == 'last'
	? ceil (count ($thread) / FORUM_POSTS)
	: (preg_match ('/^[1-9][0-9]*$/', @$_GET['page']) ? (int) $_GET['page'] : 1)
);

if (count ($thread)) {
	//sort the other way around
	//<stackoverflow.com/questions/2119686/sorting-an-array-of-simplexml-objects/2120569#2120569>
	foreach ($thread as &$node) $sort[] = strtotime ($node->pubDate);
	array_multisort ($sort, SORT_ASC, $thread);
	
	//number of pages (stickies are not included in the count as they appear on all pages)
	define ('PAGES', ceil (count ($thread) / FORUM_POSTS));
	//slice the full list into the current page
	$thread = array_slice ($thread, (PAGE-1) * FORUM_POSTS, FORUM_POSTS);
	
	//index number of the replies, accounting for which page we are on
	$no = (PAGE-1) * FORUM_POSTS;
	foreach ($thread as &$post) $POSTS[] = array (
		'AUTHOR'	=> safeHTML ($post->author),
		'DATETIME'	=> gmdate ('r', strtotime ($post->pubDate)),		//HTML5 `<time>` datetime attribute
		'TIME'		=> date (DATE_FORMAT, strtotime ($post->pubDate)),	//human readable time
		'TEXT'		=> $post->description,
		'DELETED'	=> (bool) $post->xpath ("category[text()='deleted']") ? 'deleted' : '',
		//if the current user in the curent forum can append/delete the current post:
		'CAN_ACTION'	=> CAN_REPLY && (
			//moderators can always see append/delete links on all posts
			IS_MOD ||
			//if you are not signed in, all append/delete links are shown (if forum/thread locking is off)
			//if you are signed in, then only links on posts with your name will show
			!HTTP_AUTH ||
			//if this post is the by the owner (they can append/delete to their own posts)
			(strtolower (NAME) == strtolower ($post->author) && (
				//if the forum is post-locked, they must be a member to append/delete their own posts
				(!FORUM_LOCK || FORUM_LOCK == 'threads') || IS_MEMBER
			))
		),
		'DELETE_URL'	=> FORUM_PATH . 'action.php?delete&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
				  .substr (strstr ($post->link, '#'), 1),
		'APPEND_URL'	=> FORUM_PATH . 'action.php?append&amp;path='.safeURL (PATH)."&amp;file=$FILE&amp;id="
				  .substr (strstr ($post->link, '#'), 1).'#append',
		'OP'		=> $post->author == $author ? 'op' : '',		//if author is the original poster
		'MOD'		=> isMod ($post->author) ? 'mod' : '',			//if the author is a moderator
		'NO'		=> ++$no,						//number of the reply
		'ID'		=> substr (strstr ($post->link, '#'), 1)
	);
} else {
	define ('PAGES', 1);
}

/* reply form
   ---------------------------------------------------------------------------------------------------------------------- */
if (CAN_REPLY) $FORM = array (
	'NAME'	=> safeString (NAME),
	'PASS'	=> safeString (PASS),
	'TEXT'	=> safeString (TEXT),
	'ERROR'	=> empty ($_POST) ? ERROR_NONE
		 : (!NAME ? ERROR_NAME
		 : (!PASS ? ERROR_PASS
		 : (!TEXT ? ERROR_TEXT
		 : ERROR_AUTH)))
);

//all the data prepared, now output the HTML
include FORUM_ROOT.'/themes/'.FORUM_THEME.'/thread.inc.php';

?>
