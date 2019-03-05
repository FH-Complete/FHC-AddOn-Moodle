<?php

/**
 *
 */
class Output
{
	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public static function printLineSeparator()
	{
		echo self::_getNewLine();
		echo '-----------------------------------------------------------------------------------------------------';
	}

	/**
	 *
	 */
	public static function printInfo($message)
	{
		echo self::_getNewLine().'INFO: '.$message;
	}

	/**
	 *
	 */
	public static function printWarning($message)
	{
		echo self::_getNewLine().'WARNING: '.$message;
	}

	/**
	 *
	 */
	public static function printError($message)
	{
		echo self::_getNewLine().'ERROR: '.$message;
	}

	/**
	 *
	 */
	public static function printDebug($message)
	{
		if (ADDON_MOODLE_DEBUG_ENABLED === true)
		{
			echo self::_getNewLine().'DEBUG: '.$message;
		}
	}

	/**
	 *
	 */
	public static function varDumpParameters($parameters)
	{
		echo self::_getNewLine();
		var_dump($parameters);
		echo self::_getNewLine();
	}

	// --------------------------------------------------------------------------------------------
    // Private methods

	/**
	 *
	 */
	private static function _getNewLine()
	{
		$newLine = "\n";

		if (php_sapi_name() != 'cli')
		{
			$newLine = '<br>';
		}

		return $newLine;
	}
}
