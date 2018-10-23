<?php

require_once('Logic.php');

/**
 *
 */
class LogicUsers extends Logic
{
	// --------------------------------------------------------------------------------------------
    // Public Database wrappers methods

	/**
	 *
	 */
	public static function getDBMoodleCoursesIDsArray($currentOrNextStudiensemester)
	{
		$moodleCoursesIDsArray = array();

		$moodleCoursesIDs = parent::_dbCall(
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
		return parent::_moodleAPICall(
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
		$moodleEnrolledUsers = parent::_moodleAPICall(
			'core_enrol_get_enrolled_users',
			array($moodleCourseId),
			'An error occurred while retriving enrolled users from moodle'
		);

		self::_printDebugEmptyline();
		Output::printDebug('Number of enrolled users in moodle: '.count($moodleEnrolledUsers));
		self::_printDebugEmptyline();

		return $moodleEnrolledUsers;
	}

	// --------------------------------------------------------------------------------------------
    // Public business logic methods

	/**
	 *
	 */
	public static function getUsersToUnenrol($moodleCourseId, $moodleEnrolledUsers, $studiensemester_kurzbz)
	{
		$uidsToUnenrol = array();
		$allGroupsMembers = self::_getAllGroupsMembers($moodleCourseId, $studiensemester_kurzbz);

		if (is_array($allGroupsMembers))
		{
			foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
			{
				$userFound = false;

				foreach ($allGroupsMembers as $groupsMember)
				{
					//
					if ($moodleEnrolledUser->username == $groupsMember['uid'])
					{
						$userFound = true;
						unset($uidsToUnenrol[$moodleEnrolledUser->username]);
						break;
					}
				}

				if (!$userFound)
				{
					$uidsToUnenrol[$moodleEnrolledUser->username] = $moodleEnrolledUser->username;
				}
			}
		}

		return $uidsToUnenrol;
	}

	/**
	 *
	 */
	public static function synchronizeLektoren($moodleCourseId, $moodleEnrolledUsers, &$uidsToUnenrol)
	{
		$usersToEnroll = array(); //
		$employees = self::_getMitarbeiter($moodleCourseId); //

		Output::printDebug('Number of lectors in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
			unset($uidsToUnenrol[$employee->mitarbeiter_uid]); // Removes this user from the list of users to be unenrolled

			$debugMessage = 'Syncing lector '.$employee->mitarbeiter_uid.':"'.$employee->vorname.' '.$employee->nachname.'"';
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
				if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
				{
					//
					$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid);

					$usersToEnroll[] = array(
						'roleid' => ADDON_MOODLE_LEKTOREN_ROLEID,
						'userid' => $users[0]->id,
						'courseid' => $moodleCourseId
					);

					$debugMessage .= ' >> will be enrolled in moodle in a later step';
				}
				else
				{
					$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
				}
			}

			Output::printDebug($debugMessage);
		}

		//
		if (count($usersToEnroll) > 0)
		{
			self::_enrol_manual_enrol_users($usersToEnroll);

			Output::printDebug('Number of lectors enrolled in moodle: '.count($usersToEnroll));
		}

		self::_printDebugEmptyline();
	}

	/**
	 *
	 */
	public static function synchronizeFachbereichsleitung($moodleCourseId, $moodleEnrolledUsers, &$uidsToUnenrol)
	{
		$usersToEnroll = array(); //
		$employees = self::_getFachbereichsleitung($moodleCourseId); //

		Output::printDebug('Number of members of management staff in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
			unset($uidsToUnenrol[$employee->mitarbeiter_uid]); // Removes this user from the list of users to be unenrolled
			$debugMessage = 'Syncing management staff member '.$employee->mitarbeiter_uid.':"'.$employee->vorname.' '.$employee->nachname.'"';
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
				if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
				{
					//
					$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid);

					$usersToEnroll[] = array(
						'roleid' => ADDON_MOODLE_FACHBEREICHSLEITUNG_ROLEID,
						'userid' => $users[0]->id,
						'courseid' => $moodleCourseId
					);

					$debugMessage .= ' >> will be enrolled in moodle in a later step';
				}
				else
				{
					$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
				}
			}

