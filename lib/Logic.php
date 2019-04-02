<?php

require_once('../../../config/global.config.inc.php');
require_once('../../../config/vilesci.config.inc.php');

require_once('../../../include/datum.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/student.class.php');
require_once('../../../include/studiengang.class.php');
require_once('../../../include/studiensemester.class.php');

require_once('Output.php');
require_once('MoodleAPI.php');
require_once('Database.php');

require_once('../config/config.php');

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
