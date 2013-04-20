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
 * Adaptive testing core library functions
 *
 * @package    mod_adaptivequiz
 * @category   activity
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Option controlling what options are offered on the quiz settings form.
 */
define('ADAPTIVEQUIZMAXATTEMPT', 10);
define('ADAPTIVEQUIZNAME', 'adaptivequiz');

require_once($CFG->dirroot.'/question/engine/lib.php');


/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature: FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function adaptivequiz_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the adaptivequiz into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $adaptivequiz: An object from the form in mod_form.php
 * @param mod_adaptivequiz_mod_form $mform: A formslib object
 * @return int The id of the newly inserted adaptivequiz record
 */
function adaptivequiz_add_instance(stdClass $adaptivequiz, mod_adaptivequiz_mod_form $mform = null) {
    global $DB;

    $time = time();
    $adaptivequiz->timecreated = $time;
    $adaptivequiz->timemodified = $time;
    $adaptivequiz->attemptfeedbackformat = 0;

    $instance = $DB->insert_record('adaptivequiz', $adaptivequiz);

    // Save question tag association data
    if (!empty($instance) && is_int($instance)) {
        adaptivequiz_add_questcat_association($instance, $adaptivequiz);
    }

    return $instance;
}

/**
 * This functions creates question category association record(s)
 *
 * @param int $instance: activity instance id
 * @param object $adaptivequiz: An object from the form in mod_form.php
 * @return void
 */
function adaptivequiz_add_questcat_association($instance = 0, stdClass $adaptivequiz) {
    global $DB;

    if (0 != $instance && !empty($adaptivequiz->questionpool)) {
        $qtag = new stdClass();
        $qtag->instance = $instance;

        foreach ($adaptivequiz->questionpool as $questioncatid) {
            $qtag->questioncategory = $questioncatid;
            $DB->insert_record('adaptivequiz_question', $qtag);
        }
    }
}

/**
 * This function updates the question category association records
 * @param int $instance: activity instance
 * @param object $adaptivequiz: An object from the form in mod_form.php
 * @return void;
 */
function adaptivequiz_update_questcat_association($instance = 0, stdClass $adaptivequiz) {
    global $DB;

    // Remove old references
    if (!empty($instance)) {
        $DB->delete_records('adaptivequiz_question', array('instance' => $instance));
    }

    // Insert new references
    adaptivequiz_add_questcat_association($instance, $adaptivequiz);
}

/**
 * Updates an instance of the adaptivequiz in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $adaptivequiz: An object from the form in mod_form.php
 * @param mod_adaptivequiz_mod_form $mform: A formslib object
 * @return boolean Success/Fail
 */
function adaptivequiz_update_instance(stdClass $adaptivequiz, mod_adaptivequiz_mod_form $mform = null) {
    global $DB;

    $adaptivequiz->timemodified = time();
    $adaptivequiz->id = $adaptivequiz->instance;

    $instanceid = $DB->update_record('adaptivequiz', $adaptivequiz);

    adaptivequiz_update_questcat_association($adaptivequiz->id, $adaptivequiz);

    return $instanceid;
}

/**
 * Removes an instance of the adaptivequiz from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id: Id of the module instance
 * @return boolean Success/Failure
 */
