<?php
require_once('Logic.php');


/**
 *
 */
class LogicTemplates extends Database
{

	/**
	 * @param string $term
	 * @return array
	 */
	public function findTemplates($term)
	{
		if (is_numeric($term))
			$qry = "SELECT * 
				FROM lehre.tbl_lehrveranstaltung
				WHERE lehrtyp_kurzbz='tpl' 
				AND (CAST(lehrveranstaltung_id AS TEXT) LIKE '" . $this->db_add_param($term, FHC_INTEGER) . "%'
					OR LOWER(bezeichnung) LIKE " . $this->db_add_param('%' . strtolower($term) . '%', FHC_STRING) . ")";
		else
			$qry = "SELECT * 
				FROM lehre.tbl_lehrveranstaltung
				WHERE lehrtyp_kurzbz='tpl' 
				AND LOWER(bezeichnung) LIKE " . $this->db_add_param('%' . strtolower($term) . '%', FHC_STRING);
		
		$result = $this->db_query($qry);

		if (!$result)
			return [];

		$res = [];
		while ($row = self::fetchRow($result)) {
			$this->addCoursesToTemplate($row);
			$res[] = $row;
		}

		return $res;
	}

	/**
	 * @param integer $lehrveranstaltung_id
	 * @return \stdClass | null
	 */
	public function getTemplate($lehrveranstaltung_id)
	{
		$qry = "SELECT * 
			FROM lehre.tbl_lehrveranstaltung
			WHERE lehrtyp_kurzbz='tpl' AND 
				lehrveranstaltung_id=" . $this->db_add_param($lehrveranstaltung_id, FHC_INTEGER);
		$result = $this->db_query($qry);

		if (!$result || $this->db_num_rows($result) != 1)
			return null;

		$template = self::fetchRow($result);

		$this->addCoursesToTemplate($template);

		return $template;
	}

	/**
	 * @param \stdClass $template
	 * @return void
	 */
	protected function addCoursesToTemplate($template)
	{
		$template->mdl_courses = [];

		$qry = "SELECT mdl_course_id, sprache 
			FROM addon.tbl_moodle_quellkurs 
			WHERE lehrveranstaltung_id=" . $this->db_add_param($template->lehrveranstaltung_id, FHC_INTEGER);
		
		if ($result = $this->db_query($qry)) {
			while ($row = self::fetchRow($result)) {
				$template->mdl_courses[$row->sprache] = $row->mdl_course_id;
			}
		}
	}

	/**
	 * @param integer $mdl_course_id
	 * @return \stdClass | null
	 */
	public function getSourceCourse($mdl_course_id)
	{
		$moodle = new MoodleAPI();
		$res = $moodle->core_course_get_courses([$mdl_course_id]);

		if (!$res || !count($res) || !$this->isSourceCourse($res[0]))
			return null;

		$res[0]->template_id = null;
		$res[0]->template_sprache = null;

		$qry = "SELECT lehrveranstaltung_id, sprache 
			FROM addon.tbl_moodle_quellkurs 
			WHERE mdl_course_id=" . $this->db_add_param($mdl_course_id, FHC_INTEGER);
		
		if ($result = $this->db_query($qry)) {
			$num_rows = $this->db_num_rows($result);
			if ($num_rows) {
				if ($num_rows > 1)
					throw new Exception('Too many DB entries for Moodle Source Course id: ' . $mdl_course_id); // TODO(chris): error handling
				$row = self::fetchRow($result);

				$res[0]->template_id = $row->lehrveranstaltung_id;
				$res[0]->template_sprache = $row->sprache;
			}
		}

		return $res[0];
	}

	/**
	 * @param integer | \stdClass $mdl_course
	 * @return boolean
	 */
	public function isSourceCourse($mdl_course)
	{
		if (!$mdl_course)
			return false;

		if (ADDON_MOODLE_SOURCE_COURSE_ID) {
			$moodle = new MoodleAPI();
			$cat = $moodle->call('core_course_get_categories', MoodleClient::HTTP_POST_METHOD, ['criteria' => [['key'=>'id','value'=>$mdl_course->categoryid]], 'addsubcategories' => 0]);
			if (!$cat)
				return false;
			
			if (strpos($cat[0]->path . '/', '/' . ADDON_MOODLE_SOURCE_COURSE_ID . '/') === FALSE)
				return false;
		}

		return true;
	}

