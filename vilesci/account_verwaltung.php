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
 * Dieses Programm listet nach einem Suchbegriff bestehender Benutzer auf.
 * Fuer jede UserID wird geprueft ob dieser bereits einen Moodle ID besitzt.
 * Bestehende Moodle IDs werden angezeigt, fuer alle anderen wird die Moeglichkeit
 * der Neuanlage geboten.
 */
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../config/global.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../config.inc.php');
require_once('../include/moodle_user.class.php');

$user = get_uid();

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

echo '<!DOCTYPE HTML>
<html>
<head>
	<title>Moodle - Accountverwaltung</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
	<link rel="stylesheet" href="../../../skin/tablesort.css" type="text/css">
	<script type="text/javascript" src="../../../include/js/jquery.js"></script>

	<script type="text/javascript">
		$(document).ready(function()
		{
			$("#t1").tablesorter(
			{
				sortList: [[0,0]],
				widgets: ["zebra"],
				headers: {0:{sorter:false}}
			});
		});
	</script>
</head>
<body>
<h1>Moodle - Accountverwaltung</h1>
';

if(!$rechte->isBerechtigt('addon/moodle'))
	die('Sie haben keine Berechtigung für diese Seite');

if (!$db = new basis_db())
	die('Fehler bei der Datenbankverbindung');

$uid = (isset($_POST['uid'])?trim($_POST['uid']):'');
$mdl_user_id = (isset($_REQUEST['mdl_user_id'])?trim($_REQUEST['mdl_user_id']):'');
$searchstr = (isset($_REQUEST['searchstr'])?trim($_REQUEST['searchstr']):'');

$moodle = new moodle_user();

if($uid!='')
{
	// Check ob User nicht bereits angelegt ist
	if (!$moodle->loaduser($uid))
	{
		//  User ist noch nicht in Moodle angelegt => Neuanlage
		if (!$moodle->createUser($uid))
				echo$moodle->errormsg;
	}
}

echo '
	<form name="search" method="GET" action="'.$_SERVER["PHP_SELF"].'" target="_self">
  		Bitte Suchbegriff eingeben:
  		<input type="text" name="searchstr" size="30" value="'.$db->convert_html_chars($searchstr).'">
  		<input type="submit" value="Suchen">
  	</form>
	';

if($searchstr!='' && $searchstr!='?'  && $searchstr!='*')
{
	// SQL Select-String
	$qry = "SELECT
				distinct tbl_person.person_id,tbl_person.nachname,tbl_person.vorname,
				tbl_person.aktiv,tbl_benutzer.uid
			FROM
				public.tbl_person
				JOIN public.tbl_benutzer USING(person_id)
			WHERE
				tbl_person.nachname ~* ".$db->db_add_param($searchstr)." OR
				tbl_person.vorname ~* ".$db->db_add_param($searchstr)." OR
				tbl_benutzer.alias ~* ".$db->db_add_param($searchstr)." OR
				tbl_person.nachname || ' ' || tbl_person.vorname = ".$db->db_add_param($searchstr)." OR
				tbl_person.vorname || ' ' || tbl_person.nachname = ".$db->db_add_param($searchstr)." OR
				tbl_benutzer.uid ~* ".$db->db_add_param($searchstr)."
			ORDER BY nachname, vorname;";

		if($result = $db->db_query($qry))
		{
			// Header Top mit Anzahl der gelisteten Kurse
			echo $db->db_num_rows($result).' Person(en) gefunden';

			echo'<table id="t1" class="tablesorter" style="width: auto">
				<thead>
					<tr>
						<th>Nachname</th>
						<th>Vorname</th>
						<th>UserID</th>
						<th>Status</th>
						<th>MoodleAccount</th>
					</tr>
				</thead>
				<tbody>';

			// Alle gefundenen User in einer Schleife anzeigen.
			while($row = $db->db_fetch_object($result))
			{

				// Listenzeile
				echo '<tr>';
				echo '<td><a href="../../../vilesci/personen/personen_details.php?person_id='.$db->convert_html_chars($row->person_id).'">'.$db->convert_html_chars($row->nachname).'</a></td>';
				echo '<td>'.$db->convert_html_chars($row->vorname).'</td>';
				echo '<td>'.$db->convert_html_chars($row->uid).'</td>';
				echo '<td>'.(!empty($row->aktiv) && mb_strtoupper($row->aktiv)!='F' && mb_strtoupper($row->aktiv)!='FALSE' ?'aktiv':'deaktiviert').'</td>';

				if (!$moodle->loaduser($row->uid))
					$moodle->mdl_user_id='';

				// Es gibt noch keinen Moodle User - Anlage ermoeglichen
				if (!isset($moodle->mdl_user_id) || empty($moodle->mdl_user_id))
				{
					echo '<td>';
					echo'<form style="display: inline;border:0px;" method="POST" target="_self" action="'.$_SERVER["PHP_SELF"].'">';
				  	echo '<input style="display:none" type="text" name="uid" value="'.$db->convert_html_chars($row->uid).'" />';
				  	echo '<input style="display:none" type="text" name="searchstr" value="'.$db->convert_html_chars($searchstr).'" />';
					echo '<input type="submit" value="anlegen" />';
					echo'</form>';
					echo '</td>';
				}
				else // Anzeige bestehende Moodle User ID
				{
					echo '<td>'.((isset($moodle->mdl_user_id) && !empty($moodle->mdl_user_id))?$moodle->mdl_user_id:'').'</td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
}
echo '
</body>
	</html>';
?>
