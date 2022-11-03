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

// Get search string that shall be used for suggestions in autocomplete field
$search = trim(isset($_REQUEST['term']) ? $_REQUEST['term'] : '');
if (is_null($search) || empty($search))
{
	exit();
}
else
{
	$searchItems = explode(' ', $search);
}
$filter = isset($_REQUEST['filter']) ? (object)['studiengang_kz' => $_REQUEST['filter']] : null;

$data = [];

$result = [];
$moodle = new MoodleAPI();
$templates = new LogicTemplates();
foreach ($searchItems as $searchItem) {
	if (is_numeric($searchItem)) {
		if ($res = $moodle->core_course_get_courses([$searchItem])) {
			foreach ($res as $course) {
				if ($templates->isSourceCourse($course) && ($filter === null || $templates->areMapped($course, $filter)))
					$result[$course->id] = ['value' => $course->id, 'label' => $course->fullname];
			}
		}
	}
}
foreach ($searchItems as $searchItem) {
	if ($res = $moodle->core_course_search_courses('search', $searchItem)) {
		foreach ($res->courses as $course) {
			if ($templates->isSourceCourse($course) && ($filter === null || $templates->areMapped($course, $filter)))
				$result[$course->id] = ['value' => $course->id, 'label' => $course->fullname];
		}
	}
}

echo json_encode($result);
