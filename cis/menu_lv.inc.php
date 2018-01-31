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
require_once(dirname(__FILE__).'/../config.inc.php');
require_once(dirname(__FILE__).'/../include/moodle_course.class.php');

$showmoodle=false;
$link_target='';
$link_onclick='';
$text='';
$link='';

$stg = new studiengang();
$stg->load($lv->studiengang_kz);
if ($stg->moodle)
	$showmoodle = true;

$moodle = new moodle_course();
if(!$moodle->getAll($lvid, $angezeigtes_stsem))
	echo "ERROR:".$moodle->errormsg;
if (count($moodle->result)>0)
	$showmoodle = true;

if ($angemeldet)
{
	if ($showmoodle)
	{
		$link = APP_ROOT."addons/moodle/cis/moodle_choice.php?lvid=".urlencode($lvid)."&stsem=".urlencode($angezeigtes_stsem);
		if (count($moodle->result) > 0)
		{
			if (!$is_lector)
			{
				$mdl_user_course = new moodle_course();
				$mdl_user_course->getCourse($lvid, $angezeigtes_stsem, $user);

				if(count($moodle->result)==1 || count($mdl_user_course->result)==1)
				{
					if(count($mdl_user_course->result)==1)
						$mdl_course_id = $mdl_user_course->result[0]->mdl_course_id;
					else
						$mdl_course_id = $moodle->result[0]->mdl_course_id;
					$link = ADDON_MOODLE_PATH.'course/view.php?id='.urlencode($mdl_course_id);
				}
				else
					$link = APP_ROOT."addons/moodle/cis/moodle_choice.php?lvid=".urlencode($lvid)."&stsem=".urlencode($angezeigtes_stsem);
			}
			else
			{
				if (count($moodle->result) == 1)
				{
					$link = ADDON_MOODLE_PATH.'course/view.php?id='.urlencode($moodle->result[0]->mdl_course_id);
				}
				else
				{
					$link = APP_ROOT."addons/moodle/cis/moodle_choice.php";
					$link .= "?lvid=".urlencode($lvid)."&stsem=".urlencode($angezeigtes_stsem);
				}
			}
			$link_target = '_blank';
		}
		else
		{
			$link = '';
		}

		if ($is_lector &&
			(
				!defined('ADDON_MOODLE_LECTOR_CREATE_COURSE')
				|| (defined('ADDON_MOODLE_LECTOR_CREATE_COURSE') && ADDON_MOODLE_LECTOR_CREATE_COURSE)
			))
		{
			$text .=  $p->t('moodle/subTextIcon', array(urlencode($lvid),urlencode($angezeigtes_stsem)));
		}
	}
}

if ($showmoodle)
{
	$menu[] = array
	(
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
