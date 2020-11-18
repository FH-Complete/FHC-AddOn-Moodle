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
 * Initialisierung des Addons
 */
require_once('../../../config/cis.config.inc.php');
require_once('../config.inc.php');
?>

if (typeof addon == 'undefined')
{
	var addon = Array();
}

addon.push(
	{
		init: function (page, params)
		{
			// Diese Funktion wird nach dem Laden der Seite im CIS aufgerufen
			switch (page)
			{
				case 'cis/private/lehre/benotungstool/lvgesamtnoteverwalten.php':
					break;
				case 'cis/private/lehre/lesson.php':
					break;
				case 'cis/private/lvplan/stpl_detail.php':
					let courses = params.courses;
					let stsem = params.stsem;

					getCourseId(courses, stsem);
					break;
				default:
					break;
			}
		}
	});

function getCourseId(courses, stsem)
{
	$.ajax({
		type: "GET",
		url: '<?php echo APP_ROOT;?>addons/moodle/cis/course.php',
		dataType: 'json',
		data: {courses: courses, stsem: stsem},
		success: function (result)
		{
			//make a new array containing the <a>Tags to append to the table.
			// If the element is empty (i.e. no moodle course was found for this LV)
			// then append the empty string to the array
			let moodle_courses_aTags = result.map(x => x[0]).map(x => isEmpty(x) ? '' : makeMoodleLink(x.mdl_course_id));
			
			//check if there exists at least one element of moodle_courses which is not empty
			//-> if there is at least one course it appends the moodle column to the table of stpl_detail.php
			//->then iterate through the moodle course array to fill the coulmn with the corresponding moodle course
			if (!moodle_courses_aTags.every(x => isEmpty(x)))
			{
				let headerstag = '#stdplantablerow';
				$(headerstag).append('<th>Moodle</th>');

				for (i in moodle_courses_aTags)
				{
					let tag = '#moodlelink' + (i);
					$(tag).append(moodle_courses_aTags[i]);
				}
			}

		},
		error: function ()
		{
			console.log("ERROR WHILE MAKING AJAX CALL");
		}
	});
}

function isEmpty(str)
{
	return (!str || 0 === str.length);
}

function makeMoodleLink(mdl_course_id)
{
	if (!isEmpty(mdl_course_id))
	{
		let aTag = '<a href=' + '<?php echo ADDON_MOODLE_PATH;?>' + '/course/view.php?id=' + mdl_course_id + ' target="_blank">zum Moodlekurs</a>'
		return aTag;
	}
	else
	{
		return '';
	}

}