<?php
/* Copyright (C) 2015 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at> and
 */
/*
 * Klasse zur Verwaltung der Moodle Kurse
 *
 * FHComplete Moodle Plugin muss installiert sein fuer
 * Webservice Funktion 'fhcomplete_courses_by_shortname'
 *					 'fhcomplete_get_course_grades'
 */
require_once(dirname(__FILE__).'/../../../include/basis_db.class.php');
require_once(dirname(__FILE__).'/../../../include/datum.class.php');
require_once(dirname(__FILE__).'/../../../include/studiensemester.class.php');
require_once(dirname(__FILE__).'/../../../config/global.config.inc.php');

class moodle_course extends basis_db
{
	public $result = array();
	public $serverurl;

	//Vilesci Attribute
	public $moodle_id;
	public $mdl_course_id;
	public $lehreinheit_id;
	public $lehrveranstaltung_id;
	public $studiensemester_kurzbz;
	public $insertamum;
	public $insertvon;
	public $gruppen;

	//Moodle Attribute
	public $mdl_fullname;
	public $mdl_shortname;

	public $lehrveranstaltung_bezeichnung;
	public $lehrveranstaltung_semester;
	public $lehrveranstaltung_studiengang_kz;

	public $note;

	/**
	 * Konstruktor
	 *
	 */
	public function __construct()
	{
		$this->serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
		if(ADDON_MOODLE_DEBUGLEVEL>0)
			$this->serverurl.='&'.microtime(true);
	}

	/**
	 * Laedt einen MoodleKurs
	 * @param mdl_course_id ID des Moodle Kurses
	 * @return true wenn ok, false im Fehlerfall
	 */
	public function loadMoodleCourse($mdl_course_id)
	{
		$this->mdl_fullname = '';
		$this->mdl_shortname = '';

		$this->errormsg = '';
		$this->result = array();

		if (!is_null($mdl_course_id))
			$this->mdl_course_id = $mdl_course_id;
		if (is_null($this->mdl_course_id)
			|| empty($this->mdl_course_id)
			|| !is_numeric($this->mdl_course_id))
		{
			$this->errormsg = 'Moodle Kurs ID fehlt';
			return false;
		}

		try
		{
			$client = new SoapClient($this->serverurl);
			$response = $client->core_course_get_courses(array('ids' => array($this->mdl_course_id)));
		}
		catch (SoapFault $E)
		{
			$this->errormsg .= "SOAP Fehler beim Laden des Kurses: ".$E->faultstring;
			return false;
		}

		if ($response)
		{
			if (isset($response[0]))
			{
				$this->mdl_fullname = $response[0]['fullname'];
				$this->mdl_shortname = $response[0]['shortname'];
				$this->mdl_course_id = $response[0]['id'];
				return true;
			}
			else
			{
				$this->errormsg = 'Kurs wurde nicht gefunden';
				return false;
			}
		}
		else
		{
			$this->errormsg = 'Fehler beim Laden des Kurses';
			return false;
		}
	}

	/**
	 * Legt einen Eintrag in der tbl_moodle an
	 * @return true wenn ok, false im Fehlerfall
	 */
	public function create_vilesci()
	{
		if ($this->mdl_course_id == '')
		{
			$this->errormsg = 'mdl_course_id muss angegeben sein';
			return false;
		}

		$qry = 'BEGIN; INSERT INTO addon.tbl_moodle(mdl_course_id, lehreinheit_id, lehrveranstaltung_id,
											studiensemester_kurzbz, insertamum, insertvon, gruppen)
				VALUES('.
				$this->db_add_param($this->mdl_course_id, FHC_INTEGER).','.
				$this->db_add_param($this->lehreinheit_id, FHC_INTEGER).','.
				$this->db_add_param($this->lehrveranstaltung_id, FHC_INTEGER).','.
				$this->db_add_param($this->studiensemester_kurzbz).','.
				$this->db_add_param($this->insertamum).','.
				$this->db_add_param($this->insertvon).','.
				$this->db_add_param($this->gruppen, FHC_BOOLEAN).");";

		if ($this->db_query($qry))
		{
			$qry = "SELECT currval('addon.seq_moodle_moodle_id') as id;";
			if ($this->db_query($qry))
			{
				if ($row = $this->db_fetch_object())
				{
					$this->moodle_id = $row->id;
					$this->db_query('COMMIT;');
					return true;
				}
				else
				{
					$this->db_query('ROLLBACK');
					$this->errormsg = 'Fehler beim Lesen der Sequence';
					return false;
				}
			}
			else
			{
					$this->db_query('ROLLBACK');
					$this->errormsg = 'Fehler beim Lesen der Sequence';
					return false;
			}
		}
		else
		{
			$this->errormsg = 'Fehler beim Einfuegen des Datensatzes';
			return false;
		}
	}

