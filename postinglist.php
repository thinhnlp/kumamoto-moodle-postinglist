<?php

// Authors: KAWAMURA Ryo and KITA Toshihiro http://t-kita.net 
// License: same as Moodle
/*
postinglist.php: list up all the participants and all the attached files in the forum.
[How to install]
Put this file (postinglist.php) under mod/forum/ . 
Add the following line to mod/forum/view.php just before "echo '</div>';" around L195:
echo '<div align="right"><a href="'.$CFG->wwwroot.'/mod/forum/postinglist.php?'.($f==0?'id='.$id:'f='.$f).'"> view posting list </a></div> ';
*/

    require_once('../../config.php');
    require_once('lib.php');
    require_once("$CFG->libdir/rsslib.php");

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Forum ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single forum)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '');             // search string
	 
	if (isset($_GET["sort"])) {
		$sort = $_GET["sort"];
	} else {
		$sort = "firstname_inc";
	}

    $buttontext = '';


    if ($id) {
        if (! $cm = get_coursemodule_from_id('forum', $id)) {
            error("Course Module ID was incorrect");
        }
        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
        if (! $forum = get_record("forum", "id", $cm->instance)) {
            error("Forum ID was incorrect");
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforums = get_string("modulenameplural", "forum");
        $strforum = get_string("modulename", "forum");
        $buttontext = update_module_button($cm->id, $course->id, $strforum);

    } else if ($f) {

        if (! $forum = get_record("forum", "id", $f)) {
            error("Forum ID was incorrect or no longer exists");
        }
        if (! $course = get_record("course", "id", $forum->course)) {
            error("Forum is misconfigured - don't know what course it's from");
        }

        if (!$cm = get_coursemodule_from_instance("forum", $forum->id, $course->id)) {
            error("Course Module missing");
        }

        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);

        $strforums = get_string("modulenameplural", "forum");
        $strforum = get_string("modulename", "forum");
        $buttontext = update_module_button($cm->id, $course->id, $strforum);

    } else {
        error('Must specify a course module or a forum ID');
    }

    if (!$buttontext) {
        $buttontext = forum_search_form($course, $search);
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

/// Print header.

    /// Add ajax-related libs
    require_js(array('yui_yahoo', 'yui_event', 'yui_dom', 'yui_connection', 'yui_json'));
    require_js($CFG->wwwroot . '/mod/forum/rate_ajax.js');

    $navigation = build_navigation('', $cm);
    print_header_simple(format_string($forum->name), "",
                 $navigation, "", "", true, $buttontext, navmenu($course, $cm));

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/forum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'forum'));
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/forum/view.php?id=' . $cm->id);
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

/// Okay, we can show the discussions. Log the forum view.
    if ($cm->id) {
        add_to_log($course->id, "forum", "view forum", "view.php?id=$cm->id", "$forum->id", $cm->id);
    } else {
        add_to_log($course->id, "forum", "view forum", "view.php?f=$forum->id", "$forum->id");
    }


/// Print settings and things across the top

    // If it's a simple single discussion forum, we need to print the display
    // mode control.
    if ($forum->type == 'single') {
        if (! $discussion = get_record("forum_discussions", "forum", $forum->id)) {
            if ($discussions = get_records("forum_discussions", "forum", $forum->id, "timemodified ASC")) {
                $discussion = array_pop($discussions);
            }
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("forum_displaymode", $mode);
            }
            $displaymode = get_user_preferences("forum_displaymode", $CFG->forum_displaymode);
            forum_print_mode_form($forum->id, $displaymode, $forum->type);
        }
    }

	echo '<div align="right"><a href="'.$CFG->wwwroot.'/mod/forum/view.php?id='.$id.'"> return </a><br></div>';
 
   echo '</div>';


//    print_box_end();  // forumcontrol

//    print_box('&nbsp;', 'clearer');


    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a->blockafter = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        notify(get_string('thisforumisthrottled','forum',$a));
    }

    if ($forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        notify(get_string('qandanotify','forum'));
    }

    $forum->intro = trim($forum->intro);

	$firstnamesort = "firstname_inc";
	$familynamesort = "familyname_inc";
	$attachmentsort = "attachment_inc";
	$modifiedsort = "modified_inc";

if($sort == "firstname_inc") {
	$firstnamesort = "firstname_dec";
	$sort = "firstname ASC";
} else if($sort == "firstname_dec") {
	$firstnamesort = "firstname_inc";
	$sort = "firstname DESC";
} else if($sort == "familyname_inc") {
	$familynamesort = "familyname_dec";
	$sort = "lastname ASC";
} else if($sort == "familyname_dec") {
	$familynamesort = "familyname_inc";
	$sort = "lastname DESC";
} else if($sort == "attachment_inc") {
	$attachmentsort = "attachment_dec";
	$sort = "attachment ASC";
} else if($sort == "attachment_dec") {
	$attachmentsort = "attachment_inc";
	$sort = "attachment DESC";
} else if($sort == "modified_inc") {
	$modifiedsort = "modified_dec";
	$sort = "modified ASC";
} else if($sort == "modified_dec") {
	$modifiedsort = "modified_inc";
	$sort = "modified DESC";
}



$db_connect  = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass);
mysql_select_db($CFG->dbname,$db_connect );
$str_sql_test = 
"(SELECT 
	".$CFG->prefix."forum_posts.id,
	".$CFG->prefix."forum_posts.userid,
	".$CFG->prefix."forum_posts.modified,
	".$CFG->prefix."forum_discussions.course,
	forum,
	firstname, 
	lastname, 
	attachment
FROM 
	`".$CFG->prefix."forum_posts`,
	`".$CFG->prefix."forum_discussions`,
	`".$CFG->prefix."user`,
	`".$CFG->prefix."course_display`
