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
  * core_course_get_courses
  * Load Moodle Course
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$mdl_course_id = 1;

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);
	$response = $client->core_course_get_courses(array('ids' => array($mdl_course_id)));

	foreach ($response as $course)
	{
		echo 'Fullname: '.$course['fullname'].'<br>';
		echo 'Shortname: '.$course['shortname'].'<br>';
		echo 'ID: '.$course['id'].'<br>';
	}

}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Laden des Users: ".$E->faultstring;
}