function adaptivequiz_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('adaptivequiz', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('adaptivequiz', array('id' => $id));

    // Remove association table data
    if ($DB->get_record('adaptivequiz_question', array('instance' => $id))) {
        $DB->delete_records('adaptivequiz_question', array('instance' => $id));
    }

    // Remove question_usage_by_activity records
    $attempts = $DB->get_records('adaptivequiz_attempt', array('instance' => $id));

    if (!empty($attempts)) {
        foreach ($attempts as $attempt) {
            question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
        }

        // Remove attempts data
        $DB->delete_records('adaptivequiz_attempt', array('instance' => $id));
    }

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function adaptivequiz_user_outline($course, $user, $mod, $adaptivequiz) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course: the current course record
 * @param stdClass $user: the record of the user we are generating report for
 * @param cm_info $mod: course module info
 * @param stdClass $adaptivequiz: the module instance record
 * @return void, is supposed to echp directly
 */
function adaptivequiz_user_complete($course, $user, $mod, $adaptivequiz) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in adaptivequiz activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function adaptivequiz_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link adaptivequiz_print_recent_mod_activity()}.
 *
 * @param array $activities: sequentially indexed array of objects with the 'cmid' property
 * @param int $index: the index in the $activities to use for the next record
 * @param int $timestart: append activity since this time
 * @param int $courseid: the id of the course we produce the report for
 * @param int $cmid: course module id
 * @param int $userid: check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid: check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function adaptivequiz_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $COURSE, $DB, $USER;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $adaptivequiz = $DB->get_record('adaptivequiz', array('id' => $cm->instance));

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['instance'] = $adaptivequiz->id;

    $sql = "SELECT aa.*, u.firstname, u.lastname, u.email, u.picture, u.imagealt
              FROM {adaptivequiz_attempt} aa
                   JOIN {user} u ON u.id = aa.userid
                   $groupjoin
             WHERE aa.timemodified > :timestart
                   AND aa.instance = :instance
                   $userselect
                   $groupselect
          ORDER BY aa.timemodified ASC";
    $rs = $DB->get_recordset_sql($sql, $params);

    // Check if recordset contains records
    if (!$rs->valid()) {
        return;
    }

    $context         = context_module::instance($cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $viewreport      = has_capability('mod/adaptivequiz:viewreport', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    if (is_null($modinfo->groups)) {
        // Load all my groups and cache it in modinfo.
        $modinfo->groups = groups_get_user_groups($course->id);
    }

    $usersgroups = null;
    $aname = format_string($cm->name, true);

    foreach ($rs as $attempt) {
        if ($attempt->userid != $USER->id) {
            if (!$viewreport) {
                // View report permission required to view activity other user attempts
                continue;
            }

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                if (is_null($usersgroups)) {
                    $usersgroups = groups_get_all_groups($course->id, $attempt->userid, $cm->groupingid);
                    if (is_array($usersgroups)) {
                        $usersgroups = array_keys($usersgroups);
                    } else {
                        $usersgroups = array();
                    }
                }
                if (!array_intersect($usersgroups, $modinfo->groups[$cm->id])) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();
        $tmpactivity->content = new stdClass();
        $tmpactivity->user = new stdClass();

        $tmpactivity->type       = 'adaptivequiz';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->timemodified;

        $tmpactivity->content->attemptid = $attempt->id;
        $tmpactivity->content->attemptstate = get_string('recent'.$attempt->attemptstate, 'adaptivequiz');
        $tmpactivity->content->questionsattempted = $attempt->questionsattempted;

        $tmpactivity->user->id        = $attempt->userid;
        $tmpactivity->user->firstname = $attempt->firstname;
        $tmpactivity->user->lastname  = $attempt->lastname;
        $tmpactivity->user->picture   = $attempt->picture;
        $tmpactivity->user->imagealt  = $attempt->imagealt;
        $tmpactivity->user->email     = $attempt->email;

        $activities[$index++] = $tmpactivity;
    }

    $rs->close();

    return;
}

/**
 * Prints single activity item prepared by {@see adaptivequiz_get_recent_mod_activity()}
 * @param stdClass $activity an object whose properties come from {@see adaptivequiz_get_recent_mod_activity()}
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail set to true to show more detail for the recent activity
 * @param array $modnames an array of module names
 * @param bool $viewfullnames true if the user has the capability to view full names
 * @param bool $return set to true to return output, else false to echo the output
 * @return string|void HTML markup
 */
function adaptivequiz_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames, $return = false) {
    global $CFG, $OUTPUT;

    $output = '';
    $cols = '';
    $contect = '';

    // Define table
    $attr = array('border'=> '0', 'cellpadding' => '3', 'cellspacing' => '0', 'class' => 'adaptivequiz-recent');
    $output .= html_writer::start_tag('table', $attr);

    // Define table columns
    $attr = array('class' => 'userpicture', 'valign' => 'top');
    $content = $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    $cols .= html_writer::tag('td', $content, $attr);

    $content = '';

    if ($detail) {
        $modname = $modnames[$activity->type];
        // Start div
        $attr = array('class' => 'title');
        $content .= html_writer::start_tag('div', $attr);
        // Create img markup
        $attr = array('src' => $OUTPUT->pix_url('icon', $activity->type), 'class' => 'icon', 'alt' => $modname);
        $content .= html_writer::empty_tag('img', $attr);
        // Create anchor markup
        $attr = array('href' => "{$CFG->wwwroot}/mod/adaptivequiz/view.php?id={$activity->cmid}", 'class' => 'icon', 'alt' => $modname);
        $content .= html_writer::tag('a', $activity->name, $attr);
        // End div
        $content .= html_writer::end_tag('div');
    }

    // Create div with the state of the attempt
    $attr = array('class' => 'attemptstate');
    $string = get_string('recentattemptstate', 'adaptivequiz');
    $content .= html_writer::tag('div', $string.'&nbsp;'.$activity->content->attemptstate, $attr);
    // Create div with the number of questions attempted
    $attr = array('class' => 'questionsattempted');
    $string = get_string('recentactquestionsattempted', 'adaptivequiz', $activity->content->questionsattempted);
    $content .= html_writer::tag('div', $string, $attr);

    // Start div
    $attr = array('class' => 'user');
    $content .= html_writer::start_tag('div', $attr);
    // Create anchor for link to user's profile
    $attr = array('href' => $CFG->wwwroot.'/user/view.php?id='.$activity->user->id.'&amp;course='.$courseid);
    $fullname = fullname($activity->user, $viewfullnames);
    $content .= html_writer::tag('a', $fullname, $attr);

    // Add timestamp
    $content .= '&nbsp'.userdate($activity->timestamp);
    // End div
    $content .= html_writer::end_tag('div');
    // Add all of the data for the columns to the table row
    $cols .= html_writer::tag('td', $content);
    $output .= html_writer::tag('tr', $cols);
    // End table
    $output .= html_writer::end_tag('table');

    if (!empty($return)) {
        // The return statemtn is not required, but it here so that this function can be PHPUnit testsed
        return $output;
    } else {
        // Echo output to the page
        echo $output;
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 **/
function adaptivequiz_cron () {
    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function adaptivequiz_get_extra_capabilities() {
    return array();
}

/**
 * Extends the global navigation tree by adding adaptivequiz nodes if there is a relevant content
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the adaptivequiz module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function adaptivequiz_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the adaptivequiz settings
 *
 * This function is called when the context for the page is a adaptivequiz module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav: {@link settings_navigation}
 * @param navigation_node $adaptivequiznode: {@link navigation_node}
 */
function adaptivequiz_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $adaptivequiznode = null) {
}
