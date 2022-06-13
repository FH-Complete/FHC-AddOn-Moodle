<?php
/* Copyright (C) 2013 FH Technikum-Wien
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
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');

echo '<!DOCTYPE HTML>
<html>
<head>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="../../../skin/fhcomplete.css" type="text/css">
	<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
	<title>Moodle</title>
</head>
<body>
<h1>Moodle</h1>';

$uid = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('basis/addon'))
{
	die('Sie haben keine Berechtigung fuer diese Seite');
}
echo '
<h2>Accounts</h2>
<ul>
	<li><a href="account_verwaltung.php">Einzelne Accounts anlegen</a></li>
	<li><a href="account_masscreate.php">Liste an Accounts anlegen</a></li>
	<li><a href="rollenzuteilung.php">Person in mehrere Kurse einschreiben</a></li>
	<li><a href="usersSynchronization.php">Synch \'em all</a></li>
</ul>

<h2>Kurse</h2>
<ul>
	<li><a href="kurs_verwaltung.php">Kursverwaltung</a></li>
    <li><a href="quellkurs_verwaltung.php">Quellkursverwaltung</a></li>
</ul>
';
?>
