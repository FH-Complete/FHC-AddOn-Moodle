<?php

require_once('./MoodleClient.php');

/**
 *
 */
class RestAPI
{
	private $_moodleClient;

    /**
     * Object initialization
     */
    public function __construct()
    {
		$this->_moodleClient = new MoodleClient();
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public function core_course_get_categories($name, $parent)
	{
		return $this->_moodleClient->call(
			'core_course_get_categories',
			MoodleClient::HTTP_POST_METHOD,
			array(
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
	public function core_course_create_categories($name, $parent)
	{
		return $this->_moodleClient->call(
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
	public function isSuccess()
	{
		return $this->_moodleClient->isSuccess();
	}

	/**
	 *
	 */
	public function isError()
	{
		return $this->_moodleClient->isError();
	}

	/**
	 *
	 */
	public function hasData()
	{
		return $this->_moodleClient->hasData();
	}

	/**
	 *
	 */
	public function hasEmptyResponse()
	{
		return $this->_moodleClient->hasEmptyResponse();
	}

	/**
	 *
	 */
	public function getError()
	{
		return $this->_moodleClient->getError();
	}
}
