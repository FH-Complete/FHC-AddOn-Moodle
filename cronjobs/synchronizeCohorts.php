<?php

/**
 * This script adds all the students present on FHComplete to moodle
 * The database table used to link users to moodle courses is addon.tbl_moodle
 */

require_once('../lib/LogicCohorts.php');

// Checks if the user has the permissions to run this script
LogicCohorts::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize cohorts script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Studiensemester can be passed as commandline option or automatically retrieved
// ex: php <this script> --stsem WS2019
$currentOrNextStudiensemester = LogicCohorts::getCliOrCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

$uids = LogicCohorts::getDBLehrgangsCohortArray($currentOrNextStudiensemester);

print_r($uids);

$cohorts = LogicCohorts::searchCohort($currentOrNextStudiensemester);
print_r($cohorts);

if( count($cohorts->cohorts) < 1 )
{
	$cohorts = LogicCohorts::createCohort($currentOrNextStudiensemester);
	print_r($cohorts);
}

$cohort = $cohorts->cohorts[0];
$members = LogicCohorts::getCohortMembers($cohort->id);
print_r($members);

$moodleusers = LogicCohorts::getMoodleUsersByIds($members[0]->userids);
print_r($moodleusers);

$testuids = array('bhadmin', 'ma0080');
$addmembers = LogicCohorts::addCohortMembers($cohort->id, $testuids);



/*

// Retrieves the courses to be synchronized from database (addon.tbl_moodle)
$dbuIDsArray = LogicUsers::getDBMoodleCoursesIDsArray($currentOrNextStudiensemester);

Output::printInfo('Number of courses in the database: '.count($dbMoodleCoursesIDsArray));

// Retrieves the courses from moodle using the course ids retrieved from the database
$moodleCourses = LogicUsers::getMoodleCourses($dbMoodleCoursesIDsArray);

Output::printInfo('Number of courses in moodle: '.count($moodleCourses));

// If the amount of courses does not match between database and moodle
if (count($dbMoodleCoursesIDsArray) != count($moodleCourses))
{
	Output::printWarning('The number of courses in the database and those present in moodle does not match!');
}

// Retrieves the student_uids where status is 'Abbrecher' for the selected Studiensemester
$semesterAbbrecher = LogicUsers::getSemesterAbbrecher($currentOrNextStudiensemester);

LogicUsers::synchronizeStudenten($moodleCourses, true, $semesterAbbrecher); // All the magic happens here!

*/

Output::printInfo('Ended synchronize cohorts script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
