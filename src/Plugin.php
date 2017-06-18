<?php

namespace Detain\MyAdminCpanel;

use Detain\Cpanel\Cpanel;
use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Cpanel Webhosting';
	public static $description = 'Allows selling of Cpanel Server and VPS License Types.  More info at https://www.netenberg.com/cpanel.php';
	public static $help = 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a cpanel license. Allow 10 minutes for activation.';
	public static $module = 'webhosting';
	public static $type = 'service';


	public function __construct() {
	}

	public static function getHooks() {
		return [
		];
	}

	public static function Activate(GenericEvent $event) {
		// will be executed when the licenses.license event is dispatched
		$license = $event->getSubject();
		if ($event['category'] == SERVICE_TYPES_FANTASTICO) {
			myadmin_log('licenses', 'info', 'Cpanel Activation', __LINE__, __FILE__);
			function_requirements('activate_cpanel');
			activate_cpanel($license->get_ip(), $event['field1']);
			$event->stopPropagation();
		}
	}

	public static function ChangeIp(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_FANTASTICO) {
			$license = $event->getSubject();
			$settings = get_module_settings('licenses');
			$cpanel = new Cpanel(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log('licenses', 'info', "IP Change - (OLD:".$license->get_ip().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $cpanel->editIp($license->get_ip(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log('licenses', 'error', 'Cpanel editIp('.$license->get_ip().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $license->get_ip());
				$license->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	public static function Menu(GenericEvent $event) {
		// will be executed when the licenses.settings event is dispatched
		$menu = $event->getSubject();
		$module = 'licenses';
		if ($GLOBALS['tf']->ima == 'admin') {
			$menu->add_link($module, 'choice=none.reusable_cpanel', 'icons/database_warning_48.png', 'ReUsable Cpanel Licenses');
			$menu->add_link($module, 'choice=none.cpanel_list', 'icons/database_warning_48.png', 'Cpanel Licenses Breakdown');
			$menu->add_link($module.'api', 'choice=none.cpanel_licenses_list', 'whm/createacct.gif', 'List all Cpanel Licenses');
		}
	}

	public static function Requirements(GenericEvent $event) {
		// will be executed when the licenses.loader event is dispatched
		$loader = $event->getSubject();
		$loader->add_requirement('crud_cpanel_list', '/../vendor/detain/crud/src/crud/crud_cpanel_list.php');
		$loader->add_requirement('crud_reusable_cpanel', '/../vendor/detain/crud/src/crud/crud_reusable_cpanel.php');
		$loader->add_requirement('get_cpanel_licenses', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel.inc.php');
		$loader->add_requirement('get_cpanel_list', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel.inc.php');
		$loader->add_requirement('cpanel_licenses_list', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel_licenses_list.php');
		$loader->add_requirement('cpanel_list', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel_list.php');
		$loader->add_requirement('get_available_cpanel', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel.inc.php');
		$loader->add_requirement('activate_cpanel', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel.inc.php');
		$loader->add_requirement('get_reusable_cpanel', '/../vendor/detain/myadmin-cpanel-webhosting/src/cpanel.inc.php');
		$loader->add_requirement('reusable_cpanel', '/../vendor/detain/myadmin-cpanel-webhosting/src/reusable_cpanel.php');
		$loader->add_requirement('class.Cpanel', '/../vendor/detain/cpanel-webhosting/src/Cpanel.php');
		$loader->add_requirement('vps_add_cpanel', '/vps/addons/vps_add_cpanel.php');
	}

	public static function getSettings(GenericEvent $event) {
		// will be executed when the licenses.settings event is dispatched
		$settings = $event->getSubject();
		$settings->add_text_setting('licenses', 'Cpanel', 'cpanel_username', 'Cpanel Username:', 'Cpanel Username', $settings->get_setting('FANTASTICO_USERNAME'));
		$settings->add_text_setting('licenses', 'Cpanel', 'cpanel_password', 'Cpanel Password:', 'Cpanel Password', $settings->get_setting('FANTASTICO_PASSWORD'));
		$settings->add_dropdown_setting('licenses', 'Cpanel', 'outofstock_licenses_cpanel', 'Out Of Stock Cpanel Licenses', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_LICENSES_FANTASTICO'), array('0', '1'), array('No', 'Yes', ));
	}

}
