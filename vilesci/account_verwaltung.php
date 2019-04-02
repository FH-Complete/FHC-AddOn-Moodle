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
require_once('../lib/LogicUsers.php'); // A lot happens here!

require_once('../../../include/functions.inc.php');

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen(get_uid());

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

if (!$rechte->isBerechtigt('addon/moodle')) die('Sie haben keine Berechtigung für diese Seite');

$uid = (isset($_POST['uid']) ? trim($_POST['uid']) : '');
$mdl_user_id = (isset($_REQUEST['mdl_user_id']) ? trim($_REQUEST['mdl_user_id']) : '');
$searchString = (isset($_REQUEST['searchString']) ? trim($_REQUEST['searchString']) : '');

if ($uid != '')
{
	// Check ob User nicht bereits angelegt ist
	$users = LogicUsers::core_user_get_users_by_field($uid);
	if (count($users) == 0) //
	{
		LogicUsers::createMoodleUser($uid);
	}
}

echo '
	<form name="search" method="GET" action="'.$_SERVER['PHP_SELF'].'" target="_self">
  		Bitte Suchbegriff eingeben:
  		<input type="text" name="searchString" size="30" value="'.LogicUsers::convertHtmlChars($searchString).'">
  		<input type="submit" value="Suchen">
  	</form>
	';

if ($searchString != '' && $searchString != '?'  && $searchString != '*')
{
	$persons = LogicUsers::searchPerson($searchString);

	// Header Top mit Anzahl der gelisteten Kurse
	echo Database::rowsNumber($persons).' Person(en) gefunden';

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
	while ($person = Database::fetchRow($persons))
	{
		// Listenzeile
		echo '<tr>';
		echo '<td>
				<a href="../../../vilesci/personen/personen_details.php?person_id='.LogicUsers::convertHtmlChars($person->person_id).'">'.
					LogicUsers::convertHtmlChars($person->nachname).
				'</a>
			</td>';
		echo '<td>'.LogicUsers::convertHtmlChars($person->vorname).'</td>';
		echo '<td>'.LogicUsers::convertHtmlChars($person->uid).'</td>';
		echo '<td>'.
				(!empty($person->aktiv) && mb_strtoupper($person->aktiv) != 'F' && mb_strtoupper($person->aktiv) != 'FALSE' ? 'Aktiv' : 'Deaktiviert').
			'</td>';

		// Es gibt noch keinen Moodle User - Anlage ermoeglichen
		$users = LogicUsers::core_user_get_users_by_field($person->uid);
		if (count($users) == 0) //
		{
			echo '<td>';
			echo '	<form style="display: inline; border: 0px; text-align: center;" method="POST" target="_self" action="'.$_SERVER['PHP_SELF'].'">';
		  	echo '		<input type="hidden" name="uid" value="'.LogicUsers::convertHtmlChars($person->uid).'" />';
		  	echo '		<input type="hidden" name="searchString" value="'.LogicUsers::convertHtmlChars($searchString).'" />';
			echo '		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
			echo '		<input type="submit" value="Anlegen" />';
			echo '	</form>';
			echo '</td>';
		}
		else // Anzeige bestehende Moodle User ID
		{
			$user = $users[0];
			echo '<td style="text-align: center;">'.((isset($user->id) && !empty($user->id)) ? $user->id : '').'</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}
echo '
	</body>
</html>';

?>
