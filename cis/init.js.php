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
if(typeof addon =='undefined')
	var addon=Array();

addon.push(
{
	init: function(page, params)
	{
		// Diese Funktion wird nach dem Laden der Seite im CIS aufgerufen

		switch(page)
		{
			case 'cis/private/lehre/benotungstool/lvgesamtnoteverwalten.php':
				break;

			case 'cis/private/lehre/lesson.php':
				break;
            case 'cis/private/lvplan/stpl_detail.php':

                var lvId = params.lvId;
                var leId = params.leId;
                var stsem = params.stsem;

                getCourseId(lvId, leId, stsem);

                break;

            default:
				break;
		}
	}
});

function getCourseId(lvId, leId, stsem)
{
    $.ajax({
        type: "GET",
        dataType: 'json',
        url: '<?php echo APP_ROOT;?>addons/moodle/cis/course.php?lvId='+lvId+'&leId='+leId+'&stsem='+stsem,
        success: function (result)
        {
            var first_moodle_course_id = result[0].mdl_course_id

            if(!isEmpty(first_moodle_course_id))
            {
                var headerstag = '#stdplantablerow'
                $(headerstag).append('<th>Moodle</th>')
                for (i in result)
                {
                    //var testlink ='https://moodle.technikum-wien.at/course/view.php?id=' + result[i].mdl_course_id

                    var link = '<?php echo ADDON_MOODLE_PATH;?>' + '/course/view.php?id=' + result[i].mdl_course_id
                    var tag = '#moodlelink' + (i)

                    $(tag).append('<a href=' + link + '>moodle</a>');
                }
            }

        },
        error: function(){
            console.log("ERROR");
            //alert("Error Casetime Load");
        }
    });
}

function isEmpty(str) {
    return (!str || 0 === str.length);
}
