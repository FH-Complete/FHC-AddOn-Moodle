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
 * Hinzufuegen von neuen Menuepunkten bei CIS Lehrveranstaltungen
 */
require_once(dirname(__FILE__).'/../lib/LogicCourses.php'); // A lot happens here!

$showmoodle = false;
$link_target = '';
$link_onclick = '';
$text = '';
$link = '';

$stg = new studiengang();
$stg->load($lv->studiengang_kz);
if ($stg->moodle) $showmoodle = true;

$courses = LogicCourses::getCoursesByLehrveranstaltungLehreinheit($lvid, $angezeigtes_stsem);
if (Database::rowsNumber($courses) > 0) $showmoodle = true;

if ($angemeldet)
{
	if ($showmoodle)
	{
		$link = APP_ROOT."addons/moodle/cis/moodle_choice.php?lvid=".urlencode($lvid)."&stsem=".urlencode($angezeigtes_stsem);

		if (Database::rowsNumber($courses) > 0)
		{
			if (!$is_lector)
			{
				$coursesStudent = LogicCourses::getCoursesByStudent($lvid, $angezeigtes_stsem, $user);

				if (Database::rowsNumber($courses) == 1 || Database::rowsNumber($coursesStudent) == 1)
				{
					if (Database::rowsNumber($coursesStudent) == 1)
					{
						$courseStudent = Database::fetchRow($coursesStudent);
						$mdl_course_id = $courseStudent->mdl_course_id;
					}
					else
					{
						$course = Database::fetchRow($courses);
						$mdl_course_id = $course->mdl_course_id;
					}

					$link = LogicCourses::getBaseURL().'/course/view.php?id='.urlencode($mdl_course_id);
				}
			}
			else
			{
				if (Database::rowsNumber($courses) == 1)
				{
					$course = Database::fetchRow($courses);
					$link = LogicCourses::getBaseURL().'/course/view.php?id='.urlencode($course->mdl_course_id);
				}
			}
			$link_target = '_blank';
		}
		else
		{
			$link = '';
		}

		if ($is_lector
			&& (!defined('ADDON_MOODLE_LECTOR_CREATE_COURSE') || (defined('ADDON_MOODLE_LECTOR_CREATE_COURSE') && ADDON_MOODLE_LECTOR_CREATE_COURSE))
		)
		{
			$text .= $p->t('moodle/subTextIcon', array(urlencode($lvid), urlencode($angezeigtes_stsem)));
		}
	}
}

if ($showmoodle)
{
	$menu[] = array (
		'id' => 'addon_moodle_menu_moodle',
		'position' => '70',
		'name' => $p->t('moodle/moodle'),
		'icon' => '../../../addons/moodle/skin/images/button_moodle.png',
		'link' => $link,
		'link_target' => $link_target,
		'link_onclick' => $link_onclick,
		'text' => $text
	);
}
?>