WHERE 
	".$CFG->prefix."forum_posts.discussion = ".$CFG->prefix."forum_discussions.id 
	AND ".$CFG->prefix."user.id = ".$CFG->prefix."forum_posts.userid
	AND length(".$CFG->prefix."forum_posts.attachment) > 0 
	AND ".$CFG->prefix."forum_discussions.course = $course->id
	AND ".$CFG->prefix."forum_discussions.forum = $forum->id
	AND ".$CFG->prefix."user.id=".$CFG->prefix."course_display.userid
	AND ".$CFG->prefix."course_display.course= $course->id 
) UNION (

SELECT
	IF(1>2,'-1','-1') as id,
	".$CFG->prefix."user.id as userid,
	IF(1>2,'-1','-1') as modified,
	IF(1>2,'-1','-1') as cource,
	IF(1>2,'-1','-1') as forum,
	firstname, 
	lastname, 
	IF(1>2,'-1','-1') as attachment
FROM ".$CFG->prefix."user, ".$CFG->prefix."course_display
WHERE
	".$CFG->prefix."user.id=".$CFG->prefix."course_display.userid
	AND ".$CFG->prefix."course_display.course= $course->id

	AND NOT EXISTS (
    SELECT
		".$CFG->prefix."forum_posts.id,
		".$CFG->prefix."user2.id, 
		firstname, 
		lastname, 
		".$CFG->prefix."forum_discussions.course, 
		forum, 
		attachment
    FROM 
		".$CFG->prefix."forum_posts,
		".$CFG->prefix."forum_discussions,
		".$CFG->prefix."user AS ".$CFG->prefix."user2,
		".$CFG->prefix."course_display
    WHERE ".
		$CFG->prefix."forum_posts.discussion=".$CFG->prefix."forum_discussions.id 
    	And ".$CFG->prefix."user2.id=".$CFG->prefix."forum_posts.userid 
    	And ".$CFG->prefix."forum_discussions.course= $course->id
    	And ".$CFG->prefix."forum_discussions.forum= $forum->id
 	 AND ".$CFG->prefix."course_display.course= $course->id
	 AND	".$CFG->prefix."user.id=".$CFG->prefix."course_display.userid
    	And ((".$CFG->prefix."forum_posts.attachment) Is Not Null)
    	AND ".$CFG->prefix."user.id = ".$CFG->prefix."user2.id
	)
) ORDER BY ".$sort;


$tempuserid = -1;
//SQL文の実行
//$rs_test = mysql_query($str_sql_test, $db_connect);
//結果セット内の各レコードを順次参照し、連想配列に代入
if ($rs_test = mysql_query($str_sql_test, $db_connect)) {
    $thisuri1= $CFG->wwwroot.'/mod/forum/postinglist.php?'.($f==0?'id='.$id:'f='.$f);
    echo '<table bordercolor="#dddddd" border="1" cellpadding="5">';
    echo '<th style="background-color:#E0FFFF">&nbsp</th>';
    echo '<th style="background-color:#E0FFFF">
          <a href="'.$thisuri1.'&sort='.$firstnamesort.'">firstname</a> / 
          <a href="'.$thisuri1.'&sort='.$familynamesort.'">familyname</a></td>';
    echo '<th style="background-color:#E0FFFF">
          <a href="'.$thisuri1.'&sort='.$attachmentsort.'">uploaded file name</a></th>';
    echo '<th style="background-color:#E0FFFF">
          <a href="'.$thisuri1.'&sort='.$modifiedsort.'">upload time</a></th>';
    while ($row = mysql_fetch_array($rs_test)) {
        echo '<tr>';
		//uesr image
		echo '<td>';
		if(intval("{$row['userid']}") <> $tempuserid){
		echo '<a href="'.$CFG->wwwroot.'/user/view.php?id=';
		echo "{$row['userid']}";
		echo '&course='.$course->id.'">'.'<img class="userpicture defaultuserpic" src="'.$CFG->wwwroot.'/pix/u/f2.png" title="" alt="" width="35" height="35">'.'</a>'; }
		echo '</td>';
		// user name
		echo '<td>';
		if(intval("{$row['userid']}") <> $tempuserid){
		echo '<a href="'.$CFG->wwwroot.'/user/view.php?id=';
		echo "{$row['userid']}";
		echo '&course='.$course->id.'">';
		// echo "{$row['firstname']} {$row['lastname']}";
		$postuser = new object();
		$postuser->firstname = $row['firstname'];
		$postuser->lastname  = $row['lastname'];
		$fullname = fullname($postuser);
		echo $fullname;
		echo '</a>'; }
		echo '</td>';
		$tempuserid = intval("{$row['userid']}");
		// file name
		if(intval("{$row['attachment']}") <> -1) {
			echo '<td><a href="';
        	echo $CFG->wwwroot."/file.php/{$row['course']}/moddata/forum/{$row['forum']}/{$row['id']}/{$row['attachment']}";
        	echo ' ">';
        	echo "{$row['attachment']}</a></td>" ;
        } else {
			echo "<td></td>";
		}
		
        // upload time
		$now = intval("{$row['modified']}");
		if($now == -1) {
			$gettime = '';
		} else {
			$gettime = date("Y/m/d H:i:s",$now);
		}
		echo "<td>".$gettime."</td>";
				
		
		echo '</tr>';
    }
    echo '</table>';
	
	if($sort == "firstname") {
		$temp = $row['firstname'];
	} else if ($sort == "lastname") {
		$temp = $row['firstname'];
	}
	
} else {
    echo mysql_error();
}
mysql_close($db_connect);

print_footer($course);
?>
