<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 7/11/2014
 * Time: 11:57 PM
 */

class RequestParseWorker extends Worker
{
	/** @var Threaded */
	public $processedRequestsQueue;

	public function __construct($processedRequestsQueue)
	{
		$this->processedRequestsQueue = $processedRequestsQueue;
	}

	public function run()
	{

	}
}