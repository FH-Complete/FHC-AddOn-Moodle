<?php

require_once(dirname(__FILE__).'/../../../config/global.config.inc.php');

// Could be loaded by a CIS page so its not needed to load vilesci configs
if (!defined('DB_SYSTEM')) require_once(dirname(__FILE__).'/../../../config/vilesci.config.inc.php');

require_once(dirname(__FILE__).'/../../../include/datum.class.php');
require_once(dirname(__FILE__).'/../../../include/benutzerberechtigung.class.php');
require_once(dirname(__FILE__).'/../../../include/student.class.php');
require_once(dirname(__FILE__).'/../../../include/studiengang.class.php');
require_once(dirname(__FILE__).'/../../../include/studiensemester.class.php');

require_once('Output.php');
require_once('MoodleAPI.php');
require_once('Database.php');

require_once(dirname(__FILE__).'/../config/config.php');

/**
 *
 */
abstract class Logic
{
	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public static function isExecutionAllowed()
	{
		if (!self::_commandLine() && !self::_isAdmin())
		{
			Output::printError('To call this script from browser you need "admin" permission!');
			die();
		}
	}

	/**
	 *
	 */
	public static function getCurrentOrNextStudiensemester()
	{
		$studiensemester = new studiensemester();

		$currentOrNextStudiensemester = $studiensemester->getAktOrNext();
		if (!$currentOrNextStudiensemester)
		{
			Output::printError('An error occurred while retrieving the current or the next studiensemester');
 			die();
		}

		return $currentOrNextStudiensemester;
	}

	/**
	 *
	 */
	public static function getNearestStudiensemester()
	{
		$studiensemester = new studiensemester();

		$NearestStudiensemester = $studiensemester->getNearest();
		if (!$NearestStudiensemester)
		{
			Output::printError('An error occurred while retrieving the nearest studiensemester');
 			die();
		}

		return $NearestStudiensemester;
	}

	/**
	 *
	 */
	public static function setEndDateEnabled()
	{
		return self::_getMoodleVersion() >= ADDON_MOODLE_VERSION_SET_END_DATE;
	}

	/**
	 * Generates the parameter enddate for all courses
	 */
	public static function getEndDate($studiensemester)
	{
		$endDate = null;

		if (self::setEndDateEnabled())
		{
			$datum = new Datum();

			$endDate = $datum->mktime_fromdate($studiensemester->ende);
		}

		return $endDate;
	}

	/**
	 * Generates the parameter startdate for all courses
	 */
	public static function getStartDate($studiensemester)
	{
		$datum = new Datum();

		return $datum->mktime_fromdate($studiensemester->start);
	}

	/**
	 * Generates the parameter courseformatoptions for all courses
	 */
	public static function getCourseFormatOptions()
	{
		$courseFormatOptions = null;
		if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
		{
			$numsectionsOptions = new stdClass();
			$numsectionsOptions->name = ADDON_MOODLE_NUMSECTIONS_NAME;
			$numsectionsOptions->value = ADDON_MOODLE_NUMSECTIONS_VALUE;
			$courseFormatOptions = array($numsectionsOptions);
		}

		return $courseFormatOptions;
	}

	/**
	 *
	 */
	public static function loadCourseGrades($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$courseGrades = array();
		$courses = self::getCoursesByLehrveranstaltungStudiensemester($lehrveranstaltung_id, $studiensemester_kurzbz);

		while ($course = Database::fetchRow($courses))
		{
			$grades = self::fhcomplete_get_course_grades($course->mdl_course_id, (CIS_GESAMTNOTE_PUNKTE ? 2 : 3));
			foreach ($grades as $grade)
			{
				if ($grade->note != '-')
				{
					$grade->mdl_course_id = $course->mdl_course_id;
					$courseGrades[] = $grade;
				}
			}
		}

		return $courseGrades;
	}

	/**
	 *
	 */
	public static function getOrCreateCategory($name, $parent, &$numCategoriesAddedToMoodle)
	{
		// Department
		$categories = self::core_course_get_categories_by_name_parent($name, $parent);
		if (count($categories) == 0)
		{
			$categories = self::core_course_create_categories($name, $parent);
			$numCategoriesAddedToMoodle++;
		}

		return $categories[0]->id;
	}

	// --------------------------------------------------------------------------------------------
    // Public Database wrappers methods

	/**
	 *
	 */
	public static function convertHtmlChars($string)
	{
		$database = new Database();

		return $database->convert_html_chars($string);
	}

