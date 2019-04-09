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
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 */
/*
 * Verwaltung der Moodlekurse zu einer LV
 */
require_once(dirname(__FILE__).'/../lib/LogicCourses.php'); // A lot happens here!
require_once(dirname(__FILE__).'/../lib/LogicUsers.php'); // A lot happens here!

require_once('../../../include/phrasen.class.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/lehreinheit.class.php');
require_once('../../../include/lehreinheitgruppe.class.php');
require_once('../../../include/lehrveranstaltung.class.php');
require_once('../../../include/lehreinheitmitarbeiter.class.php');

$sprache = getSprache();
$p = new phrasen($sprache);

if (!$user = get_uid()) die($p->t('moodle/sieSindNichtAngemeldet').' !');

if (isset($_GET['lvid']) && is_numeric($_GET['lvid']))
	$lvid = $_GET['lvid'];
else
	die($p->t('moodle/lvidMussUebergebenWerden'));

if (isset($_GET['stsem']) && check_stsem($_GET['stsem']))
	$stsem = $_GET['stsem'];
else
	die($p->t('moodle/esWurdeKeinStudiensemesterUebergeben'));

$art = (isset($_POST['art']) ? $_POST['art'] : 'lv');

//Pruefen ob Rechte fuer diese LV vorhanden sind
$lem = new lehreinheitmitarbeiter();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

$berechtigt = false;

if ($rechte->isBerechtigt('addon/moodle')) $berechtigt = true;


if ($lem->existsLV($lvid, $stsem, $user)) $berechtigt = true;

if (!$berechtigt)
{
	die('Sie haben keine Berechtigung für diese Seite');
}

$lv = new lehrveranstaltung();
$lv->load($lvid);

echo '<!DOCTYPE HTML>
<html>
<head>
	<meta charset="utf-8">
	<link href="../../../skin/style.css.php" rel="stylesheet" type="text/css">
</head>
<body onload="togglediv()">
<h1>'.LogicCourses::convertHtmlChars($lv->bezeichnung).'&nbsp;('.LogicCourses::convertHtmlChars($stsem).')</h1>
<table width="100%">
<tr>
<td valign="top">';

if (isset($_POST['neu']))
{
	if ($_POST['bezeichnung'] == '')
	{
		echo '<span class="error">'.$p->t('moodle/bezeichnungMussEingegebenWerden').'</span><br>';
	}
	else
	{
		$lehrveranstaltung = new lehrveranstaltung();
		$lehrveranstaltung->load($lvid);
		$studiengang = new studiengang();
		$studiengang->load($lehrveranstaltung->studiengang_kz);

		$orgform = ($lehrveranstaltung->orgform_kurzbz != '' ? $lehrveranstaltung->orgform_kurzbz : $studiengang->orgform_kurzbz);
		$shortname = $studiengang->kuerzel.'-'.$orgform.'-'.$lehrveranstaltung->semester.'-'.$stsem.'-'.$lehrveranstaltung->kurzbz;

		//Gesamte LV zu einem Moodle Kurs zusammenlegen
		if ($art == 'lv')
		{
			$courseFormatOptions = LogicCourses::getCourseFormatOptions(); // Generates the parameter courseformatoptions for all courses
			$startDate = LogicCourses::getStartDate($studiensemester); // Generates the parameter startdate for all courses
			$endDate = LogicCourses::getEndDate($studiensemester); // Generates the parameter enddate for all courses

			$course = new stdClass();
			$course->bezeichnung = $lv->bezeichnung;
			$course->studiengang = $studiengang->typ.$studiengang->kurzbz;
			$course->semester = $lv->semester;

			$numCoursesAddedToMoodle = 0;
			$numCategoriesAddedToMoodle = 0;

			$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
				$course, $stsem, $_POST['bezeichnung'], $shortname, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
			);

			LogicCourses::insertMoodleTable(
				$moodleCourseId, null, $lvid, $stsem, date('Y-m-d H:i:s'), $user, isset($_POST['gruppen'])
			);

			$numCreatedUsers = 0;
			$numEnrolledLectors = 0;

			$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourseId);

			LogicUsers::synchronizeLektoren(
				$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
			);

			$numCreatedUsers = 0;
			$numEnrolledStudents = 0;
			$numCreatedGroups = 0;

			LogicUsers::synchronizeStudenten(
				$moodleCourseId, $moodleEnrolledUsers, array(), $numCreatedUsers, $numEnrolledStudents, $numCreatedGroups
			);
		}
		elseif ($art == 'le') //Getrennte Kurse fuer die Lehreinheiten
		{
			$lehreinheiten = array();

			foreach ($_POST as $key => $value)
			{
				if (mb_strstr($key, 'lehreinheit_'))
				{
					$shortname .= '/'.$value;
					$lehreinheiten[] = $value;
				}
			}

			if (count($lehreinheiten) > 0)
			{
				$mdl_course = new moodle_course();

				$mdl_course->mdl_fullname = $_POST['bezeichnung'];
				$mdl_course->mdl_shortname = $shortname;
				$mdl_course->studiensemester_kurzbz = $stsem;
				$mdl_course->insertamum = date('Y-m-d H:i:s');
				$mdl_course->insertvon = $user;
				$mdl_course->lehreinheit_id = $lehreinheiten[0];
				$mdl_course->gruppen = isset($_POST['gruppen']);

				//Kurs im Moodle anlegen
				if ($mdl_course->create_moodle())
				{
					//fuer jede Lehreinheit einen Eintrag in VilesciDB anlegen
					foreach ($lehreinheiten as $value)
					{
						$mdl_course->lehreinheit_id = $value;
						if (!$mdl_course->create_vilesci())
							echo '<br>'.$p->t('moodle/fehlerBeimAnlegenAufgetreten').':'.$mdl_course->errormsg;
					}

					$mdl_user = new moodle_user();
					//Lektoren Synchronisieren
					if (!$mdl_user->sync_lektoren($mdl_course->mdl_course_id))
						echo $mdl_user->errormsg;

					$mdl_user = new moodle_user();
					//Studenten Synchronisieren
					if (!$mdl_user->sync_studenten($mdl_course->mdl_course_id))
						echo $mdl_user->errormsg;
				}
				else
					echo '<span class="error">Failed:'.$mdl_course->errormsg.'</span>';
			}
			else
			{
				echo '<span class="error">'.$p->t('moodle/esMussMindestensEineLehreinheitMarkiertSein').'</span><br>';
			}
		}
		else
			die($p->t('moodle/artIstUnbekannt'));
	}
}
//Gruppen Syncro ein/aus schalten
if (isset($_POST['changegruppe']))
{
	if (isset($_POST['moodle_id']) && is_numeric($_POST['moodle_id']))
	{
		$mcourse = new moodle_course();
		if ($mcourse->updateGruppenSync($_POST['moodle_id'], isset($_POST['gruppen'])))
			echo '<b>'.$p->t('moodle/datenWurdenAktualisiert').'</b><br>';
		else
			echo '<span class="error">'.$p->t('global/fehlerBeimAktualisierenDerDaten').'</span>';
	}
	else
	{
		echo '<span class="error">'.$p->t('moodle/esWurdeKeineGueltigeIdUebergeben').'</span>';
	}
}

