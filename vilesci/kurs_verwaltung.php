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
 * Authors: Karl Burkhart <burkhart@technikum-wien.at>,
  * 		Andreas Österreicher <oesi@technikum-wien.at>
 */

/*
*	Dieses Programm listet nach Selektionskriterien alle Moodelkurse zu einem Studiengang auf.
*   Fuer jede MoodleID werden die Anzahl Benotungen, und erfassten sowie angelegte Zusaetze angezeigt.
*	Jeder der angezeigten Moodle IDs kann geloescht werden.
*/
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../config/global.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/studiensemester.class.php');
require_once('../../../include/studiengang.class.php');
require_once('../../../include/lehrveranstaltung.class.php');
require_once('../../../include/lehreinheit.class.php');
require_once('../../../include/lehreinheitmitarbeiter.class.php');
require_once('../../../include/lehreinheitgruppe.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../config.inc.php');
require_once('../include/moodle_course.class.php');

$user = get_uid();

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

if(!$rechte->isBerechtigt('addon/moodle'))
	die('Sie haben keine Berechtigung für diese Seite');

$stg = new studiengang();
$stg->getAll('typ, kurzbz',true);
$stg_arr = array();
foreach ($stg->result as $row)
{
	$stg_arr[$row->studiengang_kz]=$row->kuerzel;
}
if(isset($_POST['work']) && $_POST['work'] == 'getMoodleCourse')
{
	$mdl_course_id = $_POST['mdl_course_id'];

	$moodle_course = new moodle_course();
	if($moodle_course->loadMoodleCourse($mdl_course_id))
		$data['mdl_fullname'] = $moodle_course->mdl_fullname;
	else
		$data['mdl_fullname'] = 'Kurs existiert nicht';

	echo json_encode($data);
	exit;
}
if(isset($_POST['work']) && $_POST['work'] == 'getLVs')
{
	$stg = $_POST['stg'];
	$sem = $_POST['sem'];
	$stsem = $_POST['stsem'];

	$lv = new lehrveranstaltung();
	$lv->load_lva_le($stg, $stsem, $sem);

	$data = array();
	foreach($lv->lehrveranstaltungen as $row)
	{
		$data[$row->lehrveranstaltung_id]=$row->bezeichnung;
	}
	echo json_encode($data);
	exit;
}
if(isset($_POST['work']) && $_POST['work'] == 'getLEs')
{
	$studiensemester_kurzbz = $_POST['stsem'];
	$lehrveranstaltung_id = $_POST['lvid'];

	$le = new lehreinheit();
	$le->load_lehreinheiten($lehrveranstaltung_id, $studiensemester_kurzbz);

	$data = array();
	foreach($le->lehreinheiten as $row)
	{
		$data[$row->lehreinheit_id] = getLehreinheitBezeichnung($row->lehreinheit_id);
	}
	echo json_encode($data);
	exit;
}

if(isset($_POST['saveZuteilung']))
{
	$lehreinheit_id = $_POST['lehreinheit_id'];
	$lehrveranstaltung_id = $_POST['lehrveranstaltung_id'];
	$studiensemester = $_POST['moodle_studiensemester'];
	$mdl_course_id = $_POST['mdl_course_id'];
	$gruppen = isset($_POST['gruppen']);

	$moodle_course = new moodle_course();

	$moodle_course->mdl_course_id = $mdl_course_id;
	$moodle_course->lehreinheit_id = $lehreinheit_id;
	if($lehreinheit_id == '')
		$moodle_course->lehrveranstaltung_id = $lehrveranstaltung_id;

	$moodle_course->studiensemester_kurzbz = $studiensemester;
	$moodle_course->insertamum = date('Y-m-d H:i:s');
	$moodle_course->insertvon = $user;
	$moodle_course->gruppen = $gruppen;

	if(!$moodle_course->create_vilesci())
		echo $moodle_course->errormsg;
}

$message = '';
$stsem = new studiensemester();
if (!$stsem_aktuell = $stsem->getakt())
	$stsem_aktuell = $stsem->getaktorNext();

$studiensemester_kurzbz = (isset($_REQUEST['moodle_studiensemester'])?trim($_REQUEST['moodle_studiensemester']):$stsem_aktuell);
$studiengang_kz = (isset($_REQUEST['moodle_studiengang_kz'])?trim($_REQUEST['moodle_studiengang_kz']):'');
$moodle_mdl_course_id = (isset($_REQUEST['moodle_mdl_course_id'])?trim($_REQUEST['moodle_mdl_course_id']):'');
$method = (isset($_REQUEST['method'])?trim($_REQUEST['method']):'');

