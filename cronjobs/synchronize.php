<?php

/**
 * This script adds all the users (lectors, students and management staff) present on FHComplete to moodle
 */

require_once('../lib/Logic.php');

// Checks if the user has the permissions to run this script
Logic::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize script on '.date('Y-m-d H:m:s'));

// Retrives the current studiensemester
$currentOrNextStudiensemester = Logic::getCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

// Retrives the courses to be synchronized from database (addon.tbl_moodle)
$dbMoodleCoursesIDsArray = Logic::getDBMoodleCoursesIDsArray($currentOrNextStudiensemester);

Output::printInfo('Number of courses in the database: '.count($dbMoodleCoursesIDsArray));

// Retrives the courses from moodle using the course ids retrived from the database
$moodleCourses = Logic::core_course_get_courses($dbMoodleCoursesIDsArray);

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
	Output::printDebug('Syncing moodle course '.$moodleCourse->id.':"'.$moodleCourse->shortname.'"');

	// Get all the enrolled users in this course from moodle
	$moodleEnrolledUsers = Logic::core_enrol_get_enrolled_users($moodleCourse->id);

	// Synchronizes lectors
	Logic::synchronizeLektoren($moodleCourse->id, $moodleEnrolledUsers);

	// Synchronizes management staff
	if (ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG === true)
	{
		Logic::synchronizeFachbereichsleitung($moodleCourse->id, $moodleEnrolledUsers);
	}

	// Synchronizes students
	Logic::synchronizeStudenten($moodleCourse->id, $moodleEnrolledUsers);

	Output::printDebug('------------------------------------------------------------');
}

Output::printInfo('Ended synchronize script on '.date('Y-m-d H:m:s'));
Output::printLineSeparator();
