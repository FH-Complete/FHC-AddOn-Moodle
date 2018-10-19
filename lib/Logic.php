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

class Logic
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
			Output::printError('An error occurred while retriving the current or the next studiensemester');
 			die();
		}

		return $currentOrNextStudiensemester;
	}

	// --------------------------------------------------------------------------------------------
    // Public Database wrappers methods

	/**
	 *
	 */
	public static function getDBMoodleCoursesIDsArray($currentOrNextStudiensemester)
	{
		$moodleCoursesIDsArray = array();

		$moodleCoursesIDs = self::_dbCall(
			'getMoodleCoursesIDs',
			array($currentOrNextStudiensemester),
			'An error occurred while retriving the moodle courses'
		);

		//
		while ($moodleCoursesID = Database::fetchRow($moodleCoursesIDs))
		{
			$moodleCoursesIDsArray[] = $moodleCoursesID->id;
		}

		return $moodleCoursesIDsArray;
	}

	// --------------------------------------------------------------------------------------------
    // Public MoodleAPI wrappers methods

	/**
	 *
	 */
	public static function core_course_get_courses($moodleCoursesIDsArray)
	{
		return self::_moodleAPICall(
			'core_course_get_courses',
			array($moodleCoursesIDsArray),
			'An error occurred while retriving courses from moodle'
		);
	}

	/**
	 *
	 */
	public static function core_enrol_get_enrolled_users($moodleCourseId)
	{
		return self::_moodleAPICall(
			'core_enrol_get_enrolled_users',
			array($moodleCourseId),
			'An error occurred while retriving enrolled users from moodle'
		);
	}

	// --------------------------------------------------------------------------------------------
    // Public business logic methods

	/**
	 *
	 */
	public static function synchronizeLektoren($moodleCourseId, $moodleEnrolledUsers)
	{
		//
		$employees = self::_getMitarbeiter($moodleCourseId);

		Output::printDebugI1('Number of lectors in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
			$debugMessage = 'Syncing lector "'.$employee->mitarbeiter_uid.'"'; //
			$userFound = false; //

			//
			foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
			{
				//
				if ($employee->mitarbeiter_uid == $moodleEnrolledUser->username)
				{
					$debugMessage .= ' >> already enrolled in moodle';
					$userFound = true;
					break;
				}
			}

			//
			if (!$userFound)
			{
				//
				$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid);

				//
				self::_enrol_manual_enrol_user(ADDON_MOODLE_LEKTOREN_ROLEID, $users[0]->id, $moodleCourseId);

				$debugMessage .= ' >> just enrolled in moodle';
			}

			Output::printDebugI1($debugMessage);
		}
	}

	/**
	 *
	 */
	public static function synchronizeFachbereichsleitung($moodleCourseId, $moodleEnrolledUsers)
	{
		//
		$employees = self::_getFachbereichsleitung($moodleCourseId);

		Output::printDebugI1('Number of members of management staff in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
			$debugMessage = 'Syncing management staff member "'.$employee->mitarbeiter_uid.'"'; //
			$userFound = false; //

			//
			foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
			{
				//
				if ($employee->mitarbeiter_uid == $moodleEnrolledUser->username)
				{
					$debugMessage .= ' >> already enrolled in moodle';
					$userFound = true;
					break;
				}
			}

			if (!$userFound)
			{
				//
				$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid);

				//
				self::_enrol_manual_enrol_user(ADDON_MOODLE_FACHBEREICHSLEITUNG_ROLEID, $users[0]->id, $moodleCourseId);

				$debugMessage .= ' >> just enrolled in moodle';
			}

			Output::printDebugI1($debugMessage);
		}
	}

	/**
	 *
	 */
	public static function synchronizeStudenten($moodleCourseId, $moodleEnrolledUsers)
	{
		//
		$lehreinheiten = self::_getLehreinheiten($moodleCourseId);

		Output::printDebugI1('Number of teaching units in database: '.Database::rowsNumber($lehreinheiten));
		Output::printDebugNewline();

		//
		while ($lehreinheit = Database::fetchRow($lehreinheiten))
		{
			//
			$synchronizeGroup = $lehreinheit->gruppen == 't';

			if ($lehreinheit->gruppe_kurzbz == '') // LVB Gruppe
			{
				$studenten = self::_getLVBGruppe(
					$lehreinheit->studiensemester_kurzbz, $lehreinheit->studiengang_kz,
					$lehreinheit->semester, $lehreinheit->verband, $lehreinheit->gruppe
				);

				$studiengang_obj = new studiengang();
				$studiengang_obj->load($lehreinheit->studiengang_kz);
				$groupName = $studiengang_obj->kuerzel.'-'.trim($lehreinheit->semester).trim($lehreinheit->verband).trim($lehreinheit->gruppe);
			}
			else // Spezialgruppe
			{
				$studenten = self::_getSpezialGruppe($lehreinheit->gruppe_kurzbz, $lehreinheit->studiensemester_kurzbz);

				$groupName = $lehreinheit->gruppe_kurzbz;
			}

			Output::printDebugI1('Syncing teaching unit '.$lehreinheit->studiengang_kz.'-'.$groupName);
			Output::printDebugI1('Number of students in database: '.Database::rowsNumber($studenten));

			$usersToEnroll = array(); //
			$groupsMembersToAdd = array(); //

			//
			while ($student = Database::fetchRow($studenten))
			{
				$debugMessage = 'Syncing student '.$student->student_uid.':"'.$student->vorname.' '.$student->nachname.'"';
				$userFound = false; //

				//
				foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
				{
					//
					if ($student->student_uid == $moodleEnrolledUser->username)
					{
						$debugMessage .= ' >> already enrolled in moodle';
						$userFound = true;
						break;
					}
				}

				//
				if (!$userFound)
				{
					//
					$users = self::_getOrCreateMoodleUser($student->student_uid);

					//
					$usersToEnroll[] = array(
						'roleid' => ADDON_MOODLE_STUDENT_ROLEID,
						'userid' => $users[0]->id,
						'courseid' => $moodleCourseId
					);

					$debugMessage .= ' >> will be enrolled in moodle in a later step';
				}

				//
				if ($synchronizeGroup)
				{
					//
					$group = self::_getOrCreateMoodleGroup($moodleCourseId, $groupName);

					//
					if (!self::_isMoodleUserMemberMoodleGroup($users[0]->id, $group->id))
					{
						$groupsMembersToAdd[] = array('groupid' => $group->id, 'userid' => $users[0]->id);

						$debugMessage .= ' >> will be added to moodle group '.$groupName.' in a later step';
					}
				}

				Output::printDebugI1($debugMessage);
			}

			//
			if (count($usersToEnroll) > 0)
			{
				self::_enrol_manual_enrol_users($usersToEnroll);

				Output::printDebugI1('Number of students enrolled in moodle: '.count($usersToEnroll));
			}

			//
			if (count($groupsMembersToAdd) > 0)
			{
				self::_core_group_add_group_members($groupsMembersToAdd);

				Output::printDebugI1('Number of students added to a moodle group: '.count($groupsMembersToAdd));
			}

			Output::printDebugNewline();
		}
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

	// --------------------------------------------------------------------------------------------
    // Private business logic methods

	/**
	 *
	 */
	private static function _getOrCreateMoodleGroup($moodleCourseId, $groupName)
	{
		//
		$group = self::_core_group_get_course_groups($moodleCourseId, $groupName);

		//
		if ($group == null)
		{
			//
			$groups = self::_core_group_create_groups($moodleCourseId, $groupName);
			$group = $groups[0]; //
		}

		return $group;
	}

	/**
	 *
	 */
	private static function _getOrCreateMoodleUser($uid)
	{
		$users = self::_fhcomplete_user_get_users($uid);

		// If not found
		if (is_array($users) && count($users) == 0)
		{
			$users = self::_createMoodleUser($uid);
		}

		return $users;
	}

	/**
	 *
	 */
	private static function _createMoodleUser($uid)
	{
		$users = null;

		// Then create user
		$benutzerRow = self::_getBenutzerByUID($uid);
		if ($benutzer = Database::fetchRow($benutzerRow))
		{
			$user = new stdClass();
			$user->username = $benutzer->uid;
			// Passwort muss gesetzt werden damit das Anlegen funktioniert.
			// Es wird ein random Passwort gesetzt
			// Dieses wird beim Login nicht verwendet da ueber ldap authentifiziert wird.
			// Prefix ist noetig damit es nicht zu Problemen kommt wenn
			// im Moodle die Passwort Policy aktiviert ist
			//
			// Wenn das Passwort uebergeben wird, dann versucht Moodle das auch
			// im LDAP zu setzen. Das fuehrt dazu dass der Account nicht mehr funktioniert.
			// Anlegen eines Users ohne Passwortuebergabe ist jedoch nicht moeglich-
			// Deshalb wird die Authentifizierungsmethode beim Anlegen auf manual
			// gesetzt und nach dem anlegen auf ldap geändert
			$user->password = "FHCv!A2".hash('sha512', rand());
			$user->firstname = $benutzer->vorname;
			$user->lastname = $benutzer->nachname;
			$user->email = $benutzer->uid.'@'.DOMAIN;
			$user->auth = 'manual';
			$user->idnumber = $benutzer->uid;
			$user->lang = ADDON_MOODLE_DEFAULT_LANGUAGE;

			// If activated in the configs (=== true), the student's personal identifier (matrikelnummer)
			// will be wrote in the CustomField pkz in Moodle
			if (ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN === true)
			{
				$student = new student();
				if ($student->load($benutzer->uid))
				{
					$pkz = new stdClass();
					$pkz->type = 'pkz';
					$pkz->value = $student->matrikelnr;

					$user->customfields = array($pkz);
				}
			}

			$users = self::_core_user_create_users($user);
			if (count($users) > 0)
			{
				$user = array();
				$user['id'] = $users[0]->id;
				$user['auth'] = 'ldap';

				self::_core_user_update_users($user);
			}
		}
		else
		{
			// benutzer not found
		}

		return $users;
	}

	// --------------------------------------------------------------------------------------------
	// Private Database wrappers methods

	/**
	 *
	 */
	private static function _dbCall($method, $parameters, $message)
	{
		$result = null;
		$database = new Database();

		if (method_exists($database, $method))
		{
			$result = call_user_func_array(array($database, $method), $parameters);
			if ($result == null)
	 		{
	 			Output::printError('Database: '.$message);
	 			die();
	 		}
		}

		return $result;
	}

	/**
	 *
	 */
	private static function _getMitarbeiter($moodleCourseId)
	{
		return self::_dbCall(
			'getMitarbeiter',
			array($moodleCourseId),
			'An error occurred while retriving the mitarbeiter'
		);
	}

	/**
	 *
	 */
	private static function _getBenutzerByUID($uid)
	{
		return self::_dbCall(
			'getBenutzerByUID',
			array($uid),
			'An error occurred while retriving the benutzer'
		);
	}

	/**
	 *
	 */
	private static function _getFachbereichsleitung($moodleCourseId)
	{
		return self::_dbCall(
			'getFachbereichsleitung',
			array($moodleCourseId),
			'An error occurred while retriving the fachbereichsleitung'
		);
	}

	/**
	 *
	 */
	private static function _getLehreinheiten($moodleCourseId)
	{
		return self::_dbCall(
			'getLehreinheiten',
			array($moodleCourseId),
			'An error occurred while retriving lehreinheiten'
		);
	}

	/**
	 *
	 */
	private static function _getLVBGruppe($studiensemester_kurzbz, $studiengang_kz, $semester, $verband, $gruppe)
	{
		return self::_dbCall(
			'getLVBGruppe',
			array($studiensemester_kurzbz, $studiengang_kz, $semester, $verband, $gruppe),
			'An error occurred while retriving the LVB gruppe'
		);
	}

	/**
	 *
	 */
	private static function _getSpezialGruppe($gruppe_kurzbz, $studiensemester_kurzbz)
	{
		return self::_dbCall(
			'getSpezialGruppe',
			array($gruppe_kurzbz, $studiensemester_kurzbz),
			'An error occurred while retriving the spezial gruppe'
		);
	}

	// --------------------------------------------------------------------------------------------
	// Private MoodleAPI wrappers methods

	/**
	 *
	 */
	private static function _moodleAPICall($method, $parameters, $message)
	{
		$result = null;
		$moodleAPI = new MoodleAPI();

		if (method_exists($moodleAPI, $method))
		{
			$result = call_user_func_array(array($moodleAPI, $method), $parameters);
			if ($moodleAPI->isError())
	 		{
	 			Output::printError('MoodleAPI: '.$message.': '.$moodleAPI->getError());
	 			die();
	 		}
		}

		return $result;
	}

	/**
	 *
	 */
	private static function _fhcomplete_user_get_users($uid)
	{
		$users = self::_moodleAPICall(
			'fhcomplete_user_get_users',
			array($uid),
			'An error occurred while retriving users info from moodle'
		);

		return $users->users;
	}

	/**
	 *
	 */
	private static function _enrol_manual_enrol_user($roleid, $moodleUserId, $moodleCourseId)
	{
		return self::_enrol_manual_enrol_users(
			array(
				array(
					'roleid' => $roleid,
					'userid' => $moodleUserId,
					'courseid' => $moodleCourseId
				)
			)
		);
	}

	/**
	 *
	 */
	private static function _enrol_manual_enrol_users($users)
	{
		return self::_moodleAPICall(
			'enrol_manual_enrol_users',
			array($users),
			'An error occurred while enrolling users in moodle'
		);
	}

	/**
	 *
	 */
	private static function _core_user_create_users($user)
	{
		return self::_moodleAPICall(
			'core_user_create_users',
			array($user),
			'An error occurred while creating a new user in moodle'
		);
	}

	/**
	 *
	 */
	private static function _core_user_update_users($user)
	{
		return self::_moodleAPICall(
			'core_user_update_users',
			array($user),
			'An error occurred while updating a new user in moodle'
		);
	}

	/**
	 *
	 */
	private static function _core_group_get_course_groups($moodleCourseId, $groupName)
	{
		$groups = self::_moodleAPICall(
			'core_group_get_course_groups',
			array($moodleCourseId),
			'An error occurred while retriving groups from moodle'
		);

		foreach ($groups as $group)
		{
			if ($group->name == $groupName)
			{
				return $group;
			}
		}

		return null;
	}

	/**
	 *
	 */
	private static function _core_group_create_groups($moodleCourseId, $groupName)
	{
		return self::_moodleAPICall(
			'core_group_create_groups',
			array($moodleCourseId, $groupName, $groupName),
			'An error occurred while creating a group in moodle'
		);
	}

	/**
	 *
	 */
	private function _isMoodleUserMemberMoodleGroup($moodleUserId, $groupId)
	{
		$groups = self::_moodleAPICall(
			'core_group_get_group_members',
			array($groupId),
			'An error occurred while retriving group members from moodle'
		);

		if (count($groups) > 0 && count($groups[0]['userids']) > 0)
		{
			foreach ($groups[0]['userids'] as $userId)
			{
				if ($userId == $moodleUserId)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 *
	 */
	private static function _core_group_add_group_members($members)
	{
		return self::_moodleAPICall(
			'core_group_add_group_members',
			array($members),
			'An error occurred while adding members to a moodle group'
		);
	}
}
