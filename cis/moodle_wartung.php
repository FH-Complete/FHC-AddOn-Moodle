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

$lehrveranstaltung = new lehrveranstaltung();
$lehrveranstaltung->load($lvid);

$studiengang = new studiengang();
$studiengang->load($lehrveranstaltung->studiengang_kz);

$studiensemester = new studiensemester();
$studiensemester->load($stsem);

$courseFormatOptions = LogicCourses::getCourseFormatOptions(); // Generates the parameter courseformatoptions for all courses
$startDate = LogicCourses::getStartDate($studiensemester); // Generates the parameter startdate for all courses
$endDate = LogicCourses::getEndDate($studiensemester); // Generates the parameter enddate for all courses

$numCoursesAddedToMoodle = 0;
$numCategoriesAddedToMoodle = 0;
$numCreatedUsers = 0;
$numEnrolledLectors = 0;
$numEnrolledStudents = 0;
$numCreatedGroups = 0;

$uidsToUnenrol = array();

echo '<!DOCTYPE HTML>
<html>
<head>
	<meta charset="utf-8">
	<link href="../../../skin/style.css.php" rel="stylesheet" type="text/css">
</head>

<script>
	function showLoader()
	{
		var div = document.createElement(\'div\');
		div.style.cssText = "position: fixed; top: 0; left: 0; z-index: 5000; width: 100%; height: 100%; text-align: center; background: #00000;";

		var divSpace = document.createElement(\'div\');
		divSpace.style.cssText = "height: 150px; background: #00000;";

        var img = document.createElement(\'img\');
		img.src = "/core/public/images/loader.gif";

		var divLoad = document.createElement(\'div\');
		divLoad.style.cssText = "font-size: 20px;";
		divLoad.innerHTML = "<b>Loading...</b>";

		div.appendChild(divSpace);
		div.appendChild(img);
		div.appendChild(divLoad);

        document.body.appendChild(div);
	}

	function toggleRadio()
	{
		console.log("asdfasdf");

		var chks = document.querySelectorAll("#lehreinheitencheckboxen > input[type=checkbox]");

		if (document.getElementById("radiole").checked)
		{
			for (var i = 0; i < chks.length; i++)
			{
				if (chks[i].disabled != true) chks[i].checked = false;
			}
		}
		else
		{
			for (var i = 0; i < chks.length; i++)
			{
				if (chks[i].disabled != true) chks[i].checked = true;
			}
		}
	}
</script>

<body>
<h1>'.LogicCourses::convertHtmlChars($lehrveranstaltung->bezeichnung).'&nbsp;('.LogicCourses::convertHtmlChars($stsem).')</h1>
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
		$courseFormatOptions = LogicCourses::getCourseFormatOptions(); // Generates the parameter courseformatoptions for all courses
		$startDate = LogicCourses::getStartDate($studiensemester); // Generates the parameter startdate for all courses
		$endDate = LogicCourses::getEndDate($studiensemester); // Generates the parameter enddate for all courses

		$orgform = ($lehrveranstaltung->orgform_kurzbz != '' ? $lehrveranstaltung->orgform_kurzbz : $studiengang->orgform_kurzbz);
		$shortname = $studiengang->kuerzel.'-'.$orgform.'-'.$lehrveranstaltung->semester.'-'.$stsem.'-'.$lehrveranstaltung->kurzbz;

		$course = new stdClass();
		$course->bezeichnung = $lehrveranstaltung->bezeichnung;
		$course->studiengang = $studiengang->typ.$studiengang->kurzbz;
		$course->semester = $lehrveranstaltung->semester;

		//Gesamte LV zu einem Moodle Kurs zusammenlegen
		if ($art == 'lv')
		{
			$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
				$course, $stsem, $_POST['bezeichnung'], $shortname, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
			);

			LogicCourses::insertMoodleTable(
				$moodleCourseId, null, $lvid, $stsem, date('Y-m-d H:i:s'), $user, isset($_POST['gruppen'])
			);

			$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourseId);

			LogicUsers::synchronizeLektoren(
				$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
			);

			LogicUsers::synchronizeStudenten(
				$moodleCourseId, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledStudents, $numCreatedGroups
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
				$moodleCourseId = LogicCourses::getOrCreateMoodleCourse(
					$course, $stsem, $_POST['bezeichnung'], $shortname, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle
				);

				// Fuer jede Lehreinheit einen Eintrag in VilesciDB anlegen
				foreach ($lehreinheiten as $lehreinheit_id)
				{
					LogicCourses::insertMoodleTable(
						$moodleCourseId, $lehreinheit_id, null, $stsem, date('Y-m-d H:i:s'), $user, isset($_POST['gruppen'])
					);
				}

				$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourseId);

				LogicUsers::synchronizeLektoren(
					$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
				);

				LogicUsers::synchronizeStudenten(
					$moodleCourseId, $moodleEnrolledUsers, $uidsToUnenrol, $numCreatedUsers, $numEnrolledStudents, $numCreatedGroups
				);
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

// Gruppen Syncro ein/aus schalten
if (isset($_POST['changegruppe']))
{
	if (isset($_POST['moodle_id']) && is_numeric($_POST['moodle_id']))
	{
		LogicCourses::updateGruppen($_POST['moodle_id'], isset($_POST['gruppen']));

		echo '<b>'.$p->t('moodle/datenWurdenAktualisiert').'</b><br>';
	}
	else
	{
		echo '<span class="error">'.$p->t('moodle/esWurdeKeineGueltigeIdUebergeben').'</span>';
	}
}

// Anlegen eines Testkurses
if (isset($_GET['action']) && $_GET['action'] == 'createtestkurs')
{
	$testCourseFound = false;
	$testCourses = LogicCourses::getTestCourses($lvid, $stsem);
	if (Database::rowsNumber($testCourses) > 0)
	{
		$testCourse = Database::fetchRow($testCourses);

		$moodleCourse = LogicCourses::getCourseByShortname($testCourse->coursename);
		if ($moodleCourse != null)
		{
			$testCourseFound = true;
		}
	}

	if (!$testCourseFound)
	{
		$course = new stdClass();
		$course->bezeichnung = $lehrveranstaltung->bezeichnung;
		$course->studiengang = $studiengang->typ.$studiengang->kurzbz;
		$course->semester = $stsem;

		$categoryId = LogicCourses::getOrCreateCategory('Testkurse', ADDON_MOODLE_ROOT_CATEGORY_ID, $numCategoriesAddedToMoodle);

		$moodleCourseId = LogicCourses::core_course_create_courses(
			'Testkurs - '.$lehrveranstaltung->bezeichnung, $testCourse->coursename, $categoryId, $startDate, ADDON_MOODLE_COURSE_FORMAT, $courseFormatOptions, $endDate
		);

		$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourseId);

		LogicUsers::synchronizeLektoren(
			$moodleCourseId, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors
		);

		LogicUsers::synchronizeTestStudenten($moodleCourseId, $moodleEnrolledUsers, array('student1', 'student2', 'student3'));
	}
	else
	{
		echo '<span class="error">'.$p->t('moodle/esExistiertBereitsEinTestkurs').'</span><br>';
	}
}

$coursesLvStsem = LogicCourses::coursesLehrveranstaltungStudiensemesterExists($lvid, $stsem);
$coursesAllLhStsem = LogicCourses::coursesAllLehreinheitStudiensemesterExists($lvid, $stsem);

$coursesLvStsem = Database::fetchRow($coursesLvStsem);
$coursesAllLhStsem = Database::fetchRow($coursesAllLhStsem);

if ($coursesLvStsem->count > 0 || $coursesAllLhStsem->count == 0)
{
	echo $p->t('moodle/esIstBereitsEinMoodleKursVorhanden');
}
else
{
	$db = new basis_db();

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

	$le = new lehreinheit();
	$le->load_lehreinheiten($lehrveranstaltung->lehrveranstaltung_id, $stsem);

	$lvChecked = 'checked';
	$leChecked = '';
	if (count($le->lehreinheiten) > 0)
	{
		$lvChecked = '';
		$leChecked = 'checked';
	}

	echo '<b>'.$p->t('moodle/moodleKursAnlegen').': </b><br><br>
			<form action="'.htmlentities($_SERVER['PHP_SELF']).'?lvid='.$lvid.'&stsem='.$stsem.'" method="POST">
			<input type="radio" '.$disable_lv.' name="art" value="lv" '.($art=='lv'?'checked':'').' '.$lvChecked.' onClick="toggleRadio()">'.$p->t('moodle/kursfuerganzeLV').'<br>
			<input type="radio" id="radiole" name="art" value="le" '.($art=='le'?'checked':'').' '.$leChecked.' onClick="toggleRadio()">'.$p->t('moodle/kursfuerLE').'
		  ';

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

		$coursesLehreinheitExists = LogicCourses::coursesLehreinheitExists($row->lehreinheit_id);
		$coursesLehreinheit = Database::fetchRow($coursesLehreinheitExists);

		if ($coursesLehreinheit->count > 0)
		{
			$disabled = 'disabled';
			$checked = 'checked';
		}
		else
		{
			$disabled = '';
			$checked = '';
		}

		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="checkbox" name="lehreinheit_'.$row->lehreinheit_id.'" value="'.$row->lehreinheit_id.'" '.$disabled.' '.$checked.'>'.$row->lehrform_kurzbz.' '.$gruppen.' '.$lektoren;
		echo '<br>';
	}
	echo '</div>';

	$studiengang = new studiengang();
	$studiengang->load($lehrveranstaltung->studiengang_kz);
	$orgform = ($lehrveranstaltung->orgform_kurzbz != '' ? $lehrveranstaltung->orgform_kurzbz:$studiengang->orgform_kurzbz);
	$longbezeichnung = $studiengang->kuerzel.'-'.$orgform.'-'.$lehrveranstaltung->semester.'-'.$stsem.' - '.$lehrveranstaltung->bezeichnung;

	echo '<br>'.$p->t('moodle/kursbezeichnung').': <input type="text" name="bezeichnung" maxlength="254" size="40" value="'.LogicCourses::convertHtmlChars($longbezeichnung).'">';
	echo '<br>'.$p->t('moodle/gruppenUebernehmen').': <input type="checkbox" name="gruppen">';
	echo '<br><br><input type="submit" name="neu" value="'.$p->t('moodle/kursAnlegen').'" onClick="showLoader()">
		</form>';
}
echo '</td>';

echo '<td valign="top">';
echo '<b>'.$p->t('moodle/vorhandeneMoodleKurse').'</b>';

echo '<table>';

$coursesByLehrveranstaltungLehreinheit = LogicCourses::getCoursesByLehrveranstaltungLehreinheit($lvid, $stsem);

while ($course = Database::fetchRow($coursesByLehrveranstaltungLehreinheit))
{
	$moodleCourses = LogicCourses::core_course_get_courses(array($course->mdl_course_id));

	echo '<tr>';
	echo '<td><a href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$course->mdl_course_id.'" class="Item" target="_blank">'.$moodleCourses[0]->fullname.'</a></td>';
	echo '</tr>';
}

echo '</table>';
echo '</td></tr></table>';
echo $p->t('moodle/zusatztextWartung');
echo '<br><br><br>';
echo '<b>'.$p->t('moodle/testkurse').'</b><br><br>';

// Link to create test courses
$testCourseFound = false;
$testCourses = LogicCourses::getTestCourses($lvid, $stsem);
if (Database::rowsNumber($testCourses) > 0)
{
	$testCourse = Database::fetchRow($testCourses);

	$moodleCourse = LogicCourses::getCourseByShortname($testCourse->coursename);
	if ($moodleCourse != null)
	{
		$testCourseFound = true;
		echo '<a href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$moodleCourse->id.'" class="Item" target="_blank">'.LogicCourses::convertHtmlChars($moodleCourse->fullname).'</a>';
	}
}

if (!$testCourseFound)
{
	echo "<a href='".$_SERVER['PHP_SELF']."?lvid=$lvid&stsem=$stsem&action=createtestkurs' class='Item'>".$p->t('moodle/klickenSieHierUmTestkursErstellen')."</a>";
}

echo '
</body>
</html>';
?>
