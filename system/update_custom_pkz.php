<?php
/* Copyright (C) 2017 fhcomplete.org
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
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at> and
 */
 /**
  * Script laedt alle User aus Moodle und setzt bei den Studierenden das Personenkennzeichen
  * in dem CustomField pkz
  *
  * Fuer die Ausfuehrung dieses Scripts muss zum Moodle Webservice die
  * Funktion "core_user_get_users" hinzugefuegt werden.
  */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/student.class.php');

$user = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

ini_set('max_execution_time', 3000);

if(!$rechte->isBerechtigt('admin'))
	die($rechte->errormsg);

if(defined('ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN') && ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN)
{

	$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
	$serverurl .= '&'.microtime(true);

	echo '<!DOCTYPE html>
	<html>
		<head>
			<meta charset="utf-8" />
		</head>
	<body>
	<h1>Update Personenkennzeichen</h1>
	<form method="POST" action="update_custom_pkz.php">
	Dieses Script setzt das CustomField PKZ (Personenkennzeichen) von allen Usern im Moodle.<br>
	Dadurch wird einmalig bei allen Studierenden das Feld befüllt. Bereits vorhandene Werte im Feld PKZ werden
	dabei überschrieben.<br>
	<br>
	Vor dem Start der Aktualisierung muss im Moodle das CustomField mit der Kurzbezeichnung "pkz" erstellt werden!<br>
	<input type="submit" name="run" value="Aktualisierung jetzt starten" />
	</form>
	';

	$anzahl_aktualisiert = 0;

	if(isset($_POST['run']))
	{
		try
		{
			// Alle User aus Moodle holen
			$client = new SoapClient($serverurl);
			$result = $client->core_user_get_users(array(array('key'=>'auth','value'=>'ldap')));
			$data_arr = array();

			foreach($result->users as $row_user)
			{
				if(isset($row_user['idnumber']))
				{
					echo $row_user['idnumber'];

					$student = new student();
					if($student->load($row_user['idnumber']))
					{
						$pkz = new stdClass();
						$pkz->type = 'pkz';
						$pkz->value = $student->matrikelnr;

						$data = new stdClass();
						$data->id = $row_user['id'];
						$data->customfields = array($pkz);

						$data_arr[] = $data;
						echo ' Student -> setze PKZ auf '.$student->matrikelnr;
						$anzahl_aktualisiert++;
					}
					else
					{
						echo ' Kein Student -> kein PKZ';
					}

					echo '<br>';
					flush();
				}
			}
			try
			{
				$response = $client->core_user_update_users($data_arr);
			}
			catch (SoapFault $E)
			{
				echo "SOAP Fehler beim setzen des Users: ".$row_user['idnumber'].' '.$student->matrikelnr;
				echo ' '.$E->faultstring;
			}
			echo "Aktualisierung abgeschlossen<br>Aktualisierte Einträge:".$anzahl_aktualisiert;
		}
		catch (SoapFault $E)
		{
			echo "SOAP Fehler beim Laden des Users: ".$E->faultstring;
		}
	}
}
else
{
	die('ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN muss auf true gesetzt sein um dieses Script starten zu duerfen');
}