//Anlegen eines Testkurses
if (isset($_GET['action']) && $_GET['action']=='createtestkurs')
{
	$mdl_course = new moodle_course();
	if (!$mdl_course->loadTestkurs($lvid, $stsem))
	{
		$lehrveranstaltung = new lehrveranstaltung();
		$lehrveranstaltung->load($lvid);
		$studiengang = new studiengang();
		$studiengang->load($lehrveranstaltung->studiengang_kz);

		//Kurzbezeichnung generieren
		$shortname = mb_strtoupper('TK-'.$stsem.'-'.$studiengang->kuerzel.'-'.$lehrveranstaltung->semester.'-'.$lehrveranstaltung->kurzbz);

		$mdl_course->lehrveranstaltung_id = $lvid;
		$mdl_course->studiensemester_kurzbz = $stsem;
		$mdl_course->mdl_fullname = 'Testkurs - '.$lehrveranstaltung->bezeichnung;
		$mdl_course->mdl_shortname = $shortname;

		//TestKurs erstellen
		if ($mdl_course->createTestkurs($lvid, $stsem))
		{
			$id = $mdl_course->mdl_course_id;
			$errormsg = '';

			$mdl_user = new moodle_user();
			//Lektoren zuweisen
			if (!$mdl_user->sync_lektoren($id, $lvid, $stsem))
				$errormsg .= $p->t('moodle/fehlerBeiDerLektorenZuordnung').':'.$mdl_user->errormsg.'<br>';
			//Teststudenten zuweisen
			if (!$mdl_user->createTestStudentenZuordnung($id))
				$errormsg .= $p->t('moodle/fehlerBeiDerStudentenZuordnung').':'.$mdl_user->errormsg.'<br>';

			if ($errormsg != '')
				echo $errormsg;
			else
				echo '<b>'.$p->t('moodle/testkursWurdeErfolgreichAngelegt').'</b><br>';
		}
	}
	else
	{
		echo '<span class="error">'.$p->t('moodle/esExistiertBereitsEinTestkurs').'</span><br>';
	}
}

