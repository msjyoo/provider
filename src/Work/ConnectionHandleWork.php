<?php

namespace sekjun9878\Provider\Work;

class ConnectionHandleWork extends \Collectable
{
	private $connectionSocket;

	public function __construct($connectionSocket)
	{
		$this->connectionSocket = $connectionSocket;
	}

	public function run()
	{

	}
}