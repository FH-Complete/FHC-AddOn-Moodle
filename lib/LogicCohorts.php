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
	public static function synchronizeCohorts($stsem, $options) {	
		Output::printInfo('Cohort: ' . $options['cohort_name_prefix'] . $stsem);
		$uids = self::getDBCohortUidsArray($options['uidsquery'], $stsem);

		$cohorts = self::searchCohort($stsem, $options);

		if( count($cohorts->cohorts) < 1 )
		{
			$cohorts = self::createCohort($stsem, $options);
			$cohort = $cohorts[0];
		}
		else
		{
			$cohort = $cohorts->cohorts[0];	
		}

		$members = self::getCohortMembers($cohort->id);

		$moodleusers = array();
		if( count($members[0]->userids) > 0 )
		{
			$moodleusers = self::getMoodleUsersByIds($members[0]->userids);
		}

		Output::printInfo('FHC uid count: ' . count($uids));

		$todelete = array();
		foreach( $moodleusers as $mdluser ) 
		{
			if( false !== ($idx = array_search($mdluser->username, $uids)) )
			{
				unset($uids[$idx]);
			}
			else 
			{
				$todelete[] = $mdluser->id;
			}
		}

		Output::printInfo('uids to add count: ' . count($uids));
		Output::printInfo('uids to delete count: ' . count($todelete));

		if( count($todelete) > 0 )
		{
			self::deleteCohortMembers($cohort->id, $todelete);
		}

		if( count($uids) > 0 ) 
		{
			self::addCohortMembers($cohort->id, $uids);
		}
	}
	
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
	public static function deleteCohortMembers($cohortid, $moodleuserids)
	{
		$members = array();
		foreach ($moodleuserids as $userid)
		{
			$members[] = array(
				'cohortid' => $cohortid,
				'userid' => $userid
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
	public static function createCohort($stsem, $options)
	{
		$cohort = $options['cohort_name_prefix'] . $stsem;
		$categoryid = $options['categoryid'];
		$cohorts = array(
			array(
				'categorytype' => array(
					'type' => 'id',
					'value' => $categoryid
				),
				'name' => $cohort,
				'idnumber' => strtolower($cohort),
				'theme' => $options['theme']
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
	public static function searchCohort($stsem, $options)
	{
		$cohort = $options['cohort_name_prefix'] . $stsem;
		$contextid = $options['contextid'];
		
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
	public static function getDBCohortUidsArray($query, $stsem)
	{		
		$uIDsArray = array();

		$uIDs = self::_dbCall(
			'getCohortUids',
			array($query, $stsem),
			'An error occurred while retrieving the lehrgangs cohort uids'
		);

		//
		while ($uID = Database::fetchRow($uIDs))
		{
			$uIDsArray[] = $uID->uid;
		}

		return $uIDsArray;
	}
	
	/**
	 * Studiensemester can be passed as commandline option
	 * ex: php synchronizeCohorts.php --stsem WS2019
	 */
	public static function getCliOrCurrentOrNextStudiensemester()
	{
		$stsem = null;
		$commandlineparams = getopt('', array("stsem:"));

		if (isset($commandlineparams['stsem']))
		{
			$stsem = $commandlineparams['stsem'];
		}
		else
		{
			// Retrieves the current studiensemester
			$stsem = self::getCurrentOrNextStudiensemester();
		}

		return $stsem;
	}
}
