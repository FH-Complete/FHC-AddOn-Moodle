<?php
/* Copyright (C) 2014 fhcomplete.org
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
 * Script um mehrere User auf einmal im Moodle anzulegen
 * Die UID der User die angelegt werden sollen, werden in einem Textfeld uebergeben
 */

require_once('../lib/LogicUsers.php'); // A lot happens here!

require_once('../../../include/functions.inc.php');

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen(get_uid());

if(!$rechte->isBerechtigt('addon/moodle')) die('Sie haben keine Berechtigung für diese Seite');

$userliste = (isset($_POST['userliste']) ? trim($_POST['userliste']) : '');
$messages = '';

if ($userliste != '')
{
	$uids = explode("\n", $userliste);
	foreach ($uids as $uid)
	{
		$uid = trim($uid);

		$users = LogicUsers::core_user_get_users_by_field($uid);
		if (count($users) == 0) //
		{
			$users = LogicUsers::createMoodleUser($uid);
			$messages.='<br>User "'.$uid.'" angelegt. Moodle ID: '.$users[0]->id;
		}
		else
		{
			$messages.='<br>User "'.$uid.'" bereits vorhanden. Moodle ID: '.$users[0]->id;
		}
	}
}
echo '<!DOCTYPE HTML>
<html>
<head>
	<title>Moodle - Accountverwaltung</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
</head>
<body>
<h2>Moodle - User anlegen</h2>
	<form name="createuser" method="POST" action="'.htmlentities($_SERVER["PHP_SELF"]).'" target="_self">
		Bitte geben sie die UIDs der Personen die im Moodle angelegt werden sollen ein (ein User pro Zeile):<br>
		<textarea name="userliste" cols="32" rows="20"></textarea>
		<input type="submit" value="Anlegen">
	</form>	';
echo $messages;
echo '
	</body>
</html>';

?>
