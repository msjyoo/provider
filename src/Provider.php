<?php

namespace sekjun9878\Provider;

use sekjun9878\Provider\Pool\ConnectionHandlerPool;
use sekjun9878\Provider\Work\ConnectionHandleWork;
use sekjun9878\Provider\Worker\ConnectionHandleWorker;

class Provider extends \Thread
{
	//Basic Variables
	/** @var string Address to bind the listening socket to */
	private $address = "0.0.0.0";
	/** @var int Port to bind the listening socket to */
	private $port = 8080;

	//Pool Variables
	/** @var int A maximum number of worker contexts before further requests will be queued / blocked */
	private $maximumPoolSize = 20;

	//Worker Variables

	//Other Variables
	private $queueAwaitingResponse = NULL;

	//Thread Variables
	private $stop = false;

	public function __construct()
	{
		$this->queueAwaitingResponse = new \Threaded;
	}

	/**
	 * Stops and cleans the server ready to be started again at a later time
	 */
	public function stop()
	{
		throw new ServerNotRunningException;
	}

	public function run()
	{
		if(!$listenSocket = @stream_socket_server("tcp://{$this->address}:{$this->port}", $errno, $errstr))
		{
			throw new UnableToBindException($errstr);//Exceptions may not work inside threads
		}

		if(!stream_set_blocking($listenSocket, 0))
		{
			throw new UnableToSetListenSocketBlockingMode;
		}

		$ConnectionHandlerPool = new ConnectionHandlerPool($this->maximumPoolSize, ConnectionHandleWorker::class, array());

		while(!$this->stop)
		{
			$ConnectionHandlerPool->collect(function ($work) {
				/** @var ConnectionHandleWork $work */

				if($work->isCompleted() and $work->isGarbage())
				{
					return true;//Thread has been replied to and has finished cleanup. Collect garbage.
				}
				else if($work->isAwaitingResponse())
				{
					//TODO: Add to queue
				}

				return false;//The request is probably still being processed
			});
			$connectionSocket = stream_socket_accept($listenSocket, 1);

			if(!$connectionSocket)
			{
				continue;
			}

			$ConnectionHandlerPool->submit(new ConnectionHandleWork($connectionSocket));
		}
	}

	//Basic Variables
	public function getPort()
	{
		return $this->port;
	}

	public function setPort($port)
	{
		if($this->isRunning())
		{
			throw new ServerAlreadyRunningException;
		}

		$this->port = $port;
	}

	public function getAddress()
	{
		return $this->address;
	}

	public function setAddress($address)
	{
		if($this->isRunning())
		{
			throw new ServerAlreadyRunningException;
		}

		$this->address = $address;
	}

	//Pool Variables
	public function getMaximumPoolSize()
	{
		return $this->maximumPoolSize;
	}

	public function setMaximumPoolSize($size)
	{
		if($this->isRunning())
		{
			throw new ServerAlreadyRunningException;
		}

		$this->maximumPoolSize = $size;
	}
}