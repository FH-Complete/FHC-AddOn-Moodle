<?php

/**
 * This script adds all the users (lectors, students and management staff) present on FHComplete to moodle
 * It also adds all the users from groups linked to a moodle course, into the linked moodle course
 * The database table used to link users to moodle courses is addon.tbl_moodle
 */

require_once('../lib/LogicUsers.php');

// Checks if the user has the permissions to run this script
LogicUsers::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize users script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Retrieves the current studiensemester
$currentOrNextStudiensemester = LogicUsers::getCurrentOrNextStudiensemester();

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

$numCreatedUsers = 0;
$numEnrolledLectors = 0;
$numEnrolledManagementStaff = 0;
$numEnrolledStudents = 0;
$numCreatedGroups = 0;
$numEnrolledGroupsMembers = 0;
$numUnenrolledGroupsMembers = 0;

if (count($moodleCourses) > 0) Output::printDebug('------------------------------------------------------------');

// Loops through the courses retrieved from moodle
foreach ($moodleCourses as $moodleCourse)
{
	Output::printDebug('>>> Syncing moodle course '.$moodleCourse->id.':"'.$moodleCourse->shortname.'" <<<');

	// Get all the enrolled users in this course from moodle
	$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourse->id);

	// Tries to retrieve groups from DB for this moodle course
	$courseGroups = LogicUsers::getCourseGroups($moodleCourse->id); //

	// Checks if there are groups
	if (Database::rowsNumber($courseGroups) > 0)
	{
		// Retrieves a list of UIDs of users to be unenrolled
		$uidsToUnenrol = LogicUsers::getUsersToUnenrol($moodleEnrolledUsers);

		// Synchronizes groups members
		LogicUsers::synchronizeGroupsMembers(
			$moodleCourse->id, $courseGroups, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledGroupsMembers
		);

		// Unenrol users for this group
		LogicUsers::unenrolUsers($moodleCourse->id, $uidsToUnenrol, $numUnenrolledGroupsMembers);
	}
	else // otherwise
	{
		// Synchronizes lectors
		LogicUsers::synchronizeLektoren(
			$moodleCourse->id, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
		);

		// Synchronizes management staff
		if (ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG === true)
		{
			LogicUsers::synchronizeFachbereichsleitung(
				$moodleCourse->id, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledManagementStaff
			);
		}

		// Synchronizes students
		LogicUsers::synchronizeStudenten(
			$moodleCourse->id, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledStudents, $numCreatedGroups
		);
	}

	Output::printDebug('------------------------------------------------------------');
}

// Summary
Output::printInfo('----------------------------------------------------------------------');
if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
{
	Output::printInfo('Total amount of users created in moodle: '. $numCreatedUsers);
	Output::printInfo('Total amount of lectors enrolled in moodle: '. $numEnrolledLectors);
	Output::printInfo('Total amount of management staff enrolled in moodle: '. $numEnrolledManagementStaff);
	Output::printInfo('Total amount of students enrolled in moodle: '. $numEnrolledStudents);
	Output::printInfo('Total amount of groups created in moodle: '. $numCreatedGroups);
	Output::printInfo('Total amount of groups members enrolled in moodle: '. $numEnrolledGroupsMembers);
	Output::printInfo('Total amount of UNrolled groups members in moodle: '. $numUnenrolledGroupsMembers);
}
else
{
	Output::printInfo('Total amount of users that would be created in moodle: '. $numCreatedUsers);
	Output::printInfo('Total amount of lectors that would be enrolled in moodle: '. $numEnrolledLectors);
	Output::printInfo('Total amount of management staff that would be enrolled in moodle: '. $numEnrolledManagementStaff);
	Output::printInfo('Total amount of students that would be enrolled in moodle: '. $numEnrolledStudents);
	Output::printInfo('Total amount of groups that would be created in moodle: '. $numCreatedGroups);
	Output::printInfo('Total amount of groups members that would be enrolled in moodle: '. $numEnrolledGroupsMembers);
	Output::printInfo('Total amount of groups members that would be UNenrolled in moodle: '. $numUnenrolledGroupsMembers);
}
Output::printInfo('----------------------------------------------------------------------');

Output::printInfo('Ended synchronize users script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
