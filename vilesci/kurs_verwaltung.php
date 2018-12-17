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

// Messages to be displayed like saving success/error
$msgBox = '';

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
	if (isset($_POST['mdl_course_id']) && is_numeric($_POST['mdl_course_id']))
	{
		$mdl_course_id = $_POST['mdl_course_id'];
		$gruppe_kurzbz = (isset($_POST['gruppe_kurzbz']) && !empty($_POST['gruppe_kurzbz'])) ? $_POST['gruppe_kurzbz'] : null;
		$lehreinheit_id = (isset($_POST['lehreinheit_id']) && is_numeric($_POST['lehreinheit_id'])) ? $_POST['lehreinheit_id'] : null;
		$lehrveranstaltung_id = (isset($_POST['lehrveranstaltung_id']) && is_numeric($_POST['lehrveranstaltung_id'])) ? $_POST['lehrveranstaltung_id'] : null;
		$studiensemester = (isset($_POST['studiensemester_kurzbz']) && !empty($_POST['studiensemester_kurzbz'])) ? $_POST['studiensemester_kurzbz'] : null; 
		$gruppen = isset($_POST['gruppen']);

		$moodle_course = new moodle_course();

		$moodle_course->mdl_course_id = $mdl_course_id;
		$moodle_course->gruppe_kurzbz = $gruppe_kurzbz;
		$moodle_course->lehreinheit_id = $lehreinheit_id;
		if (is_null($lehreinheit_id))
			$moodle_course->lehrveranstaltung_id = $lehrveranstaltung_id;
		$moodle_course->studiensemester_kurzbz = $studiensemester;
		$moodle_course->insertamum = date('Y-m-d H:i:s');
		$moodle_course->insertvon = $user;
		$moodle_course->gruppen = $gruppen;
		
		// Check if there are existing moodles for gruppen or lehrveranstaltung 
		// or lehreinheit to this moodle course id, which does not allow new assignment
		$moodle_course->getAllMoodleForMoodleCourse($mdl_course_id);
		$moodle_arr = $moodle_course->result;
		$moodle_course_has_groups = false;	// true if moodle course has moodles with groups assigned yet
		$moodle_course_has_lv_or_le = false;	// true if moodel course hase moodles with lv or le assigned yet
		
		foreach ($moodle_arr as $moodle)
		{
			if (!is_null($moodle->gruppe_kurzbz))
			{
				$moodle_course_has_groups = true;
			}
			
			if (!is_null($moodle->lehrveranstaltung_id) || !is_null($moodle->lehreinheit_id))
			{
				$moodle_course_has_lv_or_le = true;
			}
		}
		
		if (is_null($gruppe_kurzbz) && is_null($lehrveranstaltung_id))
		{
			$msgBox = 'Bitte treffen Sie erst eine Auswahl für die Zuteilung.';
			$moodle_mdl_course_id = $mdl_course_id;
		}	
		// Moodle course assignment requires
		// - gruppe (and no lv/le assigned yet for this course) OR
		// - studiensemester AND (lehrveranstaltung OR lehreinheit) (and no groups assigned yet for this course)
		elseif ((!is_null($moodle_course->gruppe_kurzbz) && !$moodle_course_has_lv_or_le) ||
			((!is_null($moodle_course->studiensemester_kurzbz) && (!is_null($moodle_course->lehrveranstaltung_id) || !is_null($moodle_course->lehreinheit_id)))) && !$moodle_course_has_groups)
		{	
			// Save assignment
			if(!$moodle_course->create_vilesci())
			{
				echo $moodle_course->errormsg;
			}
			else
			{
				$msgBox = 'Gespeichert!';
				$moodle_mdl_course_id = $mdl_course_id;
				
			}
		}
		else
		{
			$msgBox = "<b>Nicht gespeichert!</b><br>";
			if ($moodle_course_has_lv_or_le)
			{
				$msgBox.= ""
					. "Es wurden früher bereits Lehrveranstaltungen oder Lehreinheiten zum Moodle Kurs zugeteilt.<br>"
					. "Nur noch Zuteilung von weiteren Lehrveranstaltungen oder Lehreinheiten möglich.";
			}
			elseif ($moodle_course_has_groups)
			{
				$msgBox.= ""
					. "Es wurden früher bereits Gruppen zum Moodle Kurs zugeteilt.<br>"
					. "Nur noch Zuteilung von weiteren Gruppen möglich.<br>";
			}
			else
			{
				$msgBox.= 'Bitte treffen Sie erst eine Auswahl für die Zuteilung.';
			}
		}
	}
	else
	{
		$msgBox = 'Bitte geben Sie eine Moodle Kurs ID ein.';
	}
}

