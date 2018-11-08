<?php

/**
 * WARNING: JUST FOR TESTING DO NOT USE IN A PRODUCTION ENVIRONMENT
 *
 * The purpose of this script is merely to populate quickly the table addon.tbl_moodle
 * and moodle with categories and courses
 */

require_once('../lib/LogicCourses.php');

// Checks if the user has the permissions to run this script
LogicCourses::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Retrieves the current studiensemester
$currentOrNextStudiensemester = LogicCourses::getCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

$rootCategoryId = ADDON_MOODLE_ROOT_CATEGORY_ID; // used to create the groups course in moodle

// To load useful infos about the studiensemester
$studiensemester = new studiensemester();
$studiensemester->load($currentOrNextStudiensemester);

$courses = LogicCourses::getCourses($currentOrNextStudiensemester); // Loads courses to be synch with moodle
$courseFormatOptions = LogicCourses::getCourseFormatOptions(); // Generates the parameter courseformatoptions for all courses
$startDate = LogicCourses::getStartDate($studiensemester); // Generates the parameter startdate for all courses
$endDate = LogicCourses::getEndDate($studiensemester); // Generates the parameter enddate for all courses

$numberOfCourses = Database::rowsNumber($courses); // Contains the total number of courses to be synchronized

Output::printInfo('Number of courses in the database to be synchronized: '.$numberOfCourses);
Output::printDebug('----------------------------------------------------------------------');

// Counters variables used by the summary
$numGroupsAddedToDB = 0;
$numCoursesAddedToDB = 0;
$numCoursesAddedToMoodle = 0;
$numCategoriesAddedToMoodle = 0;

// Loops through courses
while ($course = Database::fetchRow($courses))
{
	// Generates the short and full name for the current course
	$shortname = LogicCourses::getCourseShortname($course, $currentOrNextStudiensemester);
	$fullname = LogicCourses::getCourseFullname($course, $currentOrNextStudiensemester);

	Output::printDebug('Shortname: '.$shortname);
	Output::printDebug('Fullname: '.$fullname);

	// Creates the course if does not exist, otherwise retrieves its ID in moodle
	$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
		$course, $currentOrNextStudiensemester,
		$fullname, $shortname, $startDate, $courseFormatOptions, $endDate,
		$numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
	);

	// Adds a new record in addon.tbl_moodle with the course infos
	LogicCourses::addCourseToDatabase($moodleCourseId, $course, $currentOrNextStudiensemester, $numCoursesAddedToDB); //

	Output::printDebug('----------------------------------------------------------------------');
}

// >>> Addig groups <<<

$numberOfCourses++; // +1 for groups course

// Adds a course in moodle for all the groups users
$moodleCourseId = LogicCourses::addGroupsCourseToMoodle(
	$currentOrNextStudiensemester, $startDate, $courseFormatOptions, $endDate, $numCategoriesAddedToMoodle
);

// // Adds new records in addon.tbl_moodle with the groups and course infos
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
