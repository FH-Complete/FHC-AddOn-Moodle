<?php
/**
 * The purpose of this script is to get the courses from FHComplete DB and then populates the table addon.tbl_moodle
 * and moodle with categories and courses
 */

require_once(dirname(__FILE__).'/../lib/LogicCourses.php');

// Checks if the user has the permissions to run this script
LogicCourses::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Studiensemester can be passed as commandline option or automatically retrieved
// ex: php <this script> --stsem WS2019
$currentOrNextStudiensemester = LogicCourses::getCliOrCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

// Retrieves the courses from database
$fhcCourses = LogicCourses::getCoursesFromFHC($currentOrNextStudiensemester);

$numberOfCourses = Database::rowsNumber($fhcCourses); // Contains the total number of courses to be synchronized

Output::printInfo('Number of courses in the database to be synchronized: '.$numberOfCourses);
Output::printDebug('----------------------------------------------------------------------');

// To load useful infos about the studiensemester
$studiensemester = new studiensemester();
$studiensemester->load($currentOrNextStudiensemester);
$startDate = LogicCourses::getStartDate($studiensemester); // Generates the parameter startdate for all courses
$endDate = LogicCourses::getEndDate($studiensemester); // Generates the parameter enddate for all courses

// Generates the parameter courseformatoptions for all courses
$courseFormatOptions = LogicCourses::getCourseFormatOptions();

// Counters variables used by the summary
$numGroupsAddedToDB = 0;
$numCoursesAddedToDB = 0;
$numCoursesUpdatedDB = 0;
$numCoursesAddedToMoodle = 0;
$numCategoriesAddedToMoodle = 0;

// Loops through courses
while ($course = Database::fetchRow($fhcCourses))
{
	// Generates the short and full name for the current course
	$shortname = LogicCourses::getCourseShortname($course, $currentOrNextStudiensemester);
	$fullname = LogicCourses::getCourseFullname($course, $currentOrNextStudiensemester);

	// In this way the lehreinheit_id is added everytime, by LogicCourses::getCourseShortname or here
	// In this way the lehreinheit_id is added everytime, by LogicCourses::getCourseFullname or here
	if (ADDON_MOODLE_JUST_MOODLE === true)
	{
		$shortname .= '-'.$course->lehreinheit_id;
		$fullname .= ' - '.$course->lehreinheit_id;
	}

	// Adds the lector's name and surname
	$shortname .= '-'.str_replace(' ', '', $course->lektoren); // remove the blank between lector's name and surname
	$fullname .= ' - '.$course->lektoren;

	// To avoid errors on moodle side shorts them
	$shortname = mb_substr($shortname, 0, 254);
	$fullname = mb_substr($fullname, 0, 254);

	Output::printDebug('Shortname: '.$shortname);
	Output::printDebug('Fullname: '.$fullname);

	// Creates the course if does not exist, otherwise retrieves its ID in moodle
	$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
		$course, $currentOrNextStudiensemester,
		$fullname, $shortname, $startDate, $courseFormatOptions, $endDate,
		$numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
	);

	// Checks if the course already exists in addon.tbl_mooble
	$courseExists = LogicCourses::getDBCoursesByIDs($moodleCourseId);
	if (Database::rowsNumber($courseExists) == 0)
	{
		// By default set gruppen as true
		$course->gruppen = true;

		// Set the lehrveranstaltung_id to null to insert into database courses with only the lehreinheit_id
		$course->lehrveranstaltung_id = null;

		// If not then adds a new record in addon.tbl_moodle with the course infos
		LogicCourses::addCourseToDatabase($moodleCourseId, $course, $currentOrNextStudiensemester, $numCoursesAddedToDB);
	}

	Output::printDebug('----------------------------------------------------------------------');
}

// Summary
Output::printInfo('----------------------------------------------------------------------');

if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
{
	Output::printInfo('Total amount of courses added to moodle: '. $numCoursesAddedToMoodle);
	Output::printInfo('Total amount of courses already present moodle: '. ($numberOfCourses - $numCoursesAddedToMoodle));
	Output::printInfo('Total amount of categories added to moodle: '. $numCategoriesAddedToMoodle);
	Output::printInfo('Total amount of courses added to database: '. $numCoursesAddedToDB);
	Output::printInfo('Total amount of courses updated in database: '. $numCoursesUpdatedDB);
}
else
{
	Output::printInfo('Total amount of courses that would be added to moodle: '. $numCoursesAddedToMoodle);
	Output::printInfo('Total amount of courses already present moodle: '. ($numberOfCourses - $numCoursesAddedToMoodle));
	Output::printInfo('Total amount of categories that would be added to moodle: '. $numCategoriesAddedToMoodle);
	Output::printInfo('Total amount of courses that would be added to database: '. $numCoursesAddedToDB);
	Output::printInfo('Total amount of courses that would be updated in database: '. $numCoursesUpdatedDB);
}

Output::printInfo('----------------------------------------------------------------------');

Output::printInfo('Ended synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();

