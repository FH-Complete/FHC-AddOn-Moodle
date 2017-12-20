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
  * enrol_manual_enrol_users
  * Assign User to a Course
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$mdl_course_id = 1;
$mdl_user_id = 1;

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);

	$data = new stdClass();
	$data->roleid = 3; // 3=Lektor
	$data->userid = $mdl_course_id;
	$data->courseid = $mdl_user_id;

	$client->enrol_manual_enrol_users(array($data));

	// Diese funktion liefert keinen Returnwert!

	// Wenn User zum Kurs hinzugefuegt werden, muss eine kleine Pause eingelegt werden
	// Die User werden nicht gleich zugeordnet, diese werden nach
	// abschluss des SOAP Requests von Moodle noch weiterverarbeitet und
	// erst zeitversetzt zugeordnet.
	// Die Pause ist abgaengig von der Anzahl der User die hinzugefuegt werden
	echo "Zuteilung durchgeführt";
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Laden des Users: ".$E->faultstring;
}