	/**
	 *
	 */
	public static function getDBMoodleCoursesIDsArray($currentOrNextStudiensemester)
	{
		$moodleCoursesIDsArray = array();

		$moodleCoursesIDs = self::_dbCall(
			'getMoodleCoursesIDs',
			array($currentOrNextStudiensemester),
			'An error occurred while retrieving the moodle courses'
		);

		//
		while ($moodleCoursesID = Database::fetchRow($moodleCoursesIDs))
		{
			$moodleCoursesIDsArray[] = $moodleCoursesID->id;
		}

		return $moodleCoursesIDsArray;
	}

	/**
	 * Retrieves all the courses from database to be synchronized with moodle using lehreinheit
	 */
	public static function getCoursesFromLehreinheit($studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesFromLehreinheit',
			array($studiensemester_kurzbz),
			'An error occurred while retrieving courses from lehreinheit'
		);
	}

	/**
	 * Retrieves all the courses from database to be synchronized with moodle using table addon.tbl_moodle
	 */
	public static function getCoursesFromTblMoodle($studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesFromTblMoodle',
			array($studiensemester_kurzbz),
			'An error occurred while retrieving courses from addon.tbl_moodle'
		);
	}

	/**
	 *
	 */
	public static function getDBCoursesByIDs($moodleCourseId)
	{
		return self::_dbCall(
			'getCoursesByIDs',
			array($moodleCourseId),
			'An error occurred while retrieving courses from addon.tbl_moodle by ids'
		);
	}

	/**
	 *
	 */
	public static function getCourseByMoodleId($moodleId)
	{
		return self::_dbCall(
			'getCoursesByMoodleId',
			array($moodleId),
			'An error occurred while retrieving courses from addon.tbl_moodle by its primary key'
		);
	}

	/**
	 *
	 */
	public static function getDBCoursesByStudiengangStudiensemester($studiengang_kz, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesByStudiengangStudiensemester',
			array($studiengang_kz, $studiensemester_kurzbz),
			'An error occurred while retrieving courses from addon.tbl_moodle by studiengang and studiensemester'
		);
	}

	/**
	 *
	 */
	public static function insertMoodleTable(
		$moodleCourseId, $lehreinheit_id, $lehrveranstaltung_id, $studiensemester_kurzbz,
		$insertamum = 'NOW()', $insertvon = ADDON_MOODLE_INSERTVON, $gruppen = false, $gruppe_kurzbz = null
	)
	{
		return self::_dbCall(
			'insertMoodleTable',
			array(
				$moodleCourseId, $lehreinheit_id, $lehrveranstaltung_id, $studiensemester_kurzbz,
				$insertamum, $insertvon, $gruppen, $gruppe_kurzbz
			),
			'An error occurred while inserting into table addon.tbl_moodle'
		);
	}

	/**
	 *
	 */
	public static function deleteDBCourseByMoodleId($moodleId)
	{
		return self::_dbCall(
			'deleteCourseByMoodleId',
			array($moodleId),
			'An error occurred while deleting a course from addon.tbl_moodle by its primary key'
		);
	}

	/**
	 *
	 */
	public static function deleteDBCoursesByMoodleCourseId($moodleCourseId)
	{
		return self::_dbCall(
			'deleteCoursesByMoodleCourseId',
			array($moodleCourseId),
			'An error occurred while deleting courses from addon.tbl_moodle using a moodle course id'
		);
	}

	/**
	 *
	 */
	public static function searchPerson($searchString)
	{
		return self::_dbCall(
			'searchPerson',
			array($searchString),
			'An error occurred while searching for a person'
		);
	}

	/**
	 *
	 */
	public static function getCoursesByLehrveranstaltungStudiensemester($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesByLehrveranstaltungStudiensemester',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while retrieving courses by lehrveranstaltung and studiensemester'
		);
	}

	/**
	 *
	 */
	public static function getLeFromCourse($moodleCourseId)
	{
		return self::_dbCall(
			'getLeFromCourse',
			array($moodleCourseId),
			'An error occurred while retrieving lehrveranstaltung from a course'
		);
	}

	/**
	 *
	 */
	public static function getCoursesByLehrveranstaltungLehreinheit($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesByLehrveranstaltungLehreinheit',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while retrieving courses by lehrveranstaltung and lehreinheit studiensemester'
		);
	}

	/**
	 *
	 */
	public static function getCoursesByLehrveranstaltungLehreinheitNoDistinct($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getCoursesByLehrveranstaltungLehreinheitNoDistinct',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while retrieving courses by lehrveranstaltung and lehreinheit studiensemester'
		);
	}

	/**
	 *
	 */
	public static function getCoursesByStudent($lehrveranstaltung_id, $studiensemester_kurzbz, $uid)
	{
		return self::_dbCall(
			'getCoursesByStudent',
			array($lehrveranstaltung_id, $studiensemester_kurzbz, $uid),
			'An error occurred while retrieving courses by student'
		);
	}

	/**
	 *
	 */
	public static function updateGruppen($moodle_id, $gruppen)
	{
		return self::_dbCall(
			'updateGruppen',
			array($moodle_id, $gruppen),
			'An error occurred while updating the field gruppen'
		);
	}

	/**
	 *
	 */
	public static function coursesLehrveranstaltungStudiensemesterExists($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'coursesLehrveranstaltungStudiensemesterExists',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while counting number of courses by lehrveranstaltung and studiensemester'
		);
	}

	/**
	 *
	 */
	public static function coursesMdlCourseIDForLehrveranstaltungStudiensemesterExists($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'coursesMdlCourseIDForLehrveranstaltungStudiensemesterExists',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while counting number of courses by lehrveranstaltung and studiensemester'
		);
	}

	/**
	 *
	 */
	public static function coursesAllLehreinheitStudiensemesterExists($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'coursesAllLehreinheitStudiensemesterExists',
			array($lehrveranstaltung_id, $studiensemester_kurzbz),
			'An error occurred while counting number of courses by all lehreinheit and studiensemester'
		);
	}

	/**
	 *
	 */
	public static function coursesLehreinheitExists($lehreinheit_id)
	{
		return self::_dbCall(
			'coursesLehreinheitExists',
			array($lehreinheit_id),
			'An error occurred while counting number of courses by lehreinheit'
		);
	}

	/**
	 *
	 */
	public static function coursesMdlCourseIDExists($lehreinheit_id)
	{
		return self::_dbCall(
			'coursesMdlCourseIDExists',
			array($lehreinheit_id),
			'An error occurred while counting number of courses by lehreinheit'
		);
	}

	/**
	 *
	 */
	public static function getTestCourses($lehrveranstaltung_id, $studiensemester_kurzbz, $prefix = 'TK')
	{
		return self::_dbCall(
			'getTestCourses',
			array($lehrveranstaltung_id, $studiensemester_kurzbz, $prefix),
			'An error occurred while retrieving test courses'
		);
	}

	public static function getCoursesByLehreinheit($lehreinheit_id)
	{
		return self::_dbCall(
			'getCoursesByLehreinheit',
			array($lehreinheit_id),
			'An error occurred while retrieving test courses'
		);
	}

	// --------------------------------------------------------------------------------------------
    // Public MoodleAPI wrappers methods

	/**
	 *
	 */
	public static function getBaseURL()
	{
		$moodleAPI = new MoodleAPI();

		return $moodleAPI->getBaseURL();
	}

	/**
	 *
	 */
	public static function core_course_get_courses($moodleCoursesIDsArray)
	{
		return self::_moodleAPICall(
			'core_course_get_courses',
			array($moodleCoursesIDsArray),
			'An error occurred while retrieving courses from moodle'
		);
	}

	/**
	 *
	 */
	public static function core_enrol_get_enrolled_users($moodleCourseId)
	{
		$moodleEnrolledUsers = self::_moodleAPICall(
			'core_enrol_get_enrolled_users',
			array($moodleCourseId),
			'An error occurred while retrieving enrolled users from moodle >> course id '.$moodleCourseId
		);

		self::_printDebugEmptyline();
		Output::printDebug('Number of enrolled users in moodle: '.count($moodleEnrolledUsers));
		self::_printDebugEmptyline();

		return $moodleEnrolledUsers;
	}

	/**
	 *
	 */
	public static function core_course_delete_courses($moodleCoursesIDsArray)
	{
		return self::_moodleAPICall(
			'core_course_delete_courses',
			array($moodleCoursesIDsArray),
			'An error occurred while deleting courses from moodle'
		);
	}

	/**
	 *
	 */
	public static function core_user_get_users_by_field($uid)
	{
		$users = self::_moodleAPICall(
			'core_user_get_users_by_field',
			array($uid),
			'An error occurred while retrieving users info from moodle'
		);

		return $users;
	}

	/**
	 *
	 */
	public static function core_course_create_courses(
		$fullname, $shortname, $categoryId, $startDate, $format = 'topics', $courseFormatOptions = null, $endDate = null
	)
	{
		$courses = self::_moodleAPICall(
			'core_course_create_courses',
			array($fullname, $shortname, $categoryId, $format, $courseFormatOptions, $startDate, $endDate),
			'An error occurred while creating a new course in moodle'
		);

		return $courses[0]->id;
	}

	/**
	 *
	 */
	public static function core_course_get_categories_by_name_parent($name, $parent)
	{
		return self::_moodleAPICall(
			'core_course_get_categories_by_name_parent',
			array($name, $parent),
			'An error occurred while retrieving categories from moodle'
		);
	}

	/**
	 *
	 */
	public static function core_course_create_categories($name, $parent)
	{
		return self::_moodleAPICall(
			'core_course_create_categories',
			array($name, $parent),
			'An error occurred while creating a category in moodle'
		);
	}

	/**
	 *
	 */
	public static function fhcomplete_get_course_grades($moodleCoursesId, $type)
	{
		return self::_moodleAPICall(
			'fhcomplete_get_course_grades',
			array($moodleCoursesId, $type),
			'Please update grades in this moodle course'
		);
	}

	/**
	 *
	 */
	public static function getCourseByShortname($shortname)
	{
		$courses = self::_moodleAPICall(
			'core_course_get_courses_by_field',
			array('shortname', $shortname),
			'An error occurred while retrieving a course from moodle by shortname'
		);

		if (count($courses->courses) == 0) return null;

		return $courses->courses[0];
	}

	/**
	 * Studiensemester can be passed as commandline option
	 * ex: php synchronizeCategories.php --stsem WS2019
	 */
	public static function getCliOrCurrentOrNextStudiensemester()
	{
		$currentOrNextStudiensemester = null;
		$commandlineparams = getopt('', array("stsem:"));

		if (isset($commandlineparams['stsem']))
		{
			$currentOrNextStudiensemester = $commandlineparams['stsem'];
		}
		else
		{
			// Retrieves the current studiensemester
			$currentOrNextStudiensemester = LogicUsers::getCurrentOrNextStudiensemester();
		}

		return $currentOrNextStudiensemester;
	}

	// --------------------------------------------------------------------------------------------
    // Protected methods

	/**
	 *
	 */
	protected static function _printDebugEmptyline()
	{
		Output::printDebug('');
	}

	// --------------------------------------------------------------------------------------------
	// Protected Database wrappers methods

	/**
	 *
	 */
	protected static function _dbCall($method, $parameters, $message)
	{
		$result = null;
		$database = new Database();

		if (method_exists($database, $method))
		{
			$result = call_user_func_array(array($database, $method), $parameters);
			if ($result == null)
	 		{
	 			Output::printError('Database: '.$message);
				Output::varDumpParameters($parameters);
	 			die();
	 		}
		}

		return $result;
	}

	// --------------------------------------------------------------------------------------------
	// Protected MoodleAPI wrappers methods

	/**
	 *
	 */
	protected static function _moodleAPICall($method, $parameters, $message)
	{
		$result = null;
		$moodleAPI = new MoodleAPI();

		if (method_exists($moodleAPI, $method))
		{
			$result = call_user_func_array(array($moodleAPI, $method), $parameters);
			if ($moodleAPI->isError())
	 		{
	 			Output::printError('MoodleAPI: '.$message.': '.$moodleAPI->getError());
				Output::varDumpParameters($parameters);
	 			die();
	 		}
		}

		return $result;
	}

	/**
	 *
	 */
	private static function _getMoodleVersion()
	{
		$info = self::_moodleAPICall(
			'core_webservice_get_site_info',
			array(),
			'An error occurred while retrieving moodle infos'
		);

		$release = $info->release;

		return substr($release, 0, ADDON_MOODLE_VERSION_LENGTH);
	}

	// --------------------------------------------------------------------------------------------
    // Private methods

	/**
	 *
	 */
	private static function _commandLine()
	{
		return php_sapi_name() == 'cli';
	}

	/**
	 *
	 */
	private static function _isAdmin()
	{
		$isAdmin = false;
		$benutzerberechtigung = new benutzerberechtigung();
		$benutzerberechtigung->getBerechtigungen(get_uid());

		if ($benutzerberechtigung->isBerechtigt('admin'))
		{
			$isAdmin = true;
		}

		return $isAdmin;
	}
}
