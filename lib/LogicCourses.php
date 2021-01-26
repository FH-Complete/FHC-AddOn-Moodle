<?php

require_once('Logic.php');

/**
 *
 */
class LogicCourses extends Logic
{
	// --------------------------------------------------------------------------------------------
    // Public business logic methods

	/**
	 * Generates the parameter shortname for the given course
	 */
	public static function getCourseShortname($course, $studiensemester_kurzbz)
	{
		$orgform = $course->lv_orgform_kurzbz != '' ? $course->lv_orgform_kurzbz : $course->sg_orgform_kurzbz;

		$shortname = self::_getStudiengangKuerzel($course->studiengang_kz).'-'.
			$orgform.'-'.
			$course->semester.'-'.
			$studiensemester_kurzbz.'-'.
			$course->kurzbz;

		//
		if (ADDON_MOODLE_JUST_MOODLE != true)
		{
			$shortname .= '-'.$course->lehreinheit_id;
		}

		return $shortname;
	}

	/**
	 * Generates the parameter fullname for the given course
	 */
	public static function getCourseFullname($course, $studiensemester_kurzbz)
	{
		$orgform = ($course->lv_orgform_kurzbz != '' ? $course->lv_orgform_kurzbz : $course->sg_orgform_kurzbz);

		$fullname = self::_getStudiengangKuerzel($course->studiengang_kz).'-'.
			$orgform.'-'.
			$course->semester.'-'.
			$studiensemester_kurzbz.' - '.
			$course->bezeichnung;

		//
		if (ADDON_MOODLE_JUST_MOODLE != true)
		{
			$fullname .= ' - '.$course->lehreinheit_id;
		}

		return $fullname;
	}

	/**
	 * If the given course already exists then its ID in moodle is returned
	 * otherwise creates a new course in moodle with the given parameters and its ID in moodle is returned
	 */
	public static function getOrCreateMoodleCourse(
		$course, $studiensemester_kurzbz,
		$fullname, $shortname, $startDate, $courseFormatOptions, $endDate,
		&$numCoursesAddedToMoodle, &$numCategoriesAddedToMoodle
	)
	{
		$moodleCourseId = -1;

		//
		$checkCourse = self::getCourseByShortname($shortname);
		if ($checkCourse == null)
		{
			$categoryId = self::_createCategories($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);

			$moodleCourseId = self::_addMoodleCourse(
				$fullname, $shortname, $categoryId, $startDate, $courseFormatOptions, $endDate
			);

			$numCoursesAddedToMoodle++;
		}
		else //
		{
			$moodleCourseId = $checkCourse->id;

			Output::printDebug('Already present in moodle!');
			Output::printDebug('ID: '.$checkCourse->id);
			Output::printDebug('Category ID: '.$checkCourse->categoryid);
			Output::printDebug('Start date: '.$checkCourse->startdate);
			Output::printDebug('End date: '.$checkCourse->enddate);
			Output::printDebug('Format: '.$checkCourse->format);
			$numsections = 0;
			if (count($checkCourse->courseformatoptions) > 0)
			{
				$numsections = $checkCourse->courseformatoptions[0]->value;
			}
			Output::printDebug('Sections number: '.$checkCourse->courseformatoptions[0]->value);
		}

		return $moodleCourseId;
	}

