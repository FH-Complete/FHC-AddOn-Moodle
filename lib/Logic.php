<?php

require_once('../../../../config/cis.config.inc.php');
require_once('../../../../config/global.config.inc.php');

require_once('../../../../include/basis_db.class.php');
require_once('../../../../include/datum.class.php');

require_once('../../../../include/benutzerberechtigung.class.php');
require_once('../../../../include/student.class.php');
require_once('../../../../include/studiengang.class.php');
require_once('../../../../include/studiensemester.class.php');

require_once('./RestAPI.php');
require_once('./Output.php');

class Logic
{

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public static function getOrCreateCategoriesTreeA($organizationalUnitDesignation, $teachingUnitCourseRow, $currentNextStudiensemester)
	{
		$categoryId = null;

		// Get or create the category for the organizational unit designation
		$categoryByOUD = self::_getOrCreateCategory($organizationalUnitDesignation, '0');
		if ($categoryByOUD != null)
		{
			// Get or create the sub category for the course
			$subCategoryByCourseId = self::_getOrCreateCategory($teachingUnitCourseRow->stg, $categoryByOUD[0]->id);
			if ($subCategoryByCourseId != null)
			{
				//
				$teachingUnitCourseStudiensemester = self::getTeachingUnitCourseStudiensemester($teachingUnitCourseRow->semester, $currentNextStudiensemester);

				//
				$studiensemesterYear = self::getStudiensemesterYear($teachingUnitCourseStudiensemester->start);

				// Get or create the sub sub category by year
				$subSubCategoryByYear = self::_getOrCreateCategory('Jahrgang '.$studiensemesterYear, $subCategoryByCourseId[0]->id);
				if ($subSubCategoryByYear != null)
				{
					// Get or create the sub sub sub category by semester
					$subSubSubCategoryBySemester = self::_getOrCreateCategory($currentNextStudiensemester, $subSubCategoryByYear[0]->id);
					if ($subSubSubCategoryBySemester != null)
					{
						$categoryId = $subSubSubCategoryBySemester[0]->id;
					}
				}
			}
		}

		return $categoryId;
	}

	/**
	 *
	 */
	public static function getOrCreateCategoriesTreeB($teachingUnitCourseRow, $currentNextStudiensemester)
	{
		$categoryId = null;

		// Get or create the category for current or next studiensemester
		$categoryByStudiensemester = self::_getOrCreateCategory($currentNextStudiensemester, '0');
		if ($categoryByStudiensemester != null)
		{
			// Get or create the sub category for the studiengang
			$subCategoryByCourseId = self::_getOrCreateCategory($teachingUnitCourseRow->stg, $categoryByStudiensemester[0]->id);
			if ($subCategoryByCourseId != null)
			{
				// Get or create the sub sub category by semester
				$subSubCategoryByYear = self::_getOrCreateCategory($teachingUnitCourseRow->semester, $subCategoryByCourseId[0]->id);
				if ($subSubCategoryByYear != null)
				{
					$categoryId = $subSubCategoryByYear[0]->id;
				}
			}
		}

		return $categoryId;
	}

	/**
	 *
	 */
	public static function getCurrentNextStudiensemester()
	{
		$studiensemester = new studiensemester();
		return $studiensemester->getAktOrNext();
	}

	/**
	 *
	 */
	public static function getTeachingUnitCourseStudiensemester($semester, $studiensemester_kurzbz)
	{
		$studiensemester = new studiensemester();

		if ($semester != 0)
		{
			$jahrgangstsem = $studiensemester->jump($studiensemester_kurzbz, ($semester - 1) * -1);
			$studiensemester->load($jahrgangstsem);
		}
		else
		{
			$studiensemester->load($studiensemester_kurzbz);
		}
	}

	/**
	 *
	 */
	public static function getStudiensemesterYear($studiensemesterStartDate)
	{
		$datum = new Datum();
		return $datum->formatDatum($studiensemesterStartDate, 'Y');
	}

	/**
	 *
	 */
	public static function getStudiengangById($studiengang_kz)
	{
		$studiengang = new studiengang();

		$studiengang->load($studiengang_kz);

		return $studiengang;
	}

	/**
	 *
	 */
	public static function isAdmin()
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

	/**
	 *
	 */
	public static function commandLine()
	{
		return php_sapi_name() == 'cli';
	}

	// --------------------------------------------------------------------------------------------
    // Private methods

	private static function _getOrCreateCategory($name, $parent)
	{
		$getOrCreateCategory = null;
		$restAPI = new RestAPI();

		// Get or create the category for the organizational unit designation
		$getOrCreateCategory = $restAPI->core_course_get_categories($name, $parent);
		if ($restAPI->isSuccess() && !$restAPI->hasData())
		{
			printInfo('No category found with: '.$name.' - '.$parent);

			$getOrCreateCategory = $restAPI->core_course_create_categories($name, $parent);
			if ($restAPI->isError() || !$restAPI->hasData())
			{
				printError('An error occurred while callig core_course_create_categories: '.$restAPI->getError());
				return null;
			}
		}
		else
		{
			printError('An error occurred while callig core_course_get_categories: '.$restAPI->getError());
			return null;
		}

		return $getOrCreateCategory;
	}
}
