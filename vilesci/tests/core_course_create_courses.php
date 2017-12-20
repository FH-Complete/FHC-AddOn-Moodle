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
  * core_course_create_courses
  * Create a new Moodle Course
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

	$data = new stdClass();
	$data->fullname = 'Test Fullname';
	$data->shortname = 'Test Short';
	$data->categoryid = 1;
	$data->format = 'topics';

	if(defined('ADDON_MOODLE_NUM_SECTIONS') && !is_null(ADDON_MOODLE_NUM_SECTIONS))
	{
		$numsections_option = new stdClass();
		$numsections_option->name = 'numsections';
		$numsections_option->value = ADDON_MOODLE_NUM_SECTIONS;
		$data->courseformatoptions = array($numsections_option);
	}

	$data->startdate = time();
	$data->enddate = time();

	$response = $client->core_course_create_courses(array($data));

	if (isset($response[0]))
	{
		echo "ID des neuen Kurses:".$response[0]['id'];
	}
	else
	{
		echo 'Fehler beim Anlegen des Kurses';
	}
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Anlegen des Kurses: ".$E->faultstring;
}
