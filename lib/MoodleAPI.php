<?php

require_once('MoodleClient.php');

/**
 *
 */
class MoodleAPI extends MoodleClient
{
    /**
     * Object initialization
     */
    public function __construct()
    {
		parent::__construct();
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public function getBaseURL()
	{
		return parent::getBaseURL();
	}

	/**
	 *
	 */
	public function core_course_get_courses($moodleCoursesIDsArray)
	{
		return $this->call(
			'core_course_get_courses',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'options' => array(
					'ids' => $moodleCoursesIDsArray
				)
			)
		);
	}

	/**
	 * 
	 */
	public function local_fhtw_std_latest_template($qkCourseId)
	{
		return $this->call(
			'local_fhtw_std_latest_template',
			MoodleClient::HTTP_GET_METHOD,
			[
				'qkcourseid' => (int)$qkCourseId
			]
		);
	}

	/**
	 * 
	 */
	public function local_fhtw_std_restore_qk($source, $target)
	{
		return $this->call(
			'local_fhtw_std_restore_qk',
			MoodleClient::HTTP_GET_METHOD,
			[
				'courseid' => (int)$target,
				'qkid' => (int)$source
			]
		);
	}

	/**
	 * 
	 */
	public function local_fhtw_std_current_restore($target)
	{
		return $this->call(
			'local_fhtw_std_current_restore',
			MoodleClient::HTTP_POST_METHOD,
			[
				'tgtcourseid' => $target
			]
		);
	}

	/**
	 * 
	 */
	public function local_fhtw_std_async_unzip_progress($unzipid)
	{
		return $this->call(
			'local_fhtw_std_async_unzip_progress',
			MoodleClient::HTTP_POST_METHOD,
			[
				'unzipid' => $unzipid
			]
		);
	}

	/**
	 * 
	 */
	public function core_backup_get_async_backup_progress($contextid, $backupids)
	{
		return $this->call(
			'core_backup_get_async_backup_progress',
			MoodleClient::HTTP_POST_METHOD,
			[
				'contextid' => $contextid,
				'backupids' => $backupids
			]
		);
	}

	/**
	 *
	 */
	public function core_course_search_courses($criterianame, $criteriavalue, $page = 0, $perpage = 0, $requiredcapabilities = [], $limittoenrolled = 0, $onlywithcompletion = 0)
	{
		return $this->call(
			'core_course_search_courses',
			MoodleClient::HTTP_POST_METHOD,
			[
				'criterianame' => $criterianame,
				'criteriavalue' => $criteriavalue,
				'page' => $page,
				'perpage' => $perpage,
				'requiredcapabilities' => $requiredcapabilities,
				'limittoenrolled' => $limittoenrolled,
				'onlywithcompletion' => $onlywithcompletion
			]
		);
	}

