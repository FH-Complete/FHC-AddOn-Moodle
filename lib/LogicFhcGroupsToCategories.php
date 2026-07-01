<?php
require_once('Logic.php');

/**
 * Description of LogicFhcGroupsToCategories
 *
 * @author bambi
 */
class LogicFhcGroupsToCategories extends Logic
{
	/**
	 *
	 */
	public static function synchronizeFhcGroupToCategory($options) {
		$gruppen = $options['gruppen'];
		$roleid = $options['mdl_role_id'];
		$categoryid = $options['mdl_category_id'];

		Output::printInfo('FhcGroupToCategory:'
			. ' gruppen ' . implode(', ', $gruppen)
			. ' category_id ' . $categoryid
			. ' roleid '. $roleid);

		$uids = self::getDBFhcGroupUidsArray($gruppen);
		$category = self::getCategoryById($categoryid);
		if(count($category) < 1)
		{
			Output::printError('Category ' . $categoryid . ' not found. Skipping.');
			return;
		}

		$already_assigned_moodle_usernames = self::fetchCategoryRoleUsers($categoryid, $roleid);
		$already_assigned_fhc_uids = array_map(function($moodleusername) {
			return FHCMoodleUsernameMapper::MoodleUsernameToFHCUid($moodleusername);
		}, $already_assigned_moodle_usernames);

		$uids_to_add = array_diff($uids, $already_assigned_fhc_uids);
		$uids_to_remove = array_diff($already_assigned_fhc_uids, $uids);

		if( count($uids_to_add) > 0 )
		{
			self::assignRoleToUsersOnCategory($roleid, $uids_to_add, $categoryid);
		}

		if( count($uids_to_remove) > 0 )
		{
			self::unassignRoleToUsersOnCategory($roleid, $uids_to_remove, $categoryid);
		}
	}

	/**
	 * 
	 */
	public static function assignRoleToUsersOnCategory($roleid, $uids, $categoryid)
	{
		$assignments = array();
		foreach ($uids as $uid)
		{
			$users = self::core_user_get_users_by_field(FHCMoodleUsernameMapper::FHCUidToMoodleUsername($uid));
			if(is_array($users) && count($users) === 1)
			{
				$assignments[] = array(
					'roleid' => $roleid,
					'userid' => $users[0]->id,
					'contextlevel' => 'coursecat',
					'instanceid' => $categoryid
				);
			}
		}
		
		if(count($assignments) < 1) {
			return;
		}

		return self::_moodleAPICall(
			'core_role_assign_roles', 
			array(
				$assignments
			), 
			'An error occured while assigning group members to category.');
	}

	/**
	 * 
	 */
	public static function unassignRoleToUsersOnCategory($roleid, $uids, $categoryid)
	{
		$unassignments = array();
		foreach ($uids as $uid)
		{
			$users = self::core_user_get_users_by_field(FHCMoodleUsernameMapper::FHCUidToMoodleUsername($uid));
			if(is_array($users) && count($users) === 1)
			{
				$unassignments[] = array(
					'roleid' => $roleid,
					'userid' => $users[0]->id,
					'contextlevel' => 'coursecat',
					'instanceid' => $categoryid
				);
			}
		}

		if(count($unassignments) < 1) {
			return;
		}

		return self::_moodleAPICall(
			'core_role_unassign_roles', 
			array(
				$unassignments
			), 
			'An error occured while unassigning group members from category.');
	}

	/**
	 *
	 */
	public static function fetchCategoryRoleUsers($categoryid, $roleid)
	{
		return self::_moodleAPICall(
			'fhcomplete_category_role_users',
			array(
				$categoryid,
				$roleid
			),
			'An error occured while fetching moodleusers by ids.');
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
	public static function getDBFhcGroupUidsArray($gruppen)
	{
		$uIDsArray = array();

		$uIDs = self::_dbCall(
			'getFhcGroupUids',
			array($gruppen),
			'An error occurred while retrieving the uids for groups ' . implode(', ', $gruppen)
		);

		//
		while ($uID = Database::fetchRow($uIDs))
		{
			$uIDsArray[] = $uID->uid;
		}

		return $uIDsArray;
	}

	/**
	 *
	 */
	public static function getCategoryById($mdl_category_id)
	{
		return self::_moodleAPICall(
			'core_course_get_categories_by_id',
			array($mdl_category_id),
			'An error occured while fetching the category.');
	}
}
