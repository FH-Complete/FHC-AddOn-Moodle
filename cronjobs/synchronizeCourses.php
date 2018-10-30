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

// Retrives the courses to be synchronized from database (addon.tbl_moodle)
$courses = LogicCourses::getCourses($currentOrNextStudiensemester);

$numberOfCourses = Database::rowsNumber($courses);
$coursesCounter = 0;

Output::printInfo('Number of courses in the database: '.$numberOfCourses);

//
while ($course = Database::fetchRow($courses))
{
	$coursesCounter++;

	$studiengang = new studiengang();
	$studiengang->load($course->studiengang_kz);

	$shortname = $studiengang->kuerzel.($course->orgform_kurzbz != '' ? '-'.$course->orgform_kurzbz : '').
		($course->semester != '' ? '-'.$course->semester : '').
		'-'.$currentOrNextStudiensemester.'-'.$course->kurzbz.'-'.$course->lehreinheit_id.'-'.$course->lektoren;

	$fullname = $studiengang->kuerzel.($course->orgform_kurzbz != '' ? '-'.$course->orgform_kurzbz : '').
		($course->semester != '' ? '-'.$course->semester : '').
		'-'.$currentOrNextStudiensemester.'-'.$course->bezeichnung.'-'.$course->lehreinheit_id.'-'.$course->lektoren;

	// Fix the length
	$shortname = mb_substr($shortname, 0, 254);
	$fullname = mb_substr($fullname, 0, 254);

	Output::printDebug($shortname);
	Output::printDebug($fullname);
	Output::printDebug('------------------------------------------------------------------------------------');

	$studiensemester = new studiensemester();
	$datum = new Datum();
	$categoryId = null;

	// Build categories tree
	if (ADDON_MOODLE_COURSE_SCHEMA == 'DEP-STG-JG-STSEM')
	{
		// Department -> Studiengang -> Jahrgang -> Studiensemester
		// (Ex. Informationstechnologie und Informationsmanagement -> BIMK -> Jahrgang 2014 -> WS2014)

		// Department category
		$departmentId = LogicCourses::getOrCreateCategory($course->bezeichnung, ADDON_MOODLE_ROOT_CATEGORY_ID);

		Output::printDebug('Department category '.$course->bezeichnung.'-'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$departmentId);

		// Studiengang category
		$studiengangId = LogicCourses::getOrCreateCategory($course->studiengang, $departmentId);

		Output::printDebug('Studiengang category '.$course->studiengang.'-'.$departmentId.' ID: '.$studiengangId);

		// Determine the semester jumping back one >> one to determine the year
		if ($course->semester != 0)
		{
			$studiensemesterYear = $studiensemester->jump($currentOrNextStudiensemester, ($course->semester - 1) * - 1);
			$studiensemester->load($studiensemesterYear);
		}
		else
		{
			$studiensemester->load($currentOrNextStudiensemester);
		}

		$jahrgang = $datum->formatDatum($studiensemester->start, 'Y');

		// Jahrgang category
		$jahrgangId = LogicCourses::getOrCreateCategory(ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang, $studiengangId);

		Output::printDebug('Jahrgang category '.ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang.'"-'.$jahrgangId.' ID: '.$jahrgangId);

		// Studiensemester category
		$categoryId = LogicCourses::getOrCreateCategory($currentOrNextStudiensemester, $jahrgangId);

		Output::printDebug('Course category '.$currentOrNextStudiensemester.'"-'.$jahrgangId.' ID: '.$categoryId);
	}
	else // Studiensemester -> Studiengang -> Ausbsemester (Ex. WS2014 -> BEL -> 1)
	{
		// Studiensemester category
		$studiensemesterId = LogicCourses::getOrCreateCategory($currentOrNextStudiensemester, ADDON_MOODLE_ROOT_CATEGORY_ID);

		Output::printDebug('Studiensemester category '.$currentOrNextStudiensemester.'"-'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$studiensemesterId);

		// Studiengang category
		$studiengangId = LogicCourses::getOrCreateCategory($course->studiengang, $studiensemesterId);

		Output::printDebug('Studiengang category '.$course->studiengang.'"-'.$studiensemesterId.' ID: '.$studiengangId);

		// Semester category
		$categoryId = LogicCourses::getOrCreateCategory($course->semester, $studiengangId);

		Output::printDebug('Course category '.$course->semester.'"-'.$studiengangId.' ID: '.$categoryId);
	}

	$studiensemester->load($currentOrNextStudiensemester);
	$startDate = $datum->mktime_fromdate($studiensemester->start);

	$courseFormatOptions = null;
	if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
	{
		$numsectionsOptions = new stdClass();
		$numsectionsOptions->name = ADDON_MOODLE_NUMSECTIONS_NAME;
		$numsectionsOptions->value = ADDON_MOODLE_NUMSECTIONS_VALUE;
		$courseFormatOptions = array($numsectionsOptions);
	}

	if (ADDON_MOODLE_SET_END_DATE === true)
	{
		$endDate = $datum->mktime_fromdate($studiensemester->ende);
	}

	$moodleCourseId = LogicCourses::core_course_create_courses(
		$fullname, $shortname, $categoryId, $startDate, ADDON_MOODLE_COURSE_FORMAT, $courseFormatOptions, $endDate
	);

	//
	if ($coursesCounter <= ($numberOfCourses / 2))
	{
		LogicCourses::insertMoodleTable($moodleCourseId, $course->lehreinheit_id, null, $currentOrNextStudiensemester);
	}
	else
	{
		LogicCourses::insertMoodleTable($moodleCourseId, null, $course->lehrveranstaltung_id, $currentOrNextStudiensemester);
	}
}

Output::printInfo('Ended synchronize courses script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
