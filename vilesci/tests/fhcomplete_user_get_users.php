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
  * fhcomplete_user_get_users
  * FHComplete specific Webservice to get Users according to its Username
  */
require_once('../../config.inc.php');

if(ADDON_MOODLE_DEBUGLEVEL < 10)
	die('You need to Set Debuglevel >= 10 to use this Script');

$uid = 'admin';

$serverurl = ADDON_MOODLE_PATH.'/webservice/soap/server.php?wsdl=1&wstoken='.ADDON_MOODLE_TOKEN;
$serverurl .= '&'.microtime(true);

try
{
	$client = new SoapClient($serverurl);
	$response = $client->fhcomplete_user_get_users(array(array('key'=>'username', 'value'=>$uid)));

	if (is_object($response))
	{
		$response_obj = $response;
		unset($response);
		$response['users'] = $response_obj->users;
	}

	if (isset($response['users'][0]))
	{
		$mdl_user_id = $response['users'][0]['id'];
		$mdl_user_username = $response['users'][0]['username'];
		$mdl_user_firstname = $response['users'][0]['firstname'];
		$mdl_user_lastname = $response['users'][0]['lastname'];

		echo 'ID: '.$mdl_user_id;
		echo '<br>Username: '.$mdl_user_username;
		echo '<br>Firstname: '.$mdl_user_firstname;
		echo '<br>Lastname: '.$mdl_user_lastname;
	}
	else
	{
		echo 'Fehler beim Laden des Users';
	}
}
catch (SoapFault $E)
{
	echo "SOAP Fehler beim Laden des Users: ".$E->faultstring;
}
