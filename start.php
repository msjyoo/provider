<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 14/11/2014
 * Time: 11:02 PM
 */

$files = glob(__DIR__.'/src/*.php');

foreach($files as $file)
{
	require_once($file);
}

$server = new WebServer(8080, "0.0.0.0");
$server->start();
$task = new HandleRequestsTask($server);
$task->run();
$server->join();