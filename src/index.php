<?php 

set_time_limit(0);

include 'vendor/autoload.php';

use Goutte\Client;
 
try
{
    $db = new PDO('mysql:host=localhost;dbname=location', 'root', '123456', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
}
catch (PDOException $e)
{
    echo 'Connection failed: ' . $e->getMessage();
}

$data = [];

$client = new Client();

$crawler = $client->request('GET', 'http://postakodu.ptt.gov.tr');

$inputs = [];
$crawler->filter('input')->each(function ($node) use (&$inputs)
{
	$inputs[$node->getNode(0)->getAttribute('name')] = $node->getNode(0)->getAttribute('value');
});

$crawler->filter('#MainContent_DropDownList1 > option')->each(function ($node) use ($client, $inputs, $db)
{
	$id = $node->getNode(0)->getAttribute('value');
	
	if('-1' == $id) return;

	$name = convertUcwords($node->text());
	
	if($db->exec('INSERT INTO province (name) VALUES ("'. $name.'")'))
	{
	    $dbId = $db->lastInsertId();
	}
	else
	{
	    die('insert error');
	}

	$inputs['ctl00$MainContent$DropDownList1'] = $node->getNode(0)->getAttribute('value');
	$inputs['EVENTARGUMENT']                   = '';
	$inputs['__EVENTTARGET']                   = 'ctl00$MainContent$DropDownList1';

	$crawler = $client->request('POST', 'http://postakodu.ptt.gov.tr', $inputs);

	$inputs = [];
	$crawler->filter('input')->each(function ($node) use (&$inputs) {
		$inputs[$node->getNode(0)->getAttribute('name')] = $node->getNode(0)->getAttribute('value');
	});

	$crawler->filter('#MainContent_DropDownList2 > option')->each(function ($node) use ($client, $inputs, $db, $dbId)
	{
		$id = $node->getNode(0)->getAttribute('value');

		if('-1' == $id) return;

		$name = convertUcwords($node->text());
		
		if($db->exec('INSERT INTO district (name, province_id) VALUES ("'. $name.'", "'. $dbId.'")'))
		{
		    $dbId = $db->lastInsertId();
		}
		else
		{
			die('insert error');
		}

		$inputs['ctl00$MainContent$DropDownList2'] = $node->getNode(0)->getAttribute('value');
		$inputs['EVENTARGUMENT']                   = '';
		$inputs['__EVENTTARGET']                   = 'ctl00$MainContent$DropDownList2';

    	$crawler = $client->request('POST', 'http://postakodu.ptt.gov.tr', $inputs);

    	$crawler->filter('#MainContent_DropDownList3 > option')->each(function ($node) use ($client, $inputs, $db, $dbId)
    	{
			$id = $node->getNode(0)->getAttribute('value');

			if('-1' == $id) return;

			$nameParts = explode('/', convertUcwords($node->text()));
			$name = $nameParts[0];
			
			if($db->exec('INSERT INTO area (name, district_id) VALUES ("'. $name.'", "'. $dbId.'")'))
			{
			    $dbId = $db->lastInsertId();
			}
			else
			{
			    die('insert error');
			}
		});
	    	
	});
});


function convertUcwords($str)
{
	$letters = array('I','İ','Ç','Ş','Ü','Ö','Ğ');

    $replace = array('ı','i','ç','ş','ü','ö','ğ');

    $str = mb_strtolower(str_replace($letters, $replace, trim($str)), "UTF-8");

    $words = array();

    foreach(explode(" ", $str) as $word)
    {
        $first = str_replace($replace, $letters, mb_substr($word, 0, 1, "UTF-8"));

        $other = mb_substr($word, 1, strlen($word)-1, "UTF-8");

        $words[] = $first . $other;
    }

    $str = implode(" ", $words);

    return ucwords($str);
}