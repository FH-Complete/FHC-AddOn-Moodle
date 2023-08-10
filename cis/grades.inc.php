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
require_once(dirname(__FILE__).'/../config.inc.php');
require_once(dirname(__FILE__).'/../include/moodle_course.class.php');

$moodle = new moodle_course();
$moodle->loadNoten($lvid, $stsem);

$moodle_course_bezeichnung = array();
$moodle_course_gewicht = array();

if (isset($moodle) && numberOfElements($moodle->result) > 0)
{
	foreach ($moodle->result as $moodle_noten)
	{
		// Kursbezeichnung laden falls noch nicht bekannt
		if (!isset($moodle_course_bezeichnung[$moodle_noten->mdl_course_id]))
		{
			$moodlecourse = new moodle_course();
			$moodlecourse->loadMoodleCourse($moodle_noten->mdl_course_id);

			$moodle_course_bezeichnung[$moodle_noten->mdl_course_id] = $moodlecourse->mdl_shortname;
		}

		// Gewichtung des Kurses laden falls noch nicht bekannt
		if (!isset($moodle_course_gewicht[$moodle_noten->mdl_course_id]))
		{
			$mdl_obj = new moodle_course();
			$mdl_lehreinheiten = $mdl_obj->getLeFromCourse($moodle_noten->mdl_course_id);

			foreach ($mdl_lehreinheiten as $row_mdl_lehreinheit)
			{
				if ($row_mdl_lehreinheit != '')
				{
					$lehreinheit_gewicht_obj = new lehreinheit();
					$lehreinheit_gewicht_obj->load($row_mdl_lehreinheit);

					if ($lehreinheit_gewicht_obj->gewicht != '')
					{
						$moodle_course_gewicht[$moodle_noten->mdl_course_id] = $lehreinheit_gewicht_obj->gewicht;
						break;
					}
				}
			}
		}

		$gewichtung = 1;
		if (isset($moodle_course_gewicht[$moodle_noten->mdl_course_id]))
			$gewichtung = $moodle_course_gewicht[$moodle_noten->mdl_course_id];

		if ($gewichtung == '')
			$gewichtung = 1;

		if (defined('CIS_GESAMTNOTE_PUNKTE') && CIS_GESAMTNOTE_PUNKTE)
		{
			$points = $moodle_noten->note;
			$grade = null;
		}
		else
		{
			$points = null;
			$grade = $moodle_noten->note;
		}

		if (isset($noten_array[$moodle_noten->note]))
			$note_bezeichnung = $noten_array[$moodle_noten->note]['anmerkung'];
		else
			$note_bezeichnung = $moodle_noten->note;

		if(isset($grades[$moodle_noten->uid]))
		{
			$grades[$moodle_noten->uid]['grades'][] = array(
				'grade' => $grade,
				'points' => $points,
				'weight' => $gewichtung,
				'text' => $note_bezeichnung.' ('.$moodle_course_bezeichnung[$moodle_noten->mdl_course_id].')'
			);
		}
	}
}