	/**
	 * Adds the a new record in the table addon.tbl_moodle
	 */
	public static function addCourseToDatabase($moodleCourseId, $course, $studiensemester_kurzbz, &$numCoursesAddedToDB)
	{
		$lehreinheitOrLehrveranstaltung = rand(0, 1);

		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			// By default gruppen is false
			$gruppen = false;

			// If set inside the course object then use it
			if (isset($course->gruppen)) $gruppen = $course->gruppen;

			// Insert into database
			if ($lehreinheitOrLehrveranstaltung == 0)
			{
				self::insertMoodleTable(
					$moodleCourseId,
					$course->lehreinheit_id,
					null,
					$studiensemester_kurzbz,
					'NOW()',
					ADDON_MOODLE_INSERTVON,
					$gruppen
				);

				Output::printDebug('Added into database >> lehreinheit_id: '.$course->lehreinheit_id);
			}
			else
			{
				self::insertMoodleTable(
					$moodleCourseId,
					null,
					$course->lehrveranstaltung_id,
					$studiensemester_kurzbz,
					'NOW()',
					ADDON_MOODLE_INSERTVON,
					$gruppen
				);

				Output::printDebug('Added into database >> lehrveranstaltung_id: '.$course->lehrveranstaltung_id);
			}
		}
		else
		{
			//
			if ($lehreinheitOrLehrveranstaltung == 0)
			{
				Output::printDebug('Dry run >> should be added into database >> lehreinheit_id: '.$course->lehreinheit_id);
			}
			else
			{
				Output::printDebug('Dry run >> should be added into database >> lehrveranstaltung_id: '.$course->lehrveranstaltung_id);
			}
		}

