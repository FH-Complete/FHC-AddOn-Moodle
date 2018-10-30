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
		$insertamum = 'NOW()', $insertvon = ADDON_MOODLE_INSERTVON, $gruppen = false
	)
	{
		return parent::_dbCall(
			'insertMoodleTable',
			array($moodleCourseId, $lehreinheit_id, $lehrveranstaltung_id, $studiensemester_kurzbz, $insertamum, $insertvon, $gruppen),
			'An error occurred while inserting into table addon.tbl_moodle'
		);
	}

	// --------------------------------------------------------------------------------------------
    // Public MoodleAPI wrappers methods

	/**
	 *
	 */
	public static function getOrCreateCategory($name, $parent)
	{
		// Department
		$categories = LogicCourses::core_course_get_categories($name, $parent);
		if (count($categories) == 0)
		{
			$categories = LogicCourses::core_course_create_categories($name, $parent);
		}

		return $categories[0]->id;
	}

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
	public static function core_course_get_courses_by_field($shortname, $categoryId)
	{
		return parent::_moodleAPICall(
			'core_course_get_courses_by_field',
			array($shortname, $categoryId),
			'An error occurred while retriving a course from moodle'
		);
	}

	// --------------------------------------------------------------------------------------------
    // Public business logic methods



	// --------------------------------------------------------------------------------------------
    // Private business logic methods



	// --------------------------------------------------------------------------------------------
	// Private Database wrappers methods



	// --------------------------------------------------------------------------------------------
	// Private MoodleAPI wrappers methods


}