	/**
	 * Legt einen Kurs im Moodle an
	 * @return true wenn ok, false im Fehlerfall
	 */
	public function create_moodle()
	{
		//CourseCategorie ermitteln

		//lehrveranstalung ID holen falls nur die lehreinheit_id angegeben wurde
		if ($this->lehrveranstaltung_id == '')
		{
			$qry = "SELECT lehrveranstaltung_id FROM lehre.tbl_lehreinheit
					WHERE lehreinheit_id = ".$this->db_add_param($this->lehreinheit_id, FHC_INTEGER);
			if ($res = $this->db_query($qry))
			{
				if($row = $this->db_fetch_object($res))
				{
					$lvid = $row->lehrveranstaltung_id;
				}
				else
				{
					$this->errormsg = 'Fehler beim Ermitteln der LehrveranstaltungID';
					return false;
				}
			}
			else
			{
				$this->errormsg = 'Fehler beim Ermitteln der LehrveranstaltungID';
				return false;
			}
		}
		else
			$lvid = $this->lehrveranstaltung_id;

		//Studiengang und Semester holen
		$qry = "SELECT tbl_lehrveranstaltung.semester, UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as stg,
				studiengang_kz, tbl_studiengang.oe_kurzbz
				FROM lehre.tbl_lehrveranstaltung JOIN public.tbl_studiengang USING(studiengang_kz)
				WHERE lehrveranstaltung_id=".$this->db_add_param($lvid, FHC_INTEGER);

		if ($res = $this->db_query($qry))
		{
			if($row = $this->db_fetch_object($res))
			{
				$semester = $row->semester;
				$stg = $row->stg;
				$stg_kz = $row->studiengang_kz;
				$oe_kurzbz = $row->oe_kurzbz;
			}
			else
			{
				$this->errormsg = 'Fehler beim Ermitteln von Studiengang und Semester';
				return false;
			}
		}
		else
		{
			$this->errormsg = 'Fehler beim Ermitteln von Studiengang und Semester';
			return false;
		}

		// Kategoriebaum Aufbauen
		if (defined('ADDON_MOODLE_COURSE_SCHEMA') && ADDON_MOODLE_COURSE_SCHEMA == 'DEP-STG-JG-STSEM')
		{
			// Struktur: Department -> STG -> Jahrgang -> StSem
			// (Informationstechnologie und Informationsmanagement -> BIMK -> Jahrgang 2014 -> WS2014)

			// Studiengang der Lehrveranstaltung holen
			// Uebergeordnetes Department ermitteln
			$qry = 'SELECT
						bezeichnung
					FROM
						public.tbl_organisationseinheit
					WHERE
						oe_kurzbz = (SELECT oe_parent_kurzbz
									FROM public.tbl_organisationseinheit
									WHERE oe_kurzbz='.$this->db_add_param($oe_kurzbz).')';

			if ($result_department = $this->db_query($qry))
			{
				if ($row_department = $this->db_fetch_object($result_department))
				{
					$department = $row_department->bezeichnung;
				}
				else
				{
					$this->errormsg = 'Fehler beim Ermitteln des Departments';
					return false;
				}
			}
			// Department
			$id_department = $this->getCategorie($department, '0');
			if ($id_department === false)
				return false;
			if ($id_department === -1)
			{
				if (!$id_department = $this->createCategorie($department, '0'))
				{
					echo "<br>$this->errormsg";
					return false;
				}
			}

			// Studiengang
			$id_stg = $this->getCategorie($stg, $id_department);
			if ($id_stg === false)
				return false;
			if ($id_stg === -1)
			{
				if (!$id_stg = $this->createCategorie($stg, $id_department))
				{
					echo "<br>$this->errormsg";
					return false;
				}
			}

			// Jahrgang - 1. Studiensemester ermitteln (Stsem um Ausbsem -1 zurückspringen) und das Jahr ermitteln
			$studiensemester = new studiensemester();
			if ($semester != 0)
			{
				$jahrgangstsem = $studiensemester->jump($this->studiensemester_kurzbz, ($semester-1)*-1);
				$studiensemester->load($jahrgangstsem);
			}
			else
			{
				$jahrgangstsem = $this->studiensemester_kurzbz;
				$studiensemester->load($jahrgangstsem);
			}

			$datum = new Datum();
			$jahr = $datum->formatDatum($studiensemester->start, 'Y');

			$id_jahrgang = $this->getCategorie('Jahrgang '.$jahr, $id_stg);
			if ($id_jahrgang === false)
				return false;
			if ($id_jahrgang === -1)
			{
				if (!$id_jahrgang = $this->createCategorie('Jahrgang '.$jahr, $id_stg))
				{
					echo "<br>$this->errormsg";
					return false;
				}
			}

			// Studiensemester
			$id_stsem = $this->getCategorie($this->studiensemester_kurzbz, $id_jahrgang);
			if ($id_stsem === false)
				return false;
			if ($id_stsem === -1)
			{
				if (!$id_stsem = $this->createCategorie($this->studiensemester_kurzbz, $id_jahrgang))
				{
					echo "<br>Fehler beim Anlegen des Studiensemesters";
					return false;
				}
			}

			$categoryid = $id_stsem;
		}
		else
		{
			// Struktur: STSEM -> STG -> Ausbsemester (WS2014 -> BEL -> 1)

			//Studiensemester Categorie holen
			$id_stsem = $this->getCategorie($this->studiensemester_kurzbz, '0');
			if ($id_stsem === false)
				return false;
			if ($id_stsem === -1)
			{
				if (!$id_stsem = $this->createCategorie($this->studiensemester_kurzbz, '0'))
				{
					echo "<br>Fehler beim Anlegen des Studiensemesters";
					return false;
				}
			}
			//Studiengang Categorie holen
			$id_stg = $this->getCategorie($stg, $id_stsem);
			if ($id_stg === false)
				return false;
			if ($id_stg === -1)
			{
				if (!$id_stg = $this->createCategorie($stg, $id_stsem))
				{
					echo "<br>$this->errormsg";
					return false;
				}
			}
			//Semester Categorie holen
			$id_sem = $this->getCategorie($semester, $id_stg);
			if ($id_sem === false)
				return false;
			if ($id_sem === -1)
			{
				if (!$id_sem = $this->createCategorie($semester, $id_stg))
				{
					echo "<br>$this->errormsg";
					return false;
				}
			}
			$categoryid = $id_sem;
		}

		try
		{
			$client = new SoapClient($this->serverurl);


			$data = new stdClass();
			$data->fullname = $this->mdl_fullname;
			$data->shortname = $this->mdl_shortname;
			$data->categoryid = $categoryid;
			$data->format = 'topics';

			if(defined('ADDON_MOODLE_NUM_SECTIONS') && !is_null(ADDON_MOODLE_NUM_SECTIONS))
			{
				$numsections_option = new stdClass();
				$numsections_option->name = 'numsections';
				$numsections_option->value = ADDON_MOODLE_NUM_SECTIONS;
				$data->courseformatoptions = array($numsections_option);
			}

			$stsem = new studiensemester();
			$stsem->load($this->studiensemester_kurzbz);
			$datum_obj = new datum();
			$data->startdate = $datum_obj->mktime_fromdate($stsem->start);
			$data->enddate = $datum_obj->mktime_fromdate($stsem->ende);

			$response = $client->core_course_create_courses(array($data));
			if (isset($response[0]))
			{
				$this->mdl_course_id = $response[0]['id'];
				return true;
			}
			else
			{
				$this->errormsg = 'Fehler beim Anlegen des Kurses';
				return false;
			}
		}
		catch (SoapFault $E)
		{
			$this->errormsg .= "SOAP Fehler beim Anlegen des Kurses: ".$E->faultstring;
			return false;
		}

		return true;
	}

