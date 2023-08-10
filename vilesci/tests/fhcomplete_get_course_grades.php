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
  * fhcomplete_get_course_grades
  * FHComplete specific Webservice to load Grades from Moodle
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);

	$mdl_course_id = 98;
	$type = 3;
	// 1 = Punkte, 2 = Prozentpunkte, 3 = Note laut Skala

	$response = $client->fhcomplete_get_course_grades($mdl_course_id, $type);

	if (numberOfElements($response) > 0)
	{

		foreach ($response as $row)
		{
			if ($row['note'] != '-')
			{
				echo '<br>';
				echo 'ID:'.$mdl_course_id.'<br>';
				echo 'Vorname:'.$row['vorname'].'<br>';
				echo 'Nachname:'.$row['nachname'].'<br>';
				echo 'IDnummer:'.$row['idnummer'].'<br>';
				echo 'Username:'.$row['username'].'<br>';
				echo 'Note:'.$row['note'].'<br>';
			}
		}
	}
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Laden des Kurs: ".$E->faultstring;
}