			Output::printDebug($debugMessage);
		}

		//
		if (count($usersToEnroll) > 0)
		{
			self::_enrol_manual_enrol_users($usersToEnroll);

			Output::printDebug('Number of management staff members enrolled in moodle: '.count($usersToEnroll));
		}

		self::_printDebugEmptyline();
	}

	/**
	 *
	 */
	public static function synchronizeStudenten($moodleCourseId, $moodleEnrolledUsers, &$uidsToUnenrol)
	{
		//
		$lehreinheiten = self::_getLehreinheiten($moodleCourseId);

		Output::printDebug('Number of teaching units in database: '.Database::rowsNumber($lehreinheiten));
		self::_printDebugEmptyline();

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

			Output::printDebug('Syncing teaching unit '.$lehreinheit->studiengang_kz.'-'.$groupName);
			Output::printDebug('Number of students in database: '.Database::rowsNumber($studenten));

			$usersToEnroll = array(); //
			$groupsMembersToAdd = array(); //

			//
			while ($student = Database::fetchRow($studenten))
			{
				unset($uidsToUnenrol[$student->student_uid]); // Removes this user from the list of users to be unenrolled
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
					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
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
					else
					{
						$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
					}
				}

				//
				if ($synchronizeGroup)
				{
					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
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
					else
					{
						$debugMessage .= ' >> dry run >> should be added to moodle group '.$groupName.' in a later step';
					}
				}

				Output::printDebug($debugMessage);
			}

			//
			if (count($usersToEnroll) > 0)
			{
				self::_enrol_manual_enrol_users($usersToEnroll);

				Output::printDebug('Number of students enrolled in moodle: '.count($usersToEnroll));
			}

			//
			if (count($groupsMembersToAdd) > 0)
			{
				self::_core_group_add_group_members($groupsMembersToAdd);

				self::_printDebugEmptyline();
				Output::printDebug('Number of students added to a moodle group: '.count($groupsMembersToAdd));
			}

			self::_printDebugEmptyline();
		}
	}

	/**
	 *
	 */
	public static function synchronizeGroupsMembers($moodleCourseId, $moodleEnrolledUsers, $studiensemester_kurzbz)
	{
		$courseGroups = self::_getCourseGroups($moodleCourseId, $studiensemester_kurzbz); //

		Output::printDebug('Number of groups in database: '.Database::rowsNumber($courseGroups));
		self::_printDebugEmptyline();

		//
		while ($courseGroup = Database::fetchRow($courseGroups))
		{
			$usersToEnroll = array(); //

			Output::printDebug('Syncing group '.$courseGroup->gruppe_kurzbz);

			$groupMembers = self::_getGroupsMembers($studiensemester_kurzbz, $courseGroup->gruppe_kurzbz); //

			Output::printDebug('Number of groups members in database: '.Database::rowsNumber($groupMembers));

			//
			while ($groupMember = Database::fetchRow($groupMembers))
			{
				$debugMessage = 'Syncing group member '.$groupMember->uid.':"'.$groupMember->vorname.' '.$groupMember->nachname.'"';
				$userFound = false; //

				//
				foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
				{
					//
					if ($groupMember->uid == $moodleEnrolledUser->username)
					{
						$debugMessage .= ' >> already enrolled in moodle';
						$userFound = true;
						break;
					}
				}

				//
				if (!$userFound)
				{
					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						//
						$users = self::_getOrCreateMoodleUser($groupMember->uid);

						//
						$usersToEnroll[] = array(
							'roleid' => ADDON_MOODLE_STUDENT_ROLEID,
							'userid' => $users[0]->id,
							'courseid' => $moodleCourseId
						);

						$debugMessage .= ' >> will be enrolled in moodle in a later step';
					}
					else
					{
						$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
					}
				}

				Output::printDebug($debugMessage);
			}

			//
			if (count($usersToEnroll) > 0)
			{
				self::_enrol_manual_enrol_users($usersToEnroll);

				Output::printDebug('Number of group members enrolled in moodle: '.count($usersToEnroll));
			}

			self::_printDebugEmptyline();
		}
	}

	/**
	 *
	 */
	public static function unenrolUsers($moodleCourseId, $uidsToUnenrol)
	{
		$usersToUnenrol = array(); //

		Output::printDebug('Number of group members to be unenrolled in database: '.count($uidsToUnenrol));

		//
		foreach ($uidsToUnenrol as $uidToUnenrol)
		{
			//
			$users = self::_fhcomplete_user_get_users($uidToUnenrol);
			if (count($users) > 0) //
			{
				//
				$usersToUnenrol[] = array(
					'userid' => $users[0]->id,
					'courseid' => $moodleCourseId
				);

				Output::printDebug(
					'Group member '.$uidToUnenrol.':"'.$users[0]->firstname.' '.$users[0]->lastname.
					'" >> will be unenrolled from moodle in a later step'
				);
			}
		}

		if (count($usersToUnenrol) > 0)
		{
			self::_enrol_manual_unenrol_users($usersToUnenrol);

			Output::printDebug('Number of group members unenrolled from moodle: '.count($usersToUnenrol));
		}

		self::_printDebugEmptyline();
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

			Output::printDebug('User '.$uid.' does not exist, it has been created');
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
	private static function _getMitarbeiter($moodleCourseId)
	{
		return parent::_dbCall(
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
		return parent::_dbCall(
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
		return parent::_dbCall(
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
		return parent::_dbCall(
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
		return parent::_dbCall(
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
		return parent::_dbCall(
			'getSpezialGruppe',
			array($gruppe_kurzbz, $studiensemester_kurzbz),
			'An error occurred while retriving the spezial gruppe'
		);
	}

	/**
	 *
	 */
	private static function _getAllGroupsMembers($moodleCourseId, $studiensemester_kurzbz)
	{
		$allGroupsMembers = parent::_dbCall(
			'getAllGroupsMembers',
			array($moodleCourseId, $studiensemester_kurzbz),
			'An error occurred while retriving all the groups members'
		);

		return Database::fetchAll($allGroupsMembers);
	}

	/**
	 *
	 */
	private static function _getCourseGroups($moodleCourseId, $studiensemester_kurzbz)
	{
		return parent::_dbCall(
			'getCourseGroups',
			array($moodleCourseId, $studiensemester_kurzbz),
			'An error occurred while retriving course groups'
		);
	}

	/**
	 *
	 */
	private static function _getGroupsMembers($studiensemester_kurzbz, $gruppe_kurzbz)
	{
		return parent::_dbCall(
			'getGroupsMembers',
			array($studiensemester_kurzbz, $gruppe_kurzbz),
			'An error occurred while retriving groups members'
		);
	}

	// --------------------------------------------------------------------------------------------
	// Private MoodleAPI wrappers methods

	/**
	 *
	 */
	private static function _fhcomplete_user_get_users($uid)
	{
		$users = parent::_moodleAPICall(
			'fhcomplete_user_get_users',
			array($uid),
			'An error occurred while retriving users info from moodle'
		);

		return $users->users;
	}

	/**
	 *
	 */
	private static function _enrol_manual_enrol_users($users)
	{
		return parent::_moodleAPICall(
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
		return parent::_moodleAPICall(
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
		return parent::_moodleAPICall(
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
		$groups = parent::_moodleAPICall(
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
		return parent::_moodleAPICall(
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
		$groups = parent::_moodleAPICall(
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
		return parent::_moodleAPICall(
			'core_group_add_group_members',
			array($members),
			'An error occurred while adding members to a moodle group'
		);
	}

	/**
	 *
	 */
	private static function _enrol_manual_unenrol_users($users)
	{
		return parent::_moodleAPICall(
			'enrol_manual_unenrol_users',
			array($users),
			'An error occurred while removing enrolled users in moodle'
		);
	}
}
