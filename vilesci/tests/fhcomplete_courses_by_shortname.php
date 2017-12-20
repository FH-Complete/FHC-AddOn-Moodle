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
  * fhcomplete_courses_by_shortname
  * FHComplete specific Webservice to load a Moodle Course according to the Shortname
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$shortname='Test Short';

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);

	$response = $client->fhcomplete_courses_by_shortname(array('shortnames'=>array($shortname)));

	if (isset($response[0]))
	{
		echo 'Fullname: '.$response[0]['fullname'].'<br>';
		echo 'Shortname: '.$response[0]['shortname'].'<br>';
		echo 'ID: '.$response[0]['id'].'<br>';
	}
	else
	{
		echo 'Es wurde kein kurs gefunden';
	}
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Laden des Kurs: ".$E->faultstring;
}
