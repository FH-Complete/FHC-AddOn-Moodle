<?php
/* Copyright (C) 2013 FH Technikum-Wien
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
 * Synchronisiert die Lektoren und Studenten der aktuellen MoodleKurse
 * wenn kein aktuelles Studiensemester vorhanden ist, wird NICHT Synchronisiert
 */
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/studiensemester.class.php');
require_once('../../../include/studiengang.class.php');
require_once('../../../include/mail.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/functions.inc.php');
require_once('../config.inc.php');
require_once('../include/moodle_course.class.php');
require_once('../include/moodle_user.class.php');

$db = new basis_db();
$sync_lektoren_gesamt=0;
$sync_studenten_gesamt=0;
$group_updates=0;
$fehler=0;
$message='';
$message_lkt='';
$lektoren=array();
$nl = "\n";

// Wenn das Script nicht ueber Commandline gestartet wird, muss eine
// Authentifizierung stattfinden
if (php_sapi_name() != 'cli')
{
	$uid = get_uid();
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);

	if (!$rechte->isBerechtigt('admin'))
		die('Sie haben keine Berechtigung fuer diese Seite');
	$nl = '<br>';
}

//ini_set('soap.wsdl_cache_enabled',0);
//ini_set('soap.wsdl_cache_ttl',0);
set_time_limit(1000);
echo "-- Start ".date('Y-m-d H:i:s')."--".$nl;

//nur Synchronisieren wenn ein aktuelles Studiensemester existiert damit keine
//Probleme durch die Vorrueckung entstehen
$stsem = new studiensemester();
if ($stsem_kurzbz = $stsem->getakt())
{
	//nur die Eintraege des aktuellen Studiensemesters syncen
	$qry = "SELECT
				distinct mdl_course_id
			FROM
				addon.tbl_moodle
			WHERE
				studiensemester_kurzbz = ".$db->db_add_param($stsem_kurzbz);

	if ($result = $db->db_query($qry))
	{
		while($row = $db->db_fetch_object($result))
		{
			echo $nl."Sync fuer Kurs $row->mdl_course_id";
			flush();

			$course = new moodle_course();
			if ($course->loadMoodleCourse($row->mdl_course_id))
			{
				echo " - ".$course->mdl_fullname;
				$message_lkt='';
				//Lektoren
				$mdluser = new moodle_user();
				$mitarbeiter = $mdluser->getMitarbeiter($row->mdl_course_id);

				echo $nl."-- Mitarbeiter --";
				flush();
				if ($mdluser->sync_lektoren($row->mdl_course_id))
				{
					$sync_lektoren_gesamt += $mdluser->sync_create;
					$group_updates += $mdluser->group_update;
					if ($mdluser->sync_create>0 || $mdluser->group_update>0)
					{
						$message .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname) $course->mdl_course_id:\n".$mdluser->log."\n";
						$message_lkt .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname) $course->mdl_course_id:\n".$mdluser->log_public."\n";
					}
				}
				else
				{
					$message .= "\nFehler: $mdluser->errormsg";
					$fehler++;
				}
				echo $mdluser->log;

				//Fachbereichsleitung
				if (defined('ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG') && ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG)
				{
					echo $nl."-- Fachbereichsleitung --";
					flush();
					if ($mdluser->sync_fachbereichsleitung($row->mdl_course_id))
					{
						$sync_lektoren_gesamt += $mdluser->sync_create;
						$group_updates += $mdluser->group_update;
						if ($mdluser->sync_create > 0 || $mdluser->group_update > 0)
						{
							$message .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname) $course->mdl_course_id:\n".$mdluser->log."\n";
							$message_lkt .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname) $course->mdl_course_id:\n".$mdluser->log_public."\n";
						}
					}
					else
					{
						$message .= "\nFehler: $mdluser->errormsg";
						$fehler++;
					}
					echo $mdluser->log;
				}
				echo $nl."-- Studenten --";
				flush();

				//Studenten
				$mdluser = new moodle_user();
				if ($mdluser->sync_studenten($row->mdl_course_id))
				{
					$sync_studenten_gesamt += $mdluser->sync_create;
					$group_updates += $mdluser->group_update;
					if ($mdluser->sync_create>0 || $mdluser->group_update>0)
					{
						$message .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname):\n".$mdluser->log."\n";
						$message_lkt .= "\nKurs: $course->mdl_fullname ($course->mdl_shortname):\n".$mdluser->log_public."\n";
					}
				}
				else
				{
					$message .= "\nFehler: $mdluser->errormsg";
					$fehler++;
				}

				echo $mdluser->log;
				flush();
				foreach ($mitarbeiter as $uid)
				{
					if (!isset($lektoren[$uid]))
						$lektoren[$uid] = '';
					$lektoren[$uid] .= $message_lkt;
				}
			}
			else
			{
				$message .= "\nFehler: in der Tabelle addon.tbl_moodle wird auf den Kurs $row->mdl_course_id verwiesen, dieser existiert jedoch nicht im Moodle!";
				$fehler++;
			}
		}

		if ($sync_lektoren_gesamt>0 || $sync_studenten_gesamt>0 || $fehler>0 || $group_updates>0)
		{
			//Mail an die Lektoren
			foreach ($lektoren as $uid=>$message_lkt)
			{
				if ($message_lkt!='' && $uid!='_DummyLektor')
				{
					$header = "Dies ist eine automatische Mail!\n";
					$header.= "Es wurden folgende Aktualisierungen an Ihren Moodle-Kursen durchgeführt:\n\n";

					$to = "$uid@".DOMAIN;

					$mail = new mail($to, 'vilesci@'.DOMAIN,'Moodle - Aktualisierungen',$header.$message_lkt);
					if ($mail->send())
						echo "Mail wurde an $to versandt".$nl;
					else
						echo "Fehler beim Senden des Mails an $to".$nl;
				}
			}
			//Mail an Admin
			$header = "Dies ist eine automatische Mail!\n";
			$header.= "Folgende Syncros mit den MoodleKursen wurde durchgeführt:\n\n";
			$header.= "Anzahl der aktualisierten Lektoren: $sync_lektoren_gesamt\n";
			$header.= "Anzahl der aktualisierten Studenten: $sync_studenten_gesamt\n";
			$header.= "Anzahl der Fehler: $fehler\n";

			$to = MAIL_ADMIN;

			$mail = new mail($to, 'vilesci@'.DOMAIN,'Moodle Syncro',$header.$message);
			if ($mail->send())
				echo "Mail wurde an $to versandt:".$nl.$header;
			else
				echo "Fehler beim Senden des Mails an $to:".$nl.$header;
		}
		else
		{
			echo $nl.'Alle Zuteilungen sind auf dem neuesten Stand';
		}
	}
	else
	{
		echo $nl.'Fehler bei Select:'.$qry;
	}
}
else
	echo $nl."Kein aktuelles Studiensemester vorhanden->kein Syncro";
echo $nl."-- Ende ".date('Y-m-d H:i:s')." --".$nl;
?>
