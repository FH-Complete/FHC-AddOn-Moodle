<?php
require_once('Logic.php');

/**
 * Description of LogicCohorts
 *
 * @author bambi
 */
class LogicCohorts extends Logic
{
	/**
	 * 
	 */
	public static function addCohortMembers($cohortid, $uids)
	{
		$members = array();
		foreach ($uids as $uid)
		{
			$members[] = array(
				'cohorttype' => array(
					'type' => 'id',
					'value' => $cohortid
				),
				'usertype' => array(
					'type' => 'username',
					'value' => $uid
				)
			);
		}
		
		return self::_moodleAPICall(
			'core_cohort_add_cohort_members', 
			array(
				$members
			), 
			'An error occured while adding cohort members.');
	}

	/**
	 * 
	 */
	public static function deleteCohortMembers($cohortid, $moodleusers)
	{
		$members = array();
		foreach ($moodleusers as $user)
		{
			$members[] = array(
				'cohortid' => $cohortid,
				'userid' => $user->id
			);
		}
		
		return self::_moodleAPICall(
			'core_cohort_delete_cohort_members', 
			array(
				$members
			), 
			'An error occured while deleting cohort members.');
	}
	
	/**
	 * 
	 */
	public static function getMoodleUsersByIds($moodleuserids)
	{		
		return self::_moodleAPICall(
			'core_user_get_users_by_field_id', 
			array(
				$moodleuserids
			), 
			'An error occured while fetching moodleusers by ids.');
	}
	
	/**
	 * 
	 */
	public static function getCohortMembers($cohortid)
	{
		return self::_moodleAPICall(
			'core_cohort_get_cohort_members', 
			array(
				array($cohortid)
			), 
			'An error occured while getting cohort members.');
	}
	
	/**
	 * 
	 */
	public static function createCohort($currentOrNextStudiensemester)
	{
		$cohort = 'TW_Academy_' . $currentOrNextStudiensemester;
		$categoryid = 91;
		$cohorts = array(
			array(
				'categorytype' => array(
					'type' => 'id',
					'value' => $categoryid
				),
				'name' => $cohort,
				'idnumber' => strtolower($cohort),
				'theme' => 'twacademy'
			)
		);
		
		return self::_moodleAPICall(
			'core_cohort_create_cohorts', 
			array(
				$cohorts
			), 
			'An error occured while creating the cohort.');
	}	
	
	/**
	 * 
	 */
	public static function searchCohort($currentOrNextStudiensemester)
	{
		$cohort = 'TW_Academy_' . $currentOrNextStudiensemester;
		$contextid = 2153;
		
		return self::_moodleAPICall(
			'core_cohort_search_cohorts', 
			array(
				$cohort,
				array(
					'contextid' => $contextid,
					'contextlevel' => '',
					'instanceid' => 0
				)
			), 
			'An error occured while searching for cohort.');
	}
	
	/**
	 *
	 */
	public static function getDBLehrgangsCohortArray($currentOrNextStudiensemester)
	{
		$uIDsArray = array();

		$uIDs = self::_dbCall(
			'getLehrgangsCohort',
			array($currentOrNextStudiensemester),
			'An error occurred while retrieving the lehrgangs cohort uids'
		);

		//
		while ($uID = Database::fetchRow($uIDs))
		{
			$uIDsArray[] = $uID->student_uid;
		}

		return $uIDsArray;
	}
	
	/**
	 * Studiensemester can be passed as commandline option
	 * ex: php synchronizeCohorts.php --stsem WS2019
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
			$currentOrNextStudiensemester = self::getCurrentOrNextStudiensemester();
		}

		return $currentOrNextStudiensemester;
	}
}