if ($method == 'delete')
{
	if (!$rechte->isBerechtigt('basis/moodle', null, 'suid'))
		die('Sie haben keine Berechtigung fuer diesen Vorgang');

	$moodle_id = isset($_REQUEST['moodle_id'])?$_REQUEST['moodle_id']:'';

	if($moodle_id != '')
	{
		// delete
		$moodle = new moodle_course();
		$moodle->load($moodle_id);
		$error = false;

		if(isset($_GET['all']))
		{
			// mittels webservice moodlekurs
			$moodlecourse = new moodle_course();
			if($moodlecourse->deleteKurs($moodle->mdl_course_id))
			{
				$message = "Erfolgreich gelöscht";
				// Zuordnung löschen
				if($moodle->deleteZuordnung($moodle->mdl_course_id))
					$message= "Erfolgreich gelöscht";
				else
					$message ="Fehler beim Löschen aufgetreten";
			}
			else
			{
				$message = $moodlecourse->errormsg;
				$error = true;
			}
		}
		else
		{
			// einzelne Zuordnung löschen
			if($moodle->delete($moodle->moodle_id))
				$message= "Erfolgreich gelöscht";
			else
				$message ="Fehler beim Löschen aufgetreten";
		}

	}
	else
		$message = 'Ungültige Moodle ID übergeben';
}

echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<link rel="stylesheet" href="../../../skin/fhcomplete.css" type="text/css">
		<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">';
require_once('../../../include/meta/jquery.php');
require_once('../../../include/meta/jquery-tablesorter.php');

