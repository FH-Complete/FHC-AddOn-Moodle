<?php

/**
 * This script adds all the users (lectors, students and management staff) present on FHComplete to moodle
 */

require_once('../lib/LogicUsers.php');

// Checks if the user has the permissions to run this script
LogicUsers::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize users script on '.date('Y-m-d H:m:s'));

// Retrives the current studiensemester
$currentOrNextStudiensemester = LogicUsers::getCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

// Retrives the courses to be synchronized from database (addon.tbl_moodle)
$dbMoodleCoursesIDsArray = LogicUsers::getDBMoodleCoursesIDsArray($currentOrNextStudiensemester);

Output::printInfo('Number of courses in the database: '.count($dbMoodleCoursesIDsArray));

// Retrives the courses from moodle using the course ids retrived from the database
$moodleCourses = LogicUsers::core_course_get_courses($dbMoodleCoursesIDsArray);

Output::printInfo('Number of courses in moodle: '.count($moodleCourses));

// If the amount of courses does not match between database and moodle
if (count($dbMoodleCoursesIDsArray) != count($moodleCourses))
{
	Output::printWarning('The number of courses in the database and those present in moodle does not match!');
}

if (count($moodleCourses) > 0) Output::printDebug('------------------------------------------------------------');

// Loops through the courses retrived from moodle
foreach ($moodleCourses as $moodleCourse)
{
	Output::printInfo('>>> Syncing moodle course '.$moodleCourse->id.':"'.$moodleCourse->shortname.'" <<<');

	// Get all the enrolled users in this course from moodle
	$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourse->id);

	// Retrives a list of UIDs of users to be unenrolled
	$usersToUnenrol = LogicUsers::getUsersToUnenrol(
		$moodleCourse->id, $moodleEnrolledUsers, $currentOrNextStudiensemester
	);

	// Synchronizes lectors
	LogicUsers::synchronizeLektoren($moodleCourse->id, $moodleEnrolledUsers, $usersToUnenrol);

	// Synchronizes management staff
	if (ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG === true)
	{
		LogicUsers::synchronizeFachbereichsleitung($moodleCourse->id, $moodleEnrolledUsers, $usersToUnenrol);
	}

	// Synchronizes students
	LogicUsers::synchronizeStudenten($moodleCourse->id, $moodleEnrolledUsers, $usersToUnenrol);

	// Synchronizes groups members
	LogicUsers::synchronizeGroupsMembers($moodleCourse->id, $moodleEnrolledUsers, $currentOrNextStudiensemester);

	// Unenrol users
	LogicUsers::unenrolUsers($moodleCourse->id, $usersToUnenrol);

	Output::printDebug('------------------------------------------------------------');
}

Output::printInfo('Ended synchronize users script on '.date('Y-m-d H:m:s'));
Output::printLineSeparator();
