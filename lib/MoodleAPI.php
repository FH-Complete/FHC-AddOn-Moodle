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
						'value' => 'id,username'
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
	public function fhcomplete_user_get_users($uid)
	{
		return $this->call(
			'fhcomplete_user_get_users',
			MoodleClient::HTTP_POST_METHOD,
			array(
				'criteria' => array(
					array(
						'key' => 'username',
						'value' => $uid
					)
				)
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
}