		$numCoursesAddedToDB++;
	}

	/**
	 *
	 */
	public static function updateCourseToDatabase($moodleCourseId, $course, &$numCoursesUpdatedDB)
	{
		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			self::_updateMoodleTable($moodleCourseId, $course->moodle_id);

			Output::printDebug('Updated in database >> new mdl_course_id: '.$moodleCourseId);
		}
		else
		{
			Output::printDebug('Dry run >> should be updated into database >> new mdl_course_id: '.$moodleCourseId);
		}

		$numCoursesUpdatedDB++;
	}

	/**
	 * Adds a new course to moodle to contains all groups users
	 */
	public static function addGroupsCourseToMoodle(
		$studiensemester_kurzbz, $startDate, $courseFormatOptions, $endDate, &$numCategoriesAddedToMoodle
	)
	{
		$moodleCourseId = -1;

		Output::printDebug('Adding a course to moodle to contain all the users from groups');
		self::_printDebugEmptyline();
		Output::printDebug('Shortname: '.ADDON_MOODLE_GROUPS_COURSE_SHORTNAME);
		Output::printDebug('Fullname: '.ADDON_MOODLE_GROUPS_COURSE_FULLNAME);

		//
		$checkCourse = self::getCourseByShortname(ADDON_MOODLE_GROUPS_COURSE_SHORTNAME);
		if ($checkCourse == null)
		{
			if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
			{
				$rootCategoryId = self::getOrCreateCategory(mb_strtoupper($studiensemester_kurzbz), ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

				Output::printDebug('Root category '.$studiensemester_kurzbz.'->'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$rootCategoryId);

				$moodleCourseId = self::core_course_create_courses(
					ADDON_MOODLE_GROUPS_COURSE_FULLNAME, ADDON_MOODLE_GROUPS_COURSE_SHORTNAME,
					$rootCategoryId, $startDate, ADDON_MOODLE_COURSE_FORMAT, $courseFormatOptions, $endDate
				);

				Output::printDebug('Added to moodle!');
				Output::printDebug('ID: '.$moodleCourseId);
				Output::printDebug('Category ID: '.$rootCategoryId);
				Output::printDebug('Start date: '.$startDate);
				Output::printDebug('End date: '.$endDate);
				Output::printDebug('Format: '.ADDON_MOODLE_COURSE_FORMAT);
				$numsections = 0;
				if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
				{
					$numsections = ADDON_MOODLE_NUMSECTIONS_VALUE;
				}
				Output::printDebug('Sections number: '.$numsections);
			}
			else
			{
				Output::printDebug('Dry run >> should be added to moodle');
				Output::printDebug('Start date: '.$startDate);
				Output::printDebug('End date: '.$endDate);
				Output::printDebug('Format: '.ADDON_MOODLE_COURSE_FORMAT);
				$numsections = 0;
				if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
				{
					$numsections = ADDON_MOODLE_NUMSECTIONS_VALUE;
				}
				Output::printDebug('Sections number: '.$numsections);
			}
		}
		else //
		{
			$moodleCourseId = $checkCourse->id;

			Output::printDebug('Already present in moodle!');
			Output::printDebug('ID: '.$checkCourse->id);
			Output::printDebug('Category ID: '.$checkCourse->categoryid);
			Output::printDebug('Start date: '.$checkCourse->startdate);
			Output::printDebug('End date: '.$checkCourse->enddate);
			Output::printDebug('Format: '.$checkCourse->format);
			$numsections = 0;
			if (count($checkCourse->courseformatoptions) > 0)
			{
				$numsections = $checkCourse->courseformatoptions[0]->value;
			}
			Output::printDebug('Sections number: '.$checkCourse->courseformatoptions[0]->value);
		}

		self::_printDebugEmptyline();

		return $moodleCourseId;
	}

	/**
	 * Adds a new record in addon.tbl_moodle with groups
	 */
	public static function addGroupsToDatabase($moodleCourseId, $studiensemester_kurzbz, &$numGroupsAddedToDB)
	{
		//
		$groups = explode(ADDON_MOODLE_GROUPS_TO_SYNCH_DELIMITER, ADDON_MOODLE_GROUPS_TO_SYNCH);

		//
		if (is_array($groups) && count($groups) > 0)
		{
			Output::printDebug('Number of groups to be added to the database: '.count($groups));
			self::_printDebugEmptyline();

			//
			foreach ($groups as $group)
			{
				//
				if (!self::_groupExists($studiensemester_kurzbz, $moodleCourseId, $group))
				{
					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						self::insertMoodleTable($moodleCourseId, null, null, $studiensemester_kurzbz, 'NOW()', ADDON_MOODLE_INSERTVON, false, $group);

						Output::printDebug('Added into database >> gruppe_kurzbz: '.$group);
					}
					else
					{
						Output::printDebug('Dry run >> should be added into database >> gruppe_kurzbz: '.$group);
					}

					$numGroupsAddedToDB++;
				}
				else
				{
					Output::printDebug('Group already present in database: '.$group);
				}

				Output::printDebug('----------------------------------------------------------------------');
			}
		}
		else
		{
			Output::printDebug('No groups were defined to be added to the database');
		}
	}

	// --------------------------------------------------------------------------------------------
    // Private business logic methods

	/**
	 *
	 */
	private static function _createCategories($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		$categoryId = null;

		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			// Build categories tree
			if (ADDON_MOODLE_COURSE_SCHEMA == 'DEP-STG-JG-STSEM')
			{
				// Department -> Studiengang -> Jahrgang -> Studiensemester
				// (Ex. Informationstechnologie und Informationsmanagement -> BIMK -> Jahrgang 2014 -> WS2014)
				$categoryId = self::_createCategoriesTreeA($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);
			}
			else // Studiensemester -> Studiengang -> Ausbsemester (Ex. WS2014 -> BEL -> 1)
			{
				$categoryId = self::_createCategoriesTreeB($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);
			}
		}
		else
		{
			if (ADDON_MOODLE_COURSE_SCHEMA == 'DEP-STG-JG-STSEM')
			{
				Output::printDebug('Dry run >> category tree should start with category '.$course->bezeichnung);
			}
			else
			{
				Output::printDebug('Dry run >> category tree should start with category '.$studiensemester_kurzbz);
			}
		}

		return $categoryId;
	}

	/**
	 *
	 */
	private static function _getStudiengangKuerzel($studiengang_kz)
	{
		$studiengang = new studiengang();
		$studiengang->load($studiengang_kz);

		return $studiengang->kuerzel;
	}

	/**
	 *
	 */
	private static function _addMoodleCourse($fullname, $shortname, $categoryId, $startDate, $courseFormatOptions, $endDate)
	{
		$moodleCourseId = -1;

		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			$moodleCourseId = self::core_course_create_courses(
				$fullname, $shortname, $categoryId, $startDate, ADDON_MOODLE_COURSE_FORMAT, $courseFormatOptions, $endDate
			);

			Output::printDebug('Added to moodle!');
			Output::printDebug('ID: '.$moodleCourseId);
			Output::printDebug('Category ID: '.$categoryId);
			Output::printDebug('Start date: '.$startDate);
			Output::printDebug('End date: '.$endDate);
			Output::printDebug('Format: '.ADDON_MOODLE_COURSE_FORMAT);
			$numsections = 0;
			if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
			{
				$numsections = ADDON_MOODLE_NUMSECTIONS_VALUE;
			}
			Output::printDebug('Sections number: '.$numsections);
		}
		else
		{
			Output::printDebug('Dry run >> should be added to moodle');
			Output::printDebug('Start date: '.$startDate);
			Output::printDebug('End date: '.$endDate);
			Output::printDebug('Format: '.ADDON_MOODLE_COURSE_FORMAT);
			$numsections = 0;
			if (ADDON_MOODLE_NUMSECTIONS_VALUE > 0)
			{
				$numsections = ADDON_MOODLE_NUMSECTIONS_VALUE;
			}
			Output::printDebug('Sections number: '.$numsections);
		}

		return $moodleCourseId;
	}

	/**
	 *
	 */
	private static function _createCategoriesTreeA($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		$studiensemester = new studiensemester();

		// Department category
		$departmentId = self::getOrCreateCategory(mb_strtoupper($course->bezeichnung), ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

		Output::printDebug('Department category '.$course->bezeichnung.'->'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$departmentId);

		// Studiengang category
		$studiengangId = self::getOrCreateCategory(mb_strtoupper($course->studiengang), $departmentId, $numCategoriesAddedToMoodle);

		Output::printDebug('Studiengang category '.$course->studiengang.'->'.$departmentId.' ID: '.$studiengangId);

		// Determine the semester jumping back one >> one to determine the year
		if ($course->semester != 0)
		{
			$studiensemesterYear = $studiensemester->jump($studiensemester_kurzbz, ($course->semester - 1) * - 1);
			$studiensemester->load($studiensemesterYear);
		}
		else
		{
			$studiensemester->load($studiensemester_kurzbz);
		}

		$datum = new Datum();
		$jahrgang = $datum->formatDatum($studiensemester->start, 'Y');

		// Jahrgang category
		$jahrgangId = self::getOrCreateCategory(mb_strtoupper(ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang), $studiengangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Jahrgang category '.ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang.'->'.$jahrgangId.' ID: '.$jahrgangId);

		// Studiensemester category
		$categoryId = self::getOrCreateCategory(mb_strtoupper($studiensemester_kurzbz), $jahrgangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Course category '.$studiensemester_kurzbz.'->'.$jahrgangId.' ID: '.$categoryId);

		return $categoryId;
	}

	/**
	 *
	 */
	private static function _createCategoriesTreeB($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		// Studiensemester category
		$studiensemesterId = self::getOrCreateCategory(mb_strtoupper($studiensemester_kurzbz), ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

		Output::printDebug('Studiensemester category '.$studiensemester_kurzbz.'->'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$studiensemesterId);

		// Studiengang category
		$studiengangId = self::getOrCreateCategory(mb_strtoupper($course->studiengang), $studiensemesterId, $numCategoriesAddedToMoodle);

		Output::printDebug('Studiengang category '.$course->studiengang.'->'.$studiensemesterId.' ID: '.$studiengangId);

		// Semester category
		$categoryId = self::getOrCreateCategory(mb_strtoupper($course->semester), $studiengangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Course category '.$course->semester.'->'.$studiengangId.' ID: '.$categoryId);

		return $categoryId;
	}

	// --------------------------------------------------------------------------------------------
	// Private Database wrappers methods

	/**
	 *
	 */
	private static function _updateMoodleTable($moodleCourseId, $tblMoodleId)
	{
		return parent::_dbCall(
			'updateMoodleTable',
			array($moodleCourseId, $tblMoodleId),
			'An error occurred while updating table addon.tbl_moodle'
		);
	}

	/**
	 *
	 */
	private static function _groupExists($studiensemester_kurzbz, $mdl_course_id, $gruppe_kurzbz)
	{
		$groups = parent::_dbCall(
			'getMoodleCoursesByGroup',
			array($studiensemester_kurzbz, $mdl_course_id, $gruppe_kurzbz),
			'An error occurred while retrieving groups from table addon.tbl_moodle'
		);

		return Database::rowsNumber($groups) == 0 ? false : true;
	}
}
