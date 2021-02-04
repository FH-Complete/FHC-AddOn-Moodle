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
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 */
/**
 * Load grades from Moodle
 */
require_once(dirname(__FILE__).'/../lib/Logic.php'); // A lot happens here!

// If only grades from moodle are enabled in the configs
if (ADDON_MOODLE_ENABLE_GRADES === true)
{
	$courseGrades = Logic::loadCourseGrades($lvid, $stsem);
	$moodle_course_bezeichnung = array();
	$moodle_course_gewicht = array();
	
	foreach ($courseGrades as $courseGrade)
	{
		// Kursbezeichnung laden falls noch nicht bekannt
		if (!isset($moodle_course_bezeichnung[$courseGrade->mdl_course_id]))
		{
			$courses = Logic::core_course_get_courses(array($courseGrade->mdl_course_id));
			if (count($courses) > 0)
			{
				$moodle_course_bezeichnung[$courseGrade->mdl_course_id] = $courses[0]->shortname;
			}
		}
	
		// Gewichtung des Kurses laden falls noch nicht bekannt
		if (!isset($moodle_course_gewicht[$courseGrade->mdl_course_id]))
		{
			$les = Logic::getLeFromCourse($courseGrade->mdl_course_id);
			while ($le = Database::fetchRow($les))
			{
				if ($le->lehreinheit_id != '')
				{
					$lehreinheit_gewicht_obj = new lehreinheit();
					$lehreinheit_gewicht_obj->load($le->lehreinheit_id);
	
					if ($lehreinheit_gewicht_obj->gewicht != '')
					{
						$moodle_course_gewicht[$courseGrade->mdl_course_id] = $lehreinheit_gewicht_obj->gewicht;
						break;
					}
				}
			}
		}
	
		$gewichtung = 1;
		if (isset($moodle_course_gewicht[$courseGrade->mdl_course_id]))
			$gewichtung = $moodle_course_gewicht[$courseGrade->mdl_course_id];
	
		if ($gewichtung == '')
			$gewichtung = 1;
	
		if (defined('CIS_GESAMTNOTE_PUNKTE') && CIS_GESAMTNOTE_PUNKTE)
		{
			$points = $courseGrade->note;
			$grade = null;
		}
		else
		{
			$points = null;
			$grade = $courseGrade->note;
		}
	
		if (isset($noten_array[$courseGrade->note]))
			$note_bezeichnung = $noten_array[$courseGrade->note]['anmerkung'];
		else
			$note_bezeichnung = $courseGrade->note;
	
		if(isset($grades[$courseGrade->username]))
		{
			$grades[$courseGrade->username]['grades'][] = array(
				'grade' => $grade,
				'points' => $points,
				'weight' => $gewichtung,
				'text' => $note_bezeichnung.' ('.$moodle_course_bezeichnung[$courseGrade->mdl_course_id].')'
			);
		}
	}
}
