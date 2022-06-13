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
/**
 * FH-Complete Addon Template Datenbank Check
 *
 * Prueft und aktualisiert die Datenbank
 */
require_once('../../config/system.config.inc.php');
require_once('../../include/basis_db.class.php');
require_once('../../include/functions.inc.php');
require_once('../../include/benutzerberechtigung.class.php');

// Datenbank Verbindung
$db = new basis_db();

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" href="../../skin/fhcomplete.css" type="text/css">
	<link rel="stylesheet" href="../../skin/vilesci.css" type="text/css">
	<title>Addon Datenbank Check</title>
</head>
<body>
<h1>Addon Datenbank Check</h1>';

$uid = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('basis/addon'))
{
	exit('Sie haben keine Berechtigung für die Verwaltung von Addons');
}
echo '<h2>Aktualisierung der Datenbank</h2>';

// Code fuer die Datenbankanpassungen
if($result = $db->db_query("SELECT 1 FROM system.tbl_berechtigung WHERE berechtigung_kurzbz='addon/moodle'"))
{
	if($db->db_num_rows($result)==0)
	{
		$qry="INSERT INTO system.tbl_berechtigung(berechtigung_kurzbz, beschreibung) VALUES('addon/moodle','Addon Moodle');";

		if(!$db->db_query($qry))
			echo '<strong>'.$db->db_last_error().'</strong><br>';
		else
			echo ' neue Berechtigung addon/moodle hinzugefuegt!<br>';
	}
}
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_moodle"))
{
	$qry = 'CREATE TABLE addon.tbl_moodle
			(
				moodle_id bigint NOT NULL,
				mdl_course_id bigint,
				mdl_source_course_id bigint,
				lehreinheit_id bigint,
				lehrveranstaltung_id bigint,
				studiensemester_kurzbz varchar(16),
				gruppen boolean,
				insertamum timestamp,
				insertvon varchar(32),
				updateamum timestamp,
				updatevon varchar(32),
				ext_id bigint
			);

	CREATE SEQUENCE addon.seq_moodle_moodle_id
	 INCREMENT BY 1
	 NO MAXVALUE
	 NO MINVALUE
	 CACHE 1;

	ALTER TABLE addon.tbl_moodle ADD CONSTRAINT pk_moodle PRIMARY KEY (moodle_id);
	ALTER TABLE addon.tbl_moodle ALTER COLUMN moodle_id SET DEFAULT nextval(\'addon.seq_moodle_moodle_id\');

	ALTER TABLE addon.tbl_moodle ADD CONSTRAINT fk_moodle_studiensemester FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester (studiensemester_kurzbz) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_moodle ADD CONSTRAINT fk_moodle_lehreinheit FOREIGN KEY (lehreinheit_id) REFERENCES lehre.tbl_lehreinheit (lehreinheit_id) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_moodle ADD CONSTRAINT fk_moodle_lehrveranstaltung FOREIGN KEY (lehrveranstaltung_id) REFERENCES lehre.tbl_lehrveranstaltung (lehrveranstaltung_id) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_moodle TO web;
	GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_moodle TO vilesci;
	GRANT SELECT, UPDATE ON addon.seq_moodle_moodle_id TO web;
	GRANT SELECT, UPDATE ON addon.seq_moodle_moodle_id TO vilesci;

	INSERT INTO addon.tbl_moodle(mdl_course_id, lehreinheit_id, lehrveranstaltung_id,
		studiensemester_kurzbz, gruppen, insertamum, insertvon, updateamum, updatevon, ext_id)
		SELECT
			mdl_course_id, lehreinheit_id, lehrveranstaltung_id, studiensemester_kurzbz, gruppen, insertamum,
			insertvon, null, null, moodle_id
		FROM lehre.tbl_moodle WHERE moodle_version=\'2.4\';
	';

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_moodle: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_moodle: Tabelle addon.tbl_moodle hinzugefuegt!<br>';
}
elseif(!$result = @$db->db_query("SELECT mdl_source_course_id FROM addon.tbl_moodle"))
{
	$qry = 'ALTER TABLE addon.tbl_moodle ADD mdl_source_course_id bigint;';
	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_moodle: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_moodle: Tabelle addon.tbl_moodle um Feld mdl_source_course erweitert!<br>';
}
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_moodle_quellkurs"))
{
	$qry = 'CREATE TABLE addon.tbl_moodle_quellkurs
			(
				moodle_quellkurs_id bigint NOT NULL,
				lehrveranstaltung_id bigint NOT NULL,
				sprache varchar(16) NOT NULL,
				mdl_course_id bigint NOT NULL,
				insertamum timestamp,
				insertvon varchar(32),
				updateamum timestamp,
				updatevon varchar(32)
			);

	CREATE SEQUENCE addon.seq_moodle_quellkurs_moodle_quellkurs_id
	 INCREMENT BY 1
	 NO MAXVALUE
	 NO MINVALUE
	 CACHE 1;

	ALTER TABLE addon.tbl_moodle_quellkurs ADD CONSTRAINT pk_moodle_quellkurs PRIMARY KEY (moodle_quellkurs_id);
	ALTER TABLE addon.tbl_moodle_quellkurs ADD CONSTRAINT uc_moodle_quellkurs_local UNIQUE (lehrveranstaltung_id, sprache);
	ALTER TABLE addon.tbl_moodle_quellkurs ADD CONSTRAINT uc_moodle_quellkurs_foreign UNIQUE (mdl_course_id);
	ALTER TABLE addon.tbl_moodle_quellkurs ALTER COLUMN moodle_quellkurs_id SET DEFAULT nextval(\'addon.seq_moodle_quellkurs_moodle_quellkurs_id\');

	ALTER TABLE addon.tbl_moodle_quellkurs ADD CONSTRAINT fk_moodle_quellkurs_lehrveranstaltung FOREIGN KEY (lehrveranstaltung_id) REFERENCES lehre.tbl_lehrveranstaltung (lehrveranstaltung_id) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_moodle_quellkurs ADD CONSTRAINT fk_moodle_quellkurs_sprache FOREIGN KEY (sprache) REFERENCES public.tbl_sprache (sprache) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_moodle_quellkurs TO web;
	GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_moodle_quellkurs TO vilesci;
	GRANT SELECT, UPDATE ON addon.seq_moodle_quellkurs_moodle_quellkurs_id TO web;
	GRANT SELECT, UPDATE ON addon.seq_moodle_quellkurs_moodle_quellkurs_id TO vilesci;
	';

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_moodle_quellkurs: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_moodle_quellkurs: Tabelle addon.tbl_moodle_quellkurs hinzugefuegt!<br>';
}

// Cronjob Installieren
if($result = $db->db_query("SELECT 1 FROM system.tbl_cronjob WHERE titel='AddOn Moodle User Sync'"))
{
	if($db->db_num_rows($result)==0)
	{
		$file = dirname(__FILE__).'/cronjobs/sync_moodle_user.php';
		$qry="INSERT INTO system.tbl_cronjob(titel, beschreibung,file,last_execute, aktiv, stunde, minute)
		VALUES('AddOn Moodle User Sync','Addon Moodle User Synchronisation', ".$db->db_add_param($file).",now(), false, '2','0');";

		if(!$db->db_query($qry))
			echo '<strong>'.$db->db_last_error().'</strong><br>';
		else
			echo ' neuer Cronjob "AddOn Moodle User Sync" hinzugefuegt!<br>';
	}
}

// Add column gruppe_kurzbz to addon.tbl_moodle
if (!$result = @$db->db_query('SELECT gruppe_kurzbz FROM addon.tbl_moodle LIMIT 1'))
{
	$qry = 'ALTER TABLE addon.tbl_moodle ADD COLUMN gruppe_kurzbz CHARACTER VARYING(32);';
	if (!$db->db_query($qry))
		echo '<strong>addon.tbl_moodle: '.$db->db_last_error().'</strong><br>';
	else
		echo '<br>Added column gruppe_kurzbz to table addon.tbl_moodle<br>';
}

// FOREIGN KEY fk_moodle_gruppe_kurzbz: addon.tbl_moodle.gruppe_kurzbz references public.tbl_gruppe.gruppe_kurzbz
if ($result = @$db->db_query("SELECT conname FROM pg_constraint WHERE conname = 'fk_moodle_gruppe_kurzbz'"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "ALTER TABLE addon.tbl_moodle ADD CONSTRAINT fk_moodle_gruppe_kurzbz FOREIGN KEY (gruppe_kurzbz) REFERENCES public.tbl_gruppe(gruppe_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;";

		if (!$db->db_query($qry))
			echo '<strong>addon.tbl_moodle: '.$db->db_last_error().'</strong><br>';
		else
			echo '<br>addon.tbl_moodle: added foreign key on column gruppe_kurzbz referenced to public.tbl_gruppe(gruppe_kurzbz)<br>';
	}
}

echo '<br>Aktualisierung abgeschlossen<br><br>';
echo '<h2>Gegenprüfung</h2>';

// Liste der verwendeten Tabellen / Spalten des Addons
$tabellen = array(
	'addon.tbl_moodle' => array(
		'moodle_id',
		'mdl_course_id',
		'lehreinheit_id',
		'lehrveranstaltung_id',
		'studiensemester_kurzbz',
		'gruppen',
		'insertamum',
		'insertvon',
		'updateamum',
		'updatevon',
		'ext_id',
                'gruppe_kurzbz',
                'mdl_source_course_id'
	),
        'addon.tbl_moodle_quellkurs' => array(
                'moodle_quellkurs_id',
	        'lehrveranstaltung_id',
                'sprache',
	        'mdl_course_id',
	        'insertamum',
	        'insertvon',
	        'updateamum',
	        'updatevon'
        )
);

$tabs=array_keys($tabellen);
$i=0;
foreach ($tabellen AS $attribute)
{
	$sql_attr='';
	foreach($attribute AS $attr)
		$sql_attr.=$attr.',';
	$sql_attr=substr($sql_attr, 0, -1);

	if (!@$db->db_query('SELECT '.$sql_attr.' FROM '.$tabs[$i].' LIMIT 1;'))
		echo '<BR><strong>'.$tabs[$i].': '.$db->db_last_error().' </strong><BR>';
	else
		echo $tabs[$i].': OK - ';
	flush();
	$i++;
}
