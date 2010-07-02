<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Library of interface functions and constants for module pcast
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the pcast specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_pcast
 * @copyright 2010 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/rating/lib.php');

define("PCAST_SHOW_ALL_CATEGORIES", 0);
define("PCAST_SHOW_NOT_CATEGORISED", -1);

define("PCAST_NO_VIEW", -1);
define("PCAST_STANDARD_VIEW", 0);
define("PCAST_CATEGORY_VIEW", 1);
define("PCAST_DATE_VIEW", 2);
define("PCAST_AUTHOR_VIEW", 3);
define("PCAST_ADDENTRY_VIEW", 4);
define("PCAST_APPROVAL_VIEW", 5);
define("PCAST_ENTRIES_PER_PAGE", 20);

define("PCAST_DATE_UPDATED",100);
define("PCAST_DATE_CREATED",101);
define("PCAST_AUTHOR_LNAME",200);
define("PCAST_AUTHOR_FNAME",201);

/** example constant */
//define('PCAST_ULTIMATE_ANSWER', 42);

/**
 * If you for some reason need to use global variables instead of constants, do not forget to make them
 * global as this file can be included inside a function scope. However, using the global variables
 * at the module level is not a recommended.
 */
//global $pcast_GLOBAL_VARIABLE;
//$pcast_QUESTION_OF = array('Life', 'Universe', 'Everything');

/**
 * Lists supported features
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
**/
function pcast_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return false;

        default: return null;
    }

}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $pcast An object from the form in mod_form.php
 * @return int The id of the newly inserted pcast record
 */
function pcast_add_instance($pcast) {
    global $DB, $USER;


    $pcast->timecreated = time();

    // If it is a new instance time created is the same as modified
    $pcast->timemodified = $pcast->timecreated;


    // If no owner then set it to the instance creator.
    // TODO: THIS COULD BE A POTENTIAL BUG!!!
    if(isset($pcast->enablerssitunes) and ($pcast->enablerssitunes == 1)) {
        if(!isset($pcast->userid)) {
            $pcast->userid = $USER->id;
        }
    }

    // Get the episode category information
    $pcast = pcast_get_itunes_categories($pcast);

    # You may have to add extra stuff in here #

    $result = $DB->insert_record('pcast', $pcast);

    $cmid = $pcast->coursemodule;
    $draftitemid = $pcast->image;
    // we need to use context now, so we need to make sure all needed info is already in db
    $context = get_context_instance(CONTEXT_MODULE, $cmid);

    if ($draftitemid) {
        file_save_draft_area_files($draftitemid, $context->id, 'pcast_logo', $pcast->image, array('subdirs'=>false));
    }


    return $result;

}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $pcast An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function pcast_update_instance($pcast) {
    global $DB;

    $pcast->timemodified = time();
    $pcast->id = $pcast->instance;

    // If no owner then set it to the instance creator.
    // TODO: THIS COULD BE A POTENTIAL BUG!!!
    if(isset($pcast->enablerssitunes) and ($pcast->enablerssitunes == 1)) {
        if(!isset($pcast->userid)) {
            $pcast->userid = $USER->id;
        }
    }

    // Get the episode category information
    $pcast = pcast_get_itunes_categories($pcast);

    # You may have to add extra stuff in here #

    $result = $DB->update_record('pcast', $pcast);

    $cmid = $pcast->coursemodule;
    $draftitemid = $pcast->image;
    // we need to use context now, so we need to make sure all needed info is already in db
    $context = get_context_instance(CONTEXT_MODULE, $cmid);

    if ($draftitemid) {
        file_save_draft_area_files($draftitemid, $context->id, 'pcast_logo', $pcast->image, array('subdirs'=>false));
    }


    return $result;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function pcast_delete_instance($id) {
    global $DB;

    if (! $pcast = $DB->get_record('pcast', array('id' => $id))) {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records('pcast', array('id' => $pcast->id));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $pcast
 * @return object $result

 */
function pcast_user_outline($course, $user, $mod, $pcast) {

    global $DB;

    if ($logs = $DB->get_records("log", array('userid'=>$user->id, 'module'=>'pcast',
                                              'action'=>'view', 'info'=>$pcast->id), "time ASC")) {

        $numviews = count($logs);
        $lastlog = array_pop($logs);

        $result = new object();
        $result->info = get_string("numviews", "", $numviews);
        $result->time = $lastlog->time;

        return $result;
    }
    return NULL;

}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $ipodcast
 * @return object $result

 */
function pcast_user_complete($course, $user, $mod, $pcast) {
    global $CFG, $DB;

    if ($logs = $DB->get_records("log", array('userid'=>$user->id, 'module'=>'pcast',
                                              'action'=>'view', 'info'=>$pcast->id), "time ASC")) {
        $numviews = count($logs);
        $lastlog = array_pop($logs);

        $strmostrecently = get_string("mostrecently");
        $strnumviews = get_string("numviews", "", $numviews);

        echo "$strnumviews - $strmostrecently ".userdate($lastlog->time);

    } else {
        print_string("noviews", "pcast");
    }

}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in pcast activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function pcast_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function pcast_cron () {
    return true;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of pcast. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $pcastid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function pcast_get_participants($pcastid) {
    return false;
}

/**
 * This function returns if a scale is being used by one pcast
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $pcastid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function pcast_scale_used($pcastid, $scaleid) {
    global $DB;

    $return = false;

    //$rec = $DB->get_record("pcast", array("id" => "$pcastid", "scale" => "-$scaleid"));
    //
    //if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of pcast.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any pcast
 */
function pcast_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('pcast', 'grade', -$scaleid)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function pcast_uninstall() {
    return true;
}

/**
 * Lists all browsable file areas
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @return array
 */
function pcast_get_file_areas($course, $cm, $context) {
    $areas = array('pcast_episode','pcast_logo');
    return $areas;
}

/**
 * Support for the Reports (Participants)
 * @return array()
 */
 function pcast_get_view_actions() {
     return array('view', 'view all', 'get attachment');
 }
/**
 * Support for the Reports (Participants)
 * @return array()
 */
 function pcast_get_post_actions() {
     return array('add', 'update');
 }

 /**
  * Tells if files in moddata are trusted and can be served without XSS protection.
  *
  * @return bool (true if file can be submitted by teacher only, otherwise false)
  */

function pcast_is_moddata_trusted() {
    return false;
}


function pcast_get_itunes_categories($item) {

    // Split the category info into the top category and nested category
    if(isset($item->category)) {
        $length = strlen($item->category);
        switch ($length) {
            case 4:
                $item->topcategory = substr($item->category,0,1);
                $item->nestedcategory = (int)substr($item->category,1,3);
                break;
            case 5:
                $item->topcategory = substr($item->category,0,2);
                $item->nestedcategory = (int)substr($item->category,2,3);
                break;
            case 6:
                $item->topcategory = substr($item->category,0,3);
                $item->nestedcategory = (int)substr($item->category,3,3);
                break;

            default:
                // SHOULD NEVER HAPPEN
                //TODO: Get the category from the podcast
                $item->topcategory = 1;
                $item->nestedcategory = 1;
                break;
        }
    }
    return $item;
}
