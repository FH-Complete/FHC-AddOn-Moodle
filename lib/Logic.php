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
				var_dump($parameters);
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
				var_dump($parameters);
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
