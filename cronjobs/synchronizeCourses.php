<?php

/**
 * This script populates the database table addon.tbl_moodle
 *
 * NOTE: JUST FOR TESTING
 */

require_once('../lib/LogicCourses.php');

// Checks if the user has the permissions to run this script
LogicCourses::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Retrives the current studiensemester
$currentOrNextStudiensemester = LogicCourses::getCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

$coursesCounter = 0; //
$rootCategoryId = ADDON_MOODLE_ROOT_CATEGORY_ID; //

//
$studiensemester = new studiensemester();
$studiensemester->load($currentOrNextStudiensemester);

//
$courses = LogicCourses::getCourses($currentOrNextStudiensemester); //
$courseFormatOptions = LogicCourses::getCourseFormatOptions(); //
$startDate = LogicCourses::getStartDate($studiensemester); //
$endDate = LogicCourses::getEndDate($studiensemester); //

$numberOfCourses = Database::rowsNumber($courses); //

Output::printInfo('Number of courses in the database to be synchronized: '.$numberOfCourses);
Output::printDebug('----------------------------------------------------------------------');

//
$numGroupsAddedToDB = 0;
$numCoursesAddedToDB = 0;
$numCoursesAddedToMoodle = 0;
$numCategoriesAddedToMoodle = 0;

//
while ($course = Database::fetchRow($courses))
{
	//
	$shortname = LogicCourses::getCourseShortname($course, $currentOrNextStudiensemester);
	$fullname = LogicCourses::getCourseFullname($course, $currentOrNextStudiensemester);

	Output::printDebug('Shortname: '.$shortname);
	Output::printDebug('Fullname: '.$fullname);

	//
	$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
		$course, $currentOrNextStudiensemester,
		$fullname, $shortname, $startDate, $courseFormatOptions, $endDate,
		$numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
	);

	//
	LogicCourses::addCourseToDatabase($moodleCourseId, $course, $currentOrNextStudiensemester, $numCoursesAddedToDB); //

	Output::printDebug('----------------------------------------------------------------------');
}

// Groups

$numberOfCourses++; // +1 for groups course

//
$moodleCourseId = LogicCourses::addGroupsCourseToMoodle(
	$currentOrNextStudiensemester, $startDate, $courseFormatOptions, $endDate, $numCategoriesAddedToMoodle
);

//
LogicCourses::addGroupsToDatabase($moodleCourseId, $currentOrNextStudiensemester, $numGroupsAddedToDB); //

// Summary
Output::printInfo('----------------------------------------------------------------------');
if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
{
	Output::printInfo('Total amount of courses added to moodle (+1 for groups): '. $numCoursesAddedToMoodle);
	Output::printInfo('Total amount of courses already present moodle: '. ($numberOfCourses - $numCoursesAddedToMoodle));
	Output::printInfo('Total amount of categories added to moodle: '. $numCategoriesAddedToMoodle);
	Output::printInfo('Total amount of courses added to database: '. $numCoursesAddedToDB);
	Output::printInfo('Total amount of groups added to database: '.$numGroupsAddedToDB);
}
else
{
	Output::printInfo('Total amount of courses that would be added to moodle (+1 for groups): '. $numCoursesAddedToMoodle);
	Output::printInfo('Total amount of courses already present moodle: '. ($numberOfCourses - $numCoursesAddedToMoodle));
	Output::printInfo('Total amount of categories that would be added to moodle: '. $numCategoriesAddedToMoodle);
	Output::printInfo('Total amount of courses that would be added to database: '. $numCoursesAddedToDB);
	Output::printInfo('Total amount of groups that would be added to database: '.$numGroupsAddedToDB);
}
Output::printInfo('----------------------------------------------------------------------');

Output::printInfo('Ended synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
