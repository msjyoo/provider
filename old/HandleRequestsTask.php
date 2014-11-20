<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 17/11/2014
 * Time: 6:46 PM
 */

class HandleRequestsTask
{
	/** @var WebServer */
	private $server;

	public function __construct($server)
	{
		$this->server = $server;
	}

	public function run()
	{
		$pool = new Pool(3, ResponseSendWorker::class, array());

		while(true)
		{
			if($this->server->processedRequestsQueue->count() > 0)
			{
				var_dump("new request to reply!");
				$response = <<<RES
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8

<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Hello World</title>
    </head>
    <body>
        Hello World~~~!!!
    </body>
</html>
RES;

				$pool->submit(new SendResponseWork($this->server->processedRequestsQueue->shift(), $response));
			}
		}
	}
}