$message = '';
$stsem = new studiensemester();
if (!$stsem_aktuell = $stsem->getakt())
	$stsem_aktuell = $stsem->getaktorNext();

$studiensemester_kurzbz = (isset($_REQUEST['moodle_studiensemester'])?trim($_REQUEST['moodle_studiensemester']):$stsem_aktuell);
$studiengang_kz = (isset($_REQUEST['moodle_studiengang_kz'])?trim($_REQUEST['moodle_studiengang_kz']):'');

if (isset($_REQUEST['moodle_mdl_course_id']))
{
	$moodle_mdl_course_id = trim($_REQUEST['moodle_mdl_course_id']); // first try to set moodle course id by request
}
elseif (isset($mdl_course_id) && !empty($mdl_course_id))
{
	$moodle_mdl_course_id = $mdl_course_id; // second try to set moodle course by reusing former post request
}
else
{
	$moodle_mdl_course_id = '';	
}
	
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

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title>Moodle - Kursverwaltung</title>
		<link rel="stylesheet" href="../../../skin/fhcomplete.css" type="text/css">
		<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
		<link href="../../../skin/jquery-ui-1.9.2.custom.min.css" rel="stylesheet" type="text/css">
		<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
		<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
		<?php require_once '../../../include/meta/jquery-tablesorter.php';?>
		<script type="text/javascript">		
		$(document).ready(function()
		{
			$("#myTable").tablesorter(
			{
				sortList: [[0,0]],
				widgets: ["zebra"]
			});
			
			// Autocomplete field "Gruppe"
			$("#gruppe").autocomplete(
			{
				source: "kursverwaltung_autocomplete.php?autocomplete=gruppe",
				minLength: 2,
				response: function(event, ui)
				{
					// Set group-label and -value
					for(i in ui.content)
					{
						ui.content[i].value = ui.content[i].gruppe_kurzbz;
						ui.content[i].label = ui.content[i].gruppe_kurzbz + " - " + ui.content[i].bezeichnung;
					}
				},
				select: function()
				{
					// If a group is selected, disable all following dropdowns/checkboxes
					$('#studiensemester_kurzbz').prop('disabled', true);
					$('#studiengang').prop('disabled', true);
					$('#semester').prop('disabled', true);
					$('#lehrveranstaltung').prop('disabled', true);
					$('#lehreinheit').prop('disabled', true);
					$('#gruppen').prop('disabled', true);
					
					// If the selection is taken back, enable dropdowns again/checkboxes
					$("#gruppe").keyup(function() 
					{
						if (!this.value) 
						{
							$('#studiensemester_kurzbz').prop('disabled', false);
							$('#studiengang').prop('disabled', false);
							$('#semester').prop('disabled', false);
							$('#lehrveranstaltung').prop('disabled', false);
							$('#lehreinheit').prop('disabled', false);
							$('#gruppen').prop('disabled', false);
						}
					});
				}
			});
			
			// Enable dropdowns/checkboxes when a moodle course id is entered
			$('#mdl_course_id').keyup(function()
			{
				// Enable when entering field
				if(this.value) 
				{
					$('#gruppe').prop('disabled', false);
					$('#studiensemester_kurzbz').prop('disabled', false);
					$('#studiengang').prop('disabled', false);
					$('#semester').prop('disabled', false);
					$('#lehrveranstaltung').prop('disabled', false);
					$('#lehreinheit').prop('disabled', false);
					$('#gruppen').prop('disabled', false); 
				}
				// Disable when field is empty
				else
				{
					$('#gruppe').prop('disabled', true);
					$('#studiensemester_kurzbz').prop('disabled', true);
					$('#studiengang').prop('disabled', true);
					$('#semester').prop('disabled', true);
					$('#lehrveranstaltung').prop('disabled', true);
					$('#lehreinheit').prop('disabled', true);
					$('#gruppen').prop('disabled', true);
				}
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
				stsem: "<?php echo $studiensemester_kurzbz ?>",
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
					$("#lehreinheit").append('<option value="">-- gesamter Kurs --</option>');
					$.each(data, function(i, entry)
					{
						$("#lehreinheit").append('<option value="' + i + '">' + entry + '</option>');
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
				stsem: "<?php echo $studiensemester_kurzbz ?>",
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
					$("#lehrveranstaltung").append('<option value="">-- Auswahl --</option>');
					$.each(data, function(i, entry){
						$("#lehrveranstaltung").append('<option value="' + i + '">' + entry + '</option>');
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
			
			// Empty messagebox
			$('#msgBox').empty();

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

<?php
echo '
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
if (($studiengang_kz != '' && $studiensemester_kurzbz != '') || !empty($moodle_mdl_course_id))
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
				<th>Semester</th>
				<th>Moodle-Gruppen</th>
				<th>OE-Gruppe</th>
				<th>Moodle ID</th>			
				<th>1)</th>
				<th>2)</th>
			</tr>
		</thead>
		<tbody>';
	$mdl_course_bezeichnung = array();
	foreach($moodle->result as $row)
	{
		// If LV was assigned, load LV
		if (!is_null($row->lehrveranstaltung_id))
		{		
			$lv = new lehrveranstaltung($row->lehrveranstaltung_id);
			$lehreinheit = ' ';
		}
		// If LE was assigned -> load LV by using LE
		elseif (!is_null($row->lehreinheit_id))
		{
			$le = new lehreinheit();
			$lv = new Lehrveranstaltung();
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

		if (!is_null($row->lehrveranstaltung_id) || !is_null($row->lehreinheit_id))
		{	
		echo '
			<tr>
				<td>'.$stg_arr[$lv->studiengang_kz].' '.$lv->semester.' '.$lv->bezeichnung.' ('.$lv->lehrveranstaltung_id.')</td>
				<td>'.$lehreinheit.'</td>
				<td>'.$lv->kurzbz.'</td>
				<td>'.$lv->semester.'</td>
				<td>'.($row->gruppen?'Ja':'Nein').'</td>
				<td align="center"> - </td>';
		}
		elseif (!is_null($row->gruppe_kurzbz))
		{
		echo '
			<tr>
				<td align="center"> - </td>
				<td align="center"> - </td>
				<td align="center"> - </td>
				<td align="center"> - </td>
				<td align="center"> - </td>
				<td>'. $row->gruppe_kurzbz. '</td>';
		}
		echo '
				<td>
					<a href="'.ADDON_MOODLE_PATH.'/course/view.php?id='.$row->mdl_course_id.'" target="_blank">
					'.$mdl_course_bezeichnung[$row->mdl_course_id].' ('.$row->mdl_course_id.')
					</a>
				</td>
		
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
	<table>
		<tr>
			<td><b>Moodle Kurs ID</b></td>
			<td>
				<input type="text" value="" size="20" id="mdl_course_id" name="mdl_course_id" onchange="changeMoodle()">
				<span id="mdl_course_bezeichnung"></span>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td>Gruppe</td>
			<td>
				<input class="search" disabled placeholder="Gruppe eingeben" type="text" id="gruppe" name="gruppe_kurzbz" size="40">
			</td>
		</tr>
		<tr>
			<td></td>
			<td style="padding: 10px;">ODER</td>
		</tr>
		<tr>
			<td>Studiensemester: </td>
			<td>
				<select id="studiensemester_kurzbz" name="studiensemester_kurzbz" disabled>';

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
		<tr>
			<td>Studiengang</td>
			<td>
				<select id="studiengang" name="studiengang_kz" disabled onchange="changeStudiengang()">
				<option value="">-- bitte wählen --</option>';

foreach ($stg->result as $row)
{
	if (!$row->moodle)
		continue;
		echo '
				<option value="'.$row->studiengang_kz.'">'.$row->kuerzel.' ('.$row->kurzbzlang.')</option>';
}
		echo '
				</select>
			</td>
		</tr>
		<tr>
			<td>Semester</td>
			<td>
				<select id="semester" name="semester" disabled onchange="changeSemester()">
					<option value="">-- bitte wählen --</option>';
		for($i = 1; $i <= 10; $i++)
		{
			echo '
					<option value="'.$i.'">'.$i.'. Semester</option>';
		}
		echo '
				</select>
			</td>
		</tr>
		<tr>
			<td>Lehrveranstaltung</td>
			<td>
				<select id="lehrveranstaltung" name="lehrveranstaltung_id" disabled onchange="changeLV()">
					<option>-- zuerst Semester auswählen --</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Lehreinheit</td>
			<td>
				<select id="lehreinheit" name="lehreinheit_id" disabled>
					<option>-- zuerst LV auswählen --</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Gruppen</td>
			<td><input type="checkbox" id="gruppen" name="gruppen" disabled></td>
		</tr>
		<tr>
			<td></td>
			<td><input type="submit" name="saveZuteilung" value="Zuteilung speichern"></td>
		</tr>
	</table>
</form>
<br><br>
<span id="msgBox">'. $msgBox. '</span>
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