	/**
	 * Laedt die ID einer Kurskategorie anhand der Bezeichnung und der ParentID
	 *
	 * @param bezeichnung Bezeichnung der Kategorie
	 * @param parent ID der uebergeordneten Kurskategorie
	 *
	 * @return id der Kategorie, -1 wenn Kategorie nicht existiert, false im Fehlerfall
	 */
	public function getCategorie($bezeichnung, $parent)
	{
		if ($bezeichnung == '')
		{
			$this->errormsg = 'Bezeichnung muss angegeben werden';
			return false;
		}
		if ($parent == '')
		{
			$this->errormsg = 'getCategorie: parent wurde nicht uebergeben';
			return false;
		}

		try
		{
			$client = new SoapClient($this->serverurl);
			$response = $client->core_course_get_categories(array(array('key'=>'name','value'=>$bezeichnung),array('key'=>'parent','value'=>$parent)));

			if (isset($response[0]))
			{
				return $response[0]['id'];
			}
			else
			{
				$this->errormsg = 'Fehler beim Laden der Kurskategorie';
				return -1;
			}
		}
		catch (SoapFault $E)
		{
			$this->errormsg .= "SOAP Fehler beim Laden der Kurskategorie: ".$E->faultstring;
			return false;
		}
	}

	/**
	 * Erzeugt eine Kurskategorie anhand der Bezeichnung und der ParentID
	 * @param bezeichnung Bezeichnung der Kategorie
	 * @param parent ID der uebergeordneten Kategorie, false im Fehlerfall
	 */
	public function createCategorie($bezeichnung, $parent)
	{
		if ($bezeichnung == '')
		{
			$this->errormsg = 'Bezeichnung muss angegeben werden';
			return false;
		}
		if ($parent == '')
		{
			$this->errormsg = 'createCategorie: parent wurde nicht uebergeben';
			return false;
		}

		try
		{
			$client = new SoapClient($this->serverurl);
			$response = $client->core_course_create_categories(array(array('name'=>$bezeichnung,'parent'=>$parent)));

			if (isset($response[0]))
			{
				return $response[0]['id'];
			}
			else
			{
				$this->errormsg = 'Fehler beim Anlegen der Kategorie';
				return false;
			}
		}
		catch (SoapFault $E)
		{
			$this->errormsg .= "SOAP Fehler beim Anlegen der Kategorie: ".$E->faultstring;
			return false;
		}
	}

