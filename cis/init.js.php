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
			let moodle_courses = result.map(x => x[0]);
			//checks if every element of moodle_courses is empty
			//-> if yes then there are no Moodle Courses for any of the requested JSON params
			if (!moodle_courses.every(x => isEmpty(x)))
			{
				let headerstag = '#stdplantablerow'
				$(headerstag).append('<th>Moodle</th>')

				for (i in moodle_courses)
				{
					let link = '<?php echo ADDON_MOODLE_PATH;?>' + '/course/view.php?id=' + moodle_courses[i].mdl_course_id
					let tag = '#moodlelink' + (i)

					$(tag).append('<a href=' + link + ' target="_blank">zum Moodlekurs</a>');
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