	/**
	 *
	 */
	public function core_enrol_get_enrolled_users($moodleCourseId)
	{
		return $this->call(
			'core_enrol_get_enrolled_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'courseid' => $moodleCourseId,
				'options' => array(
					array(
						'name' => 'userfields',
						'value' => 'id,username,roles'
					),
					array(
						'name' => 'sortby',
						'value' => 'firstname'
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_user_get_users_by_field($uid)
	{
		return $this->call(
			'core_user_get_users_by_field',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'field' => 'username',
				'values' => array($uid)
			)
		);
	}

	/**
	 *
	 */
	public function enrol_manual_enrol_users($users)
	{
		return $this->call(
			'enrol_manual_enrol_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'enrolments' => $users
			)
		);
	}

	/**
	 *
	 */
	public function core_user_create_users($user)
	{
		return $this->call(
			'core_user_create_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'users' => array(
					$user
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_user_update_users($user)
	{
		return $this->call(
			'core_user_update_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'users' => array(
					$user
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_group_get_course_groups($moodleCourseId)
	{
		return $this->call(
			'core_group_get_course_groups',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'courseid' => $moodleCourseId
			)
		);
	}

	/**
	 *
	 */
	public function core_group_create_groups($moodleCourseId, $groupName, $groupDescription)
	{
		return $this->call(
			'core_group_create_groups',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'groups' => array(
					array(
						'courseid' => $moodleCourseId,
						'name' => $groupName,
						'description' => $groupDescription
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_group_get_group_members($groupId)
	{
		return $this->call(
			'core_group_get_group_members',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'groupids' => array(
					$groupId
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_group_add_group_members($members)
	{
		return $this->call(
			'core_group_add_group_members',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'members' => $members
			)
		);
	}

	/**
	 *
	 */
	public function core_group_delete_group_members($members)
	{
		return $this->call(
			'core_group_delete_group_members',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'members' => $members
			)
		);
	}
	
	/**
	 *
	 */
	public function enrol_manual_unenrol_users($users)
	{
		return $this->call(
			'enrol_manual_unenrol_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'enrolments' => $users
			)
		);
	}

	/**
	 *
	 */
	public function core_course_get_categories_by_name_parent($name, $parent, $addsubcategories = '0')
	{
		return $this->call(
			'core_course_get_categories',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'addsubcategories' => $addsubcategories,
				'criteria' => array(
					array(
						'key' => 'name',
						'value' => $name
					),
					array(
						'key' => 'parent',
						'value' => $parent
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_course_get_categories_by_id($id, $addsubcategories = '0')
	{
		return $this->call(
			'core_course_get_categories',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'addsubcategories' => $addsubcategories,
				'criteria' => array(
					array(
						'key' => 'id',
						'value' => $id
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_course_create_categories($name, $parent)
	{
		return $this->call(
			'core_course_create_categories',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'categories' => array(
					array(
						'name' => $name,
						'parent' => $parent
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_course_create_courses($fullname, $shortname, $categoryId, $format, $courseFormatOptions, $startDate, $endDate)
	{
		return $this->call(
			'core_course_create_courses',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'courses' => array(
					array(
						'fullname' => $fullname,
						'shortname' => $shortname,
						'categoryid' => $categoryId,
						'format' => $format,
						'courseformatoptions' => $courseFormatOptions,
						'startdate' => $startDate,
						'enddate' => $endDate,
					)
				)
			)
		);
	}

	/**
	 *
	 */
	public function core_course_get_courses_by_field($field, $value)
	{
		return $this->call(
			'core_course_get_courses_by_field',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'field' => $field,
				'value' => $value
			)
		);
	}

	/**
	 *
	 */
	public function core_webservice_get_site_info()
	{
		return $this->call(
			'core_webservice_get_site_info',
			MoodleClient::HTTP_POST_METHOD,
			array()
		);
	}

	/**
	 *
	 */
	public function core_role_assign_roles($assignments)
	{
		return $this->call(
			'core_role_assign_roles',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'assignments' => $assignments
			)
		);
	}

	/**
	 *
	 */
	public function core_role_unassign_roles($assignments)
	{
		return $this->call(
			'core_role_unassign_roles',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'unassignments' => $assignments
			)
		);
	}
	
	/**
	 *
	 */
	public function core_course_delete_courses($moodleCoursesIDsArray)
	{
		return $this->call(
			'core_course_delete_courses',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'courseids' => $moodleCoursesIDsArray
			)
		);
	}

	/**
	 *
	 */
	public function fhcomplete_get_course_grades($moodleCoursesId, $type)
	{
		return $this->call(
			'fhcomplete_get_course_grades',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'courseid' => $moodleCoursesId,
				'type' => $type
			)
		);
	}
	
	/**
	 * 
	 */
	public function core_cohort_create_cohorts($cohorts)
	{
		return $this->call(
			'core_cohort_create_cohorts', 
			MoodleClient::HTTP_POST_METHOD, 
			array(
				'cohorts' => $cohorts
			)
		);
	}
	
	/**
	 * 
	 */
	public function core_cohort_search_cohorts($query, 
		$context=array(
			'contextid' => 0, 
			'contextlevel' => '',
			'instanceid' => 0
		), 
		$includes='parents' , $limitfrom=0, $limitnum=25)
	{
		return $this->call(
			'core_cohort_search_cohorts', 
			MoodleClient::HTTP_POST_METHOD, 
			array(
				'query' => $query,
				'context' => $context, 
				'includes' => $includes, 
				'limitfrom' => $limitfrom, 
				'limitnum' => $limitnum
			)
		);
	}
	
	/**
	 * 
	 */
	public function core_cohort_get_cohort_members($cohortids)
	{
		return $this->call(
			'core_cohort_get_cohort_members', 
			MoodleClient::HTTP_POST_METHOD, 
			array(
				'cohortids' => $cohortids
			)
		);
	}

	/**
	 * 
	 */
	public function core_cohort_add_cohort_members($members)
	{
		return $this->call(
			'core_cohort_add_cohort_members', 
			MoodleClient::HTTP_POST_METHOD, 
			array(
				'members' => $members
			)
		);
	}
	
	/**
	 * 
	 */
	public function core_cohort_delete_cohort_members($members)
	{
		return $this->call(
			'core_cohort_delete_cohort_members', 
			MoodleClient::HTTP_POST_METHOD, 
			array(
				'members' => $members
			)
		);
	}
	
	/**
	 *
	 */
	public function core_user_get_users_by_field_id($ids)
	{
		return $this->call(
			'core_user_get_users_by_field',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'field' => 'id',
				'values' => $ids
			)
		);
	}
}
