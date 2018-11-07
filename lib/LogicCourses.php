<?php

require_once('Logic.php');

/**
 *
 */
class LogicCourses extends Logic
{
	// --------------------------------------------------------------------------------------------
    // Public Database wrappers methods

	/**
	 *
	 */
	public static function getCourses($studiensemester_kurzbz)
	{
		return parent::_dbCall(
			'getCourses',
			array($studiensemester_kurzbz),
			'An error occurred while retriving courses'
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
		return parent::_dbCall(
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
	public static function groupExists($studiensemester_kurzbz, $mdl_course_id, $gruppe_kurzbz)
	{
		$groups = parent::_dbCall(
			'getMoodleCoursesByGroup',
			array($studiensemester_kurzbz, $mdl_course_id, $gruppe_kurzbz),
			'An error occurred while retriving groups from table addon.tbl_moodle'
		);

		return Database::rowsNumber($groups) == 0 ? false : true;
	}

	// --------------------------------------------------------------------------------------------
    // Public MoodleAPI wrappers methods

	/**
	 *
	 */
	public static function core_course_get_categories($name, $parent)
	{
		return parent::_moodleAPICall(
			'core_course_get_categories',
			array($name, $parent),
			'An error occurred while retriving categories from moodle'
		);
	}

	/**
	 *
	 */
	public static function core_course_create_categories($name, $parent)
	{
		return parent::_moodleAPICall(
			'core_course_create_categories',
			array($name, $parent),
			'An error occurred while creating a category in moodle'
		);
	}

	/**
	 *
	 */
	public static function core_course_create_courses(
		$fullname, $shortname, $categoryId, $startDate, $format = 'topics', $courseFormatOptions = null, $endDate = null
	)
	{
		$courses = parent::_moodleAPICall(
			'core_course_create_courses',
			array($fullname, $shortname, $categoryId, $format, $courseFormatOptions, $startDate, $endDate),
			'An error occurred while creating a new course in moodle'
		);

		return $courses[0]->id;
	}

	/**
	 *
	 */
	public static function getCourseByShortname($shortname)
	{
		$courses = parent::_moodleAPICall(
			'core_course_get_courses_by_field',
			array('shortname', $shortname),
			'An error occurred while retriving a course from moodle by shortname'
		);

		if (count($courses->courses) == 0) return null;

		return $courses->courses[0];
	}

	/**
	 *
	 */
	public static function getMoodleVersion()
	{
		$info = parent::_moodleAPICall(
			'core_webservice_get_site_info',
			array(),
			'An error occurred while retriving moodle infos'
		);

		$release = $info->release;

		return substr($release, 0, ADDON_MOODLE_VERSION_LENGTH);
	}

	// --------------------------------------------------------------------------------------------
    // Public business logic methods

	/**
	 *
	 */
	public static function getOrCreateCategory($name, $parent, &$numCategoriesAddedToMoodle)
	{
		// Department
		$categories = self::core_course_get_categories($name, $parent);
		if (count($categories) == 0)
		{
			$categories = self::core_course_create_categories($name, $parent);
			$numCategoriesAddedToMoodle++;
		}

		return $categories[0]->id;
	}

	/**
	 *
	 */
	public static function setEndDateEnabled()
	{
		return self::getMoodleVersion() >= ADDON_MOODLE_VERSION_SET_END_DATE;
	}

	/**
	 *
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
	 *
	 */
	public static function getStartDate($studiensemester)
	{
		$datum = new Datum();

		return $datum->mktime_fromdate($studiensemester->start);
	}

	/**
	 *
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
	public static function getStudiengangKuerzel($studiengang_kz)
	{
		$studiengang = new studiengang();
		$studiengang->load($studiengang_kz);

		return $studiengang->kuerzel;
	}

	/**
	 *
	 */
	public static function getCourseShortname($course, $studiensemester_kurzbz)
	{
		return mb_substr(self::getStudiengangKuerzel($course->studiengang_kz).
				($course->orgform_kurzbz != '' ? '-'.$course->orgform_kurzbz : '').
   				($course->semester != '' ? '-'.$course->semester : '').'-'.
				$studiensemester_kurzbz.'-'.$course->kurzbz.'-'.$course->lehreinheit_id.'-'.
				$course->lektoren, 0, 254);
	}

	/**
	 *
	 */
	public static function getCourseFullname($course, $studiensemester_kurzbz)
	{
		return mb_substr(self::getStudiengangKuerzel($course->studiengang_kz).
				($course->orgform_kurzbz != '' ? '-'.$course->orgform_kurzbz : '').
   				($course->semester != '' ? '-'.$course->semester : '').'-'.
				$studiensemester_kurzbz.'-'.$course->bezeichnung.'-'.$course->lehreinheit_id.'-'.
				$course->lektoren, 0, 254);
	}

	/**
	 *
	 */
	public static function getOrCreateMoodleCourse(
		$course, $studiensemester_kurzbz,
		$fullname, $shortname, $startDate, $courseFormatOptions, $endDate,
		&$numCoursesAddedToMoodle, &$numCategoriesAddedToMoodle
	)
	{
		//
		$checkCourse = self::getCourseByShortname($shortname);
		if ($checkCourse == null)
		{
			$categoryId = self::createCategories($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);

			$moodleCourseId = self::addMoodleCourse(
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
	}

	/**
	 *
	 */
	public static function createCategories($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		$categoryId = null;

		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			// Build categories tree
			if (ADDON_MOODLE_COURSE_SCHEMA == 'DEP-STG-JG-STSEM')
			{
				// Department -> Studiengang -> Jahrgang -> Studiensemester
				// (Ex. Informationstechnologie und Informationsmanagement -> BIMK -> Jahrgang 2014 -> WS2014)
				$categoryId = self::createCategoriesTreeA($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);
			}
			else // Studiensemester -> Studiengang -> Ausbsemester (Ex. WS2014 -> BEL -> 1)
			{
				$categoryId = self::createCategoriesTreeB($course, $studiensemester_kurzbz, $numCategoriesAddedToMoodle);
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
	public static function addMoodleCourse($fullname, $shortname, $categoryId, $startDate, $courseFormatOptions, $endDate)
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
	public static function addCourseToDatabase($moodleCourseId, $course, $studiensemester_kurzbz, &$numCoursesAddedToDB)
	{
		$lehreinheitOrLehrveranstaltung = rand(0, 1);

		if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
		{
			//
			if ($lehreinheitOrLehrveranstaltung == 0)
			{
				self::insertMoodleTable($moodleCourseId, $course->lehreinheit_id, null, $studiensemester_kurzbz);

				Output::printDebug('Added into database >> lehreinheit_id: '.$course->lehreinheit_id);
			}
			else
			{
				self::insertMoodleTable($moodleCourseId, null, $course->lehrveranstaltung_id, $studiensemester_kurzbz);

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
				$rootCategoryId = self::getOrCreateCategory($studiensemester_kurzbz, ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

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
	 * Adding groups to table addon.tbl_moodle
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
				if (!LogicCourses::groupExists($studiensemester_kurzbz, $moodleCourseId, $group))
				{
					if (!ADDON_MOODLE_DRY_RUN) // If a dry run is NOT required
					{
						LogicCourses::insertMoodleTable($moodleCourseId, null, null, $studiensemester_kurzbz, 'NOW()', ADDON_MOODLE_INSERTVON, false, $group);

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

	/**
	 *
	 */
	public static function createCategoriesTreeA($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		$studiensemester = new studiensemester();

		// Department category
		$departmentId = self::getOrCreateCategory($course->bezeichnung, ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

		Output::printDebug('Department category '.$course->bezeichnung.'->'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$departmentId);

		// Studiengang category
		$studiengangId = self::getOrCreateCategory($course->studiengang, $departmentId, $numCategoriesAddedToMoodle);

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
		$jahrgangId = self::getOrCreateCategory(ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang, $studiengangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Jahrgang category '.ADDON_MOODLE_JAHRGANG_CATEGORY_NAME.$jahrgang.'->'.$jahrgangId.' ID: '.$jahrgangId);

		// Studiensemester category
		$categoryId = self::getOrCreateCategory($studiensemester_kurzbz, $jahrgangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Course category '.$studiensemester_kurzbz.'->'.$jahrgangId.' ID: '.$categoryId);

		return $categoryId;
	}

	/**
	 *
	 */
	public static function createCategoriesTreeB($course, $studiensemester_kurzbz, &$numCategoriesAddedToMoodle)
	{
		// Studiensemester category
		$studiensemesterId = self::getOrCreateCategory($studiensemester_kurzbz, ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

		Output::printDebug('Studiensemester category '.$studiensemester_kurzbz.'->'.ADDON_MOODLE_ROOT_CATEGORY_ID.' ID: '.$studiensemesterId);

		// Studiengang category
		$studiengangId = self::getOrCreateCategory($course->studiengang, $studiensemesterId, $numCategoriesAddedToMoodle);

		Output::printDebug('Studiengang category '.$course->studiengang.'->'.$studiensemesterId.' ID: '.$studiengangId);

		// Semester category
		$categoryId = self::getOrCreateCategory($course->semester, $studiengangId, $numCategoriesAddedToMoodle);

		Output::printDebug('Course category '.$course->semester.'->'.$studiengangId.' ID: '.$categoryId);

		return $categoryId;
	}

	// --------------------------------------------------------------------------------------------
    // Private business logic methods



	// --------------------------------------------------------------------------------------------
	// Private Database wrappers methods



	// --------------------------------------------------------------------------------------------
	// Private MoodleAPI wrappers methods


}
