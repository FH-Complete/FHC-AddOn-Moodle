<?php

require_once(dirname(__DIR__).'/lib/MoodleAPI.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/include/sprache.class.php');

$moodleAPI = new MoodleAPI();
$langcode = 'de';
$sprache = new sprache();
if( true === ($sprache->load(getSprache())) )
{
	$langcode = substr($sprache->locale, 0, 2);
}

$moodle_start_date = new DateTime($start_date);
$timestart = $moodle_start_date->getTimestamp();
$moodle_end_date = new DateTime($end_date);
$timeend = $moodle_end_date->getTimestamp();

// Call the method
$events = $moodleAPI->fhcomplete_events_by_userid($username, $timestart, $timeend, $langcode);
$tz = new DateTimeZone(date_default_timezone_get());

if(is_array($events))
{
	foreach($events as $event){
		$tmptimestart = new DateTime("@".$event->timestart);
		$tmptimestart->setTimezone($tz);
		$tmptimestart->modify('-1 seconds');
		$event->timestart = $tmptimestart->format(DateTime::ATOM);

		$tmptimeend = clone $tmptimestart;
		$tmptimeend->modify('+'.$event->timeduration.' seconds');
		$event->timeend = $tmptimeend->format(DateTime::ATOM);

		$moodle_event_timestart = new DateTime($event->timestart);
		$moodle_event_timeend = new DateTime($event->timeend);

		$moodle_event = (object) array(
			'type' => 'moodle',
			'beginn' => $moodle_event_timestart->format('H:i:s'),
			'ende' => $moodle_event_timeend->format('H:i:s'),
			'isostart' => $moodle_event_timestart->format('c'),
			'isoend' => $moodle_event_timeend->format('c'),
			'allDayEvent' => true,
			'datum' => $moodle_event_timestart->format('Y-n-j'),
			'purpose' => $event->purpose,
			'assignment' => $event->activityname,
			'topic' => $event->activitystr,
			'lektor' => [],
			'gruppe' => [],
			'ort_kurzbz' => $event->location,
			'lehreinheit_id' => $event->lehreinheitsNummber ?? null,
			'titel' => isset($event->course->fullname)? $event->course->fullname:null,
			'lehrfach' => '',
			'lehrform' => '',
			'lehrfach_bez' => '',
			'organisationseinheit' => '',
			'farbe' => '00689E',
			'lehrveranstaltung_id' => 0,
			'ort_content_id' => 0,
			'url' => $event->url,
			'activityIcon' => isset($event->icon->iconurl)? $event->icon->iconurl:null,
			'actionname' => isset($event->action->name)?$event->action->name:null,
			'overdue' => !empty($event->overdue)
		);

		$moodle_events[] = $moodle_event;
	}
}
?>