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
 * Authors: Andreas Oesterreicher 	< andreas.oesterreicher@technikum-wien.at >
 */
/**
 * Legt fuer jede Lehreinheit im aktuellen Semester einen Moodle Kurs an
 * falls noch keiner vorhanden ist
 * und teilt Lektoren und Studierende zu dem Kurs zu
 * Aber nur wenn die Lehrform der Lehreinheit=Lehrform der LV
 */
require_once('../../../config/cis.config.inc.php');
require_once('../../../include/studiensemester.class.php');
require_once('../../../include/studiengang.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../config.inc.php');
require_once('../include/moodle_course.class.php');
require_once('../include/moodle_user.class.php');

$nl = "\n";

// Wenn das Script nicht ueber Commandline gestartet wird, muss eine
// Authentifizierung stattfinden
if(php_sapi_name() != 'cli')
{
	$uid = get_uid();
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);

	if(!$rechte->isBerechtigt('admin'))
		die('Sie haben keine Berechtigung fuer diese Seite');

	$nl = '<br>';
}

set_time_limit(10000);

$db = new basis_db();

$stsem_obj = new studiensemester();
$stsem = $stsem_obj->getAktOrNext();
$neue_kurse = 0;
$vorhandene_kurse = 0;
$anzahl_fehler = 0;


$qry = "SELECT
			distinct lehrveranstaltung_id, tbl_lehrveranstaltung.bezeichnung, tbl_lehrveranstaltung.kurzbz,
			tbl_lehrveranstaltung.studiengang_kz, tbl_lehrveranstaltung.orgform_kurzbz, tbl_lehrveranstaltung.semester,
			tbl_lehreinheit.lehreinheit_id, trim(string_agg(vorname||nachname,'_')) AS lektoren
		FROM
			lehre.tbl_lehreinheit
			JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
			JOIN lehre.tbl_lehreinheitmitarbeiter USING (lehreinheit_id)
			JOIN public.tbl_mitarbeiter USING (mitarbeiter_uid)
			JOIN public.tbl_benutzer ON (uid=mitarbeiter_uid)
			JOIN public.tbl_person USING (person_id)
		WHERE
			studiensemester_kurzbz=".$db->db_add_param($stsem)."
			AND semester is not null
			AND semester!=0
			AND tbl_lehreinheit.lehrform_kurzbz=tbl_lehrveranstaltung.lehrform_kurzbz
			AND uid not like '_Dummy%'
		GROUP BY lehrveranstaltung_id, tbl_lehrveranstaltung.bezeichnung, tbl_lehrveranstaltung.kurzbz,
			tbl_lehrveranstaltung.studiengang_kz, tbl_lehrveranstaltung.orgform_kurzbz, tbl_lehrveranstaltung.semester,
			tbl_lehreinheit.lehreinheit_id
		ORDER BY lehrveranstaltung_id
		";

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$mdl_course = new moodle_course();

		if(!$mdl_course->course_exists_for_le($row->lehreinheit_id))
		{
			$studiengang = new studiengang();
			$studiengang->load($row->studiengang_kz);

			$shortname = $studiengang->kuerzel.($row->orgform_kurzbz!=''?'-'.$row->orgform_kurzbz:'').($row->semester!=''?'-'.$row->semester:'').'-'.$stsem.'-'.$row->kurzbz.'-'.$row->lehreinheit_id.'-'.$row->lektoren;
			$bezeichnung = $studiengang->kuerzel.($row->orgform_kurzbz!=''?'-'.$row->orgform_kurzbz:'').($row->semester!=''?'-'.$row->semester:'').'-'.$stsem.'-'.$row->bezeichnung.'-'.$row->lehreinheit_id.'-'.$row->lektoren;

			// Bezeichnung kuerzen wenn zu lange
			$shortname = mb_substr($shortname,0,254);
			$bezeichnung = mb_substr($bezeichnung,0,254);

			$mdl_course->studiensemester_kurzbz = $stsem;
			$mdl_course->lehreinheit_id = $row->lehreinheit_id;
			$mdl_course->mdl_fullname = $bezeichnung;
			$mdl_course->mdl_shortname = $shortname;
			$mdl_course->insertamum = date('Y-m-d H:i:s');
			$mdl_course->insertvon = 'auto';
			$mdl_course->gruppen = true;

			echo $nl."Create Course: $bezeichnung";

			//Moodlekurs anlegen
			if($mdl_course->create_moodle())
			{
				$neue_kurse++;
				//Eintrag in der Vilesci DB
				$mdl_course->create_vilesci();

				$mdl_user = new moodle_user();
				//Lektoren Synchronisieren
				if(!$mdl_user->sync_lektoren($mdl_course->mdl_course_id))
				{
					$anzahl_fehler++;
					echo $nl."Lektor Sync Failed:".$mdl_user->errormsg;
				}

				if(defined('ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG') && ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG)
				{
					$mdl_user = new moodle_user();
					//Fachbereichsleitung Synchronisieren
					if(!$mdl_user->sync_fachbereichsleitung($mdl_course->mdl_course_id))
					{
						$anzahl_fehler++;
						echo $nl."FBL Sync Failed:".$mdl_user->errormsg;
					}
				}

				$mdl_user = new moodle_user();
				//Studenten Synchronisieren
				if(!$mdl_user->sync_studenten($mdl_course->mdl_course_id))
				{
					$anzahl_fehler++;
					echo $nl."Student Sync Failed:".$mdl_user->errormsg;
				}
			}
			else
			{
				$anzahl_fehler++;
				echo $nl."Failed:".$mdl_course->errormsg;
			}
		}
		else
		{
			$vorhandene_kurse++;
		}
	}
}
echo $nl."Fertig";
echo $nl."Neue Kurse:".$neue_kurse;
echo $nl."Vorhandene Kurse:".$vorhandene_kurse;
echo $nl."Anzahl Fehler:".$anzahl_fehler;
echo $nl;
