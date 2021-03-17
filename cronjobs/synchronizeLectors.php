<?php

/**
 * This script adds all the lectors present on FHComplete to moodle
 * The database table used to link users to moodle courses is addon.tbl_moodle
 */

require_once('../lib/LogicUsers.php');

// Checks if the user has the permissions to run this script
LogicUsers::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize lectors script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Studiensemester can be passed as commandline option or automatically retrieved
// ex: php <this script> --stsem WS2019
$currentOrNextStudiensemester = LogicUsers::getCliOrCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

// Retrieves the courses to be synchronized from database (addon.tbl_moodle)
$dbMoodleCoursesIDsArray = LogicUsers::getDBMoodleCoursesIDsArray($currentOrNextStudiensemester);

Output::printInfo('Number of courses in the database: '.count($dbMoodleCoursesIDsArray));

// Retrieves the courses from moodle using the course ids retrieved from the database
$moodleCourses = LogicUsers::getMoodleCourses($dbMoodleCoursesIDsArray);

Output::printInfo('Number of courses in moodle: '.count($moodleCourses));

// If the amount of courses does not match between database and moodle
if (count($dbMoodleCoursesIDsArray) != count($moodleCourses))
{
	Output::printWarning('The number of courses in the database and those present in moodle does not match!');
}

LogicUsers::synchronizeLektoren($moodleCourses); // All the magic happens here!

Output::printInfo('Ended synchronize lectors script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();

