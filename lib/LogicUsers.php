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
			'An error occurred while retrieving the moodle courses'
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
	public static function core_enrol_get_enrolled_users($moodleCourseId)
	{
		$moodleEnrolledUsers = parent::_moodleAPICall(
			'core_enrol_get_enrolled_users',
			array($moodleCourseId),
			'An error occurred while retrieving enrolled users from moodle >> course id '.$moodleCourseId
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
	public static function vilesciIsAllowed()
	{
		$isMoodleUser = false;
		$benutzerberechtigung = new benutzerberechtigung();
		$benutzerberechtigung->getBerechtigungen(get_uid());

		if ($benutzerberechtigung->isBerechtigt('addon/moodle'))
		{
			$isMoodleUser = true;
		}

		return $isMoodleUser;
	}

	/**
	 *
	 */
	public static function synchronizeUsers($moodleCourses)
	{
		//
		$numCreatedUsers = 0;
		$numEnrolledLectors = 0;
		$numEnrolledManagementStaff = 0;
		$numEnrolledStudents = 0;
		$numCreatedGroups = 0;
		$numEnrolledGroupsMembers = 0;
		$numUnenrolledGroupsMembers = 0;
		$numEnrolledLeaders = 0;

		if (count($moodleCourses) > 0) Output::printDebug('------------------------------------------------------------');

		// Loops through the courses retrieved from moodle
		foreach ($moodleCourses as $moodleCourse)
		{
			$moodleCourseId = $moodleCourse->id;
			$moodleCourseDesc = ':'.$moodleCourse->shortname;

			Output::printDebug('>>> Syncing moodle course '.$moodleCourseId.$moodleCourseDesc.'" <<<');

			// Get all the enrolled users in this course from moodle
			$moodleEnrolledUsers = self::core_enrol_get_enrolled_users($moodleCourseId);

			// Tries to retrieve groups from DB for this moodle course
			$courseGroups = self::getCourseGroups($moodleCourseId); //

			// Retrieves a list of UIDs of users to be unenrolled selecting them by role
			$uidsToUnenrol = self::getUsersToUnenrol($moodleEnrolledUsers);

			// Checks if there are groups
			if (Database::rowsNumber($courseGroups) > 0)
			{
				// Synchronizes groups members
				self::synchronizeGroupsMembers(
					$moodleCourseId, $courseGroups, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledGroupsMembers
				);
			}
			else // otherwise
			{
				// Synchronizes lectors
				self::synchronizeLektoren(
					$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
				);

				// Synchronizes management staff
				if (ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG === true)
				{
					self::synchronizeFachbereichsleitung(
						$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledManagementStaff
					);
				}

				// Synchronizes students
				self::synchronizeStudenten(
					$moodleCourseId, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledStudents, $numCreatedGroups
				);

				// Synchronizes groups members
				self::synchronizeCompetenceFieldDepartmentLeaders(
					$moodleCourseId, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledLeaders
				);
			}

			// Unenrol users for this group
			self::unenrolUsers($moodleCourseId, $uidsToUnenrol, $numUnenrolledGroupsMembers);

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
			Output::printInfo('Total amount of leaders enrolled in moodle: '. $numEnrolledLeaders);
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
			Output::printInfo('Total amount of leaders that would be enrolled in moodle: '. $numEnrolledLeaders);
			Output::printInfo('Total amount of groups members that would be UNenrolled in moodle: '. $numUnenrolledGroupsMembers);
		}
		Output::printInfo('----------------------------------------------------------------------');
	}

	/**
	 *
	 */
	public static function vilesciSynchronize()
	{
		$moodleCoursesIDs = self::_getVilesciMoodleCoursesIDs();

		if ($moodleCoursesIDs == null) return;

		//
		if (count($moodleCoursesIDs) >= 1 && count($moodleCoursesIDs) <= ADDON_MOODLE_VILESCI_MAX_NUMBER_COURSES)
		{
			// Retrieves the courses from moodle using the course ids given as POST parameters
			$moodleCourses = LogicUsers::getMoodleCourses($moodleCoursesIDs);

			self::synchronizeUsers($moodleCourses);
		}
		else //
		{
			Output::printError('Maximum number of courses is '.ADDON_MOODLE_VILESCI_MAX_NUMBER_COURSES);
		}
	}

	/**
	 *
	 */
	public static function synchronizeCategories($moodleCourses)
	{
		//
		$numCreatedUsers = 0;
		$numAssignedLeaders = 0;
		$moodleParentCategoryIds = array();

		if (count($moodleCourses) > 0) Output::printDebug('------------------------------------------------------------');

		// Loops through the courses retrieved from moodle
		foreach ($moodleCourses as $moodleCourse)
		{
			$moodleCourseId = $moodleCourse->id; // moodle course id
			$moodleCourseDesc = ':'.$moodleCourse->shortname; // moodle course short name
			$moodleCourseCategoryId = -42; // moodle course category id

			//
			if (isset($moodleParentCategoryIds[$moodleCourse->categoryid]))
			{
				$moodleCourseCategoryId = $moodleParentCategoryIds[$moodleCourse->categoryid];
			}
			else
			{
				$category = self::_core_course_get_categories($moodleCourse->categoryid);

				if ($category != null) $moodleCourseCategoryId = $moodleParentCategoryIds[$moodleCourse->categoryid] = $category[0]->parent;
			}

			Output::printDebug('>>> Syncing '.$moodleCourseId.':'.$moodleCourseDesc.':'.$moodleCourseCategoryId.'" <<<');

			$usersToAssign = array(); // users to assign to the category

			$organisationUnits = self::_getOrganisationunitsDegree($moodleCourseId); // retrieves organisation units from DB for this degree

			Output::printDebug('Number of organisation units in database: '.Database::rowsNumber($organisationUnits));

			// Loops through organisation units
			while ($organisationUnit = Database::fetchRow($organisationUnits))
			{
				Output::printDebug('Current organisation unit: '.$organisationUnit->oe_kurzbz);

				$cLeaders = self::_getCourseleadersDelegatesAssistentsOE($organisationUnit->oe_kurzbz); // get category leaders for this organisation

				Output::printDebug('Number of category leaders in database for this organisation unit: '.Database::rowsNumber($cLeaders));

				// Loops through category leaders
				while ($cLeader = Database::fetchRow($cLeaders))
				{
					$debugMessage = 'Syncing category leader '.$cLeader->uid.':'.$cLeader->funktion_kurzbz.':"'.$cLeader->vorname.' '.$cLeader->nachname.'"';

					$users = self::_getOrCreateMoodleUser($cLeader->uid, $numCreatedUsers); // self-explanatory ;)

					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						$usersToAssign[] = array(
							'roleid' => ADDON_MOODLE_STUDIENGANGSLEITUNG_ROLEID,
							'userid' => $users[0]->id,
							'contextlevel' => 'coursecat',
							'instanceid' => $moodleCourseCategoryId
						);

						$debugMessage .= ' >> will be assigned to a category in moodle in a later step';
					}
					else
					{
						$debugMessage .= ' >> dry run >> should be assigned to a category in moodle in a later step';
					}

					$numAssignedLeaders++;

					Output::printDebug($debugMessage);
				}
			}

			//
			if (count($usersToAssign) > 0)
			{
				self::_core_role_assign_roles($usersToAssign);

				Output::printDebug('Number of leaders assigned to a category in moodle: '.count($usersToAssign));
			}

			self::_printDebugEmptyline();

			Output::printDebug('------------------------------------------------------------');
		}

		// Summary
		Output::printInfo('----------------------------------------------------------------------');
		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			Output::printInfo('Total amount of users created in moodle: '. $numCreatedUsers);
			Output::printInfo('Total amount of leaders assigned to a category in moodle: '. $numAssignedLeaders);
		}
		else
		{
			Output::printInfo('Total amount of users that would be created in moodle: '. $numCreatedUsers);
			Output::printInfo('Total amount of leaders that would be assigned to a category in moodle: '. $numAssignedLeaders);
		}
		Output::printInfo('----------------------------------------------------------------------');
	}

	/**
	 * Returns all the courses from moodle identified by a list of IDs given
	 * with the parameter $moodleCoursesIDsArray
	 * The call is divided by chunks, the size of these chunks is given
	 * by the config entry ADDON_MOODLE_POST_PARAMS_NUMBER
	 * This is because apache + php by default limits the number of post paraters
	 */
	public static function getMoodleCourses($moodleCoursesIDsArray)
	{
		$offset = 0; //
		$moodleCourses = array(); //
		$moodleCoursesIDsArraySlice = null;

		//
		while (count($moodleCoursesIDsArraySlice = array_slice($moodleCoursesIDsArray, $offset, ADDON_MOODLE_POST_PARAMS_NUMBER)) > 0)
		{
			// Retrieves a chunk of courses from moodle
			$tmpMoodleCourses = self::_core_course_get_courses($moodleCoursesIDsArraySlice);

			// Adds this chunk to array $moodleCourses
			$moodleCourses = array_merge($moodleCourses, $tmpMoodleCourses);

			// Increments the offset
			$offset += ADDON_MOODLE_POST_PARAMS_NUMBER;
		}

		return $moodleCourses;
	}

	/**
	 * Copies all the enrolled users for a moodle course to a new array
	 * which keys are the UIDs of these users
	 * These users will be eventually unenrolled later
	 */
	public static function getUsersToUnenrol($moodleEnrolledUsers)
	{
		$uidsToUnenrol = array();

		// Loops through all the enrolled users
		foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
		{
			$usersRoleFound = false; // user is still not found

			// Loops through all the user's roles
			foreach ($moodleEnrolledUser->roles as $role)
			{
				// If one of the user's roles is "student", "kompetenzfeldleitung" or "departmentleitung"
				if ($role->roleid == ADDON_MOODLE_STUDENT_ROLEID
					|| $role->roleid == ADDON_MOODLE_KOMPETENZFELDLEITUNG_ROLEID
					|| $role->roleid == ADDON_MOODLE_DEPARTMENTLEITUNG_ROLEID)
				{
					$usersRoleFound = true; // found!
					break; // quit from the loop
				}
			}

			// User was found
			if ($usersRoleFound)
			{
				// Save the user's uid into array uidsToUnenrol
				$uidsToUnenrol[$moodleEnrolledUser->username] = $moodleEnrolledUser->username;
			}
		}

		return $uidsToUnenrol;
	}

	/**
	 *
	 */
	public static function synchronizeLektoren(
		$moodleCourseId, $moodleEnrolledUsers, &$numCreatedUsers, &$numEnrolledLectors
	)
	{
		$usersToEnroll = array(); //
		$employees = self::_getCourseMitarbeiter($moodleCourseId); //

		Output::printDebug('Number of lectors in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
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
				$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid, $numCreatedUsers);

				if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
				{
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

				$numEnrolledLectors++;
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
	public static function synchronizeFachbereichsleitung(
		$moodleCourseId, $moodleEnrolledUsers, &$numCreatedUsers, &$numEnrolledManagementStaff
	)
	{
		$usersToEnroll = array(); //
		$employees = self::_getCourseFachbereichsleitung($moodleCourseId); //

		Output::printDebug('Number of members of management staff in database: '.Database::rowsNumber($employees));

		//
		while ($employee = Database::fetchRow($employees))
		{
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
				$users = self::_getOrCreateMoodleUser($employee->mitarbeiter_uid, $numCreatedUsers);

				if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
				{
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

				$numEnrolledManagementStaff++;
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
	public static function synchronizeStudenten(
		$moodleCourseId, $moodleEnrolledUsers, &$uidsToUnenrol, &$numCreatedUsers, &$numEnrolledStudents, &$numCreatedGroups
	)
	{
		//
		$lehreinheiten = self::_getCourseLehreinheiten($moodleCourseId);

		//
		$courseAngerechnet = self::_getCourseAngerechnet($moodleCourseId);

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
					$users = self::_getOrCreateMoodleUser($student->student_uid, $numCreatedUsers);

					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						//
						$roleId = ADDON_MOODLE_STUDENT_ROLEID;
						if (array_search($student->student_uid, $courseAngerechnet) !== false)
						{
							$roleId = ADDON_MOODLE_LV_ANGERECHNET_ROLEID;
						}

						//
						$usersToEnroll[] = array(
							'roleid' => $roleId,
							'userid' => $users[0]->id,
							'courseid' => $moodleCourseId
						);

						$debugMessage .= ' >> will be enrolled in moodle in a later step';
					}
					else
					{
						$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
					}

					$numEnrolledStudents++;

					//
					if ($synchronizeGroup)
					{
						if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
						{
							//
							$group = self::_getOrCreateMoodleGroup($moodleCourseId, $groupName, $numCreatedGroups);

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
	public static function synchronizeGroupsMembers(
		$moodleCourseId, $courseGroups, $moodleEnrolledUsers, &$uidsToUnenrol, &$numCreatedUsers, &$numEnrolledGroupsMembers
	)
	{
		Output::printDebug('Number of groups in database: '.Database::rowsNumber($courseGroups));
		self::_printDebugEmptyline();

		//
		while ($courseGroup = Database::fetchRow($courseGroups))
		{
			$usersToEnroll = array(); //

			Output::printDebug('Syncing group '.$courseGroup->gruppe_kurzbz);

			$groupMembers = self::_getGroupsMembers($courseGroup->gruppe_kurzbz); //

			Output::printDebug('Number of groups members in database: '.Database::rowsNumber($groupMembers));

			//
			while ($groupMember = Database::fetchRow($groupMembers))
			{
				unset($uidsToUnenrol[$groupMember->uid]); // Removes this user from the list of users to be unenrolled
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
					$users = self::_getOrCreateMoodleUser($groupMember->uid, $numCreatedUsers);

					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
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

					$numEnrolledGroupsMembers++;
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
	public static function synchronizeCompetenceFieldDepartmentLeaders(
		$moodleCourseId, $moodleEnrolledUsers, &$uidsToUnenrol, &$numCreatedUsers, &$numEnrolledLeaders
	)
	{
		$usersToEnroll = array(); //

		$organisationUnits = self::_getOrganisationunitsCourseUnit($moodleCourseId); //

		Output::printDebug('Number of organisation units in database: '.Database::rowsNumber($organisationUnits));

		//
		while ($organisationUnit = Database::fetchRow($organisationUnits))
		{
			$leaders = self::_getCompetenceFieldAndDeparmentLeadersOE($organisationUnit->oe_kurzbz); //

			Output::printDebug('Number of leaders in database for this organisation unit: '.Database::rowsNumber($leaders));

			//
			while ($leader = Database::fetchRow($leaders))
			{
				unset($uidsToUnenrol[$leader->uid]); // Removes this user from the list of users to be unenrolled
				$userFound = false; //

				$leaderDesc = '';
				if ($leader->organisationseinheittyp_kurzbz == ADDON_MOODLE_DEPARTMENT)
				{
					$leaderDesc = 'department';
				}
				elseif ($leader->organisationseinheittyp_kurzbz == ADDON_MOODLE_KOMPETENZFELD)
				{
					$leaderDesc = 'competence field';
				}

				$debugMessage = 'Syncing '.$leaderDesc.' leader '.$leader->uid.':"'.$leader->vorname.' '.$leader->nachname.'"';

				//
				foreach ($moodleEnrolledUsers as $moodleEnrolledUser)
				{
					//
					if ($leader->uid == $moodleEnrolledUser->username)
					{
						$debugMessage .= ' >> already enrolled in moodle';
						$userFound = true;
						break;
					}
				}

				//
				if (!$userFound)
				{
					$users = self::_getOrCreateMoodleUser($leader->uid, $numCreatedUsers);

					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						$roleid = -42;
						if ($leader->organisationseinheittyp_kurzbz == ADDON_MOODLE_DEPARTMENT)
						{
							$roleid = ADDON_MOODLE_DEPARTMENTLEITUNG_ROLEID;
						}
						elseif ($leader->organisationseinheittyp_kurzbz == ADDON_MOODLE_KOMPETENZFELD)
						{
							$roleid = ADDON_MOODLE_KOMPETENZFELDLEITUNG_ROLEID;
						}

						$usersToEnroll[] = array(
							'roleid' => $roleid,
							'userid' => $users[0]->id,
							'courseid' => $moodleCourseId
						);

						$debugMessage .= ' >> will be enrolled in moodle in a later step';
					}
					else
					{
						$debugMessage .= ' >> dry run >> should be enrolled in moodle in a later step';
					}

					$numEnrolledLeaders++;
				}

				Output::printDebug($debugMessage);
			}
		}

		//
		if (count($usersToEnroll) > 0)
		{
			self::_enrol_manual_enrol_users($usersToEnroll);

			Output::printDebug('Number of leaders enrolled in moodle: '.count($usersToEnroll));
		}

		self::_printDebugEmptyline();
	}

	/**
	 *
	 */
	public static function unenrolUsers($moodleCourseId, $uidsToUnenrol, &$numUnenrolledGroupsMembers)
	{
		$usersToUnenrol = array(); //

		Output::printDebug('Number of group members to be UNenrolled in database: '.count($uidsToUnenrol));

		//
		foreach ($uidsToUnenrol as $uidToUnenrol)
		{
			//
			$users = self::_core_user_get_users_by_field($uidToUnenrol);
			if (count($users) > 0) //
			{
				$debugMessage = 'Group member '.$uidToUnenrol.':"'.$users[0]->firstname.' '.$users[0]->lastname.'"';

				if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
				{
					//
					$usersToUnenrol[] = array(
						'userid' => $users[0]->id,
						'courseid' => $moodleCourseId
					);

					$debugMessage .= ' >> will be UNenrolled from moodle in a later step';
				}
				else
				{
					$debugMessage .= ' >> dry run >> should be UNenrolled from moodle in a later step';
				}

				Output::printDebug($debugMessage);

				$numUnenrolledGroupsMembers++;
			}
		}

		if (count($usersToUnenrol) > 0)
		{
			self::_enrol_manual_unenrol_users($usersToUnenrol);

			Output::printDebug('Number of group members UNenrolled from moodle: '.count($usersToUnenrol));
		}

		self::_printDebugEmptyline();
	}

	/**
	 *
	 */
	public static function getCourseGroups($moodleCourseId)
	{
		return parent::_dbCall(
			'getCourseGroups',
			array($moodleCourseId),
			'An error occurred while retrieving course groups'
		);
	}

	// --------------------------------------------------------------------------------------------
    // Private business logic methods

	/**
	 *
	 */
	private static function _getVilesciMoodleCoursesIDs()
	{
		$moodleCoursesIDs = null;

		//
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			$postMoodleCoursesIDs = null; //

			//
			if (isset($_POST['moodleCoursesIDs'])) $postMoodleCoursesIDs = $_POST['moodleCoursesIDs'];

			//
			if (is_string($postMoodleCoursesIDs)
				&& trim($postMoodleCoursesIDs) != ''
				&& mb_substr(trim($postMoodleCoursesIDs), 0, 1) != ADDON_MOODLE_VILESCI_COURSES_IDS_SEPARATOR)
			{
				$tmpMoodleCoursesIDs;

				//
				if (strstr($postMoodleCoursesIDs, ADDON_MOODLE_VILESCI_COURSES_IDS_SEPARATOR) === false)
				{
					$tmpMoodleCoursesIDs = array($postMoodleCoursesIDs);
				}
				else //
				{
					$tmpMoodleCoursesIDs = explode(ADDON_MOODLE_VILESCI_COURSES_IDS_SEPARATOR, $postMoodleCoursesIDs);
				}

				//
				if (is_array($tmpMoodleCoursesIDs) && count($tmpMoodleCoursesIDs) > 0)
				{
					$moodleCoursesIDs = array();

					//
					foreach ($tmpMoodleCoursesIDs as $tmpMoodleCoursesID)
					{
						if (trim($tmpMoodleCoursesID) == '' || !is_numeric($tmpMoodleCoursesID))
						{
							Output::printWarning('Not a valid course ID -> discarted: "'.$tmpMoodleCoursesID.'"');
						}
						else
						{
							$moodleCoursesIDs[] = $tmpMoodleCoursesID;
						}
					}
				}
			}
			else //
			{
				Output::printError('Nothing interesting was posted on this page');
			}
		}

		return $moodleCoursesIDs;
	}

	/**
	 *
	 */
	private static function _getOrCreateMoodleGroup($moodleCourseId, $groupName, &$numCreatedGroups)
	{
		//
		$group = self::_core_group_get_course_groups($moodleCourseId, $groupName);

		//
		if ($group == null)
		{
			//
			$groups = self::_core_group_create_groups($moodleCourseId, $groupName);
			$group = $groups[0]; //
			$numCreatedGroups++;
		}

		return $group;
	}

	/**
	 *
	 */
	private static function _getOrCreateMoodleUser($uid, &$numCreatedUsers)
	{
		$users = self::_core_user_get_users_by_field($uid);

		// If not found
		if (is_array($users) && count($users) == 0)
		{
			if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
			{
				$users = self::_createMoodleUser($uid);

				Output::printDebug('User '.$uid.' does not exist, it has been created');
			}
			else
			{
				Output::printDebug('User '.$uid.' does not exist, it would be created');
			}

			$numCreatedUsers++;
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
			$user->password = ADDON_MOODLE_USER_PWD_PREFIX.hash('sha512', rand());
			$user->firstname = $benutzer->vorname;
			$user->lastname = $benutzer->nachname;
			$user->email = $benutzer->uid.'@'.DOMAIN;
			$user->auth = ADDON_MOODLE_USER_MANUAL_AUTH;
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
					$pkz->type = ADDON_MOODLE_USER_PKZ_TYPE;
					$pkz->value = $student->matrikelnr;

					$user->customfields = array($pkz);
				}
			}

			$users = self::_core_user_create_users($user);
			if (count($users) > 0)
			{
				$user = array();
				$user['id'] = $users[0]->id;
				$user['auth'] = ADDON_MOODLE_USER_LDAP_AUTH;

				self::_core_user_update_users($user);
			}
		}
		else
		{
			// benutzer not found
		}

		return $users;
	}

	/**
	 *
	 */
	private static function _moodleAPICallChunks($arrayParameter, $apiName, $errorMessage)
	{
		$offset = 0; //
		$arraySlice = null;

		//
		while (count($arraySlice = array_slice($arrayParameter, $offset, ADDON_MOODLE_POST_PARAMS_NUMBER)) > 0)
		{
			// Enrols a chunk of users in a moodle course
			parent::_moodleAPICall($apiName, array($arraySlice), $errorMessage);

			// Increments the offset
			$offset += ADDON_MOODLE_POST_PARAMS_NUMBER;
		}
	}

	// --------------------------------------------------------------------------------------------
	// Private Database wrappers methods

	/**
	 *
	 */
	private static function _getCourseMitarbeiter($moodleCourseId)
	{
		return parent::_dbCall(
			'getCourseMitarbeiter',
			array($moodleCourseId),
			'An error occurred while retrieving the mitarbeiter'
		);
	}

	/**
	 *
	 */
	private static function _getOrganisationunitsCourseUnit($moodleCourseId)
	{
		return parent::_dbCall(
			'getOrganisationunitsCourseUnit',
			array($moodleCourseId),
			'An error occurred while retrieving organisation units for a moodle course from its course unit'
		);
	}

	/**
	 *
	 */
	private static function _getOrganisationunitsDegree($moodleCourseId)
	{
		return parent::_dbCall(
			'getOrganisationunitsDegree',
			array($moodleCourseId),
			'An error occurred while retrieving organisation units for a moodle course from its degree'
		);
	}

	/**
	 *
	 */
	private static function _getCompetenceFieldAndDeparmentLeadersOE($oe_kurzbz)
	{
		return parent::_dbCall(
			'getCompetenceFieldAndDeparmentLeadersOE',
			array($oe_kurzbz),
			'An error occurred while retrieving competence field and department leaders for an organisation unit'
		);
	}

	/**
	 *
	 */
	private static function _getCourseleadersDelegatesAssistentsOE($oe_kurzbz)
	{
		return parent::_dbCall(
			'getCourseleadersDelegatesAssistentsOE',
			array($oe_kurzbz),
			'An error occurred while retrieving course leaders, delegates and assistants for an organisation unit'
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
			'An error occurred while retrieving the benutzer'
		);
	}

	/**
	 *
	 */
	private static function _getCourseFachbereichsleitung($moodleCourseId)
	{
		return parent::_dbCall(
			'getCourseFachbereichsleitung',
			array($moodleCourseId),
			'An error occurred while retrieving the fachbereichsleitung'
		);
	}

	/**
	 *
	 */
	private static function _getCourseLehreinheiten($moodleCourseId)
	{
		return parent::_dbCall(
			'getCourseLehreinheiten',
			array($moodleCourseId),
			'An error occurred while retrieving lehreinheiten'
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
			'An error occurred while retrieving the LVB gruppe'
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
			'An error occurred while retrieving the spezial gruppe'
		);
	}

	/**
	 *
	 */
	private static function _getGroupsMembers($gruppe_kurzbz)
	{
		return parent::_dbCall(
			'getGroupsMembers',
			array($gruppe_kurzbz),
			'An error occurred while retrieving groups members'
		);
	}

	/**
	 *
	 */
	private static function _getCourseAngerechnet($moodleCourseId)
	{
		$courseAngerechnet = array();

		$courseAngerechnetDataset = parent::_dbCall(
			'getCourseAngerechnet',
			array($moodleCourseId),
			'An error occurred while retrieving angerechnet students'
		);

		if (Database::rowsNumber($courseAngerechnetDataset) > 0)
		{
			$courseAngerechnetAll = Database::fetchAll($courseAngerechnetDataset);

			foreach ($courseAngerechnetAll as $ca)
			{
				$courseAngerechnet[] = $ca['student_uid'];
			}
		}

		return $courseAngerechnet;
	}

	// --------------------------------------------------------------------------------------------
	// Private MoodleAPI wrappers methods

	/**
	 *
	 */
	private static function _core_course_get_courses($moodleCoursesIDsArray)
	{
		return parent::_moodleAPICall(
			'core_course_get_courses',
			array($moodleCoursesIDsArray),
			'An error occurred while retrieving courses from moodle'
		);
	}

	/**
	 *
	 */
	private static function _core_user_get_users_by_field($uid)
	{
		$users = parent::_moodleAPICall(
			'core_user_get_users_by_field',
			array($uid),
			'An error occurred while retrieving users info from moodle'
		);

		return $users;
	}

	/**
	 *
	 */
	private static function _enrol_manual_enrol_users($users)
	{
		self::_moodleAPICallChunks($users, 'enrol_manual_enrol_users', 'An error occurred while enrolling users in moodle');
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
			'An error occurred while retrieving groups from moodle'
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
	private static function _isMoodleUserMemberMoodleGroup($moodleUserId, $groupId)
	{
		$groups = parent::_moodleAPICall(
			'core_group_get_group_members',
			array($groupId),
			'An error occurred while retrieving group members from moodle'
		);

		if (count($groups) > 0 && count($groups[0]->userids) > 0)
		{
			foreach ($groups[0]->userids as $userId)
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
	private static function _core_role_assign_roles($usersToAssign)
	{
		self::_moodleAPICallChunks($usersToAssign, 'core_role_assign_roles', 'An error occurred while assigning roles');
	}

	/**
	 *
	 */
	private static function _core_group_add_group_members($members)
	{
		self::_moodleAPICallChunks($members, 'core_group_add_group_members', 'An error occurred while adding members to a moodle group');
	}

	/**
	 *
	 */
	private static function _enrol_manual_unenrol_users($users)
	{
		self::_moodleAPICallChunks($users, 'enrol_manual_unenrol_users', 'An error occurred while removing enrolled users in moodle');
	}

	/**
	 *
	 */
	private static function _core_course_get_categories($id)
	{
		return parent::_moodleAPICall(
			'core_course_get_categories_by_id',
			array($id),
			'An error occurred while retrieving categories from moodle'
		);
	}
}
