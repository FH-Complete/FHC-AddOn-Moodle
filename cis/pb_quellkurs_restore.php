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
 *          Christopher Gerbrich <christopher.gerbrich@technikum-wien.at>
 */
require_once(dirname(__FILE__).'/../../../config/cis.config.inc.php');
require_once(dirname(__FILE__).'/../../../include/basis_db.class.php');
require_once(dirname(__FILE__).'/../../../include/benutzerberechtigung.class.php');
require_once(dirname(__FILE__).'/../../../include/functions.inc.php');
require_once(dirname(__FILE__).'/../lib/LogicTemplates.php');
require_once(dirname(__FILE__).'/../lib/MoodleAPI.php');
require_once(dirname(__FILE__).'/../config/config.php');

$uid = get_uid();

if (!$db = new basis_db())
	die('Es konnte keine Verbindung zum Server aufgebaut werden.');

// Check permission
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('addon/moodle'))
	die('Sie haben keine Berechtigung für diese Seite');

function returnOutput($result, $code = 200) {
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	http_response_code($code);

	echo json_encode($result);
	die();
}

// Get parameters
$unzipid = trim(isset($_REQUEST['unzipid']) ? $_REQUEST['unzipid'] : '');
$contextid = trim(isset($_REQUEST['contextid']) ? $_REQUEST['contextid'] : '');
$restoreid = trim(isset($_REQUEST['restoreid']) ? $_REQUEST['restoreid'] : '');

$moodle = new MoodleAPI();
if ($contextid && $restoreid) {
	$result = $moodle->core_backup_get_async_backup_progress($contextid, [$restoreid]);
	if ($moodle->isError()) {
		returnOutput(['message' => $moodle->getError()], 400);
	}
	$res = is_array($result) ? current($result) : $result;
	if (!property_exists($res, 'status') || !property_exists($res, 'progress'))
		returnOutput($result, 400);
	if ($res->status != 1000 && $res->progress == 1) {
		$result = ["value" => 99.9999];
	} else {
		$result = ["value" => 50 + $res->progress * 50, "debug" => $res->progress];
	}
} elseif ($unzipid) {
	$result = $moodle->local_fhtw_std_async_unzip_progress($unzipid);
	if ($moodle->isError()) {
		returnOutput(['message' => $moodle->getError()], 400);
	}
	if (!property_exists($result, 'progress') || !property_exists($result, 'restoreid') || !property_exists($result, 'contextid'))
		returnOutput($result, 400);
	if (floatval($result->progress) == 1 && $result->restoreid && $result->contextid) {
		$contextid = $result->contextid;
		$restoreid = $result->restoreid;
		$result = $moodle->core_backup_get_async_backup_progress($contextid, [$restoreid]);
		$res = is_array($result) ? current($result) : $result;
		if (!property_exists($res, 'status') || !property_exists($res, 'progress'))
			returnOutput($result, 400);
		if ($res->status != 1000 && $res->progress == 1) {
			$result = ["contextid" => $contextid, "restoreid" => $restoreid, "value" => 99.9999];
		} else {
			$result = ["contextid" => $contextid, "restoreid" => $restoreid, "value" => 50 + $res->progress * 50];
		}
	} else {
		$result = ["value" => floatval($result->progress) * 50];
	}
} else {
	$result = ["value" => false];
}

returnOutput($result);
