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
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.reactivate' => [__CLASS__, 'getReactivate'],
			'ui.menu' => [__CLASS__, 'getMenu'],
		];
	}

	public static function getActivate(GenericEvent $event) {
		$license = $event->getSubject();
		if ($event['category'] == SERVICE_TYPES_WEB_CPANEL) {
			myadmin_log(self::$module, 'info', 'Cpanel Activation', __LINE__, __FILE__);
			function_requirements('whm_api');
			$user = 'root';
			$whm = new \xmlapi($ip);
			//$whm->set_debug('true');
			$whm->set_port('2087');
			$whm->set_protocol('https');
			$whm->set_output('json');
			$whm->set_auth_type('hash');
			$whm->set_user($user);
			$whm->set_hash($hash);
			//		$whm = whm_api('faith.interserver.net');
			$options = array(
				'ip' => 'n',
				'cgi' => 1,
				'frontpage' => 0,
				'hasshell' => 0,
				'cpmod' => 'paper_lantern',
				'maxsql' => 'unlimited',
				'maxpop' => 'unlimited',
				'maxlst' => 0,
				'maxsub' => 'unlimited',
			);
			if ($service_types[$type]['services_field1'] == 'reseller')
				$reseller = true;
			else
				$reseller = false;
			if ($service_types[$type]['services_field2'] != '') {
				$fields = explode(',', $service_types[$type]['services_field2']);
				foreach ($fields as $field) {
					list($key, $value) = explode('=', $field);
					if ($key == 'script')
						$extra[$key] = $value;
					else
						$options[$key] = $value;
				}
			}
			$options = array_merge($options, array(
				'domain' => $hostname,
				'username' => $username,
				'password' => $password,
				'contactemail' => $email,
			));
			myadmin_log(self::$module, 'info', json_encode($options), __LINE__, __FILE__);
			$response = $whm->xmlapi_query('createacct', $options);
			request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'createacct', $options, $response);
			myadmin_log(self::$module, 'info', 'Response: '.str_replace('\n', '', strip_tags($response)), __LINE__, __FILE__);
			$response = json_decode($response);
			if ($response->result[0]->statusmsg == 'Sorry, the password may not contain the username for security reasons.') {
				$ousername = $username;
				while ($response->result[0]->statusmsg == 'Sorry, the password may not contain the username for security reasons.') {
					$username .= 'a';
					$username = mb_substr($username, 1);
					$options['username'] = $username;
					myadmin_log(self::$module, 'info', "Trying Username {$options['username']}", __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: {$response}", __LINE__, __FILE__);
					$response = json_decode($response);
				}
			}

			if ($response->result[0]->statusmsg == 'Sorry, a group for that username already exists.') {
				$ousername = $username;
				while ($response->result[0]->statusmsg == 'Sorry, a group for that username already exists.') {
					$username .= 'a';
					$username = mb_substr($username, 1);
					$options['username'] = $username;
					myadmin_log(self::$module, 'info', 'Trying Username '.$options['username'], __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
			}
			if (preg_match("/^.*This system already has an account named .{1,3}{$username}.{1,3}\.$/m", $response->result[0]->statusmsg) || preg_match("/^.*The name of another account on this server has the same initial/m", $response->result[0]->statusmsg)) {
				$ousername = $username;
				while (preg_match("/^.*This system already has an account named .{1,3}{$username}.{1,3}\.$/m", $response->result[0]->statusmsg) || preg_match("/^.*The name of another account on this server has the same initial/m", $response->result[0]->statusmsg)) {
					$username .= 'a';
					$username = mb_substr($username, 1);
					$options['username'] = $username;
					myadmin_log(self::$module, 'info', 'Trying Username '.$options['username'], __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
			}
			if (mb_strpos($response->result[0]->statusmsg, 'Sorry, the password you selected cannot be used because it is too weak and would be too easy to crack.') !== false) {
				while (mb_strpos($response->result[0]->statusmsg, 'Sorry, the password you selected cannot be used because it is too weak and would be too easy to crack.') !== false) {
					$options['password'] .= '1';
					myadmin_log(self::$module, 'info', "Trying Password {$options['password']}", __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
				$GLOBALS['tf']->history->add($settings['PREFIX'], 'password', $id, $options['password']);
			}
			if ($response->result[0]->status == 1) {
				$ip = $response->result[0]->options->ip;
				if (isset($options['bwlimit']) && $options['bwlimit'] != 'unlimited') {
					$response3 = $whm->limitbw($username, $options['bwlimit']);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'limitbw', array('username' => $username, 'options' => $options['bwlimit']), $response3);
					myadmin_log(self::$module, 'info', 'Response: '.str_replace('\n', "\n", strip_tags($response3)), __LINE__, __FILE__);
				}
				if ($reseller === true) {
					$response2 = $whm->setupreseller($username, false);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'setupreseller', array('username' => $username), $response2);
					myadmin_log(self::$module, 'info', "Response: {$response2}", __LINE__, __FILE__);
					$response3 = $whm->listacls();

					$acls = json_decode($response3);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'listacls', array(), $response);
					//myadmin_log(self::$module, 'info', json_encode($acls));
					if (!isset($acls->acls->reseller)) {
						$acl = array(
							'acl-add-pkg' => 1, // Allow the creation of packages.
							'acl-add-pkg-ip' => 1, // Allow the creation of packages with dedicated IPs.
							'acl-add-pkg-shell' => 1, // Allow the creation of packages with shell access.
							'acl-all' => 0, // All features.
							'acl-allow-addoncreate' => 0, // Allow the creation of packages with unlimited addon domains.
							'acl-allow-parkedcreate' => 1, // Allow the creation of packages with parked domains.
							'acl-allow-unlimited-disk-pkgs' => 0, // Allow the creation of packages with unlimited disk space.
							'acl-allow-unlimited-pkgs' => 0, // Allow the creation of packages with unlimited bandwidth.
							'acl-clustering' => 0, // Enable clustering.
							'acl-create-acct' => 1, // Allow the reseller to create a new account.
							'acl-create-dns' => 1, // Allow the reseller to add DNS zones.
							'acl-demo-setup' => 0, // Allow the reseller to turn an account into a demo account.
							'acl-disallow-shell' => 0, // Never allow creation of accounts with shell access.
							'acl-edit-account' => 1, // Allow the reseller to modify an account.
							'acl-edit-dns' => 1, // Allow editing of DNS zones.
							'acl-edit-mx' => 0, // Allow editing of MX entries,
							'acl-edit-pkg' => 1, // Allow editing of packages.
							'acl-frontpage' => 0, // Allow the reseller to install and uninstall FrontPage extensions.
							'acl-kill-acct' => 1, // Allow termination of accounts.
							'acl-kill-dns' => 0, // Allow the reseller to remove DNS entries.
							'acl-limit-bandwidth' => 1, // Allow the reseller to modify bandiwdth limits.   Warning: This will allow the reseller to circumvent package bandwidth limits, if you are not using resource limits!
							'acl-list-accts' => 1, // Allow the reseller to list his or her accounts.
							'acl-mailcheck' => 1, // Allow the reseller to access the WHM Mail Troubleshooter.
							'acl-mod-subdomains' => 1, // Allow the reseller to enable and disable subdomains.
							'acl-news' => 1, // Allow the reseller to modify cPanel/WHM news.
							'acl-onlyselfandglobalpkgs' => 1, // Prevent the creation of accounts with packages that are neither global nor owned by this user.
							'acl-park-dns' => 1, // Allow the reseller to park domains.
							'acl-passwd' => 1, // Allow the reseller to modify passwords.
							'acl-quota' => 1, // Allow the reseller to modify disk quotas.   Warning: This will allow resellers to circumvent package limits for disk space, if you are not using resource limits!
							'acl-rearrange-accts' => 0, // Allow the reseller to rearrange accounts.
							'acl-res-cart' => 0, // Allow the reseller to reset the shopping cart.
							'acl-status' => 0, // Allow the reseller to view the Service Status feature in WHM.
							'acl-resftp' => 0, // Allow the reseller to synchronize FTP passwords.
							'acl-restart' => 0, // Allow the reseller to restart services.
							'acl-show-bandwidth' => 1, // Allow the reseller to view accounts' bandwidth usage.
							'acl-ssl' => 0, // Allow the reseller to access the SSL Manager.
							'acl-ssl-gencrt' => 1, // Allow the reseller to access the SSL CSR/CRT generator.
							'acl-stats' => 1, // Allow the reseller to view account statistics.
							'acl-suspend-acct' => 1, // Allow the reseller to suspend accounts.
							'acl-upgrade-account' => 1, // Allow the reseller to upgrade and downgrade accounts.
							'acllist' => 'reseller'
						);
						$result = $whm->saveacllist($acl);
						myadmin_log(self::$module, 'info', $result, __LINE__, __FILE__);
						request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'saveacllist', $acl, $result);
						myadmin_log(self::$module, 'info', 'Reseller ACL Created', __LINE__, __FILE__);
					} else {
						myadmin_log(self::$module, 'info', 'Reseller ACL Exists', __LINE__, __FILE__);
					}
					$request = array('reseller' => $username, 'acllist' => 'reseller');
					$result = $whm->setacls($request);
					myadmin_log(self::$module, 'info', $result, __LINE__, __FILE__);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'cpanel', 'setacls', $request, $result);
					myadmin_log(self::$module, 'info', 'Reseller assigned to ACL', __LINE__, __FILE__);
				}
				$username = $db->real_escape($username);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='$ip', {$settings['PREFIX']}_username='$username' where {$settings['PREFIX']}_id='$id'", __LINE__, __FILE__);
				website_welcome_email($id);
				if (isset($extra['script']) && $extra['script'] > 0) {
					$script = (int)$extra['script'];
					include_once('include/webhosting/softaculous/sdk.php');
					$userdata = $GLOBALS['tf']->accounts->read($service[$settings['PREFIX'].'_custid']);
					$soft = new Softaculous_SDK();
					$soft->login = "https://{$username}:{$password}@{$serverdata[$settings['PREFIX'].'_name']}:2083/frontend/paper_lantern/softaculous/index.live.php";
					$soft->list_scripts();
					$data['overwrite_existing'] = 1;
					$data['softdomain'] = $hostname;
					$data['softdirectory'] = '';
					$data['admin_username'] = 'admin';
					$data['admin_pass'] = $password;
					$data['admin_email'] = $email;
					$data['admin_realname'] = (isset($userdata['name']) ? $userdata['name'] : $userdata['account_lid']);
					list($data['admin_fname'], $data['admin_lname']) = (isset($userdata['name']) ? explode(' ', $userdata['name']) : explode('@', $userdata['account_lid']));
					$data['softdb'] = $soft->scripts[$script]['softname'];
					$data['dbusername'] = $soft->scripts[$script]['softname'];
					$data['dbuserpass'] = $password;
					$data['language'] = 'en';
					$data['site_name'] = $soft->scripts[$script]['fullname'];
					$data['store_name'] = $soft->scripts[$script]['fullname'];
					$data['store_owner'] = $userdata['account_lid'];
					$data['store_address'] = (isset($userdata['address']) ? $userdata['address'] : '');
					$data['site_desc'] = $soft->scripts[$script]['fullname'];
					myadmin_log(self::$module, 'info', 'Installing '.$soft->scripts[$script]['fullname'], __LINE__, __FILE__);
					//$result = myadmin_unstringify($soft->install($script, $data));
					$result = json_decode($soft->install($script, $data), true);
					request_log(self::$module, $service[$settings['PREFIX'].'_custid'], __FUNCTION__, 'softaculous', 'install', array('script' => $script, 'data' => $data), $result);
					myadmin_log(self::$module, 'info', json_encode($result), __LINE__, __FILE__);
				}
				$response = add_dns_record(14426, 'wh'.$id, $ip, 'A', 86400, 0, true);
				myadmin_log(self::$module, 'info', 'Response: '.json_encode($response), __LINE__, __FILE__);
				$response = $whm->park($options['username'], 'wh'.$id.'.ispot.cc', '');
				myadmin_log(self::$module, 'info', 'Response: '.json_encode($response), __LINE__, __FILE__);
				return true;
			} else {
				return false;
			}
			$event->stopPropagation();
		}
	}

	public static function getReactivate(GenericEvent $event) {
		$service = $event->getSubject();
		if ($event['category'] == SERVICE_TYPES_WEB_CPANEL) {
			$serviceInfo = $service->getServiceInfo();
			$settings = get_module_settings(self::$module);
			$serverdata = get_service_master($serviceInfo[$settings['PREFIX'].'_server'], self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
			$extra = run_event('parse_service_extra', $serviceInfo[$settings['PREFIX'].'_extra'], self::$module);
			function_requirements('whm_api');
			$user = 'root';
			$whm = new \xmlapi($ip);
			//$whm->set_debug('true');
			$whm->set_port('2087');
			$whm->set_protocol('https');
			$whm->set_output('json');
			$whm->set_auth_type('hash');
			$whm->set_user($user);
			$whm->set_hash($hash);
			//$whm = whm_api('faith.interserver.net');
			$field1 = explode(',', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_field1']);
			if (in_array('reseller', $field1))
				$response = json_decode($whm->unsuspendreseller($serviceInfo[$settings['PREFIX'].'_username']), TRUE);
			else
				$response = json_decode($whm->unsuspendacct($serviceInfo[$settings['PREFIX'].'_username']), TRUE);
			myadmin_log(self::$module, 'info', json_encode($response), __LINE__, __FILE__);
			$event->stopPropagation();
		}
	}

	public static function getChangeIp(GenericEvent $event) {
		if ($event['category'] == SERVICE_TYPES_WEB_CPANEL) {
			$license = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$cpanel = new Cpanel(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', "IP Change - (OLD:".$license->get_ip().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $cpanel->editIp($license->get_ip(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'Cpanel editIp('.$license->get_ip().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
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

	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
			if (has_acl('module_config')) {
				$menu->add_menu(self::$module, self::$module.'api', 'API', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-braces.png');
				$menu->add_menu(self::$module.'api', 'whmaccount', 'Accounting');
				$menu->add_menu(self::$module.'api', 'whmdns', 'DNS');
				$menu->add_menu(self::$module.'api', 'whmpackages', 'Packages');
				$menu->add_menu(self::$module.'api', 'whmresellers', 'Resellers');
				$menu->add_menu(self::$module.'api', 'whminformation', 'Server Information');
				$menu->add_menu(self::$module.'api', 'whmadministration', 'Server Administration');
				$menu->add_menu(self::$module.'api', 'whmservices', 'Services');
				$menu->add_menu(self::$module.'api', 'whmssl', 'SSL Certs');
				$menu->add_link(self::$module.'api', 'choice=none.whm_choose_server', 'icons/paper_content_pencil_48.png', 'Set What Server Your Working On.');

				// Accounting
				$menu->add_link('whmaccount', 'choice=none.whm_createacct', 'whm/createacct.gif', 'Create an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_passwd', 'whm/passwd.gif', 'Change an Accounts Password');
				$menu->add_link('whmaccount', 'choice=none.whm_limitbw', 'whm/limitbw.gif', 'Limit Bandwidth Usage (Transfer)');
				$menu->add_link('whmaccount', 'choice=none.whm_listaccts', 'whm/listaccts.gif', 'List Accounts');
				$menu->add_link('whmaccount', 'choice=none.whm_modifyacct', 'whm/modifyacct.gif', 'Modify an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_editquota', 'whm/editquota.gif', 'Edit Quota');
				$menu->add_link('whmaccount', 'choice=none.whm_accountsummary', 'whm/accountsummary.gif', 'Show an Accounts Information');
				$menu->add_link('whmaccount', 'choice=none.whm_suspendacct', 'whm/suspendacct.gif', 'Suspend an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_listsuspended', 'whm/listsuspended.gif', 'List Suspended Accounts');
				$menu->add_link('whmaccount', 'choice=none.whm_removeacct', 'whm/removeacct.gif', 'Terminate an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_unsuspendacct', 'whm/unsuspendacct.gif', 'Unsuspend an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_changepackage', 'whm/changepackage.gif', 'Upgrade or Downgrade an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_myprivs', 'whm/createacct.gif', 'View Current Users Privileges');
				$menu->add_link('whmaccount', 'choice=none.whm_domainuserdata', 'whm/createacct.gif', 'Obtain User Data for a Domain');
				$menu->add_link('whmaccount', 'choice=none.whm_setsiteip', 'whm/createacct.gif', 'Change a Sites (or Users) IP Address');
				$menu->add_link('whmaccount', 'choice=none.whm_restoreaccount', 'whm/createacct.gif', 'Restore an Account Backup');

				// DNS
				$menu->add_link('whmdns', 'choice=none.whm_adddns', 'whm/adddns.gif', 'Add a DNS Zone');
				$menu->add_link('whmdns', 'choice=none.whm_addzonerecord', 'whm/addzonerecord.gif', 'Add a Zone Record');
				$menu->add_link('whmdns', 'choice=none.whm_editzonerecord', 'whm/editzonerecord.gif', 'Edit a Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_getzonerecord', 'whm/getzonerecord.gif', 'Get a Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_killdns', 'whm/killdns.gif', 'Delete a DNS Zone');
				$menu->add_link('whmdns', 'choice=none.whm_listzones', 'whm/listzones.gif', 'List All DNS Zones');
				$menu->add_link('whmdns', 'choice=none.whm_dumpzone', 'whm/dumpzone.gif', 'List (Dump) 1 Zone');
				$menu->add_link('whmdns', 'choice=none.whm_lookupnsip', 'whm/lookupnsip.gif', 'Look up a Nameservers IP Address');
				//$menu->add_link('whmdns', 'choice=none.whm_removezonerecord', 'whm/removezonerecord.gif', 'Remove a DNS Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_resetzone', 'whm/resetzone.gif', 'Reset a DNS Zone');
				//$menu->add_link('whmdns', 'choice=none.whm_resolvedomainname', 'whm/resolvedomainname.gif', 'Resolve an IP Address from a Domain');
				$menu->add_link('whmdns', 'choice=none.whm_listmxs', 'whm/listmxs.gif', 'List a Domains MX Records');
				$menu->add_link('whmdns', 'choice=none.whm_savemxs', 'whm/savemxs.gif', 'Create a new MX Record');

				// Packages
				//$menu->add_link('whmpackages', 'choice=none.whm_addpkg', 'whm/addpkg.gif', 'Add a Package');
				//$menu->add_link('whmpackages', 'choice=none.whm_killpkg', 'whm/killpkg.gif', 'Delete a Package');
				//$menu->add_link('whmpackages', 'choice=none.whm_editpkg', 'whm/editpkg.gif', 'Edit a Package');
				$menu->add_link('whmpackages', 'choice=none.whm_listpkgs', 'whm/listpkgs.gif', 'List Packages');
				$menu->add_link('whmpackages', 'choice=none.whm_getfeaturelist', 'whm/getfeaturelist.gif', 'List Available Features');

				// Resellers
				$menu->add_link('whmresellers', 'choice=none.whm_setupreseller', 'whm/createacct.gif', 'Add Reseller Privileges');
				$menu->add_link('whmresellers', 'choice=none.whm_saveacllist', 'whm/createacct.gif', 'Create a Reseller ACL List');
				$menu->add_link('whmresellers', 'choice=none.whm_listacls', 'whm/createacct.gif', 'List Current Reseller ACL Lists');
				$menu->add_link('whmresellers', 'choice=none.whm_listresellers', 'whm/listresellers.gif', 'List Reseller Accounts');
				$menu->add_link('whmresellers', 'choice=none.whm_resellerstats', 'whm/createacct.gif', 'List Resellers Accounts Information');
				$menu->add_link('whmresellers', 'choice=none.whm_unsetupreseller', 'whm/createacct.gif', 'Remove Reseller Privileges');
				$menu->add_link('whmresellers', 'choice=none.whm_setacls', 'whm/createacct.gif', 'Set a Resellers ACL List');
				$menu->add_link('whmresellers', 'choice=none.whm_terminatereseller', 'whm/createacct.gif', 'Terminate a Reseller');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerips', 'whm/createacct.gif', 'Assign a Reseller IP Addresses');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerlimits', 'whm/createacct.gif', 'Set Reseller Limits');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellermainip', 'whm/createacct.gif', 'Set up a Resellers Main IP Address');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerpackagelimit', 'whm/createacct.gif', 'Define a Resellers Packages');
				$menu->add_link('whmresellers', 'choice=none.whm_suspendreseller', 'whm/createacct.gif', 'Suspend a Resellers Account');
				$menu->add_link('whmresellers', 'choice=none.whm_unsuspendreseller', 'whm/createacct.gif', 'Unsuspend a Resellers Account');
				$menu->add_link('whmresellers', 'choice=none.whm_acctcounts', 'whm/createacct.gif', 'View Information about Accounts Owned by a Reseller');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellernameservers', 'whm/createacct.gif', 'Set a Resellers Nameservers');

				// Information
				$menu->add_link('whminformation', 'choice=none.whm_gethostname', 'whm/gethostname.gif', 'Display Server Hostname');
				$menu->add_link('whminformation', 'choice=none.whm_version', 'whm/version.gif', 'Display cPanel &amp; WHM Version');
				$menu->add_link('whminformation', 'choice=none.whm_loadavg', 'whm/loadavg.gif', 'Display the Servers Load Average');
				$menu->add_link('whminformation', 'choice=none.whm_getdiskusage', 'whm/loadavg.gif', 'Display the Servers Disk Usage');
				//$menu->add_link('whminformation', 'choice=none.whm_systemloadavg', 'whm/createacct.gif', 'Display the Servers Load Average (with Metadata)');
				$menu->add_link('whminformation', 'choice=none.whm_getlanglist', 'whm/createacct.gif', 'View a List of Available Languages');

				// Adminsitration
				$menu->add_link('whmadministration', 'choice=none.whm_reboot', 'whm/reboot.gif', 'Reboot the Server');
				$menu->add_link('whmadministration', 'choice=none.whm_addip', 'whm/addip.gif', 'Add IP Address');
				$menu->add_link('whmadministration', 'choice=none.whm_delip', 'whm/createacct.gif', 'Delete IP Address');
				$menu->add_link('whmadministration', 'choice=none.whm_listips', 'whm/listips.gif', 'List IP Addresses');
				$menu->add_link('whmadministration', 'choice=none.whm_sethostname', 'whm/createacct.gif', 'Set Hostname');
				$menu->add_link('whmadministration', 'choice=none.whm_setresolvers', 'whm/createacct.gif', 'Set Resolvers');
				//$menu->add_link('whmadministration', 'choice=none.whm_showbw', 'whm/createacct.gif', 'Show Bandwidth');
				//$menu->add_link('whmadministration', 'choice=none.whm_nvset', 'whm/createacct.gif', 'Set a Non-Volatile Variable Value');
				//$menu->add_link('whmadministration', 'choice=none.whm_nvget', 'whm/createacct.gif', 'Retrieve a Non-Volatile Variable Value');

				// Services
				$menu->add_link('whmservices', 'choice=none.whm_restartservice', 'whm/restartservice.gif', 'Restart Service');
				$menu->add_link('whmservices', 'choice=none.whm_servicestatus', 'whm/servicestatus.gif', 'Service Status');
				//$menu->add_link('whmservices', 'choice=none.whm_configureservice', 'whm/configureservice.gif', 'Configure a Service');

				// SSL
				$menu->add_link('whmssl', 'choice=none.whm_fetchsslinfo', 'whm/fetchsslinfo.gif', 'Fetch SSL Certificate Information');
				//$menu->add_link('whmssl', 'choice=none.whm_generatessl', 'whm/generatessl.gif', 'Generate an SSL Certificate');
				//$menu->add_link('whmssl', 'choice=none.whm_installssl', 'whm/installssl.gif', 'Install an SSL Certificate');
				$menu->add_link('whmssl', 'choice=none.whm_listcrts', 'whm/listcrts.gif', 'List Available SSL Certificates');
			}
		}
	}

	public static function getRequirements(GenericEvent $event) {
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
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_cpanel_server', 'Default CPanel Setup Server', NEW_WEBSITE_CPANEL_SERVER, SERVICE_TYPES_WEB_CPANEL);
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_wordpress_server', 'Default WordPress Setup Server', NEW_WEBSITE_WORDPRESS_SERVER, SERVICE_TYPES_WEB_WORDPRESS);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_cpanel', 'Out Of Stock CPanel Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_CPANEL'), array('0', '1'), array('No', 'Yes',));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_wordpress', 'Out Of Stock WordPress Managed Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_WORDPRESS'), array('0', '1'), array('No', 'Yes',));
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_ip', 'CPanel Package Defaults - IP', 'Enable/Disable Dedicated IP for new Sites', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_IP'), array('n', 'y'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_cgi', 'CPanel Package Defaults - CGI', 'Enable/Disable CGI Access', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_CGI'), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_frontpage', 'CPanel Package Defaults - Frontpage', 'Enable/Disable Frontpage Extensions', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_FRONTPAGE'), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_hasshell', 'CPanel Package Defaults - Shell', 'Enable/Disable Shell', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_HASSHELL'), array('0', '1'), array('No', 'Yes'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_cpmod', 'CPanel Package Defaults - CP Mod', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_CPMOD'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxsql', 'CPanel Package Defaults - Max SQL', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXSQL'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxpop', 'CPanel Package Defaults - Max POP3', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXPOP'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxlst', 'CPanel Package Defaults - Max Mailing Lists', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXLST'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxsub', 'CPanel Package Defaults - Max Subdomains', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXSUB'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg', 'Reseller ACL Add PKG', 'Allow the creation of packages.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg_ip', 'Reseller ACL Add PKG IP', 'Allow the creation of packages with dedicated IPs.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_IP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_IP : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg_shell', 'Reseller ACL Add PKG Shell', 'Allow the creation of packages with Shell access.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_SHELL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_SHELL : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_all', 'Reseller ACL All', 'All features.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALL : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_addoncreate', 'Reseller ACL Allow Addoncreate', 'Allow the creation of packages with unlimited Addon domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_ADDONCREATE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_ADDONCREATE : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_parkedcreate', 'Reseller ACL Allow Parkedcreate', 'Allow the creation of packages with parked domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_PARKEDCREATE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_PARKEDCREATE : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_unlimited_disk_pkgs', 'Reseller ACL Allow Unlimited Disk PKGs', 'Allow the creation of packages with Unlimited Disk space.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_DISK_PKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_DISK_PKGS : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_unlimited_pkgs', 'Reseller ACL Allow Unlimited PKGs', 'Allow the creation of packages with Unlimited bandwidth.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_PKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_PKGS : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_clustering', 'Reseller ACL Clustering', 'Enable Clustering.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CLUSTERING') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CLUSTERING : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_create_acct', 'Reseller ACL Create Acct', 'Allow the reseller to Create a new Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_ACCT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_create_dns', 'Reseller ACL Create DNS', 'Allow the reseller to Add DNS zones.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_DNS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_demo_setup', 'Reseller ACL Demo Setup', 'Allow the reseller to turn an Account into a Demo Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DEMO_SETUP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DEMO_SETUP : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_disallow_shell', 'Reseller ACL Disallow Shell', 'Never Allow creation of Accounts with Shell access.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DISALLOW_SHELL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DISALLOW_SHELL : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_account', 'Reseller ACL Edit Account', 'Allow the reseller to modify an Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_ACCOUNT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_ACCOUNT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_dns', 'Reseller ACL Edit DNS', 'Allow editing of DNS zones.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_DNS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_mx', 'Reseller ACL Edit MX', 'Allow editing of MX entries,', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_MX') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_MX : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_pkg', 'Reseller ACL Edit PKG', 'Allow editing of packages.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_PKG') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_PKG : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_frontpage', 'Reseller ACL FrontPage', 'Allow the reseller to install and uninstall FrontPage extensions.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_FRONTPAGE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_FRONTPAGE : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_kill_acct', 'Reseller ACL Kill Acct', 'Allow termination of Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_ACCT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_kill_dns', 'Reseller ACL Kill DNS', 'Allow the reseller to remove DNS entries.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_DNS : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_limit_bandwidth', 'Reseller ACL Limit bandwidth', 'Allow the reseller to modify bandiwdth Limits.   Warning: This will Allow the reseller to circumvent package bandwidth Limits, if you are not using resource Limits!', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIMIT_BANDWIDTH') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIMIT_BANDWIDTH : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_list_accts', 'Reseller ACL List Accts', 'Allow the reseller to list his or her Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIST_ACCTS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIST_ACCTS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_mailcheck', 'Reseller ACL Mailcheck', 'Allow the reseller to access the WHM Mail Troubleshooter.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MAILCHECK') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MAILCHECK : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_mod_subdomains', 'Reseller ACL Mod SubDomains', 'Allow the reseller to enable and disable SubDomains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MOD_SUBDOMAINS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MOD_SUBDOMAINS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_news', 'Reseller ACL News', 'Allow the reseller to modify cPanel/WHM news.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_NEWS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_NEWS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_onlyselfandglobalpkgs', 'Reseller ACL Onlyselfandglobalpkgs', 'Prevent the creation of Accounts with packages that are neither global nor owned by this user.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ONLYSELFANDGLOBALPKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ONLYSELFANDGLOBALPKGS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_park_dns', 'Reseller ACL Park DNS', 'Allow the reseller to park domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PARK_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PARK_DNS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_passwd', 'Reseller ACL Passwd', 'Allow the reseller to modify Passwords.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PASSWD') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PASSWD : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_quota', 'Reseller ACL Quota', 'Allow the reseller to modify Disk Quotas.   Warning: This will Allow resellers to circumvent package Limits for Disk space, if you are not using resource Limits!', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_QUOTA') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_QUOTA : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_rearrange_accts', 'Reseller ACL Rearrange Accts', 'Allow the reseller to rearrange Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_REARRANGE_ACCTS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_REARRANGE_ACCTS : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_res_cart', 'Reseller ACL Res Cart', 'Allow the reseller to reset the shopping Cart.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RES_CART') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RES_CART : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_status', 'Reseller ACL Status', 'Allow the reseller to view the Service Status feature in WHM.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATUS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATUS : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_resftp', 'Reseller ACL Resftp', 'Allow the reseller to synchronize FTP Passwords.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESFTP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESFTP : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_restart', 'Reseller ACL Restart', 'Allow the reseller to restart services.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESTART') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESTART : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_show_bandwidth', 'Reseller ACL Show bandwidth', 'Allow the reseller to view Accounts bandwidth usage.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SHOW_BANDWIDTH') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SHOW_BANDWIDTH : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_ssl', 'Reseller ACL SSL', 'Allow the reseller to access the SSL Manager.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL : 0), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_ssl_gencrt', 'Reseller ACL SSL gencrt', 'Allow the reseller to access the SSL CSR/CRT generator.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL_GENCRT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL_GENCRT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_stats', 'Reseller ACL Stats', 'Allow the reseller to view Account Statistics.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATS : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_suspend_acct', 'Reseller ACL Suspend Acct', 'Allow the reseller to Suspend Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SUSPEND_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SUSPEND_ACCT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_upgrade_account', 'Reseller ACL Upgrade Account', 'Allow the reseller to upgrade and downgrade Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_UPGRADE_ACCOUNT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_UPGRADE_ACCOUNT : 1), array('0', '1'), array('No', 'Yes'));
		$settings->add_text_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acllist', 'CPanel Package Defaults Reseller - ACL List', '', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACLLIST') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACLLIST : 'reseller'));
	}

}