	/**
	 * @param \stdClass $template
	 * @param array $mdl_courses An array of "moodle course ids" with "sprache" as index
	 * @return string Returns an empty string on success or the error message
	 */
	public function updateMoodleQuellkurse($template, $mdl_courses)
	{
		$qrys = [];
		foreach ($mdl_courses as $sprache => $mdl_course_id) {
			if ($mdl_course_id) {
				$mdl_course = $this->getSourceCourse($mdl_course_id);
				if (!$this->isSourceCourse($mdl_course))
					return 'moodle.wrong';

				if ($mdl_course->template_id || $mdl_course->template_sprache)
					return 'moodle.duplicate';

				if (isset($template->mdl_courses[$sprache])) {
					$qrys[] = "UPDATE addon.tbl_moodle_quellkurs 
						SET mdl_course_id=" . $this->db_add_param($mdl_course_id, FHC_INTEGER) . " 
						WHERE sprache=" . $this->db_add_param($sprache, FHC_STRING) . " 
						AND lehrveranstaltung_id=" . $this->db_add_param($template->lehrveranstaltung_id, FHC_INTEGER);
				} else {
					$qrys[] = "INSERT INTO addon.tbl_moodle_quellkurs 
						(
							lehrveranstaltung_id, 
							sprache, 
							mdl_course_id
						)
						VALUES 
						(
							" . $this->db_add_param($template->lehrveranstaltung_id, FHC_INTEGER) . ", 
							" . $this->db_add_param($sprache, FHC_STRING) . ", 
							" . $this->db_add_param($mdl_course_id, FHC_INTEGER) . "
						)";
				}
			} else {
				if (isset($template->mdl_courses[$sprache])) {
					$qrys[] = "DELETE FROM addon.tbl_moodle_quellkurs 
						WHERE sprache=" . $this->db_add_param($sprache, FHC_STRING) . " 
						AND lehrveranstaltung_id=" . $this->db_add_param($template->lehrveranstaltung_id, FHC_INTEGER);
				}
			}
		}
		if (!$qrys)
			return 'update.empty';
		
		$result = @$this->db_query(implode(';', $qrys));
		if (!$result)
			return 'sql:' . $this->db_last_error();
		
		return '';
	}

	/**
	 * @param \stdClass $mdl_course
	 * @param integer $template_id
	 * @param string $sprache
	 * @param boolean $overwrite
	 * @return string Returns an empty string on success or the error message
	 */
	public function updateMoodleQuellkurs($mdl_course, $template_id, $sprache, $overwrite = false)
	{
		$template = $this->getTemplate($template_id);
		if (!$template) {
			return 'template.wrong';
		}
		$qry = '';
		if ($mdl_course->template_id) {
			if ($mdl_course->template_id == $template_id && $mdl_course->template_sprache == $sprache)
				return 'update.empty';
			$qry = "DELETE FROM addon.tbl_moodle_quellkurs
				WHERE lehrveranstaltung_id=" . $this->db_add_param($mdl_course->template_id, FHC_INTEGER) . "
				AND sprache=" . $this->db_add_param($mdl_course->template_sprache, FHC_STRING) . "
				AND mdl_course_id=" . $this->db_add_param($mdl_course->id, FHC_INTEGER) . ";";
		}
		if ($template_id) {
			if (isset($template->mdl_courses[$sprache])) {
				if (!$overwrite)
					return 'moodle.overwrite';
				$qry .= "UPDATE addon.tbl_moodle_quellkurs 
					SET mdl_course_id=" . $this->db_add_param($mdl_course->id, FHC_INTEGER) . " 
					WHERE sprache=" . $this->db_add_param($sprache, FHC_STRING) . " 
					AND lehrveranstaltung_id=" . $this->db_add_param($template_id, FHC_INTEGER);
			} else {
				$qry .= "INSERT INTO addon.tbl_moodle_quellkurs 
					(
						lehrveranstaltung_id, 
						sprache, 
						mdl_course_id
					)
					VALUES 
					(
						" . $this->db_add_param($template_id, FHC_INTEGER) . ", 
						" . $this->db_add_param($sprache, FHC_STRING) . ", 
						" . $this->db_add_param($mdl_course->id, FHC_INTEGER) . "
					)";
			}
		}

		$result = @$this->db_query($qry);
		if (!$result)
			return 'sql:' . $this->db_last_error();
		
		return '';
	}

}
