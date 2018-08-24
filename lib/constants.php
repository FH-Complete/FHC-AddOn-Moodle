<?php

// Moodle error codes
define('MOODLE_INVALID_TOKEN',			'invalidtoken');
define('MOODLE_INVALID_WS_FUNCTION',	'invalidrecord');
define('MOODLE_INVALID_WS_PARAMETER',	'invalidparameter');

define('SUCCESS', 'OK'); // success code in the addon

// Blocking errors
define('MOODLE_ERROR',					'ERR0001');
define('CONNECTION_ERROR',  			'ERR0002');
define('JSON_PARSE_ERROR',    			'ERR0003');
define('NO_RESPONSE_BODY',    			'ERR0004');
define('UNAUTHORIZED',          		'ERR0005');
define('MISSING_REQUIRED_PARAMETERS',	'ERR0006');
define('WRONG_WS_PARAMETERS',			'ERR0007');
define('INVALID_WS_FUNCTION',			'ERR0008');
define('INVALID_WS_PARAMETER',			'ERR0009');

// Non blocking errors (warnings)
define('NO_DATA',               		'WAR0001');

// Connection parameters names
define('PROTOCOL',			'protocol');
define('HOST',				'host');
define('PATH',				'path');

// Moodle REST API common parameters
define('TOKEN',				'wstoken');
define('WS_FORMAT',			'moodlewsrestformat');

// Moodle REST API parameter name
define('WS_FUNCTION',		'wsfunction');