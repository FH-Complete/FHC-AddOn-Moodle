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
	<link href="../skin/cis.css" rel="stylesheet" type="text/css">
	<link href="../../../skin/style.css.php" rel="stylesheet" type="text/css">
	<link rel="stylesheet" type="text/css" href="../../../skin/jquery-ui-1.9.2.custom.min.css">
	<script type="text/javascript" src="../../../vendor/jquery/jqueryV1/jquery-1.12.4.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
</head>

<script>

	function showLoader()
	{
		var div = document.createElement(\'div\');
		div.style.cssText = "position: fixed; top: 0; left: 0; z-index: 5000; width: 100%; height: 100%; text-align: center; background-color: white;opacity: 0.5;";

		var divSpace = document.createElement(\'div\');
		divSpace.style.cssText = "height: 150px";

		var img = document.createElement(\'img\');
		img.src = "../../../public/images/loader.gif";

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
		var chks = document.querySelectorAll("#lehreinheitencheckboxen > input[type=checkbox]");

		if (document.getElementById("radiole").checked)
		{
			for (var i = 0; i < chks.length; i++)
			{
				chks[i].checked = false;
			}
		}
		else
		{
			for (var i = 0; i < chks.length; i++)
			{
				chks[i].checked = true;
			}
		}
	}
';

if(defined('ADDON_MOODLE_COURSE_NAME_LEKTOR') && ADDON_MOODLE_COURSE_NAME_LEKTOR)
{
	echo '
	function ChangeLE()
	{
		var chks = document.querySelectorAll("#lehreinheitencheckboxen > input[type=checkbox]");
		var lektor_arr = Array();
		var lehreinheit_arr = Array();
		var lektor = " -";
		var lehreinheit = "";

		if (document.getElementById("radiole").checked)
		{
			for (var i = 0; i < chks.length; i++)
			{
				if(chks[i].checked)
				{
					if(lektor_arr.indexOf(chks[i].getAttribute("data-lektor"))<0)
					{
						lektor_arr.push(chks[i].getAttribute("data-lektor"));
					}
					lehreinheit_arr.push(chks[i].getAttribute("data-lehreinheit"));
				}
			}
		}

		for(i in lehreinheit_arr)
		{
			lehreinheit = lehreinheit + " - " + lehreinheit_arr[i];
		}

		for(i in lektor_arr)
		{
			lektor = lektor + " " + lektor_arr[i];
		}
		var bezeichnung = document.getElementById("bezeichnung_default").value;
		document.getElementById("bezeichnung").value = bezeichnung + lehreinheit + lektor;
	}
	';
}
else
{
	echo '
	function ChangeLE()
	{
	}
	';
}
echo '
</script>


<body>
<h1>'.LogicCourses::convertHtmlChars($lehrveranstaltung->bezeichnung).'&nbsp;('.LogicCourses::convertHtmlChars($stsem).')</h1>
<table width="100%">
<tr>
<td valign="top">';

$mdl_source_course_copy_state = [];

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

		if (isset($_POST['qk']) && intval($_POST['qk']) > 0) {
			$logicTemplates = new LogicTemplates();
			$sourceCourse = $logicTemplates->getSourceCourse(intval($_POST['qk']));
			$lang = new sprache();
			$lang->load($sourceCourse->template_sprache);
			$shortname .= '-' . strtoupper(current(explode('-', $lang->locale)));
		}

		//Gesamte LV zu einem Moodle Kurs zusammenlegen
		if ($art == 'lv')
		{
			if (LogicCourses::isStandardized($lehrveranstaltung)) {
				if (!isset($_POST['qk'])) {
					if (MOODLE_ADDON_CREATE_COURSE_FOR_LVTEMPLATE_WITHOUT_QUELLKURS) {
						echo '<span class="warning">' . $p->t('moodle/warn.sourcecourse.missing') . '</span><br>';
						LogicCourses::createMoodleCourseAndLinkIt($shortname, $lvid, [], $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle);
					} else {
						echo '<span class="error">' . $p->t('moodle/error.sourcecourse.missing') . '</span><br>';
					}
				} else {
					$mdl_source_course_id = $_POST['qk'];
					if (LogicCourses::isValidSourceCourse($mdl_source_course_id)) {
						$mdl_course_id = LogicCourses::createMoodleCourseAndLinkIt($shortname, $lvid, [], $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle, $mdl_source_course_id);
						$mdl_source_course_copy_state[$mdl_course_id] = LogicCourses::startSourceCourseCopy($mdl_source_course_id, $mdl_course_id);
					} else {
						echo '<span class="error">' . $p->t('moodle/error.sourcecourse.invalid', [$mdl_source_course_id]) . '</span><br>';
					}
				}
			} else {
				LogicCourses::createMoodleCourseAndLinkIt($shortname, $lvid, [], $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle);
			}
		}
		elseif ($art == 'le') //Getrennte Kurse fuer die Lehreinheiten
		{
			$lehreinheiten = array();

			foreach ($_POST as $key => $value)
			{
				if (mb_strstr($key, 'lehreinheit_'))
				{
					$lehreinheiten[] = $value;
				}
			}

			if (!count($lehreinheiten)) {
				echo '<span class="error">'.$p->t('moodle/esMussMindestensEineLehreinheitMarkiertSein').'</span><br>';
			} else {
				if (LogicCourses::isStandardized($lehrveranstaltung)) {
					if (!isset($_POST['qk'])) {
						if (MOODLE_ADDON_CREATE_COURSE_FOR_LVTEMPLATE_WITHOUT_QUELLKURS) {
							echo '<span class="warning">' . $p->t('moodle/warn.sourcecourse.missing') . '</span><br>';
							LogicCourses::createMoodleCourseAndLinkIt($shortname, null, $lehreinheiten, $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle);
						} else {
							echo '<span class="error">' . $p->t('moodle/error.sourcecourse.missing') . '</span><br>';
						}
					} else {
						$mdl_source_course_id = $_POST['qk'];
						if (LogicCourses::isValidSourceCourse($mdl_source_course_id)) {
							$mdl_course_id = LogicCourses::createMoodleCourseAndLinkIt($shortname, null, $lehreinheiten, $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle, $mdl_source_course_id);
							$mdl_source_course_copy_state[$mdl_course_id] = LogicCourses::startSourceCourseCopy($mdl_source_course_id, $mdl_course_id);
						} else {
							echo '<span class="error">' . $p->t('moodle/error.sourcecourse.invalid', [$mdl_source_course_id]) . '</span><br>';
						}
					}
				} else {
					LogicCourses::createMoodleCourseAndLinkIt($shortname, null, $lehreinheiten, $course, $stsem, $user, $startDate, $courseFormatOptions, $endDate, $numCoursesAddedToMoodle, $numCategoriesAddedToMoodle);
				}
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
		$TKcategoryId = LogicCourses::getOrCreateCategory('Testkurse zu LVs', $categoryId, $numCategoriesAddedToMoodle);

		$template = null;
		$stop = false;
		if (LogicCourses::isStandardized($lehrveranstaltung)) {
			$template = new lehrveranstaltung();
			$template->load($lehrveranstaltung->lehrveranstaltung_template_id);

			if (!isset($_GET['qk'])) {
				if (MOODLE_ADDON_CREATE_COURSE_FOR_LVTEMPLATE_WITHOUT_QUELLKURS) {
					echo '<span class="warning">' . $p->t('moodle/warn.sourcecourse.missing') . '</span><br>';
					$template = null;
				} else {
					echo '<span class="error">' . $p->t('moodle/error.sourcecourse.missing') . '</span><br>';
					$stop = true;
				}
			} else {
				$mdl_source_course_id = $_GET['qk'];
				if (!LogicCourses::isValidSourceCourse($mdl_source_course_id)) {
					echo '<span class="error">' . $p->t('moodle/error.sourcecourse.invalid', [$mdl_source_course_id]) . '</span><br>';
					$stop = true;
				}
			}
		}

		if (!$stop) {
			$moodleCourseId = LogicCourses::core_course_create_courses(
				'Testkurs - '.$lehrveranstaltung->bezeichnung, $testCourse->coursename, $TKcategoryId, $startDate, ADDON_MOODLE_COURSE_FORMAT, $courseFormatOptions, $endDate
			);

			$moodleEnrolledUsers = LogicUsers::core_enrol_get_enrolled_users($moodleCourseId);

			LogicUsers::synchronizeTestLektoren($moodleCourseId, $lvid, $stsem, $moodleEnrolledUsers, $numCreatedUsers, $numEnrolledLectors);

			LogicUsers::synchronizeTestStudenten($moodleCourseId, $moodleEnrolledUsers, array('student1', 'student2', 'student3'));

			if ($template) {
				$mdl_source_course_copy_state[$moodleCourseId] = LogicCourses::startSourceCourseCopy($mdl_source_course_id, $moodleCourseId);
			}
		}
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
	$qry = "SELECT 1 FROM addon.tbl_moodle
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
			<input type="radio" '.$disable_lv.' name="art" value="lv" '.($art=='lv'?'checked':'').' '.$lvChecked.' onClick="toggleRadio()" data-lang="'.$lehrveranstaltung->sprache.'">'.$p->t('moodle/kursfuerganzeLV').'<br>
			<input type="radio" id="radiole" name="art" value="le" '.($art=='le'?'checked':'').' '.$leChecked.' onClick="toggleRadio()">'.$p->t('moodle/kursfuerLE').'
		  ';

	echo '<div id="lehreinheitencheckboxen">';
	foreach ($le->lehreinheiten as $row)
	{
		if ( in_array($row->lehrform_kurzbz, $fhc_moodle_wartung_ignore_le_typ) ) 
		{
			continue;
		}
		
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
			$checked = '';
		}
		else
		{
			$disabled = '';
			$checked = '';
		}

		echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="checkbox" onclick="ChangeLE()"
					data-lang="'.$row->sprache.'"
					data-lektor="'.trim($lektoren).'"
					data-lehreinheit="'.$row->lehreinheit_id.'"
					id="lehreinheit_'.$row->lehreinheit_id.'"
					name="lehreinheit_'.$row->lehreinheit_id.'"
					value="'.$row->lehreinheit_id.'" '.$disabled.' '.$checked.'>
				'.$row->lehrform_kurzbz.' '.$gruppen.' '.$lektoren;
		echo '<br>';
	}
	echo '</div>';

	$studiengang = new studiengang();
	$studiengang->load($lehrveranstaltung->studiengang_kz);
	$orgform = ($lehrveranstaltung->orgform_kurzbz != '' ? $lehrveranstaltung->orgform_kurzbz:$studiengang->orgform_kurzbz);

	$longbezeichnung = sprintf(ADDON_MOODLE_COURSE_NAME,
		$studiengang->kuerzel,
		$orgform,
		$lehrveranstaltung->semester,
		$stsem,
		$lehrveranstaltung->bezeichnung
	);

	echo '<input type="hidden" name="bezeichnung_default" id="bezeichnung_default" value="'.LogicCourses::convertHtmlChars($longbezeichnung).'">';
	echo '<br>'.$p->t('moodle/kursbezeichnung').': <input type="text" name="bezeichnung" id="bezeichnung" maxlength="254" size="40" value="'.LogicCourses::convertHtmlChars($longbezeichnung).'">';
	if (LogicCourses::isStandardized($lehrveranstaltung)) {
		$logicTemplates = new LogicTemplates();
		$template = $logicTemplates->getTemplate($lehrveranstaltung->lehrveranstaltung_template_id);

		echo '<br>'.$p->t('moodle/quellkurs').': ';
		if ($template && isset($template->mdl_courses)) {
			if ($template->mdl_courses) {
				echo '<select name="qk" id="qk">';
				foreach ($template->mdl_courses as $lang => $qk) {
					$moodleCourses = LogicCourses::core_course_get_courses([$qk]);
					$bez = isset($moodleCourses[0]) ? $moodleCourses[0]->fullname : '';
					echo '<option value="' . $qk . '" data-language="' . $lang . '">' . $bez . ' (' . $lang . ')</option>';
				}
				echo '</select>';
			} else {
				echo '<span class="error">' . $p->t('moodle/error.sourcecourse.empty') . '</span>';
			}
		}
		echo '<div id="lang-warning-multiple" class="error" style="display:none">' . $p->t('moodle/warn.lang.multiple') . '</div>';
		echo '<script type="text/javascript">
			function refreshQk() {
				var all = $("input[name=\"art\"][value=\"lv\"]"),
					defaultLang = all.data("lang"),
					newVal = null;
				$("#lang-warning-multiple").hide();
				if (all.is(":checked")) {
					if ($("#lehreinheitencheckboxen input[data-lang!=\"" + defaultLang + "\"]").length) {
						$("#lang-warning-multiple").show();
					}
					newVal = $("#qk [data-language=\"" + defaultLang + "\"]").val();
				} else {
					var langs = [];
					$("#lehreinheitencheckboxen input:checked").each(function() {
						langs.push($(this).data("lang"));
					});
					langs = Array.from(new Set(langs));
					if (langs.length > 1) {
						$("#lang-warning-multiple").show();
					}
					newVal = $("#qk [data-language=\"" + langs.pop() + "\"]").val();
				}
				if (!newVal && $("#qk").children().length == 1 && $("#lehreinheitencheckboxen input").length == $("#lehreinheitencheckboxen input[data-lang=\"" + defaultLang + "\"]").length) {
					// NOTE(chris): The select would be empty, there is only 1 sourcecourse and all languages are the same (except the sourcecourse one) => select the only option
					newVal = $("#qk").children().first().val();
				}
				$("#qk").val(newVal);
			}
			$("input[name=\"art\"],#lehreinheitencheckboxen input").change(refreshQk);
		</script>';
	}
	if(defined('ADDON_MOODLE_COURSE_GRUPPEN') && ADDON_MOODLE_COURSE_GRUPPEN==true)
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

	$courseName = '';
	if (count($moodleCourses) > 0) $courseName = $moodleCourses[0]->fullname;

	$copyState = false;
	if (isset($mdl_source_course_copy_state[$course->mdl_course_id]) && $mdl_source_course_copy_state[$course->mdl_course_id]) {
		$copyState = $mdl_source_course_copy_state[$course->mdl_course_id];
	} elseif (LogicCourses::isStandardized($lehrveranstaltung)) {
		$copyState = LogicCourses::getSourceCourseCopyState($course->mdl_course_id);
	}

	echo '<tr>';

	if ($courseName != '')
	{
		if ($copyState) {
			echo '<td><a data-state-unzipid="' . $copyState->unzipid . '" data-state-contextid="' . $copyState->contextid . '" data-state-restoreid="' . (property_exists($copyState, 'restoreid') ? $copyState->restoreid : '') . '" href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$course->mdl_course_id.'" class="Item" target="_blank">'.$courseName.'</a></td>';
		} else {
			echo '<td><a href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$course->mdl_course_id.'" class="Item" target="_blank">'.$courseName.'</a></td>';
		}
	}
	else
	{
		echo '<td>Moodle course with id '.$course->mdl_course_id.' not found</td>';
	}

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
		$copyState = false;
		if (isset($mdl_source_course_copy_state[$moodleCourse->id]) && $mdl_source_course_copy_state[$moodleCourse->id]) {
			$copyState = $mdl_source_course_copy_state[$moodleCourse->id];
		} elseif (LogicCourses::isStandardized($lehrveranstaltung)) {
			$copyState = LogicCourses::getSourceCourseCopyState($moodleCourse->id);
		}
		if ($copyState) {
			echo '<span><a data-state-unzipid="' . $copyState->unzipid . '" data-state-contextid="' . $copyState->contextid . '" data-state-restoreid="' . (property_exists($copyState, 'restoreid') ? $copyState->restoreid : '') . '" href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$moodleCourse->id.'" class="Item" target="_blank">'.LogicCourses::convertHtmlChars($moodleCourse->fullname).'</a></span>';
		} else {
			echo '<a href="'.LogicCourses::getBaseURL().'/course/view.php?id='.$moodleCourse->id.'" class="Item" target="_blank">'.LogicCourses::convertHtmlChars($moodleCourse->fullname).'</a>';
		}
	}
}
if (!$testCourseFound)
{
	echo '<form method="GET">
		<input type="hidden" name="lvid" value="' . $lvid . '"/>
		<input type="hidden" name="stsem" value="' . $stsem . '"/>
		<input type="hidden" name="action" value="createtestkurs"/>
		<button type="submit">'.$p->t('moodle/klickenSieHierUmTestkursErstellen').'</button>';
	if (LogicCourses::isStandardized($lehrveranstaltung)) {
		$logicTemplates = new LogicTemplates();
		$template = $logicTemplates->getTemplate($lehrveranstaltung->lehrveranstaltung_template_id);

		if ($template && isset($template->mdl_courses)) {
			if (count($template->mdl_courses) > 1) {
				echo ' ' . $p->t('moodle/quellkurs') . ': <select name="qk">';
				foreach ($template->mdl_courses as $lang => $qk) {
					$moodleCourses = LogicCourses::core_course_get_courses([$qk]);
					$bez = $moodleCourses[0]->fullname;
					echo '<option value="' . $qk . '"' . ($lang == $lehrveranstaltung->sprache ? ' selected' : '') . '>' . $bez . ' (' . $lang . ')</option>';
				}
				echo '</select>';
			} elseif (count($template->mdl_courses)) {
				foreach ($template->mdl_courses as $lang => $qk) {
					echo '<input type="hidden" name="qk" value="' . $qk . '"/>';
				}
			}
		}
	}
	echo '</form>';
}

echo '<script type="text/javascript">
	$("[data-state-unzipid]").each(function() {
		var $this = $(this),
			to = 100, 
			progress;
		function progress_func() {
			$.ajax({
				url: "pb_quellkurs_restore.php",
				dataType: "json",
				data: {
					unzipid: $this.data("stateUnzipid"),
					contextid: $this.data("stateContextid"),
					restoreid: $this.data("stateRestoreid"),
					time: Date.now()
				},
				success: function(data) {
					if (progress.progressbar("value") == data.value) {
						to = 100;
					} else {
						to = 0;
					}
					if (data.contextid)
						$this.data("stateContextid", data.contextid);
					if (data.restoreid)
						$this.data("stateRestoreid", data.restoreid);
					if (data.value)
						progress.progressbar("value", data.value);
					if (!data.value || data.value < 100) {
						setTimeout(progress_func, to);
					} else {
						progress.progressbar("destroy").detach();
					}
				},
				error: function(data, status, msg) {
					if (data.responseJSON.message) {
						msg = data.responseJSON.message;
					}
					progress.progressbar("destroy").detach();
					$this.after($("<span/>").addClass("error").text(" Error: " + msg));
				}
			});
		}

		if ($this.data("stateUnzipid") || $this.data("stateContextid") || $this.data("stateRestoreid")) {
			progress = $("<div/>").prependTo($this.parent().addClass("progressbar-container")).progressbar({
				value: false,
				create: function(e, ui) {
					setTimeout(progress_func, 0);
				}
			});
		}
	});
</script>';
echo '
</body>
</html>';
?>
