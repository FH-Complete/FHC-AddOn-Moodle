<?php

require_once('../../../include/basis_db.class.php');

/**
 *
 */
class Database extends basis_db
{
	/**
	 *
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
	public function getMoodleCoursesIDs($studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					mdl_course_id AS id
				FROM
					addon.tbl_moodle
				WHERE
					studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getMitarbeiter($moodleCourseId)
	{
		$query = 'SELECT
					mitarbeiter_uid
				FROM
					lehre.tbl_lehreinheitmitarbeiter
					JOIN addon.tbl_moodle USING(lehreinheit_id)
				WHERE
					mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				UNION
				SELECT
					mitarbeiter_uid
				FROM
					lehre.tbl_lehreinheitmitarbeiter
					JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
					JOIN addon.tbl_moodle USING(lehrveranstaltung_id)
				WHERE
					tbl_lehreinheit.studiensemester_kurzbz = tbl_moodle.studiensemester_kurzbz
					AND mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getBenutzerByUID($uid)
	{
		$query = 'SELECT
						uid,
						vorname,
						nachname
				FROM
					campus.vw_benutzer
				WHERE
					uid = '.$this->db_add_param($uid);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getFachbereichsleitung($moodleCourseId)
	{
		$query = 'SELECT DISTINCT
					tbl_benutzer.uid AS mitarbeiter_uid
				FROM
					public.tbl_organisationseinheit
					JOIN public.tbl_benutzerfunktion USING(oe_kurzbz)
					JOIN lehre.tbl_lehrveranstaltung USING(oe_kurzbz)
					JOIN lehre.tbl_lehreinheit USING(lehrveranstaltung_id)
					JOIN public.tbl_benutzer ON(tbl_benutzerfunktion.uid = tbl_benutzer.uid)
				WHERE
					tbl_benutzer.aktiv
					AND organisationseinheittyp_kurzbz IN(\'Institut\', \'Fachbereich\')
					AND funktion_kurzbz = \'Leitung\'
					AND (tbl_benutzerfunktion.datum_von <= NOW() OR tbl_benutzerfunktion.datum_von IS NULL)
					AND (tbl_benutzerfunktion.datum_bis >= NOW() OR tbl_benutzerfunktion.datum_bis IS NULL)
					AND tbl_lehrveranstaltung.lehrveranstaltung_id IN (
						SELECT
							lehrveranstaltung_id
						FROM
							addon.tbl_moodle
						WHERE
							mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
							AND lehrveranstaltung_id IS NOT NULL
						UNION
						SELECT
							tbl_lehreinheit.lehrveranstaltung_id
						FROM
							addon.tbl_moodle
							JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
						WHERE
							mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					)';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getLehreinheiten($moodleCourseId)
	{
		$query = 'SELECT
					studiengang_kz, semester, verband, gruppe, gruppe_kurzbz, tbl_moodle.studiensemester_kurzbz, tbl_moodle.gruppen
				FROM
					lehre.tbl_lehreinheitgruppe
					JOIN addon.tbl_moodle USING(lehreinheit_id)
				WHERE
					mdl_course_id = '.$this->db_add_param($moodleCourseId).'
				UNION
				SELECT
					studiengang_kz, semester, verband, gruppe, gruppe_kurzbz, tbl_moodle.studiensemester_kurzbz, tbl_moodle.gruppen
				FROM
					lehre.tbl_lehreinheitgruppe
					JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
					JOIN addon.tbl_moodle USING(lehrveranstaltung_id)
				WHERE
					tbl_lehreinheit.studiensemester_kurzbz = tbl_moodle.studiensemester_kurzbz
					AND mdl_course_id = '.$this->db_add_param($moodleCourseId);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getLVBGruppe($studiensemester_kurzbz, $studiengang_kz, $semester, $verband, $gruppe)
	{
		$query = 'SELECT DISTINCT
					student_uid, tbl_person.vorname, tbl_person.nachname
				FROM
					public.tbl_studentlehrverband
					JOIN public.tbl_benutzer ON(student_uid = uid)
					JOIN public.tbl_person USING(person_id)
				WHERE
					tbl_benutzer.aktiv
					AND studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND studiengang_kz = '.$this->db_add_param($studiengang_kz).'
					AND semester = '.$this->db_add_param($semester);

		if (trim($verband) != '')
		{
			$query .= ' AND verband = '.$this->db_add_param($verband);

			if (trim($gruppe) != '')
			{
				$query .= ' AND gruppe = '.$this->db_add_param($gruppe);
			}
		}

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getSpezialGruppe($gruppe_kurzbz, $studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					uid as student_uid, tbl_person.vorname, tbl_person.nachname
				FROM
					public.tbl_benutzergruppe
					JOIN public.tbl_benutzer USING(uid)
					JOIN public.tbl_person USING(person_id)
				WHERE
					tbl_benutzer.aktiv
					AND gruppe_kurzbz = '.$this->db_add_param($gruppe_kurzbz).'
					AND studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz);

		return $this->_execQuery($query);
	}

	/**
	 *
	 * NOTE: PostgreSQL dependent
	 */
	public static function fetchRow(&$result)
	{
		return pg_fetch_object($result);
	}

	/**
	 *
	 * NOTE: PostgreSQL dependent
	 */
	public static function rowsNumber(&$result)
	{
		return pg_num_rows($result);
	}

	// --------------------------------------------------------------------------------------------
    // Private methods

	/**
	 *
	 */
	private function _execQuery($query)
	{
		$result = $this->db_query($query);
		if (!$result)
		{
			$result = null;
		}

		return $result;
	}
}