echo'
		<title>Moodle - Kursverwaltung</title>
		<script type="text/javascript">

		$(document).ready(function()
		{
			$("#myTable").tablesorter(
			{
				sortList: [[0,0]],
				widgets: ["zebra"]
			});
		});

		function changeStudiengang()
		{
			getLehrveranstaltungen();
		}

		function changeSemester()
		{
			getLehrveranstaltungen();
		}

		function changeLV()
		{
			// Lehreinheiten Laden
			var stg_kz = $("#studiengang").val();
			var semester = $("#semester").val();
			var lvid = $("#lehrveranstaltung").val();

			// LV holen
			data = {
				stg: stg_kz,
				sem: semester,
				lvid: lvid,
				stsem: "'.$studiensemester_kurzbz.'",
				work: "getLEs"
			};

			$.ajax({
				url: "kurs_verwaltung.php",
				data: data,
				type: "POST",
				dataType: "json",
				success: function(data)
				{
					$("#lehreinheit").empty();
					$("#lehreinheit").append(\'<option value="">-- gesamter Kurs --</option>\');
					$.each(data, function(i, entry){
						$("#lehreinheit").append(\'<option value="\'+i+\'">\'+entry+\'</option>\');
					});
				},
				error: function(data)
				{
					alert("Fehler beim Laden der Daten");
				}
			});
		}

		function getLehrveranstaltungen()
		{
			var stg_kz = $("#studiengang").val();
			var semester = $("#semester").val();

			// LV holen
			data = {
				stg: stg_kz,
				sem: semester,
				stsem: "'.$studiensemester_kurzbz.'",
				work: "getLVs"
			};

			$.ajax({
				url: "kurs_verwaltung.php",
				data: data,
				type: "POST",
				dataType: "json",
				success: function(data)
				{
					$("#lehrveranstaltung").empty();
					$("#lehrveranstaltung").append(\'<option value="">-- Auswahl --</option>\');
					$.each(data, function(i, entry){
						$("#lehrveranstaltung").append(\'<option value="\'+i+\'">\'+entry+\'</option>\');
					});
				},
				error: function(data)
				{
					alert("Fehler beim Laden der Daten");
				}
			});
		}

		function changeMoodle()
		{
			var mdl_course_id = $("#mdl_course_id").val();

			// LV holen
			data = {
				mdl_course_id: mdl_course_id,
				work: "getMoodleCourse"
			};

			$.ajax({
				url: "kurs_verwaltung.php",
				data: data,
				type: "POST",
				dataType: "json",
				success: function(data)
				{
					$("#mdl_course_bezeichnung").text(data["mdl_fullname"]);
				},
				error: function(data)
				{
					alert("Fehler beim Laden der Daten");
				}
			});
		}
		</script>
	</head>
<body>
	<h1>Moodle - Kursverwaltung</h1>
	<form name="moodle_verwaltung" action="kurs_verwaltung.php" method="POST">
		<table>
			<tr>
				<td>Studiensemester: </td>
				<td>
					<select name="moodle_studiensemester">';

$stsem->getAll();
foreach ($stsem->studiensemester as $row)
{
	if($studiensemester_kurzbz == $row->studiensemester_kurzbz)
		$selected = 'selected="selected"';
	else
		$selected = '';
	echo '<option value="'.$row->studiensemester_kurzbz.'" '.$selected.'>'.$row->studiensemester_kurzbz.'</option>';
}
echo '
					</select>
				</td>
				<td>Studiengang: </td>
				<td>
					<select name="moodle_studiengang_kz">';
$stg = new studiengang();
$stg->getAll('typ, kurzbz',true);

foreach ($stg->result as $row)
{
		if (!$row->moodle)
			continue;

		echo'<option value="'.$row->studiengang_kz.'" '.(("$studiengang_kz"=="$row->studiengang_kz")?' selected="selected" ':'').'>&nbsp;'.$row->kuerzel.'&nbsp;('.$row->kurzbzlang.')&nbsp;</option>';
}
echo '
					</select>
				</td>
				<td>
					oder Moodle Kurs ID:
					<input type="text" size="4" name="moodle_mdl_course_id" value="'.$moodle_mdl_course_id.'" />
				</td>
				<td>
					<input type="submit" value="anzeigen" name="mdl_anzeigen">
				</td>
			</tr>
		</table>
	</form>
	'.$message.'<br>';

// Liste anzeigen nachdem der Anzeigenbutton gedrückt wurde oder nach löschen die Liste wieder neu anzeigen
if ($studiengang_kz != '' && $studiensemester_kurzbz != '')
{
	$moodle = new moodle_course();
	if($moodle_mdl_course_id == '')
		$moodle->getAllMoodleForStudiengang($studiengang_kz, $studiensemester_kurzbz);
	else
		$moodle->getAllMoodleForMoodleCourse($moodle_mdl_course_id);

	echo '
	<table id="myTable" class="tablesorter">
		<thead>
			<tr>
				<th>Lehrveranstaltung</th>
				<th>Lehreinheit</th>
				<th>Kurzbz</th>
				<th>Gruppen</th>
				<th>Moodle ID</th>
				<th>Semester</th>
				<th>1)</th>
				<th>2)</th>
			</tr>
		</thead>
		<tbody>';

	$mdl_course_bezeichnung = array();
	foreach($moodle->result as $row)
	{
		$lv = new lehrveranstaltung($row->lehrveranstaltung_id);
		$lehreinheit = '';
		// wenn LE übergeben lade dazugehörige LV
		if($row->lehreinheit_id != '')
		{
			$le = new lehreinheit();
			$le->loadLE($row->lehreinheit_id);
			$lv->load($le->lehrveranstaltung_id);

			$lehreinheit = getLehreinheitBezeichnung($row->lehreinheit_id);
		}

		if(!isset($mdl_course_bezeichnung[$row->mdl_course_id]))
		{
			$course = new moodle_course();
			$course->loadMoodleCourse($row->mdl_course_id);
			$mdl_course_bezeichnung[$row->mdl_course_id] = $course->mdl_fullname;
		}

		$delpath = 'kurs_verwaltung.php?method=delete';
		$delpath .= '&moodle_id='.$row->moodle_id;
		$delpath .= '&moodle_studiensemester='.$studiensemester_kurzbz;
		$delpath .= '&moodle_studiengang_kz='.$studiengang_kz;
		$delpath .= '&moodle_mdl_course_id='.$moodle_mdl_course_id;

		$delpathall = $delpath .'&all';

		echo '
			<tr>
				<td>'.$stg_arr[$lv->studiengang_kz].' '.$lv->semester.' '.$lv->bezeichnung.' ('.$lv->lehrveranstaltung_id.')</td>
				<td>'.$lehreinheit.'</td>
				<td>'.$lv->kurzbz.'</td>
				<td>'.($row->gruppen?'Ja':'Nein').'</td>
				<td>
					<a href="'.ADDON_MOODLE_PATH.'/course/view.php?id='.$row->mdl_course_id.'" target="_blank">
					'.$mdl_course_bezeichnung[$row->mdl_course_id].' ('.$row->mdl_course_id.')
					</a>
				</td>
				<td>'.$lv->semester.'</td>
				<td>
					<a href="'.$delpath.'">
					<img src="../skin/images/tree-diagramm-delete.png" height="20px" title="Zuordnung zu Kurs löschen">
					</a>
				</td>
				<td>
					<a href="'.$delpathall.'" onclick="return confirm(\'Soll wirklich der gesamte Moodle Kurs gelöscht werden?\');">
					<img src="../../../skin/images/delete.gif" title="Zuordnung und Moodle Kurs löschen">
					</a>
				</td>
			</tr>';
	}
	echo '</tbody></table>';
}

echo "<span style='font-size:12px;'>1: Löscht nur die Zuordnung zum Moodle Kurs <br>
		2: Löscht die Zuordnung und den gesamten Moodle Kurs</span>";

echo '
<h2>Neue Zuteilung erstellen</h2>
	<form method="POST" action="kurs_verwaltung.php">
	<input type="hidden" name="moodle_studiensemester" value="'.$studiensemester_kurzbz.'" />
	<input type="hidden" name="moodle_studiengang_kz" value="'.$studiengang_kz.'" />
	<input type="hidden" name="moodle_mdl_course_id" value="'.$moodle_mdl_course_id.'" />
	<table>
		<tr>
			<td>Moodle Kurs ID</td>
			<td>
				<input type="text" value="" size="4" id="mdl_course_id" name="mdl_course_id" onchange="changeMoodle()">
				<span id="mdl_course_bezeichnung"></span>
			</td>
		</tr>
		<tr>
			<td>Studiengang</td>
			<td>
				<select id="studiengang" name="studiengang_kz" onchange="changeStudiengang()">';

foreach ($stg->result as $row)
{
	if (!$row->moodle)
		continue;
	echo '<option value="'.$row->studiengang_kz.'">'.$row->kuerzel.' ('.$row->kurzbzlang.')</option>';
}
echo '
				</select>
			</td>
		</tr>
		<tr>
			<td>Semester</td>
			<td>
				<select id="semester" name="semester" onchange="changeSemester()">
					<option value="">-- keine Auswahl --</option>';
for($i = 1; $i <= 10; $i++)
{
	echo '<option value="'.$i.'">'.$i.'. Semester</option>';
}
echo '
				</select>
			</td>
		</tr>
		<tr>
			<td>Lehrveranstaltung</td>
			<td>
				<select id="lehrveranstaltung" name="lehrveranstaltung_id" onchange="changeLV()">
					<option>-- zuerst Semester auswählen --</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Lehreinheit</td>
			<td>
				<select id="lehreinheit" name="lehreinheit_id">
					<option>-- zuerst LV auswählen --</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Gruppen</td>
			<td><input type="checkbox" name="gruppen" checked></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" name="saveZuteilung" value="Zuteilung speichern" /></td>
		</tr>
	</table>
</form>
</body>
</html>';

function getLehreinheitBezeichnung($lehreinheit_id)
{
	global $stg_arr;
	$lehreinheit_obj = new lehreinheit();
	$lehreinheit_obj->load($lehreinheit_id);

	$lektoren = '';
	$lem = new lehreinheitmitarbeiter();
	$lem->getLehreinheitmitarbeiter($lehreinheit_id);
	foreach($lem->lehreinheitmitarbeiter as $row_ma)
		$lektoren .= $row_ma->mitarbeiter_uid;

	$gruppen = '';
	$leg = new lehreinheitgruppe();
	$leg->getLehreinheitgruppe($lehreinheit_id);
	foreach($leg->lehreinheitgruppe as $row_grp)
	{
		if($row_grp->gruppe_kurzbz != '')
			$gruppen .= $row_grp->gruppe_kurzbz;
		else
			$gruppen .= $stg_arr[$row_grp->studiengang_kz].'-'.$row_grp->semester.$row_grp->verband.$row_grp->gruppe;
	}
	return $lehreinheit_obj->lehrform_kurzbz.' '.$gruppen.' '.$lektoren.' ('.$lehreinheit_id.')';
}
