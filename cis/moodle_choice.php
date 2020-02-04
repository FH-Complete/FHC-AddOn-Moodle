<?php
/* Copyright (C) 2006 Technikum-Wien
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
 * Authors: Christian Paminger <christian.paminger@technikum-wien.at>,
 *          Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 *          Rudolf Hangl 		< rudolf.hangl@technikum-wien.at >
 *          Gerald Simane-Sequens 	< gerald.simane-sequens@technikum-wien.at >
 */
require_once(dirname(__FILE__).'/../lib/LogicCourses.php'); // A lot happens here!

require_once('../../../include/functions.inc.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/lehreinheit.class.php');

if (!$db = new basis_db()) die('Fehler beim Herstellen der Datenbankverbindung');

$user = get_uid();
$p = new phrasen(getSprache());

if (isset($_GET['lvid']))
	$lvid = $_GET['lvid'];
else
	die('lvid muss uebergeben werden');

if (isset($_GET['stsem']))
	$stsem = $_GET['stsem'];
else
	die('Es wurde kein Studiensemester uebergeben');

echo '<!DOCTYPE HTML>
<html>
<head>
	<meta charset="utf-8">
	<link href="../../../skin/style.css.php" rel="stylesheet" type="text/css">
</head>
<body>
<h1>'.$p->t('moodle/kursUebersicht').'</h1>
';

$meinekurse = '';
$allgemeinekurse = '';

$myCourses = array();
$otherCourses = array();

$courses = LogicCourses::getCoursesByLehrveranstaltungLehreinheitNoDistinct($lvid, $stsem);
while ($course = Database::fetchRow($courses))
{
	$moodleCourses = LogicCourses::core_course_get_courses(array($course->mdl_course_id));

	if(isset($moodleCourses[0]))
		$bezeichnung = $moodleCourses[0]->fullname;
	else
		$bezeichnung = '';

	if ($bezeichnung == '') $bezeichnung = 'Course '.$course->mdl_course_id;

	// List of all courses
	if (!in_array($course->mdl_course_id, $otherCourses))
	{
		$allgemeinekurse .= "<a href='".LogicCourses::getBaseURL()."/course/view.php?id=".$course->mdl_course_id."' class='Item'>$bezeichnung</a><br>";
		$otherCourses[] = $course->mdl_course_id;
	}

	// Test if the course belongs to the user
	if ($course->lehreinheit_id != '' && !in_array($course->mdl_course_id, $myCourses))
	{
		$zugeordnet = false;

		$le = new lehreinheit();
		$stud = $le->getStudenten($course->lehreinheit_id);
		foreach($stud as $course_stud)
		{
			if ($course_stud->uid == $user)
			{
				$zugeordnet = true;
				break;
			}
		}

		if ($zugeordnet)
		{
			$meinekurse .= "<a href='".LogicCourses::getBaseURL()."/course/view.php?id=".$course->mdl_course_id."' class='Item'>$bezeichnung</a><br>";;
			$myCourses[] = $course->mdl_course_id;
		}
	}
}

if($meinekurse!='')
{
	echo '<h2>'.$p->t('moodle/meineKurse').'</h2>';
	echo $meinekurse;
}

echo '<br><br><h2>'.$p->t('moodle/vorhandeneKurse').'</h2>';

echo $allgemeinekurse;

echo '</body>
</html>';
?>
