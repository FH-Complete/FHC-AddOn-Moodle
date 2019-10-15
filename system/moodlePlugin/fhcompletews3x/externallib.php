<?php
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");

class local_fhcompletews_external extends external_api
{

/**************************************************
 * Webservice get_course_grades
 *
 * Laedt die Noten eines Kurses
 **************************************************/
	public static function get_course_grades_parameters()
	{
        return new external_function_parameters(
                array(
					'courseid' => new external_value(PARAM_INT, 'Moodle CourseID'),
					'type' => new external_value(PARAM_INT,'Type 1=Punkte, 2=Prozent, 3=Endnote lt Skala')
				), 'Get Course Grades'
        );
    }

    /**
     * Get course Grades
     * @param int courseid
     * @return array
     */
    public static function get_course_grades($courseid, $type)
	{
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
		require_once($CFG->dirroot.'/grade/export/lib.php');

        //validate parameter
        $params = self::validate_parameters(self::get_course_grades_parameters(),
                        array('courseid' => $courseid, 'type'=>$type));

		$notenart = $type;
		//$notenart=2; // 1=Punkte; 2=Prozent; 3=Endnote nach Skala
		$gui=array();
		$final_id='';
		$data = array();

		// Checks if grades need to be regrated...
		if (grade_needs_regrade_final_grades($courseid))
		{
			// ...if needed then regrade it!
			if (!grade_regrade_final_grades($courseid))
			{
				error_log('Error while regrading course with id: '.$courseid);
			}
		}

		// Kursdaten Laden
		if (!$course = $DB->get_record('course', array('id'=>$courseid)))
		{
			throw new moodle_exception('Course not found', '', '', null, 'The course ' . $courseid . ' is not found');
			return false;
		}

		$id=$course->id;
		$kursname=$course->fullname;
		$shortname=$course->shortname;

		//ODS Notenexport starten
		require_login($course);
		$context = get_context_instance(CONTEXT_COURSE, $courseid);
		require_once($CFG->dirroot.'/grade/export/ods/grade_export_ods.php');

		$moodle28=false;

		try
		{
			$method = new ReflectionMethod('grade_export_ods','__construct');

			if(count($method->getParameters())==3)
				$moodle28=true;
		}
		catch(ReflectionException $e)
		{
		}

		if ($moodle28)
		{
			//ab Moodle 2.8 hat grade_export_ods nur noch 3 Parameter
			$formdata = new stdClass();
			$formdata->display=$notenart;
			$formdata->itemids=0;
			$formdata->decimals=2;
			$formdata->export_feedback=false;
			if (!$export = new grade_export_ods($course, 0, $formdata))
			{
				throw new moodle_exception('Error occurred while executing grade_export_ods, moodle version <= 2.8', '', '', null, "Moodle-Kurs ".$id." ".$shortname." - keine Export Information gefunden");
				return false;
			}
		}
		else
		{
			if (!$export = new grade_export_ods($course, 0, 0, false, false, $notenart, 2))
			{
				throw new moodle_exception('Error occurred while executing grade_export_ods, moodle version > 2.8', '', '', null, "Moodle-Kurs ".$id." ".$shortname." - keine Export Information gefunden");
				return false;
			}
		}
		$grad =$export->columns;

		// Im Export sind die Noten fuer alle Abgaben, Quiz, etc enthalten
		// Wir brauchen hier nur die Gesamtnote fuer die ganzen Kurs
		foreach ($export->columns as $key=>$grade_item)
		{
			// Gesamtnote hat den itemtype "course"
			if($grade_item->itemtype=='course')
			{
				$final_id=$key;
				$finalitem = $grade_item;
				break;
			}
		}

		if(!$final_id=='')
		{
			// throw new moodle_exception('Failed to retrive the final grade', '', '', null,"Moodle-Kurs ".$id." ".$shortname." - keine Endnote gefunden");
			// return false;

			// Liste mit allen Studierenden des Kurses durchlaufen
			$geub = new grade_export_update_buffer();
			$gui = new graded_users_iterator($export->course, array($final_id=>$finalitem), $export->groupid); //$export->columns

			$gui->init();
			$kursgrad =array();

			while ($userdata = $gui->next_user())
			{
				$user_item=array();
			   	$user = $userdata->user;
			   	$user_item['vorname']=$user->firstname;
			   	$user_item['nachname']=$user->lastname;
			   	$user_item['idnummer']=$user->idnumber;
				$user_item['username']=$user->username;

				// Aus den vorhanden Noten wird die Endnote fuer den Kurs herausgesucht
				if(isset($userdata->grades[$final_id]))
				{
				  	$gradestr = $export->format_grade($userdata->grades[$final_id]);
			     	$user_item['note']=$gradestr;

					// Wenn Prozent dann Prozentzeichen entfernen
					if(strpos($user_item['note'],'%')!==false)
				     	$user_item['note']=trim(str_replace('%','',$user_item['note']));

					// nur zurueckliefern wenn eine Note gefunden wurde und diese nicht '-' ist
					if($user_item['note']!='-')
						$data[]=$user_item;
				}
			}

			$gui->close();
			$geub->close();
		}

		return $data;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_grades_returns()
	{
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'vorname' => new external_value(PARAM_TEXT, 'vorname'),
                            'nachname' => new external_value(PARAM_TEXT, 'nachname'),
                            'idnummer' => new external_value(PARAM_TEXT, 'idnummer'),
                            'username' => new external_value(PARAM_TEXT, 'username'),
                            'note' => new external_value(PARAM_TEXT, 'note'),
                        ), 'course'
                )
        );
    }
}
