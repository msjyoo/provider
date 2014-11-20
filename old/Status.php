<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 13/11/2014
 * Time: 5:08 PM
 */

class Status extends \Threaded
{
	/** @var array $messages
	 *
	 * A list of messages to HTTP codes. You may edit this field to modify HTTP code messages or add
	 * your own custom ones.
	 */
	static private $statusMessages = array(
		100 => "Continue",
		101 => "Switching Protocols",
		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		203 => "Non-Authoritative Information",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		300 => "Multiple Choices",
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",
		400 => "Bad Request",
		401 => "Unauthorized",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication Required",
		408 => "Request Timeout",
		409 => "Conflict",
		410 => "Gone",
		411 => "Length Required",
		412 => "Precondition Failed",
		413 => "Request Entity Too Large",
		414 => "Request-URI Too Long",
		415 => "Unsupported Media Type",
		416 => "Requested Range Not Satisfiable",
		417 => "Expectation Failed",
		500 => "Internal Server Error",
		501 => "Not Implemented",
		502 => "Bad Gateway",
		503 => "Service Unavailable",
		504 => "Gateway Timeout",
		505 => "HTTP Version Not Supported",
	);

	private $code;

	/** @var string|null */
	private $message;

	public function __construct($code, $message = NULL)
	{
		$this->code = $code;
		$this->message = $message;
	}

	public function __toString()
	{
		if(is_null($this->message))
		{
			if(isset(static::$statusMessages[$this->code]))
			{
				return (string) static::$statusMessages[$this->code];
			}
			else
			{
				throw new Exception("Status message not found");
			}
		}
		else
		{
			return (string) $this->message; //In reality, it doesn't need to be typecast; checked @ addStatusMessage
		}
	}

	public static function addStatusMessage($code, $message)
	{
		if(!is_numeric($code))
		{
			throw new Exception("Status code is not numeric");
		}

		if(!is_string($message))
		{
			throw new Exception("Status message is not string");
		}

		//TODO: Decide whether to enforce type by checking or typecasting

		static::$statusMessages[(integer) $code] = (string) $message;
	}
} 