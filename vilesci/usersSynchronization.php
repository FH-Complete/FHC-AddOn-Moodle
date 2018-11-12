<?php

/**
 * Copyright (C) 2014-2018 fhcomplete.org
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
 */

require_once('../lib/LogicUsers.php');

if (!LogicUsers::vilesciIsAllowed())
{
	Output::printError('Sounds like you do not have the permission "addon/moodle", you are suspicious!!!');
	die();
}

?>

<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Moodle User Sync</title>
		<link rel="stylesheet" href="../../../skin/fhcomplete.css" />
		<link rel="stylesheet" href="../../../skin/vilesci.css" />
	</head>
	<body>
		<div>
			<h1>
				Moodle Users Synchronization
			</h1>
		</div>
		<div>
			Auf dieser Seite können die Teilnehmer eines Moodle Kurses aktualisiert werden
		</div>

		<br>

		<div>
			Place one or more moodle courses numeric ID separated by the character ";" <br>
			Is not possible to synchronize more than <?php echo ADDON_MOODLE_VILESCI_MAX_NUMBER_COURSES; ?> courses each time
		</div>

		<br>

		<div>
			<form method="POST">
				<table>
					<tr>
						<td style="vertical-align: top;">
							Moodle courses ID:
						</td>
						<td style="width: 30px;">&nbsp;</td>
						<td>
							<textarea name="moodleCoursesIDs" style="width: 300px; height: 200px;"></textarea>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td colspan="3" style="text-align: center;">
							<input type="submit" value="   Synchronize  ">
						</td>
					</tr>
				</table>
			</form>
		</div>

		<br>

		<div>
			<b>NOTICE: do not close this page until the end of the operation!</b>
		</div>

		<div>
			<?php LogicUsers::vilesciSynchronize(); ?>
		</div>

	</body>
</html>