	/**
	 * Aktualisiert die Spalte gruppen in der tbl_moodle
	 *
	 * @param moodle_id ID der MoodleZuteilung
	 *		gruppen boolean true wenn syncronisiert
	 *				werden soll, false wenn nicht
	 * @return true wenn ok, false im Fehlerfall
	 */
	public function updateGruppenSync($moodle_id, $gruppen)
	{
		if (!is_numeric($moodle_id))
		{
			$this->errormsg = 'Moodle_id muss eine gueltige Zahl sein';
			return false;
		}

		$qry = "UPDATE addon.tbl_moodle SET gruppen=".$this->db_add_param($gruppen, FHC_BOOLEAN)."
				WHERE moodle_id=".$this->db_add_param($moodle_id, FHC_INTEGER);

		if ($this->db_query($qry))
		{
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Update';
			return false;
		}
	}

	/**
	 * Legt einen Testkurs an
	 */
	public function createTestkurs($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		//CourseCategorie ermitteln

		//Studiengang und Semester holen

		$qry = "SELECT
					tbl_lehrveranstaltung.semester,
					UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as stg
				FROM
					lehre.tbl_lehrveranstaltung
					JOIN public.tbl_studiengang USING(studiengang_kz)
				WHERE
					lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER);

		if ($this->db_query($qry))
		{
			if ($row = $this->db_fetch_object())
			{
				$semester = $row->semester;
				$stg = $row->stg;
			}
			else
			{
				$this->errormsg = 'Fehler beim Ermitteln von Studiengang und Semester';
				return false;
			}
		}
		else
		{
			$this->errormsg = 'Fehler beim Ermitteln von Studiengang und Semester';
			return false;
		}

		//Testkurs Categorie holen
		$id_testkurs = $this->getCategorie('Testkurse', '0');
		if ($id_testkurs === false)
			return false;
		if ($id_testkurs === -1)
		{
			if (!$id_testkurs = $this->createCategorie('Testkurse', '0'))
			{
				$this->errormsg = "Fehler beim Anlegen der Testkurskategorie";
				return false;
			}
		}

		//StSem Categorie holen
		$id_stsem = $this->getCategorie($studiensemester_kurzbz, $id_testkurs);
		if ($id_stsem === false)
			return false;
		if ($id_stsem === -1)
		{
			if (!$id_stsem = $this->createCategorie($studiensemester_kurzbz, $id_testkurs))
			{
				$this->errormsg = 'Fehler beim Anlegen der Studiensemester Kategorie';
				return false;
			}
		}

		$client = new SoapClient($this->serverurl);

		$data = new stdClass();
		$data->fullname = $this->mdl_fullname;
		$data->shortname = $this->mdl_shortname;
		$data->categoryid = $id_stsem;
		$data->format = 'topics';

