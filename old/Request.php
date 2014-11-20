<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 7/11/2014
 * Time: 11:57 PM
 */


/*
 * Extracted from youngj/httpserver
 */
class Request extends Threaded
{
	/**
	 * @var string $method
	 *
	 * HTTP method, e.g. "GET" or "POST"
	 */
	public $method;

	/**
	 * @var string $requestUri
	 *
	 * Original requested URI, with query string
	 */
	public $requestUri;//TODO: Make this private?

	/**
	 * @var string $uri
	 *
	 * Path component of URI, without query string, after decoding %xx entities
	 */
	public $uri;

	/**
	 * @var string $httpVersion
	 *
	 * Version from the request line, e.g. "HTTP/1.1"
	 */
	public $httpVersion;

	/**
	 * @var string $queryString
	 *
	 * Query string, like "a=b&c=d"
	 */
	public $queryString;

	/**
	 * @var array $headers
	 *
	 * Associative array of HTTP headers
	 */
	public $headers;

	/**
	 * @var array $lowercaseHeaders
	 *
	 * Associative array of HTTP headers, with header names in lowercase //TODO: Improve this - no separate var 4 lcase
	 */
	public $lowercaseHeaders;

	/**
	 * @var resource $contentStream
	 *
	 * Stream containing content of HTTP request (e.g. POST data)
	 */
	public $contentStream;

	/**
	 * @var string $remoteAddress
	 *
	 * IP address of client, as string
	 */
	public $remoteAddress;

	/**
	 * @var string $requestLine
	 *
	 * The HTTP request line exactly as it came from the client
	 */
	public $requestLine;

	/**
	 * @var float $startTime
	 *
	 * unix timestamp of initial request data, as float with microseconds
	 */
	public $startTime;

	// internal fields to track the state of reading the HTTP request
	private $cur_state = 0;
	private $header_buf = '';
	private $content_len = 0;
	private $content_len_read = 0;

	private $is_chunked = false;
	private $chunk_state = 0;
	private $chunk_len_remaining = 0;
	private $chunk_trailer_remaining = 0;
	private $chunk_header_buf = '';

	const READ_CHUNK_HEADER = 0;
	const READ_CHUNK_DATA = 1;
	const READ_CHUNK_TRAILER = 2;

	const READ_HEADERS = 0;
	const READ_CONTENT = 1;
	const READ_COMPLETE = 2;

	public $connectionSocket;

	public function __construct($connectionSocket, $initialData)
	{
		$this->connectionSocket = $connectionSocket;
		$this->contentStream = fopen("data://text/plain,", 'r+b');
		$this->addData($initialData);//Initial Data - more to be added later
	}

	//Start Getters and Setters

	/**
	 * @return string
	 */
	public function getUri()
	{
		return $this->uri;
	}

	/**
	 * @return string
	 */
	public function getRequestLine()
	{
		return $this->requestLine;
	}

