<?php
/* Copyright (C) 2020 fhcomplete.org
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
 /*
  * Zwischenseite zur Umleitung von MoodleIDs auf die entsprechenden
  * LV-Informationen der richtigen LV/Semester
  */
require_once('../../../config/cis.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/basis_db.class.php');

$uid = get_uid();
if (isset($_GET['moodle_course_id']) && is_numeric($_GET['moodle_course_id']))
	$moodle_course_id = $_GET['moodle_course_id'];
else
	die('moodle_course_id muss uebergeben werden');

$db = new basis_db();
$qry = "SELECT
			tbl_moodle.studiensemester_kurzbz,
 			COALESCE(tbl_lehreinheit.lehrveranstaltung_id,tbl_moodle.lehrveranstaltung_id) as lvid
		FROM
			addon.tbl_moodle
			LEFT JOIN lehre.tbl_lehreinheit USING(lehreinheit_id)
			JOIN public.tbl_studiensemester ON(tbl_studiensemester.studiensemester_kurzbz = tbl_moodle.studiensemester_kurzbz)
		WHERE
			mdl_course_id=".$db->db_add_param($moodle_course_id, FHC_INTEGER)."
		ORDER BY tbl_studiensemester.start DESC";

if ($result = $db->db_query($qry))
{
	if ($db->db_num_rows($result)>0)
	{
		if($row = $db->db_fetch_object($result))
		{
			$url = '../../lvinfo/cis/view.php';
			$url .= '?lehrveranstaltung_id='.$row->lvid.'&studiensemester_kurzbz='.$row->studiensemester_kurzbz;

			echo '<html>
			<script>
			window.location.href="'.$url.'";
			</script>
			<body>
			Sie werden in Kürze weitergeleitet. <a href="'.$url.'">Link</a>
			</body>
			</html>';
			exit;
		}
	}
}

echo '<html>
<body>
Page not found / Die gesuchte Seite wurde nicht gefunden
</body>
</html>';
?>
