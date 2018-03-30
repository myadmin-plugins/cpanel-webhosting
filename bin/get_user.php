#!/usr/bin/env php
<?php
/**
* Webhosting Functionality
* @author Joe Huss <detain@interserver.net>
* @copyright 2018
* @package MyAdmin
* @category Webhosting
*/

require_once __DIR__.'/../../../../include/functions.inc.php';
$db = get_module_db('webhosting');
$db2 = get_module_db('webhosting');
if (count($_SERVER['argv']) < 2)
	die("Call like {$_SERVER['argv'][0]} <hostname>\nwhere <hostname> is a webhosting server such as webhosting2004.interserver.net");
$db->query("select * from website_masters where website_name='".$db->real_escape($_SERVER['argv'][1])."'", __LINE__, __FILE__);
function_requirements('whm_api');
if ($db->num_rows() == 0)
	die("Invalid Server {$_SERVER['argv'][1]} passed, did not match any webhosting server name");
$db->next_record(MYSQL_ASSOC);
echo "processing {$db->Record['website_name']}\n";
$updates = [];
switch ($db->Record['website_type']) {
	case get_service_define('WEB_PPA'):				// Parallels Plesk Automation
		break;
	case get_service_define('WEB_PLESK'):			// Parallels Plesk
		break;
	case get_service_define('WEB_VESTA'):			// VestaCP
		break;
	case get_service_define('WEB_CPANEL'):			// cPanel/WHM
	default:
		try {
			$whm = whm_api($db->Record['website_id']);
			$response = json_decode($whm->get_user());
			print_r($response);
		} catch (Exception $e) {
			$msg = "Caught Exception Processing {$db->Record['website_name']}: ".$e->getMessage();
			myadmin_log('scripts', 'info', $msg, __LINE__, __FILE__);
			echo $msg.PHP_EOL;
		}
		break;
}
if (count($updates) > 0) {
	$query = [];
	foreach ($updates as $key => $value)
		$query[] = "website_{$key} = '".$db->real_escape($value)."'";
	$query = 'update website_masters set '.implode(', ', $query)." where website_id={$db->Record['website_id']}";
	$db2->query($query, __LINE__, __FILE__);
}
