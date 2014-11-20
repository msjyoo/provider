<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 8/11/2014
 * Time: 1:32 PM
 */

class Connection extends Collectable
{
	/** @var RequestParseWorker*/
	protected $worker;
	public $connectionSocket;
	/** @var \Threaded */
	private $doKeepAlive = false;
	private $remoteAddress;
	private $request;

	public function getRemoteAddress()
	{
		return $this->remoteAddress;
	}

	public function __construct($connectionSocket)
	{
		$this->connectionSocket = $connectionSocket;

		$this->remoteAddress = self::getRemoteAddressFromRemoteName(
			stream_socket_get_name($this->connectionSocket, true));
	}

	public function run()
	{
		$data = fread($this->connectionSocket, 30000);

		//Check if invalid data is passed
		if ($data === false || $data == '')
		{
			//Invalid Data Passed: Force cleanup and exit
			$this->doKeepAlive = false;
			fclose($this->connectionSocket);
			return false;
		}

		$request = $this->request = new Request($this->connectionSocket, $data);

		while(!$request->isFullyRead())
		{
			$data = @fread($this->connectionSocket, 30000);
			if($data === false)
			{
				//Invalid Data Passed: Force cleanup and exit
				$this->doKeepAlive = false;
				@fclose($this->connectionSocket);
				return false;
			}
			$request->addData($data);
		}

		//TODO: Check Firewall
		$uri = $request->getUri();
		var_dump("URI: ".$uri);

		$this->synchronized(function($thread) {
				/** @var Connection $thread */
				$thread->worker->processedRequestsQueue->synchronized(function($queue, $request) {
					$queue[] = $request;
				}, $thread->worker->processedRequestsQueue, $thread->request);
		}, $this);

		return true;
	}

	private static function getRemoteAddressFromRemoteName($name)
	{
		//Parse remote address for future usage not used here (e.g. logs)
		if($name)
		{
			$port_pos = strrpos($name, ":");
			if($port_pos)
			{
				return substr($name, 0, $port_pos);
			}
			else
			{
				return $name;
			}
		}
	}
} 