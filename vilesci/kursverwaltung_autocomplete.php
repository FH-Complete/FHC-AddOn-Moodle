<?php

/* Copyright (C) 2018 FH Technikum-Wien
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
 * Authors: Manfred Kindl <kindlm@technikum-wien.at>
 *			Cristina Hainberger	<hainberg@technikum-wien.at>
 */
require_once('../../../config/cis.config.inc.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/functions.inc.php');    
require_once('../../../include/gruppe.class.php');

$uid = get_uid();

if (!$db = new basis_db())
    die('Es konnte keine Verbindung zum Server aufgebaut werden.');

// Check permission
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('addon/moodle'))
	die('Sie haben keine Berechtigung für diese Seite');

if(!isset($_REQUEST['autocomplete']))
	die('autocomplete param missing');

// Get search string that shall be used for suggestions in autocomplete field
$search = trim((isset($_REQUEST['term']) ? $_REQUEST['term'] : ''));
if (is_null($search) || empty($search))
{
	exit();	
}
else
{
	$searchItems = explode(' ', $search);
}

$data = array();

switch($_REQUEST['autocomplete'])
{
	case 'gruppe':				
		$gruppe = new Gruppe();
		if ($gruppe->searchGruppen($searchItems))
		{
			foreach($gruppe->result as $row)
			{
				$data[]= array(
					'gruppe_kurzbz' => $row->gruppe_kurzbz,
					'bezeichnung' => $row->bezeichnung
				);
			}
			
			$data = array_unique($data, SORT_REGULAR);
			echo json_encode($data);
		}
		else
		{
			echo $gruppe->errormsg;
		}	
		break;
		
	default:
		echo 'Invalid Parameter';
		break;
}
?>
