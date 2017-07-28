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
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../config.inc.php');
require_once('../include/moodle_course.class.php');

$user = get_uid();

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

if(!$rechte->isBerechtigt('addon/moodle'))
	die('Sie haben keine Berechtigung für diese Seite');

$message = '';
$stsem = new studiensemester();
if (!$stsem_aktuell = $stsem->getakt())
	$stsem_aktuell = $stsem->getaktorNext();

$studiensemester_kurzbz=(isset($_REQUEST['moodle_studiensemester'])?trim($_REQUEST['moodle_studiensemester']):$stsem_aktuell);
$studiengang_kz=(isset($_REQUEST['moodle_studiengang_kz'])?trim($_REQUEST['moodle_studiengang_kz']):'');
$method = (isset($_REQUEST['method'])?trim($_REQUEST['method']):'');

if($method=='delete')
{
	if(!$rechte->isBerechtigt('basis/moodle', null, 'suid'))
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
				$message = "Erfolgreich gelöscht";
			else
			{
				$message = $moodlecourse->errormsg;
				$error = true;
			}
		}
		// wenn webservice aufgerufen wurde und kein fehler beim löschen aufgetreten ist
		if($error == false)
		{
			// Zuordnung löschen
			if($moodle->deleteZuordnung($moodle->mdl_course_id))
				$message= "Erfolgreich gelöscht";
			else
				$message ="Fehler beim Löschen aufgetreten";
		}

	}
	else
		$message = 'Ungültige Moodle ID übergeben';
}

	echo '<html>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
				<link rel="stylesheet" href="../../../skin/tablesort.css" type="text/css"/>
				<link rel="stylesheet" href="../../../skin/jquery.css" type="text/css"/>
				<link rel="stylesheet" href="../../../skin/fhcomplete.css" type="text/css">
				<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
				<script type="text/javascript" src="../../../include/js/jquery.js"></script>
				<title>Moodle - Kursverwaltung</title>
				<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
				<script type="text/javascript">

				$(document).ready(function()
				{
					$("#myTable").tablesorter(
					{
						sortList: [[0,0]],
						widgets: ["zebra"]
					});
				}
				);

			</script>
			</head>
			<body>';

	echo'	<h1>Moodle - Kursverwaltung</h1>
			<form name="moodle_verwaltung" method="POST">
			<table>
				<tr>
					<td>Studiensemester: </td><td><select name="moodle_studiensemester">';

	$stsem->getAll();
	foreach ($stsem->studiensemester as $row)
	{
		echo '<option value="'.$row->studiensemester_kurzbz.'" '.(("$studiensemester_kurzbz"=="$row->studiensemester_kurzbz")?' selected="selected" ':'').'>&nbsp;'.$row->studiensemester_kurzbz.'&nbsp;</option>';
	}
	echo '</select></td>';

	echo '	  <td>Studiengang: </td><td><select name="moodle_studiengang_kz"><';
	$stg = new studiengang();
	$stg->getAll('typ, kurzbz',true);

	foreach ($stg->result as $row)
	{
			if (!$row->moodle)
				continue;

			echo'<option value="'.$row->studiengang_kz.'" '.(("$studiengang_kz"=="$row->studiengang_kz")?' selected="selected" ':'').'>&nbsp;'.$row->kuerzel.'&nbsp;('.$row->kurzbzlang.')&nbsp;</option>';
	}
	echo '</select></td>
			<td><input type="submit" value="anzeigen" name="mdl_anzeigen"></td>
			</tr></table></form>'.$message.'<br>';

	// Liste anzeigen nachdem der Anzeigenbutton gedrückt wurde oder nach löschen die Liste wieder neu anzeigen
	if(isset($_REQUEST ['mdl_anzeigen']) || $method!='')
	{
		$moodle = new moodle_course();
		$moodle->getAllMoodleForStudiengang($studiengang_kz, $studiensemester_kurzbz);

		echo '
		<table id="myTable" class="tablesorter">
			<thead>
				<tr>
					<th>Lehrveranstaltung</th>
					<th>Lehreinheit
					<th>Kurzbz</th>
					<th>Moodle ID</th>
					<th>Semester</th>
					<th>1)</th>
					<th>2)</th>
				</tr>
			</thead>
			<tbody>';

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

				// alle LEs von Moodlekurs holen
				$moodle_help = new moodle_course();
				$help = $moodle_help->getLeFromCourse($row->mdl_course_id);
				$count = 0;
				foreach($help as $h)
				{
					$count++;
					$lehreinheit .=$h;
					if($count!=count($help))
						$lehreinheit .=', ';
				}
			}

			echo '
				<tr>
					<td>'.$lv->bezeichnung.'</td>
					<td>'.$lehreinheit.'</td>
					<td>'.$lv->kurzbz.'</td>
					<td>'.$row->mdl_course_id.'</td>
					<td>'.$lv->semester.'</td>
					<td><a href="'.$_SERVER['PHP_SELF'].'?method=delete&moodle_id='.$row->moodle_id.'&moodle_studiensemester='.$studiensemester_kurzbz.'&moodle_studiengang_kz='.$studiengang_kz.'"><img src="../../../skin/images/delete.gif" title="Löscht aus Zwischentabelle"></a></td>
					<td><a href="'.$_SERVER['PHP_SELF'].'?method=delete&all&moodle_id='.$row->moodle_id.'&moodle_studiensemester='.$studiensemester_kurzbz.'&moodle_studiengang_kz='.$studiengang_kz.'"><img src="../../../skin/images/cross.png" title="Löscht aus Zwischentabelle und den Moodlekurs"></a></td>
				</tr>';
		}
		echo '</tbody></table>';
	}

	echo "<span style='font-size:12px;'>1: Löscht Eintrag nur aus Zwischentabelle <br>
			2: Löscht Eintrag in Zwischentabelle und den Moodlekurs </span>";

	echo "</body></html>"

?>