		$response = $client->core_course_create_courses(array($data));
		if (isset($response[0]))
		{
			$this->mdl_course_id = $response[0]['id'];
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Anlegen des Testkurses';
			return false;
		}
	}

	/**
	 * Laedt den Testkurs zu dieser Lehrveranstaltung
	 * @param lehrveranstaltung_id
	 *		studiensemester_kurzbz
	 * @return ID wenn gefunden, false wenn nicht vorhanden
	 */
	public function loadTestkurs($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$qry = "SELECT
					UPPER(tbl_studiengang.typ::varchar(1) || tbl_studiengang.kurzbz) as kuerzel,
					tbl_lehrveranstaltung.semester, tbl_lehrveranstaltung.kurzbz
				FROM
					lehre.tbl_lehrveranstaltung JOIN public.tbl_studiengang USING(studiengang_kz)
				WHERE
					lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER, false);

		if ($this->db_query($qry))
		{
			if ($row = $this->db_fetch_object())
			{
				$shortname = mb_strtoupper('TK-'.$studiensemester_kurzbz.'-'.$row->kuerzel.'-'.$row->semester.'-'.$row->kurzbz);
			}
			else
			{
				$this->errormsg = 'Fehler beim Laden des Testkurses';
				return false;
			}
		}
		else
		{
			$this->errormsg = 'Fehler beim Laden des Testkurses';
			return false;
		}

		//Testkurs Categorie holen
		$id_testkurs = $this->getCategorie('Testkurse', '0');
		if ($id_testkurs === false)
			return false;
		if ($id_testkurs === -1)
		{
			$this->errormsg = 'Categorie nicht gefunden';
			return false;
		}

		//StSem Categorie holen
		$id_stsem = $this->getCategorie($studiensemester_kurzbz, $id_testkurs);
		if ($id_stsem === false)
			return false;
		if ($id_stsem === -1)
		{
			$this->errormsg = 'Categorie nicht gefunden';
			return false;
		}

		$client = new SoapClient($this->serverurl);
		$response = $client->fhcomplete_courses_by_shortname(array('shortnames'=>array($shortname)));

		if (isset($response[0]))
		{
			$this->mdl_fullname = $response[0]['fullname'];
			$this->mdl_shortname = $response[0]['shortname'];
			$this->mdl_course_id = $response[0]['id'];
			return true;
		}
		else
		{
			$this->errormsg = 'Es wurde kein Testkurs gefunden';
			return false;
		}
	}

	/**
	 * Laedt die Moodle Noten zu allen Moodlekursen einer Lehrveranstaltung
	 * @param lehrveranstaltung_id
	 * @param $studiensemester_kurzbz
	 *
	 * @return objekt mit den Noten der Teilnehmer dieses Kurses
	 */
	public function loadNoten($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$this->errormsg = '';
		$this->result = null;

		if ($lehrveranstaltung_id == '' || $studiensemester_kurzbz == '')
		{
			$this->errormsg = 'LehrveranstaltungID und Studiensemester_kurzbz muss uebergeben werden';
			return false;
		}

		// Ermitteln die Lehreinheiten und Moodle ID
		$qry = "
		SELECT
			distinct mdl_course_id
		FROM
			addon.tbl_moodle
			JOIN lehre.tbl_lehreinheit USING(lehrveranstaltung_id, studiensemester_kurzbz)
		WHERE
			tbl_moodle.lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id)."
			AND tbl_moodle.studiensemester_kurzbz = ".$this->db_add_param($studiensemester_kurzbz)."
		UNION
		SELECT
			distinct mdl_course_id
		FROM
			addon.tbl_moodle
			JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
		WHERE
			tbl_lehreinheit.lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id)."
			AND tbl_moodle.studiensemester_kurzbz = ".$this->db_add_param($studiensemester_kurzbz).";";

		if (!$result_moodle = $this->db_query($qry))
		{
			$this->errormsg = 'Fehler beim Lesen der Moodle Kurse , '.$this->errormsg;
			return false;
		}


		while ($row_moodle = $this->db_fetch_object($result_moodle))
		{
			try
			{
				$client = new SoapClient($this->serverurl);
				if(CIS_GESAMTNOTE_PUNKTE)
					$type = 2; // Prozentpunkte
				else
					$type = 3; // Noten aufgrund Skala
				// 1 = Punkte, 2 = Prozentpunkte, 3 = Note laut Skala

				$response = $client->fhcomplete_get_course_grades($row_moodle->mdl_course_id, $type);

				if (count($response) > 0)
				{

					foreach ($response as $row)
					{
						if ($row['note'] != '-')
						{
							$userobj = new stdClass();
							$userobj->mdl_course_id = $row_moodle->mdl_course_id;
							$userobj->vorname = $row['vorname'];
							$userobj->nachname = $row['nachname'];
							$userobj->idnummer = $row['idnummer'];
							$userobj->uid = $row['username'];
							$userobj->note = $row['note'];
							$this->result[] = $userobj;
						}
					}
				}
			}
			catch(SoapFault $e)
			{
				//echo print_r($e, true);
				//return false;
			}

		}
		return true;
	}


	/**
	 * Loescht einen Moodle Course im Moodle
	 * Wenn erfolgreich gelöscht wird kein Wert in response zurückgegeben
	 * @param mdl_course_id
	 *
	 */
	public function deleteKurs($mdl_course_id)
	{
		$client = new SoapClient($this->serverurl);

		$data = array($mdl_course_id);

		$response = $client->core_course_delete_courses(array($mdl_course_id));

		if (is_object($response))
		{
			$response_obj = $response;
			unset($response);
			if (isset($response_obj->warnings) && isset($response_obj->warnings->message))
				$response[0] = $response_obj->warnings->message;
		}

		if (isset($response[0]))
		{
			$this->errormsg = $response[0];
			return false;
		}

		return true;
	}

	public function load($moodle_id)
	{
		$qry = "SELECT * FROM addon.tbl_moodle WHERE moodle_id =".$this->db_add_param($moodle_id, FHC_INTEGER).';';

		if ($result = $this->db_query($qry))
		{
			if ($row = $this->db_fetch_object())
			{
				$this->moodle_id = $row->moodle_id;
				$this->mdl_course_id = $row->mdl_course_id;
				$this->lehreinheit_id = $row->lehreinheit_id;
				$this->lehrveranstaltung_id = $row->lehrveranstaltung_id;
				$this->studiensemester_kurzbz = $row->studiensemester_kurzbz;
				$this->insertamum = $row->insertamum;
				$this->insertvon = $row->insertvon;
				$this->gruppen = $this->db_parse_bool($row->gruppen);
				return true;
			}
			else
			{
				$this->errormsg = "Kein Moodleeintrag gefunden";
				return false;
			}
		}
		else
		{
			$this->errormsg = "Fehler bei der Abfrage aufgetreten";
			return false;
		}
	}

	/**
	 * Laedt alle Moodlekurse zu einer LV/Stsem
	 * plus die Moodlekurse die auf dessen LE haengen
	 *
	 * @param lehrveranstaltung_id
	 * @param studiensemester_kurzbz
	 *
	 * @return true wenn ok, false im Fehlerfall
	 */
	public function getAll($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$qry = "SELECT
					distinct on(mdl_course_id) *
				FROM
					lehre.tbl_lehrveranstaltung, lehre.tbl_lehreinheit, addon.tbl_moodle
				WHERE
					tbl_lehrveranstaltung.lehrveranstaltung_id=tbl_lehreinheit.lehrveranstaltung_id
					AND	tbl_lehrveranstaltung.lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER)."
					AND	tbl_lehreinheit.studiensemester_kurzbz=".$this->db_add_param($studiensemester_kurzbz)."
					AND	((tbl_lehrveranstaltung.lehrveranstaltung_id=tbl_moodle.lehrveranstaltung_id
						  AND tbl_moodle.studiensemester_kurzbz=tbl_lehreinheit.studiensemester_kurzbz)
						OR
						(tbl_lehreinheit.lehreinheit_id=tbl_moodle.lehreinheit_id))";

		if ($result = $this->db_query($qry))
		{
			while ($row = $this->db_fetch_object($result))
			{
				$obj = new stdClass();

				$obj->moodle_id = $row->moodle_id;
				$obj->mdl_course_id = $row->mdl_course_id;
				$obj->lehreinheit_id = $row->lehreinheit_id;
				$obj->lehrveranstaltung_id = $row->lehrveranstaltung_id;
				$obj->studiensemester_kurzbz = $row->studiensemester_kurzbz;
				$obj->insertamum = $row->insertamum;
				$obj->insertvon = $row->insertvon;
				$obj->gruppen = $this->db_parse_bool($row->gruppen);

				$this->result[] = $obj;
			}
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Laden der Daten';
			return false;
		}
	}

	/**
	 * gibt alle Moodlekurseinträge der Zwischentabelle für übergebenen Studiengang und Semester zurück
	 * @param type $studiengang_kz
	 * @param type $studiensemester
	 */
	public function getAllMoodleForStudiengang($studiengang_kz, $studiensemester)
	{
		$qry = '
			SELECT
				mdl_course_id, moodle.moodle_id, moodle.lehreinheit_id, moodle.lehrveranstaltung_id,
				moodle.studiensemester_kurzbz, moodle.insertamum, moodle.insertvon, gruppen
			FROM addon.tbl_moodle moodle
				JOIN lehre.tbl_lehrveranstaltung lv USING(lehrveranstaltung_id)
			WHERE
				moodle.studiensemester_kurzbz = '.$this->db_add_param($studiensemester).'
				AND lv.studiengang_kz ='.$this->db_add_param($studiengang_kz).'
				AND moodle.lehreinheit_id is null
			UNION
			SELECT
				mdl_course_id, moodle.moodle_id, moodle.lehreinheit_id, moodle.lehrveranstaltung_id,
				moodle.studiensemester_kurzbz, moodle.insertamum, moodle.insertvon, gruppen
			FROM addon.tbl_moodle moodle
				JOIN lehre.tbl_lehreinheit le ON(moodle.lehreinheit_id = le.lehreinheit_id)
				JOIN lehre.tbl_lehrveranstaltung lv ON(le.lehrveranstaltung_id = lv.lehrveranstaltung_id)
			WHERE
				moodle.studiensemester_kurzbz = '.$this->db_add_param($studiensemester).'
				AND lv.studiengang_kz ='.$this->db_add_param($studiengang_kz).'
				AND moodle.lehrveranstaltung_id is null
			';

		if ($result = $this->db_query($qry))
		{
			while ($row = $this->db_fetch_object($result))
			{
				$obj = new stdClass();

				$obj->moodle_id = $row->moodle_id;
				$obj->mdl_course_id = $row->mdl_course_id;
				$obj->lehreinheit_id = $row->lehreinheit_id;
				$obj->lehrveranstaltung_id = $row->lehrveranstaltung_id;
				$obj->studiensemester_kurzbz = $row->studiensemester_kurzbz;
				$obj->insertamum = $row->insertamum;
				$obj->insertvon = $row->insertvon;
				$obj->gruppen = $this->db_parse_bool($row->gruppen);

				$this->result[] = $obj;
			}
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Laden der Daten';
			return false;
		}
	}

	/**
	 * gibt alle Moodlekurseinträge der Zwischentabelle für den uebergebenen Moodle Kurs zurueck
	 * @param type $mdl_course_id ID des Moodle Kurses
	 */
	public function getAllMoodleForMoodleCourse($mdl_course_id)
	{
		$qry = '
			SELECT
				mdl_course_id, moodle_id, lehreinheit_id, lehrveranstaltung_id,
				studiensemester_kurzbz, insertamum, insertvon, gruppen
			FROM
				addon.tbl_moodle
			WHERE
				mdl_course_id='.$this->db_add_param($mdl_course_id);

		if ($result = $this->db_query($qry))
		{
			while ($row = $this->db_fetch_object($result))
			{
				$obj = new stdClass();

				$obj->moodle_id = $row->moodle_id;
				$obj->mdl_course_id = $row->mdl_course_id;
				$obj->lehreinheit_id = $row->lehreinheit_id;
				$obj->lehrveranstaltung_id = $row->lehrveranstaltung_id;
				$obj->studiensemester_kurzbz = $row->studiensemester_kurzbz;
				$obj->insertamum = $row->insertamum;
				$obj->insertvon = $row->insertvon;
				$obj->gruppen = $this->db_parse_bool($row->gruppen);

				$this->result[] = $obj;
			}
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Laden der Daten';
			return false;
		}
	}

	/**
	 * Löscht den Zuordnungseintrag in der Moodletablle
	 * @param type $moodle_id
	 */
	public function deleteZuordnung($mdl_course_id)
	{
		$qry = "DELETE FROM addon.tbl_moodle WHERE mdl_course_id=".$this->db_add_param($mdl_course_id, FHC_INTEGER).';';

		if ($result = $this->db_query($qry))
			return true;
		else
		{
			$this->errormsg = "Fehler beim Löschen der Daten";
			return false;
		}
	}

	/**
	 * Löscht einen Eintrag aus der Zuordnungstabelle
	 * @param type $moodle_id
	 */
	public function delete($moodle_id)
	{
		$qry = "DELETE FROM addon.tbl_moodle WHERE moodle_id=".$this->db_add_param($moodle_id, FHC_INTEGER).';';

		if ($result = $this->db_query($qry))
			return true;
		else
		{
			$this->errormsg = "Fehler beim Löschen der Daten";
			return false;
		}
	}

	/**
	 * gibt alle LE Ids der Übergebenen Moodle_Course_ID zurück
	 */
	public function getLeFromCourse($moodle_course_id)
	{
		$qry = "SELECT
					lehreinheit_id
				FROM
					addon.tbl_moodle
				WHERE
					mdl_course_id =".$this->db_add_param($moodle_course_id, FHC_INTEGER);

		$le = array();
		if ($result = $this->db_query($qry))
		{
			while($row = $this->db_fetch_object())
			{
				$le[] = $row->lehreinheit_id;
			}
		}
		return $le;
	}

	/**
	 * Schaut ob fuer diese LV/StSem schon ein
	 * Moodle Kurs existiert
	 *
	 * @param lehrveranstaltung_id
	 * @param studiensemester_kurzbz
	 * @return true wenn vorhanden, false wenn nicht
	 */
	public function course_exists_for_lv($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$qry = "SELECT
					1
				FROM
					addon.tbl_moodle
				WHERE
					lehrveranstaltung_id = ".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER)."
					AND studiensemester_kurzbz = ".$this->db_add_param($studiensemester_kurzbz);

		if ($this->db_query($qry))
		{
			if ($this->db_num_rows() > 0)
				return true;
			else
				return false;
		}
		else
		{
			$this->errormsg = 'Fehler bei einer Datenbankabfrage';
			return false;
		}
	}

	/**
	 * Schaut ob fuer diese LE schon ein Moodle
	 * Kurs existiert
	 * @param lehreinheit_id
	 * @return true wenn vorhanden, false wenn nicht
	 */
	public function course_exists_for_le($lehreinheit_id)
	{
		$qry = "SELECT 1 FROM addon.tbl_moodle WHERE lehreinheit_id=".$this->db_add_param($lehreinheit_id, FHC_INTEGER);
		if ($this->db_query($qry))
		{
			if ($this->db_num_rows()>0)
				return true;
			else
				return false;
		}
		else
		{
			$this->errormsg = 'Fehler bei Datenbankabfrage';
			return false;
		}
	}

	/**
	 * Prueft ob fuer alle Lehreinheiten dieser LV bereits ein Moodlekurs existiert
	 *
	 * @param lehrveranstaltung_id
	 * @param studiensemester_kurzbz
	 * @return true wenn vorhanden, false wenn nicht
	 */
	public function course_exists_for_allLE($lehrveranstaltung_id, $studiensemester_kurzbz)
	{
		$qry = "SELECT 1 FROM lehre.tbl_lehreinheit
				WHERE
					lehrveranstaltung_id=".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER)."
					AND studiensemester_kurzbz=".$this->db_add_param($studiensemester_kurzbz)."
					AND lehreinheit_id NOT IN (
						SELECT lehreinheit_id FROM addon.tbl_moodle
						WHERE lehreinheit_id=tbl_lehreinheit.lehreinheit_id)";

		if ($this->db_query($qry))
		{
			if ($this->db_num_rows() > 0)
				return false;
			else
				return true;
		}
		else
		{
			$this->errormsg = 'Fehler bei einer Datenbankabfrage';
			return false;
		}
	}

	/**
	 * Liefert alle Kurse dieser LV zu denen der Student
	 * zugeteilt ist
	 *
	 * @param lehrveranstaltung_id
	 * @param studiensemester_kurzbz
	 * @param student_uid
	 * @return array mit Moodle Kurs IDs
	 */
	public function getCourse($lehrveranstaltung_id, $studiensemester_kurzbz, $student_uid)
	{
		//alle betreffenden Kurse holen
		$qry = "SELECT
					tbl_lehreinheit.lehreinheit_id, mdl_course_id
				FROM
					addon.tbl_moodle
					JOIN lehre.tbl_lehreinheit USING(lehrveranstaltung_id, studiensemester_kurzbz)
				WHERE
					tbl_moodle.lehrveranstaltung_id=".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER)."
					AND tbl_moodle.studiensemester_kurzbz=".$this->db_add_param($studiensemester_kurzbz)."
				UNION
				SELECT
					tbl_lehreinheit.lehreinheit_id, mdl_course_id
				FROM
					addon.tbl_moodle
					JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
				WHERE
					tbl_lehreinheit.lehrveranstaltung_id=".$this->db_add_param($lehrveranstaltung_id, FHC_INTEGER)."
					AND tbl_lehreinheit.studiensemester_kurzbz=".$this->db_add_param($studiensemester_kurzbz);

		$courses = array();
		if ($result = $this->db_query($qry))
		{
			while ($row = $this->db_fetch_object($result))
			{
				//schauen in welchen Kursen der Student ist
				$qry = "SELECT 1 FROM campus.vw_student_lehrveranstaltung
						WHERE
							uid=".$this->db_add_param($student_uid)."
							AND lehreinheit_id=".$this->db_add_param($row->lehreinheit_id, FHC_INTEGER);

				if ($result_vw = $this->db_query($qry))
				{
					if ($this->db_num_rows($result_vw) > 0)
					{
						if (!array_key_exists($row->mdl_course_id, $courses))
						{
							$obj = new stdClass();
							$obj->mdl_course_id = $row->mdl_course_id;
							$this->result[] = $obj;
						}
					}
				}
			}
		}
		return true;
	}
}
