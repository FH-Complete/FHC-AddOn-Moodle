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
					studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
				ORDER BY
					mdl_course_id';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getMitarbeiter($moodleCourseId)
	{
		$query = 'SELECT * FROM (
					SELECT
						mitarbeiter_uid, p.vorname, p.nachname
					FROM
						lehre.tbl_lehreinheitmitarbeiter l
						JOIN addon.tbl_moodle USING(lehreinheit_id)
						JOIN public.tbl_benutzer b ON(l.mitarbeiter_uid = b.uid)
						JOIN public.tbl_person p USING(person_id)
					WHERE
						mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					UNION
					SELECT
						mitarbeiter_uid, p.vorname, p.nachname
					FROM
						lehre.tbl_lehreinheitmitarbeiter l
						JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
						JOIN addon.tbl_moodle USING(lehrveranstaltung_id)
						JOIN public.tbl_benutzer b ON(l.mitarbeiter_uid = b.uid)
						JOIN public.tbl_person p USING(person_id)
					WHERE
						tbl_lehreinheit.studiensemester_kurzbz = tbl_moodle.studiensemester_kurzbz
						AND mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				) employees
				ORDER BY
					employees.vorname, employees.nachname';

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
					b.uid AS mitarbeiter_uid,
					p.vorname,
					p.nachname
				FROM
					public.tbl_organisationseinheit
					JOIN public.tbl_benutzerfunktion bf USING(oe_kurzbz)
					JOIN lehre.tbl_lehrveranstaltung USING(oe_kurzbz)
					JOIN lehre.tbl_lehreinheit USING(lehrveranstaltung_id)
					JOIN public.tbl_benutzer b ON(bf.uid = b.uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					b.aktiv = TRUE
					AND organisationseinheittyp_kurzbz IN(\'Institut\', \'Fachbereich\')
					AND funktion_kurzbz = \'Leitung\'
					AND (bf.datum_von <= NOW() OR bf.datum_von IS NULL)
					AND (bf.datum_bis >= NOW() OR bf.datum_bis IS NULL)
					AND tbl_lehrveranstaltung.lehrveranstaltung_id IN
						(
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
						)
				ORDER BY
					p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getLehreinheiten($moodleCourseId)
	{
		$query = 'SELECT
					lg.studiengang_kz, lg.semester, lg.verband, lg.gruppe, lg.gruppe_kurzbz, m.studiensemester_kurzbz, m.gruppen
				FROM
					lehre.tbl_lehreinheitgruppe lg
					JOIN addon.tbl_moodle m USING(lehreinheit_id)
				WHERE
					m.mdl_course_id = '.$this->db_add_param($moodleCourseId).'
				UNION
				SELECT
					lg.studiengang_kz, lg.semester, lg.verband, lg.gruppe, lg.gruppe_kurzbz, m.studiensemester_kurzbz, m.gruppen
				FROM
					lehre.tbl_lehreinheitgruppe lg
					JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
					JOIN addon.tbl_moodle m USING(lehrveranstaltung_id)
				WHERE
					l.studiensemester_kurzbz = m.studiensemester_kurzbz
					AND m.mdl_course_id = '.$this->db_add_param($moodleCourseId);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getLVBGruppe($studiensemester_kurzbz, $studiengang_kz, $semester, $verband = '', $gruppe = '')
	{
		$query = 'SELECT DISTINCT
					sl.student_uid, p.vorname, p.nachname
				FROM
					public.tbl_studentlehrverband sl
					JOIN public.tbl_benutzer b ON(student_uid = uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					b.aktiv = TRUE
					AND sl.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND sl.studiengang_kz = '.$this->db_add_param($studiengang_kz).'
					AND sl.semester = '.$this->db_add_param($semester);

		if (trim($verband) != '')
		{
			$query .= ' AND sl.verband = '.$this->db_add_param($verband);

			if (trim($gruppe) != '')
			{
				$query .= ' AND sl.gruppe = '.$this->db_add_param($gruppe);
			}
		}

		$query .= ' ORDER BY p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getSpezialGruppe($gruppe_kurzbz, $studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					b.uid as student_uid, p.vorname, p.nachname
				FROM
					public.tbl_benutzergruppe bg
					JOIN public.tbl_benutzer b USING(uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					b.aktiv = TRUE
					AND bg.gruppe_kurzbz = '.$this->db_add_param($gruppe_kurzbz).'
					AND bg.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
				ORDER BY
					p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getAllGroupsMembers($moodleCourseId, $studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					bg.uid, p.vorname, p.nachname, m.gruppe_kurzbz
				FROM
					addon.tbl_moodle m
					JOIN public.tbl_benutzergruppe bg USING(gruppe_kurzbz)
					JOIN public.tbl_benutzer b USING(uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					AND (
						bg.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
						OR bg.studiensemester_kurzbz IS NULL
					)
				ORDER BY
					p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getCourseGroups($moodleCourseId, $studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					bg.gruppe_kurzbz
				FROM
					addon.tbl_moodle m
					JOIN public.tbl_benutzergruppe bg USING(gruppe_kurzbz)
				WHERE
					m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					AND (
						bg.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
						OR bg.studiensemester_kurzbz IS NULL
					)
				ORDER BY
					bg.gruppe_kurzbz';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getGroupsMembers($studiensemester_kurzbz, $gruppe_kurzbz)
	{
		$query = 'SELECT
					bg.uid, p.vorname, p.nachname
				FROM
					public.tbl_benutzergruppe bg
					JOIN public.tbl_benutzer b USING(uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					bg.gruppe_kurzbz = '.$this->db_add_param($gruppe_kurzbz).'
					AND (
						bg.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
						OR bg.studiensemester_kurzbz IS NULL
					)
				ORDER BY
					p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	// --------------------------------------------------------------------------------------------
    // Public static methods

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

	/**
	 *
	 * NOTE: PostgreSQL dependent
	 */
	public static function fetchAll(&$result)
	{
		return pg_fetch_all($result);
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
