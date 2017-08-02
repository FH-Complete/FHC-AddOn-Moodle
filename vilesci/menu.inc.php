<?php
/* Copyright (C) 2015 fhcomplete.org
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
 */
/**
 * Hinzufuegen von neuen Menuepunkten im Vilesci
 */
$menu_addon=array
(
	'AddonMoodle'=>array
	(
		'name'=>'Moodle', 'permissions'=>array('basis/moodle'),
		'Account'=>array('name'=>'Account Moodle', 'link'=>'../addons/moodle/vilesci/account_verwaltung.php', 'target'=>'main'),
		'Kursentfernen'=>array('name'=>'Kurs entfernen', 'link'=>'../addons/moodle/vilesci/kurs_verwaltung.php', 'target'=>'main'),
		'Rollenzuteilung'=>array('name'=>'Rollenzuteilung', 'link'=>'../addons/moodle/vilesci/rollenzuteilung.php', 'target'=>'main'),
		'UserSync'=>array('name'=>'User Sync', 'link'=>'../addons/moodle/vilesci/user_sync.php', 'target'=>'main'),
		'UserMassCreate'=>array('name'=>'User Massenanlage', 'link'=>'../addons/moodle/vilesci/account_masscreate.php', 'target'=>'main'),
	)
);

$menu['Lehre']=array_merge($menu['Lehre'],$menu_addon);
?>
