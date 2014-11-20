<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 17/11/2014
 * Time: 12:15 AM
 */

class SendResponseWork extends Collectable
{
	/** @var Request */
	private $request;
	/** @var Response */
	private $response;

	public function __construct($request, /*Response*/$response)
	{
		$this->request = $request;
		$this->response = $response;
	}

	public function run()
	{
		var_dump("fwrite: ".fwrite($this->request->connectionSocket, $this->response));
		var_dump($this->request->connectionSocket);
		var_dump("fclose: ".fclose($this->request->connectionSocket));
	}
} 