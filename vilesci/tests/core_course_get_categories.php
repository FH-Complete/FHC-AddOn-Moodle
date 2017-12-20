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
  * core_course_get_categories
  * Load Moodle Categories
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$parent = '';
$bezeichnung = 'WS2017';

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);

	$response = $client->core_course_get_categories(
		array(
			array(
				'key'=>'name',
				'value'=>$bezeichnung
			),
			array(
				'key'=>'parent',
				'value'=>$parent)
			)
		);

	if (isset($response[0]))
	{
		echo "ID:".$response[0]['id'];
	}
	else
	{
		echo 'Fehler beim Laden der Kurskategorie';
	}
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Anlegen des Kurses: ".$E->faultstring;
}
