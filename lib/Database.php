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
	public function getCourseMitarbeiter($moodleCourseId)
	{
		$query = 'SELECT * FROM (
					SELECT
						mitarbeiter_uid, p.vorname, p.nachname
					FROM
						lehre.tbl_lehreinheitmitarbeiter lm
						JOIN addon.tbl_moodle m USING(lehreinheit_id)
						JOIN public.tbl_benutzer b ON(lm.mitarbeiter_uid = b.uid)
						JOIN public.tbl_person p USING(person_id)
					WHERE
						mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					UNION
					SELECT
						mitarbeiter_uid, p.vorname, p.nachname
					FROM
						lehre.tbl_lehreinheitmitarbeiter lm
						JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
						JOIN addon.tbl_moodle m USING(lehrveranstaltung_id)
						JOIN public.tbl_benutzer b ON(lm.mitarbeiter_uid = b.uid)
						JOIN public.tbl_person p USING(person_id)
					WHERE
						l.studiensemester_kurzbz = m.studiensemester_kurzbz
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
	public function getCourseFachbereichsleitung($moodleCourseId)
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
	public function getCourseLehreinheiten($moodleCourseId)
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
	public function getCourseGroups($moodleCourseId)
	{
		$query = 'SELECT DISTINCT
					bg.gruppe_kurzbz
				FROM
					addon.tbl_moodle m
					JOIN public.tbl_benutzergruppe bg USING(gruppe_kurzbz)
				WHERE
					m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
					AND (
						bg.studiensemester_kurzbz = m.studiensemester_kurzbz
						OR bg.studiensemester_kurzbz IS NULL
					)
				ORDER BY
					bg.gruppe_kurzbz';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getGroupsMembers($gruppe_kurzbz)
	{
		$query = 'SELECT
					bg.uid, p.vorname, p.nachname
				FROM
					addon.tbl_moodle m
					JOIN public.tbl_benutzergruppe bg USING(gruppe_kurzbz)
					JOIN public.tbl_benutzer b USING(uid)
					JOIN public.tbl_person p USING(person_id)
				WHERE
					bg.gruppe_kurzbz = '.$this->db_add_param($gruppe_kurzbz).'
					AND (
						bg.studiensemester_kurzbz = m.studiensemester_kurzbz
						OR bg.studiensemester_kurzbz IS NULL
					)
				ORDER BY
					p.vorname, p.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getCourseAngerechnet($moodleCourseId)
	{
		$query = 'SELECT DISTINCT
					z.student_uid AS student_uid
				FROM
					addon.tbl_moodle m
					JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id, studiensemester_kurzbz)
					JOIN lehre.tbl_zeugnisnote z ON(z.lehrveranstaltung_id = l.lehrveranstaltung_id)
					JOIN lehre.tbl_note n USING(note)
				WHERE
					n.lkt_ueberschreibbar = FALSE
					AND m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				ORDER BY
					student_uid';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getCourses($studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					lv.lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz,
					lv.semester,
					l.lehreinheit_id,
					TRIM(STRING_AGG(vorname || nachname, \'_\')) AS lektoren,
					UPPER(s.typ || s.kurzbz) AS studiengang,
					s.oe_kurzbz,
					o.bezeichnung AS oe_bezeichnung
				FROM
					lehre.tbl_lehreinheit l
					JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
					JOIN lehre.tbl_lehreinheitmitarbeiter lm USING(lehreinheit_id)
					JOIN public.tbl_mitarbeiter USING(mitarbeiter_uid)
					JOIN public.tbl_benutzer b ON(uid = mitarbeiter_uid)
					JOIN public.tbl_person USING(person_id)
					JOIN public.tbl_studiengang s USING(studiengang_kz)
					JOIN public.tbl_organisationseinheit o ON(o.oe_kurzbz = s.oe_kurzbz)
				WHERE
					l.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND lv.semester IS NOT NULL
					AND lv.semester != 0
					AND l.lehrform_kurzbz = lv.lehrform_kurzbz
					AND b.uid NOT LIKE \'_Dummy%\'
					AND l.lehreinheit_id NOT IN (
							SELECT DISTINCT lehreinheit_id FROM addon.tbl_moodle
						)
				GROUP BY
					lv.lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz,
					lv.semester,
					l.lehreinheit_id,
					s.typ,
					s.kurzbz,
					s.oe_kurzbz,
					o.bezeichnung
				ORDER BY
					lv.lehrveranstaltung_id';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function insertMoodleTable(
		$moodleCourseId, $lehreinheit_id, $lehrveranstaltung_id, $studiensemester_kurzbz, $insertamum, $insertvon, $gruppen
	)
	{
		$query = 'INSERT INTO addon.tbl_moodle(
					mdl_course_id, lehreinheit_id, lehrveranstaltung_id, studiensemester_kurzbz, insertamum, insertvon, gruppen
				)
				VALUES('.
					$this->db_add_param($moodleCourseId, FHC_INTEGER).', '.
					$this->db_add_param($lehreinheit_id, FHC_INTEGER).', '.
					$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER).', '.
					$this->db_add_param($studiensemester_kurzbz).', '.
					$this->db_add_param($insertamum).', '.
					$this->db_add_param($insertvon).', '.
					$this->db_add_param($gruppen, FHC_BOOLEAN).
				')';

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
