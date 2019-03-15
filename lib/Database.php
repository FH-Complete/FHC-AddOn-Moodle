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
					OR studiensemester_kurzbz IS NULL
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
						AND mitarbeiter_uid NOT ILIKE \'%dummy%\'
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
						AND mitarbeiter_uid NOT ILIKE \'%dummy%\'
				) employees
				ORDER BY
					employees.vorname, employees.nachname';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getOrganisationunits($moodleCourseId)
 	{
 		$query = 'SELECT
						lv.oe_kurzbz
					FROM
						addon.tbl_moodle m
						JOIN lehre.tbl_lehreinheit lh USING(lehreinheit_id)
						JOIN lehre.tbl_lehrveranstaltung lv ON(lv.lehrveranstaltung_id = lh.lehrveranstaltung_id)
					WHERE
						m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
						AND lv.oe_kurzbz NOT ILIKE \'%dummy%\'
					UNION
					SELECT
						lv.oe_kurzbz
					FROM
						addon.tbl_moodle m
						JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
					WHERE
						m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
						AND lv.oe_kurzbz NOT ILIKE \'%dummy%\'
					';

 		return $this->_execQuery($query);
 	}

	/**
	 *
	 */
	public function getCompetenceFieldAndDeparmentLeadersOE($oe_kurzbz)
 	{
		$query = 'WITH RECURSIVE oes(oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz) AS (
						SELECT
							oe_kurzbz, oe_parent_kurzbz, organisationseinheittyp_kurzbz
						FROM
							public.tbl_organisationseinheit
						WHERE
							oe_kurzbz = '.$this->db_add_param($oe_kurzbz).'
							AND aktiv = true
						UNION ALL
						SELECT
							o.oe_kurzbz, o.oe_parent_kurzbz, o.organisationseinheittyp_kurzbz
						FROM
							public.tbl_organisationseinheit o, oes
						WHERE
							o.oe_kurzbz = oes.oe_parent_kurzbz
							AND o.aktiv = true
					)
					SELECT
						b.uid, p.vorname, p.nachname, oes.organisationseinheittyp_kurzbz
					FROM
						oes
						JOIN public.tbl_benutzerfunktion bf USING(oe_kurzbz)
						JOIN public.tbl_benutzer b USING(uid)
						JOIN public.tbl_person p USING(person_id)
					WHERE
						bf.funktion_kurzbz = \''.ADDON_MOODLE_LEITUNG.'\'
						AND (
								oes.organisationseinheittyp_kurzbz = \''.ADDON_MOODLE_DEPARTMENT.'\'
								OR oes.organisationseinheittyp_kurzbz = \''.ADDON_MOODLE_KOMPETENZFELD.'\'
						)
					';

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
					AND b.uid NOT ILIKE \'%dummy%\'
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
					AND sl.student_uid NOT ILIKE \'%dummy%\'
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
					AND b.uid NOT ILIKE \'%dummy%\'
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
					AND bg.uid NOT ILIKE \'%dummy%\'
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
					JOIN lehre.tbl_zeugnisnote z ON(z.lehrveranstaltung_id = l.lehrveranstaltung_id AND z.studiensemester_kurzbz=l.studiensemester_kurzbz)
					JOIN lehre.tbl_note n USING(note)
				WHERE
					n.lkt_ueberschreibbar = FALSE
					AND z.student_uid NOT ILIKE \'%dummy%\'
					AND m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				UNION
				SELECT DISTINCT
							z.student_uid AS student_uid
						FROM
							addon.tbl_moodle m
							JOIN lehre.tbl_zeugnisnote z USING(lehrveranstaltung_id, studiensemester_kurzbz)
							JOIN lehre.tbl_note n USING(note)
						WHERE
							n.lkt_ueberschreibbar = FALSE
							AND z.student_uid NOT ILIKE \'%dummy%\'
							AND m.mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				ORDER BY
					student_uid';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getCoursesFromLehreinheit($studiensemester_kurzbz)
	{
		$query = 'SELECT DISTINCT
					lv.lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz AS lv_orgform_kurzbz,
					lv.semester,
					l.lehreinheit_id,
					UPPER(s.typ || s.kurzbz) AS studiengang,
					s.oe_kurzbz,
					s.orgform_kurzbz AS sg_orgform_kurzbz
				FROM
					lehre.tbl_lehreinheit l
					JOIN lehre.tbl_lehrveranstaltung lv ON(l.lehrveranstaltung_id = lv.lehrveranstaltung_id AND l.lehrform_kurzbz = lv.lehrform_kurzbz)
					JOIN public.tbl_studiengang s USING(studiengang_kz)
				WHERE
					l.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND lv.semester IS NOT NULL
					AND lv.semester != 0
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
					s.orgform_kurzbz
				ORDER BY
					lv.lehrveranstaltung_id';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getCoursesFromTblMoodle($studiensemester_kurzbz)
	{
		$query = 'SELECT
					m.moodle_id,
					NULL AS lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz AS lv_orgform_kurzbz,
					lv.semester,
					m.lehreinheit_id,
					UPPER(s.typ || s.kurzbz) AS studiengang,
					s.oe_kurzbz,
					s.orgform_kurzbz AS sg_orgform_kurzbz
				FROM
					addon.tbl_moodle m
					JOIN lehre.tbl_lehreinheit l USING(lehreinheit_id)
					JOIN lehre.tbl_lehrveranstaltung lv ON(l.lehrveranstaltung_id = lv.lehrveranstaltung_id)
					JOIN public.tbl_studiengang s USING(studiengang_kz)
				WHERE
					m.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND m.lehrveranstaltung_id IS NULL
					AND m.gruppe_kurzbz IS NULL
					AND lv.semester IS NOT NULL
					AND lv.semester != 0
				GROUP BY
					m.moodle_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz,
					lv.semester,
					m.lehreinheit_id,
					s.typ,
					s.kurzbz,
					s.oe_kurzbz,
					s.orgform_kurzbz
				UNION ALL
				SELECT
					m.moodle_id,
					m.lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz AS lv_orgform_kurzbz,
					lv.semester,
					NULL as lehreinheit_id,
					UPPER(s.typ || s.kurzbz) AS studiengang,
					s.oe_kurzbz,
					s.orgform_kurzbz AS sg_orgform_kurzbz
				FROM
					addon.tbl_moodle m
					JOIN lehre.tbl_lehrveranstaltung lv ON(lv.lehrveranstaltung_id = m.lehrveranstaltung_id)
					JOIN public.tbl_studiengang s USING(studiengang_kz)
				WHERE
					m.studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND m.lehreinheit_id IS NULL
					AND m.gruppe_kurzbz IS NULL
					AND lv.semester IS NOT NULL
					AND lv.semester != 0
				GROUP BY
					m.moodle_id,
					lv.lehrveranstaltung_id,
					lv.bezeichnung,
					lv.kurzbz,
					lv.studiengang_kz,
					lv.orgform_kurzbz,
					lv.semester,
					s.typ,
					s.kurzbz,
					s.oe_kurzbz,
					s.orgform_kurzbz
				';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function insertMoodleTable(
		$moodleCourseId, $lehreinheit_id, $lehrveranstaltung_id, $studiensemester_kurzbz,
		$insertamum, $insertvon, $gruppen, $gruppe_kurzbz
	)
	{
		$query = 'INSERT INTO addon.tbl_moodle(
					mdl_course_id, lehreinheit_id, lehrveranstaltung_id, studiensemester_kurzbz,
					insertamum, insertvon, gruppen, gruppe_kurzbz
				)
				VALUES('.
					$this->db_add_param($moodleCourseId, FHC_INTEGER).', '.
					$this->db_add_param($lehreinheit_id, FHC_INTEGER).', '.
					$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER).', '.
					$this->db_add_param($studiensemester_kurzbz).', '.
					$this->db_add_param($insertamum).', '.
					$this->db_add_param($insertvon).', '.
					$this->db_add_param($gruppen, FHC_BOOLEAN).', '.
					$this->db_add_param($gruppe_kurzbz).
				')';

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function updateMoodleTable($moodleCourseId, $tblMoodleId)
	{
		$query = 'UPDATE addon.tbl_moodle SET
					mdl_course_id = '.$this->db_add_param($moodleCourseId, FHC_INTEGER).'
				WHERE
					moodle_id = '.$this->db_add_param($tblMoodleId, FHC_INTEGER);

		return $this->_execQuery($query);
	}

	/**
	 *
	 */
	public function getMoodleCoursesByGroup($studiensemester_kurzbz, $mdl_course_id, $gruppe_kurzbz)
	{
		$query = 'SELECT DISTINCT
					gruppe_kurzbz AS group
				FROM
					addon.tbl_moodle
				WHERE
					studiensemester_kurzbz = '.$this->db_add_param($studiensemester_kurzbz).'
					AND mdl_course_id = '.$this->db_add_param($mdl_course_id, FHC_INTEGER).'
					AND gruppe_kurzbz = '.$this->db_add_param($gruppe_kurzbz);

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