$moodle = new moodle_course();
if ($moodle->course_exists_for_lv($lvid, $stsem) || $moodle->course_exists_for_allLE($lvid, $stsem))
{
	echo $p->t('moodle/esIstBereitsEinMoodleKursVorhanden');
}
else
{
	//wenn bereits ein Moodle Kurs fuer eine Lehreinheit angelegt wurde, dann dass
	//anlegen fuer die Lehrveranstaltung verhindern
	$qry = "SELECT 1 FROM lehre.tbl_moodle
			WHERE lehreinheit_id in(SELECT lehreinheit_id FROM lehre.tbl_lehreinheit
									WHERE lehrveranstaltung_id=".$db->db_add_param($lvid, FHC_INTEGER)."
									AND studiensemester_kurzbz=".$db->db_add_param($stsem).")";
	$disable_lv = '';
	if ($result = $db->db_query($qry))
		if ($db->db_num_rows($result)>0)
		{
			$disable_lv = 'disabled="true"';
			//wenn schon ein Moodle Kurs zu einer Lehreinheit angelegt wurde,
			//dann ist standardmaessig die Lehreinheit markiert
			if ($art == 'lv')
				$art = 'le';
		}

	echo '<b>'.$p->t('moodle/moodleKursAnlegen').': </b><br><br>
			<form action="'.htmlentities($_SERVER['PHP_SELF']).'?lvid='.$lvid.'&stsem='.$stsem.'" method="POST">
			<input type="radio" '.$disable_lv.' name="art" value="lv" onclick="togglediv()" '.($art=='lv'?'checked':'').'>'.$p->t('moodle/kursfuerganzeLV').'<br>
			<input type="radio" id="radiole" name="art" value="le" onclick="togglediv()" '.($art=='le'?'checked':'').'>'.$p->t('moodle/kursfuerLE').'
		  ';

	$le = new lehreinheit();
	$le->load_lehreinheiten($lv->lehrveranstaltung_id, $stsem);
	echo '<div id="lehreinheitencheckboxen">';
	foreach ($le->lehreinheiten as $row)
	{
		//Gruppen laden
		$gruppen = '';

		$lehreinheitgruppe = new lehreinheitgruppe();
		$lehreinheitgruppe->getLehreinheitgruppe($row->lehreinheit_id);
		foreach ($lehreinheitgruppe->lehreinheitgruppe as $grp)
		{
			if ($grp->gruppe_kurzbz == '')
				$gruppen .= ' '.$grp->semester.$grp->verband.$grp->gruppe;
			else
				$gruppen .= ' '.$grp->gruppe_kurzbz;
		}

		//Lektoren laden
		$lektoren = '';
		$lehreinheitmitarbeiter = new lehreinheitmitarbeiter();
		$lehreinheitmitarbeiter->getLehreinheitmitarbeiter($row->lehreinheit_id);

		foreach ($lehreinheitmitarbeiter->lehreinheitmitarbeiter as $ma)
		{
			$benutzer = new benutzer();
			$benutzer->load($ma->mitarbeiter_uid);
			$lektoren .= ' '.$benutzer->vorname.' '.$benutzer->nachname;
		}

		if ($moodle->course_exists_for_le($row->lehreinheit_id))
			$disabled = 'disabled';
		else
			$disabled = '';
		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="checkbox" name="lehreinheit_'.$row->lehreinheit_id.'" value="'.$row->lehreinheit_id.'" '.$disabled.'>'.$row->lehrform_kurzbz.' '.$gruppen.' '.$lektoren;
		echo '<br>';
	}
	echo '</div>';

	$studiengang = new studiengang();
	$studiengang->load($lv->studiengang_kz);
	$orgform = ($lv->orgform_kurzbz != ''?$lv->orgform_kurzbz:$studiengang->orgform_kurzbz);
	$longbezeichnung = $studiengang->kuerzel.'-'.$orgform.'-'.$lv->semester.'-'.$stsem.' - '.$lv->bezeichnung;

	echo '<br>'.$p->t('moodle/kursbezeichnung').': <input type="text" name="bezeichnung" maxlength="254" size="40" value="'.LogicCourses::convertHtmlChars($longbezeichnung).'">';
	echo '<br>'.$p->t('moodle/gruppenUebernehmen').': <input type="checkbox" name="gruppen">';
	echo '<br><br><input type="submit" name="neu" value="'.$p->t('moodle/kursAnlegen').'">
			</form>';
}
echo '</td>';

echo '<td valign="top">';
echo '<b>'.$p->t('moodle/vorhandeneMoodleKurse').'</b>';
if (!$moodle->getAll($lvid, $stsem))
	echo $moodle->errormsg;
echo '<table>';
foreach ($moodle->result as $course)
{
	$mdlcourse = new moodle_course();
	$mdlcourse->loadMoodleCourse($course->mdl_course_id);
	echo '<tr>';
	echo '<td><a href="'.ADDON_MOODLE_PATH.'course/view.php?id='.$course->mdl_course_id.'" class="Item" target="_blank">'.$mdlcourse->mdl_fullname.'</a></td>';
}
echo '</table>';
echo '</td></tr></table>';
echo $p->t('moodle/zusatztextWartung');
// echo '<br><br><br>';
echo '<b>'.$p->t('moodle/testkurse').'</b><br><br>';
$mdlcourse = new moodle_course();
if ($mdlcourse->loadTestkurs($lvid, $stsem))
{
	echo '<a href="'.ADDON_MOODLE_PATH.'course/view.php?id='.$mdlcourse->mdl_course_id.'" class="Item" target="_blank">'.LogicCourses::convertHtmlChars($mdlcourse->mdl_fullname).'</a>';
}
else
{
	echo "<a href='".$_SERVER['PHP_SELF']."?lvid=$lvid&stsem=$stsem&action=createtestkurs' class='Item'>".$p->t('moodle/klickenSieHierUmTestkursErstellen')."</a>";
}
echo '
</body>
</html>';
?>
