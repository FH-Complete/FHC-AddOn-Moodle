<?php

require_once(dirname(__FILE__).'/../../../vendor/autoload.php');
require_once('MoodleClientConstants.php');

/**
 * Manages REST API calls
 */
class MoodleClient
{
    const HTTP_GET_METHOD = 'GET'; // http get method name
    const HTTP_POST_METHOD = 'POST'; // http post method name
	const URI_TEMPLATE = '%s://%s/%s?%s=%s&%s=%s&%s=%s'; // URI format
	const BASE_URL_TEMPLATE = '%s://%s'; // Base URL format

	private $_connectionArray;		// contains the connection parameters configuration array

	private $_wsFunction;			//

	private $_httpMethod;			// http method used to call this server
	private $_callParametersArray;	// contains the parameters to give to the remote web service

	private $_callResult; 			// contains the result of the called remote web service

	private $_error;				// true if an error occurred
	private $_errorMessage;			// contains the error message

	private $_hasData;				// indicates if there are data in the response or not
	private $_emptyResponse;		// indicates if the response is empty or not

    /**
     * Object initialization
     */
    public function __construct()
    {
		$this->_setPropertiesDefault(); // properties initialization

        $this->_setConnection(); // loads the configurations
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

    /**
     * Performs a call to a remote web service
     */
    public function call($wsFunction, $httpMethod = MoodleClient::HTTP_GET_METHOD, $callParametersArray = array())
    {
		if ($wsFunction != null && trim($wsFunction) != '')
		{
			$this->_wsFunction = $wsFunction;
		}
		else
		{
			$this->_error(MISSING_REQUIRED_PARAMETERS);
		}

		if ($httpMethod != null
			&& ($httpMethod == MoodleClient::HTTP_GET_METHOD || $httpMethod == MoodleClient::HTTP_POST_METHOD))
		{
			$this->_httpMethod = $httpMethod;
		}
		else
		{
			$this->_error(WRONG_WS_PARAMETERS);
		}

		if (is_array($callParametersArray))
		{
			$this->_callParametersArray = $callParametersArray;
		}
		else
		{
			$this->_error(WRONG_WS_PARAMETERS);
		}

		if ($this->isError()) return null; //

        return $this->_callRemoteWS($this->_generateURI()); // perform a remote ws call with the given uri
    }

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Returns false if an error occurred, otherwise true
	 */
	public function isSuccess()
	{
		return !$this->isError();
	}

	/**
	 * Returns true if the response contains data, otherwise false
	 */
	public function hasData()
	{
		return $this->_hasData;
	}

	/**
	 * Returns true if the response was empty, otherwise false
	 */
	public function hasEmptyResponse()
	{
		return $this->_emptyResponse;
	}

	// --------------------------------------------------------------------------------------------
    // Protected  methods

	/**
     *
     */
    protected function getBaseURL()
    {
        $baseURL = sprintf(
            MoodleClient::BASE_URL_TEMPLATE,
            $this->_connectionArray[PROTOCOL],
            $this->_connectionArray[HOST]
        );

		return $baseURL;
    }

    // --------------------------------------------------------------------------------------------
    // Private methods

	/**
     * Initialization of the properties of this object
     */
	private function _setPropertiesDefault()
	{
		$this->_connectionArray = null;

		$this->_wsFunction = null;

		$this->_httpMethod = null;

		$this->_callParametersArray = array();

		$this->_callResult = null;

		$this->_error = false;

		$this->_errorMessage = '';

		$this->_hasData = false;

		$this->_emptyResponse = false;
	}

    /**
     * Sets the connection
     */
    private function _setConnection()
    {
		global $connection, $activeConnection;

		$this->_connectionArray = $connection[$activeConnection];
    }

    /**
     * Returns true if the HTTP method used to call this server is GET
     */
    private function _isGET()
    {
        return $this->_httpMethod == MoodleClient::HTTP_GET_METHOD;
    }

    /**
     * Returns true if the HTTP method used to call this server is POST
     */
    private function _isPOST()
    {
        return $this->_httpMethod == MoodleClient::HTTP_POST_METHOD;
    }

    /**
     * Generate the URI to call the remote web service
     */
    private function _generateURI()
    {
        $uri = sprintf(
            MoodleClient::URI_TEMPLATE,
            $this->_connectionArray[PROTOCOL],
            $this->_connectionArray[HOST],
            $this->_connectionArray[PATH],
			WS_FORMAT,
			$this->_connectionArray[WS_FORMAT],
			TOKEN,
			$this->_connectionArray[TOKEN],
			WS_FUNCTION,
			$this->_wsFunction
        );

		// If the call was performed using a HTTP GET then append the query string to the URI
        if ($this->_isGET())
        {
			$queryString = '';

			// Create the query string
			foreach ($this->_callParametersArray as $name => $value)
			{
				if (is_array($value)) // if is an array
				{
					foreach ($value as $key => $val)
					{
						$queryString .= '&'.$name.'[]='.$val;
					}
				}
				else // otherwise
				{
					$queryString .= '&'.$name.'='.$value;
				}
			}

            $uri .= $queryString;
        }

        return $uri;
    }

	/**
	 * Performs a remote web service call with the given uri and returns the result after having checked it
	 */
	private function _callRemoteWS($uri)
	{
		$response = null;

		try
		{
			if ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			else // else if the call was performed using a HTTP POST...
			{
				$response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP GET method
			}

			// Checks the response of the remote web service and handles possible errors
			// Eventually here is also called a hook, so the data could have been manipulated
			$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$response = null;
			$this->_error(CONNECTION_ERROR);
		}
		// otherwise another error has occurred, most likely the result of the
		// remote web service is not json so a parse error is raised
		catch (Exception $e)
		{
			$response = null;
			$this->_error(JSON_PARSE_ERROR);
		}

		return $response;
	}

    /**
     * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are placed into the URI
     */
    private function _callGET($uri)
    {
        return \Httpful\Request::get($uri)
            ->expectsJson() // parse from json
            ->send();
    }

    /**
     * Performs a remote call using the POST HTTP method
     */
    private function _callPOST($uri)
    {
        return \Httpful\Request::post($uri)
            ->expectsJson() // parse response as json
            ->body(http_build_query($this->_callParametersArray)) // post parameters
			->sendsType(\Httpful\Mime::FORM)
            ->send();
    }

    /**
     * Checks the response from the remote web service
     */
    private function _checkResponse($response)
    {
		$checkResponse = null;

        if (is_object($response)) // must be an object returned by the Httpful call
        {
            if (isset($response->body)) // the response must have a body
            {
				// If is present the property errorcode then it's an error
                if (isset($response->body->errorcode))
                {
					if ($response->body->errorcode == MOODLE_INVALID_TOKEN)
					{
						$this->_error(UNAUTHORIZED, $response->body->message);
					}
					elseif ($response->body->errorcode == MOODLE_INVALID_WS_FUNCTION)
					{
						$this->_error(INVALID_WS_FUNCTION, $response->body->message);
					}
					elseif ($response->body->errorcode == MOODLE_INVALID_WS_PARAMETER)
					{
						$this->_error(INVALID_WS_PARAMETER, $response->body->message);
					}
					else
					{
						$this->_error(MOODLE_ERROR, $response->body->message);
					}
                }
                else // otherwise the remote web service has given a valid response
                {
					// If no data are present
                    if ((is_string($response->body) && trim($response->body) == '')
						|| (is_array($response->body) && count($response->body) == 0)
                        || (is_object($response->body) && count((array)$response->body) == 0))
                    {
						$this->_hasData = false; // set property _hasData to false
                    }
                    else
                    {
						$this->_hasData = true; // set property _hasData to true
                    }

					$checkResponse = $response->body; // returns a success
                }
            }
            else // if the response has no body
            {
				// NOTE: moodle RestAPI could have an empty response even if the call was a success (so lame)
				$this->_emptyResponse = true; // set property _hasData to false
            }
        }

		return $checkResponse;
    }

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorMessage = $code.': '.$message;
	}
}