	/**
	 * @return string
	 */
	public function getRemoteAddress()
	{
		return $this->remoteAddress;
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * @return array
	 */
	public function getLowercaseHeaders()
	{
		return $this->lowercaseHeaders;
	}

	/**
	 * @return string
	 */
	public function getHttpVersion()
	{
		return $this->httpVersion;
	}

	//End Getters and Setters

	public function cleanup()
	{
		fclose($this->contentStream);
		$this->contentStream = null;
	}

	/*
	 * Reads a chunk of a HTTP request from a client socket.
	 */
	public function addData($data) //TODO: Rename this to something a bit more appropriate, like queue to read or something.
	{
		switch ($this->cur_state)
		{
			case static::READ_HEADERS:
				if (!$this->startTime)
				{
					$this->startTime = microtime(true);
				}

				$header_buf =& $this->header_buf;

				$header_buf .= $data;

				if (strlen($header_buf) < 4)
				{
					break;
				}

				$end_headers = strpos($header_buf, "\r\n\r\n", 4);
				if ($end_headers === false)
				{
					break;
				}

				// parse HTTP request line
				$end_req = strpos($header_buf, "\r\n");
				$this->requestLine = substr($header_buf, 0, $end_req);
				$req_arr = explode(' ', $this->requestLine, 3);

				$this->method = $req_arr[0];
				$this->requestUri = $req_arr[1];
				$this->httpVersion = $req_arr[2];

				$parsed_uri = parse_url($this->requestUri);
				$this->uri = urldecode($parsed_uri['path']);
				$this->queryString = @$parsed_uri['query'];

				// parse HTTP headers
				$start_headers = $end_req + 2;

				$headers_str = substr($header_buf, $start_headers, $end_headers - $start_headers);
				$this->headers = static::parseHeaders($headers_str);//TODO: MUST EDIT

				$this->lowercaseHeaders = array();
				foreach ($this->headers as $k => $v)
				{
					$this->lowercaseHeaders[strtolower($k)] = $v;
				}

				if (isset($this->lowercaseHeaders['transfer-encoding']))
				{
					$this->is_chunked = $this->lowercaseHeaders['transfer-encoding'][0] == 'chunked';

					unset($this->lowercaseHeaders['transfer-encoding']);
					unset($this->headers['Transfer-Encoding']);

					$this->content_len = 0;
				}
				else
				{
					$this->content_len = (int)@$this->lowercaseHeaders['content-length'][0];
				}

				$start_content = $end_headers + 4; // $end_headers is before last \r\n\r\n

				$data = substr($header_buf, $start_content);
				$header_buf = '';

				$this->cur_state = static::READ_CONTENT;

			// fallthrough to READ_CONTENT with leftover data
			case static::READ_CONTENT:

				if ($this->is_chunked)
				{
					$this->readChunkedData($data);
				}
				else
				{
					fwrite($this->contentStream, $data);
					$this->content_len_read += strlen($data);

					if ($this->content_len - $this->content_len_read <= 0)
					{
						$this->cur_state = static::READ_COMPLETE;
					}
				}
				break;
			case static::READ_COMPLETE:
				break;
		}

		if ($this->cur_state == static::READ_COMPLETE)
		{
			fseek($this->contentStream, 0);
		}
	}

	private function readChunkedData($data)
	{
		$content_stream =& $this->contentStream;
		$chunk_header_buf =& $this->chunk_header_buf;
		$chunk_len_remaining =& $this->chunk_len_remaining;
		$chunk_trailer_remaining =& $this->chunk_trailer_remaining;
		$chunk_state =& $this->chunk_state;

		while (isset($data[0])) // keep processing chunks until we run out of data
		{
			switch ($chunk_state)
			{
				case static::READ_CHUNK_HEADER:
					$chunk_header_buf .= $data;
					$data = "";

					$end_chunk_header = strpos($chunk_header_buf, "\r\n");
					if ($end_chunk_header === false) // still need to read more chunk header
					{
						break;
					}

					// done with chunk header
					$chunk_header = substr($chunk_header_buf, 0, $end_chunk_header);

					list($chunk_len_hex) = explode(";", $chunk_header, 2);

					$chunk_len_remaining = intval($chunk_len_hex, 16);

					$chunk_state = static::READ_CHUNK_DATA;

					$data = substr($chunk_header_buf, $end_chunk_header + 2);
					$chunk_header_buf = '';

					if ($chunk_len_remaining == 0)
					{
						$this->cur_state = static::READ_COMPLETE;
						$this->headers['Content-Length'] = $this->lowercaseHeaders['content-length'] = array($this->content_len);

						// todo: this is where we should process trailers...
						return;
					}

				// fallthrough to READ_CHUNK_DATA with leftover data
				case static::READ_CHUNK_DATA:
					if (strlen($data) > $chunk_len_remaining)
					{
						$chunk_data = substr($data, 0, $chunk_len_remaining);
					}
					else
					{
						$chunk_data = $data;
					}

					$this->content_len += strlen($chunk_data);
					fwrite($content_stream, $chunk_data);
					$data = substr($data, $chunk_len_remaining);
					$chunk_len_remaining -= strlen($chunk_data);

					if ($chunk_len_remaining == 0)
					{
						$chunk_trailer_remaining = 2;
						$chunk_state = static::READ_CHUNK_TRAILER;
					}
					break;
				case static::READ_CHUNK_TRAILER: // each chunk ends in \r\n, which we ignore
					$len_to_read = min(strlen($data), $chunk_trailer_remaining);

					$data = substr($data, $len_to_read);
					$chunk_trailer_remaining -= $len_to_read;

					if ($chunk_trailer_remaining == 0)
					{
						$chunk_state = static::READ_CHUNK_HEADER;
					}

					break;
			}
		}
	}

	/*
	 * Returns the value of a HTTP header from this request (case-insensitive)
	 */
	public function getHeader($name)
	{
		return @$this->lowercaseHeaders[strtolower($name)][0];
	}

	/*
	 * Returns true if a full HTTP request has been read by addData().
	 */
	public function isFullyRead()
	{
		return $this->cur_state == static::READ_COMPLETE;
	}

	static function parseHeaders($headers_str)
	{
		$headers_arr = explode("\r\n", $headers_str);

		$headers = array();
		foreach ($headers_arr as $header_str)
		{
			$header_arr = explode(": ", $header_str, 2);
			if (sizeof($header_arr) == 2)
			{
				$header_name = $header_arr[0];
				$value = $header_arr[1];

				if (!isset($headers[$header_name]))
				{
					$headers[$header_name] = array($value);
				}
				else
				{
					$headers[$header_name][] = $value;
				}
			}
		}
		return $headers;
	}
}
