<?php

namespace Detain\MyAdminCpanel;

use Detain\Cpanel\Cpanel;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminCpanel
 */
class Plugin {

	public static $name = 'CPanel Webhosting';
	public static $description = 'web-based control panel makes site management a piece of cake. Empower your customers and offer them the ability to administer every facet of their website using simple, point-and-click software.  More info at https://cpanel.com/';
	public static $help = '';
	public static $module = 'webhosting';
	public static $type = 'service';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			self::$module.'.activate' => [__CLASS__, 'getActivate'],
			self::$module.'.reactivate' => [__CLASS__, 'getReactivate'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
			self::$module.'.terminate' => [__CLASS__, 'getTerminate'],
			'function.requirements' => [__CLASS__, 'getRequirements'],
			'ui.menu' => [__CLASS__, 'getMenu']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 * @throws \Exception
	 */
	public static function getActivate(GenericEvent $event) {
		if ($event['category'] == get_service_define('WEB_CPANEL')) {
			myadmin_log(self::$module, 'info', 'Cpanel Activation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$serviceTypes = run_event('get_service_types', FALSE, self::$module);
			$settings = get_module_settings(self::$module);
			$extra = run_event('parse_service_extra', $serviceClass->getExtra(), self::$module);
			$serverdata = get_service_master($serviceClass->getServer(), self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
			$hostname = $serviceClass->getHostname();
			if (trim($hostname) == '')
				$hostname = $serviceClass->getId().'.server.com';
			$password = website_get_password($serviceClass->getId());
			$username = get_new_webhosting_username($serviceClass->getId(), $hostname, $serviceClass->getServer());
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
			$options = [
				'ip' => 'n',
				'cgi' => 1,
				'frontpage' => 0,
				'hasshell' => 0,
				'cpmod' => 'paper_lantern',
				'maxsql' => 'unlimited',
				'maxpop' => 'unlimited',
				'maxlst' => 0,
				'maxsub' => 'unlimited'
			];
			if (in_array('reseller', explode(',', $event['field1'])))
				$reseller = TRUE;
			else
				$reseller = FALSE;
			if ($serviceTypes[$serviceClass->getType()]['services_field2'] != '') {
				$fields = explode(',', $serviceTypes[$serviceClass->getType()]['services_field2']);
				foreach ($fields as $field) {
					list($key, $value) = explode('=', $field);
					if ($key == 'script')
						$extra[$key] = $value;
					else
						$options[$key] = $value;
				}
			}
			$options = array_merge($options, [
				'domain' => $hostname,
				'username' => $username,
				'password' => $password,
				'contactemail' => $event['email']
			]);
			myadmin_log(self::$module, 'info', json_encode($options), __LINE__, __FILE__);
			$response = $whm->xmlapi_query('createacct', $options);
			request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'createacct', $options, $response);
			myadmin_log(self::$module, 'info', 'Response: '.str_replace('\n', '', strip_tags($response)), __LINE__, __FILE__);
			$response = json_decode($response);
			if (mb_substr($response->result[0]->statusmsg, 0, 19) == 'Sorry, the password') {
				while (mb_substr($response->result[0]->statusmsg, 0, 19) == 'Sorry, the password') {
					$password = generateRandomString(10, 2, 2, 2, 1);
					$options['password'] = $password;
					myadmin_log(self::$module, 'info', "Trying Password {$options['password']}", __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
				$GLOBALS['tf']->history->add($settings['PREFIX'], 'password', $serviceClass->getId(), $options['password']);
			}
			if ($response->result[0]->statusmsg == 'Sorry, a group for that username already exists.') {
				while ($response->result[0]->statusmsg == 'Sorry, a group for that username already exists.') {
					$username .= 'a';
					$username = mb_substr($username, 1);
					$options['username'] = $username;
					myadmin_log(self::$module, 'info', 'Trying Username '.$options['username'], __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
			}
			if (preg_match("/^.*This system already has an account named .{1,3}{$username}.{1,3}\.$/m", $response->result[0]->statusmsg) || preg_match('/^.*The name of another account on this server has the same initial/m', $response->result[0]->statusmsg)) {
				while (preg_match("/^.*This system already has an account named .{1,3}{$username}.{1,3}\.$/m", $response->result[0]->statusmsg) || preg_match('/^.*The name of another account on this server has the same initial/m', $response->result[0]->statusmsg)) {
					$username .= 'a';
					$username = mb_substr($username, 1);
					$options['username'] = $username;
					myadmin_log(self::$module, 'info', 'Trying Username '.$options['username'], __LINE__, __FILE__);
					$response = $whm->xmlapi_query('createacct', $options);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'createacct', $options, $response);
					myadmin_log(self::$module, 'info', "Response: $response", __LINE__, __FILE__);
					$response = json_decode($response);
				}
			}
			if ($response->result[0]->status == 1) {
				$ip = $response->result[0]->options->ip;
				if (isset($options['bwlimit']) && $options['bwlimit'] != 'unlimited') {
					$response3 = $whm->limitbw($username, $options['bwlimit']);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'limitbw', ['username' => $username, 'options' => $options['bwlimit']], $response3);
					myadmin_log(self::$module, 'info', 'Response: '.str_replace('\n', "\n", strip_tags($response3)), __LINE__, __FILE__);
				}
				if ($reseller === TRUE) {
					$response2 = $whm->setupreseller($username, FALSE);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'setupreseller', ['username' => $username], $response2);
					myadmin_log(self::$module, 'info', "Response: {$response2}", __LINE__, __FILE__);
					$response3 = $whm->listacls();

					$acls = json_decode($response3);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'listacls', [], $response);
					//myadmin_log(self::$module, 'info', json_encode($acls), __LINE__, __FILE__);
					if (!isset($acls->acls->reseller)) {
						$acl = [
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
						];
						$result = $whm->saveacllist($acl);
						myadmin_log(self::$module, 'info', $result, __LINE__, __FILE__);
						request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'saveacllist', $acl, $result);
						myadmin_log(self::$module, 'info', 'Reseller ACL Created', __LINE__, __FILE__);
					} else {
						myadmin_log(self::$module, 'info', 'Reseller ACL Exists', __LINE__, __FILE__);
					}
					$request = ['reseller' => $username, 'acllist' => 'reseller'];
					$result = $whm->setacls($request);
					myadmin_log(self::$module, 'info', $result, __LINE__, __FILE__);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'cpanel', 'setacls', $request, $result);
					myadmin_log(self::$module, 'info', 'Reseller assigned to ACL', __LINE__, __FILE__);
				}
				$db = get_module_db(self::$module);
				$username = $db->real_escape($username);
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='$ip', {$settings['PREFIX']}_username='$username' where {$settings['PREFIX']}_id='{$serviceClass->getId()}'", __LINE__, __FILE__);
				website_welcome_email($serviceClass->getId());
				if (isset($extra['script']) && $extra['script'] > 0) {
					$script = (int) $extra['script'];
					include_once __DIR__.'/../../../../include/webhosting/softaculous/sdk.php';
					$userdata = $GLOBALS['tf']->accounts->read($serviceClass->getCustid());
					$soft = new \Softaculous_SDK();
					$soft->login = "https://{$username}:{$password}@{$serverdata[$settings['PREFIX'].'_name']}:2083/frontend/paper_lantern/softaculous/index.live.php";
					$soft->list_scripts();
					$data['overwrite_existing'] = 1;
					$data['softdomain'] = $hostname;
					$data['softdirectory'] = '';
					$data['admin_username'] = 'admin';
					$data['admin_pass'] = $password;
					$data['admin_email'] = $event['email'];
					$data['admin_realname'] = (isset($userdata['name']) ? $userdata['name'] : $userdata['account_lid']);
					list($data['admin_fname'], $data['admin_lname']) = isset($userdata['name']) ? explode(' ', $userdata['name']) : explode('@', $userdata['account_lid']);
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
					$result = json_decode($soft->install($script, $data), TRUE);
					request_log(self::$module, $serviceClass->getCustid(), __FUNCTION__, 'softaculous', 'install', ['script' => $script, 'data' => $data], $result);
					myadmin_log(self::$module, 'info', json_encode($result), __LINE__, __FILE__);
				}
				$response = add_dns_record(14426, 'wh'.$serviceClass->getId(), $ip, 'A', 86400, 0, TRUE);
				myadmin_log(self::$module, 'info', 'Response: '.json_encode($response), __LINE__, __FILE__);
				$response = $whm->park($options['username'], 'wh'.$serviceClass->getId().'.ispot.cc', '');
				myadmin_log(self::$module, 'info', 'Response: '.json_encode($response), __LINE__, __FILE__);
				$event['success'] = TRUE;
			} else {
				$event['success'] = FALSE;
			}
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 * @throws \Exception
	 */
	public static function getReactivate(GenericEvent $event) {
		if ($event['category'] == get_service_define('WEB_CPANEL')) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$serverdata = get_service_master($serviceClass->getServer(), self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
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
			if (in_array('reseller', explode(',', $event['field1'])))
				$response = json_decode($whm->unsuspendreseller($serviceClass->getUsername()), TRUE);
			else
				$response = json_decode($whm->unsuspendacct($serviceClass->getUsername()), TRUE);
			myadmin_log(self::$module, 'info', json_encode($response), __LINE__, __FILE__);
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 * @throws \Exception
	 */
	public static function getDeactivate(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('WEB_CPANEL'), get_service_define('WEB_WORDPRESS')])) {
			myadmin_log(self::$module, 'info', 'Cpanel Deactivation', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			if ($serviceClass->getServer() > 0) {
				$serverdata = get_service_master($serviceClass->getServer(), self::$module);
				$hash = $serverdata[$settings['PREFIX'].'_key'];
				$ip = $serverdata[$settings['PREFIX'].'_ip'];
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
				if (in_array('reseller', explode(',', $event['field1'])))
					$response = json_decode($whm->suspendreseller($serviceClass->getUsername(), 'Canceled Service'), TRUE);
				else
					$response = json_decode($whm->suspendacct($serviceClass->getUsername(), 'Canceled Service'), TRUE);
				myadmin_log(self::$module, 'info', json_encode($response), __LINE__, __FILE__);
			}
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 * @return boolean|null
	 * @throws \Exception
	 */
	public static function getTerminate(GenericEvent $event) {
		if (in_array($event['type'], [get_service_define('WEB_CPANEL'), get_service_define('WEB_WORDPRESS')])) {
			myadmin_log(self::$module, 'info', 'Cpanel Termination', __LINE__, __FILE__);
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$serverdata = get_service_master($serviceClass->getServer(), self::$module);
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$ip = $serverdata[$settings['PREFIX'].'_ip'];
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
			if (trim($serviceClass->getUsername()) != '') {
				if (in_array('reseller', explode(',', $event['field1'])))
					$response = json_decode($whm->terminatereseller($serviceClass->getUsername(), TRUE));
				else
					$response = json_decode($whm->removeacct($serviceClass->getUsername(), FALSE));
				myadmin_log(self::$module, 'info', json_encode($response), __LINE__, __FILE__);
			} else
				myadmin_log(self::$module, 'info', "Skipping WHMAPI/Server Removal for {$serviceClass->getHostname()} because username is blank", __LINE__, __FILE__);
			$dnsr = json_decode($whm->dumpzone($serviceClass->getHostname()));
			if ($dnsr->result[0]->status == 1) {
				$db = get_module_db(self::$module);
				$db->query("select * from {$settings['TABLE']} where {$settings['PREFIX']}_hostname='{$serviceClass->getHostname()}' and {$settings['PREFIX']}_id != {$serviceClass->getId()} and {$settings['PREFIX']}_status = 'active'", __LINE__, __FILE__);
				if ($db->num_rows() == 0) {
					myadmin_log(self::$module, 'info', "Removing Hanging DNS entry for {$serviceClass->getHostname()}", __LINE__, __FILE__);
					$whm->killdns($serviceClass->getHostname());
				} else
					myadmin_log(self::$module, 'info', "Skipping Removing DNS entry for {$serviceClass->getHostname()} because other non deleted sites w/ the same hostname exist", __LINE__, __FILE__);
			}
			$event->stopPropagation();
			if (trim($serviceClass->getUsername()) == '')
				return TRUE;
			elseif ($response->result[0]->status == 1)
				return TRUE;
			elseif ($response->result[0]->statusmsg == "System user {$serviceClass->getUsername()} does not exist!")
				return TRUE;
			else
				return FALSE;
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getChangeIp(GenericEvent $event) {
		if ($event['category'] == get_service_define('WEB_CPANEL')) {
			$serviceClass = $event->getSubject();
			$settings = get_module_settings(self::$module);
			$cpanel = new Cpanel(FANTASTICO_USERNAME, FANTASTICO_PASSWORD);
			myadmin_log(self::$module, 'info', 'IP Change - (OLD:'.$serviceClass->getIp().") (NEW:{$event['newip']})", __LINE__, __FILE__);
			$result = $cpanel->editIp($serviceClass->getIp(), $event['newip']);
			if (isset($result['faultcode'])) {
				myadmin_log(self::$module, 'error', 'Cpanel editIp('.$serviceClass->getIp().', '.$event['newip'].') returned Fault '.$result['faultcode'].': '.$result['fault'], __LINE__, __FILE__);
				$event['status'] = 'error';
				$event['status_text'] = 'Error Code '.$result['faultcode'].': '.$result['fault'];
			} else {
				$GLOBALS['tf']->history->add($settings['TABLE'], 'change_ip', $event['newip'], $serviceClass->getIp());
				$serviceClass->set_ip($event['newip'])->save();
				$event['status'] = 'ok';
				$event['status_text'] = 'The IP Address has been changed.';
			}
			$event->stopPropagation();
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
			if (has_acl('module_config')) {
				$menu->add_menu(self::$module, self::$module.'api', 'API', '/images/myadmin/api.png');
				$menu->add_menu(self::$module.'api', 'whmaccount', 'Accounting');
				$menu->add_menu(self::$module.'api', 'whmdns', 'DNS');
				$menu->add_menu(self::$module.'api', 'whmpackages', 'Packages');
				$menu->add_menu(self::$module.'api', 'whmresellers', 'Resellers');
				$menu->add_menu(self::$module.'api', 'whminformation', 'Server Information');
				$menu->add_menu(self::$module.'api', 'whmadministration', 'Server Administration');
				$menu->add_menu(self::$module.'api', 'whmservices', 'Services');
				$menu->add_menu(self::$module.'api', 'whmssl', 'SSL Certs');
				$menu->add_link(self::$module.'api', 'choice=none.whm_choose_server', '/images/icons/paper_content_pencil_48.png', 'Set What Server Your Working On.');

				// Accounting
				$menu->add_link('whmaccount', 'choice=none.whm_createacct', '/images/whm/createacct.gif', 'Create an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_passwd', '/images/whm/passwd.gif', 'Change an Accounts Password');
				$menu->add_link('whmaccount', 'choice=none.whm_limitbw', '/images/whm/limitbw.gif', 'Limit Bandwidth Usage (Transfer)');
				$menu->add_link('whmaccount', 'choice=none.whm_listaccts', '/images/whm/listaccts.gif', 'List Accounts');
				$menu->add_link('whmaccount', 'choice=none.whm_modifyacct', '/images/whm/modifyacct.gif', 'Modify an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_editquota', '/images/whm/editquota.gif', 'Edit Quota');
				$menu->add_link('whmaccount', 'choice=none.whm_accountsummary', '/images/whm/accountsummary.gif', 'Show an Accounts Information');
				$menu->add_link('whmaccount', 'choice=none.whm_suspendacct', '/images/whm/suspendacct.gif', 'Suspend an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_listsuspended', '/images/whm/listsuspended.gif', 'List Suspended Accounts');
				$menu->add_link('whmaccount', 'choice=none.whm_removeacct', '/images/whm/removeacct.gif', 'Terminate an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_unsuspendacct', '/images/whm/unsuspendacct.gif', 'Unsuspend an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_changepackage', '/images/whm/changepackage.gif', 'Upgrade or Downgrade an Account');
				$menu->add_link('whmaccount', 'choice=none.whm_myprivs', '/images/whm/createacct.gif', 'View Current Users Privileges');
				$menu->add_link('whmaccount', 'choice=none.whm_domainuserdata', '/images/whm/createacct.gif', 'Obtain User Data for a Domain');
				$menu->add_link('whmaccount', 'choice=none.whm_setsiteip', '/images/whm/createacct.gif', 'Change a Sites (or Users) IP Address');
				$menu->add_link('whmaccount', 'choice=none.whm_restoreaccount', '/images/whm/createacct.gif', 'Restore an Account Backup');

				// DNS
				$menu->add_link('whmdns', 'choice=none.whm_adddns', '/images/whm/adddns.gif', 'Add a DNS Zone');
				$menu->add_link('whmdns', 'choice=none.whm_addzonerecord', '/images/whm/adddns.gif', 'Add a Zone Record');
				$menu->add_link('whmdns', 'choice=none.whm_editzonerecord', '/images/whm/editzonerecord.gif', 'Edit a Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_getzonerecord', '/images/whm/getzonerecord.gif', 'Get a Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_killdns', '/images/whm/killdns.gif', 'Delete a DNS Zone');
				$menu->add_link('whmdns', 'choice=none.whm_listzones', '/images/whm/listzones.gif', 'List All DNS Zones');
				$menu->add_link('whmdns', 'choice=none.whm_dumpzone', '/images/whm/listzones.gif', 'List (Dump) 1 Zone');
				$menu->add_link('whmdns', 'choice=none.whm_lookupnsip', '/images/whm/listzones.gif', 'Look up a Nameservers IP Address');
				//$menu->add_link('whmdns', 'choice=none.whm_removezonerecord', '/images/whm/removezonerecord.gif', 'Remove a DNS Zone Record');
				//$menu->add_link('whmdns', 'choice=none.whm_resetzone', '/images/whm/resetzone.gif', 'Reset a DNS Zone');
				//$menu->add_link('whmdns', 'choice=none.whm_resolvedomainname', '/images/whm/resolvedomainname.gif', 'Resolve an IP Address from a Domain');
				$menu->add_link('whmdns', 'choice=none.whm_listmxs', '/images/whm/savemxs.gif', 'List a Domains MX Records');
				$menu->add_link('whmdns', 'choice=none.whm_savemxs', '/images/whm/savemxs.gif', 'Create a new MX Record');

				// Packages
				//$menu->add_link('whmpackages', 'choice=none.whm_addpkg', '/images/whm/addpkg.gif', 'Add a Package');
				//$menu->add_link('whmpackages', 'choice=none.whm_killpkg', '/images/whm/killpkg.gif', 'Delete a Package');
				//$menu->add_link('whmpackages', 'choice=none.whm_editpkg', '/images/whm/editpkg.gif', 'Edit a Package');
				$menu->add_link('whmpackages', 'choice=none.whm_listpkgs', '/images/whm/listpkgs.gif', 'List Packages');
				$menu->add_link('whmpackages', 'choice=none.whm_getfeaturelist', '/images/whm/getfeaturelist.gif', 'List Available Features');

				// Resellers
				$menu->add_link('whmresellers', 'choice=none.whm_setupreseller', '/images/whm/createacct.gif', 'Add Reseller Privileges');
				$menu->add_link('whmresellers', 'choice=none.whm_saveacllist', '/images/whm/createacct.gif', 'Create a Reseller ACL List');
				$menu->add_link('whmresellers', 'choice=none.whm_listacls', '/images/whm/createacct.gif', 'List Current Reseller ACL Lists');
				$menu->add_link('whmresellers', 'choice=none.whm_listresellers', '/images/whm/listresellers.gif', 'List Reseller Accounts');
				$menu->add_link('whmresellers', 'choice=none.whm_resellerstats', '/images/whm/createacct.gif', 'List Resellers Accounts Information');
				$menu->add_link('whmresellers', 'choice=none.whm_unsetupreseller', '/images/whm/createacct.gif', 'Remove Reseller Privileges');
				$menu->add_link('whmresellers', 'choice=none.whm_setacls', '/images/whm/createacct.gif', 'Set a Resellers ACL List');
				$menu->add_link('whmresellers', 'choice=none.whm_terminatereseller', '/images/whm/createacct.gif', 'Terminate a Reseller');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerips', '/images/whm/createacct.gif', 'Assign a Reseller IP Addresses');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerlimits', '/images/whm/createacct.gif', 'Set Reseller Limits');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellermainip', '/images/whm/createacct.gif', 'Set up a Resellers Main IP Address');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellerpackagelimit', '/images/whm/createacct.gif', 'Define a Resellers Packages');
				$menu->add_link('whmresellers', 'choice=none.whm_suspendreseller', '/images/whm/createacct.gif', 'Suspend a Resellers Account');
				$menu->add_link('whmresellers', 'choice=none.whm_unsuspendreseller', '/images/whm/createacct.gif', 'Unsuspend a Resellers Account');
				$menu->add_link('whmresellers', 'choice=none.whm_acctcounts', '/images/whm/createacct.gif', 'View Information about Accounts Owned by a Reseller');
				$menu->add_link('whmresellers', 'choice=none.whm_setresellernameservers', '/images/whm/createacct.gif', 'Set a Resellers Nameservers');

				// Information
				$menu->add_link('whminformation', 'choice=none.whm_gethostname', '/images/whm/gethostname.gif', 'Display Server Hostname');
				$menu->add_link('whminformation', 'choice=none.whm_version', '/images/whm/version.gif', 'Display cPanel &amp; WHM Version');
				$menu->add_link('whminformation', 'choice=none.whm_loadavg', '/images/whm/loadavg.gif', 'Display the Servers Load Average');
				$menu->add_link('whminformation', 'choice=none.whm_getdiskusage', '/images/whm/loadavg.gif', 'Display the Servers Disk Usage');
				//$menu->add_link('whminformation', 'choice=none.whm_systemloadavg', '/images/whm/createacct.gif', 'Display the Servers Load Average (with Metadata)');
				$menu->add_link('whminformation', 'choice=none.whm_getlanglist', '/images/whm/createacct.gif', 'View a List of Available Languages');

				// Adminsitration
				$menu->add_link('whmadministration', 'choice=none.whm_reboot', '/images/whm/reboot.gif', 'Reboot the Server');
				$menu->add_link('whmadministration', 'choice=none.whm_addip', '/images/whm/addip.gif', 'Add IP Address');
				$menu->add_link('whmadministration', 'choice=none.whm_delip', '/images/whm/createacct.gif', 'Delete IP Address');
				$menu->add_link('whmadministration', 'choice=none.whm_listips', '/images/whm/listips.gif', 'List IP Addresses');
				$menu->add_link('whmadministration', 'choice=none.whm_sethostname', '/images/whm/createacct.gif', 'Set Hostname');
				$menu->add_link('whmadministration', 'choice=none.whm_setresolvers', '/images/whm/createacct.gif', 'Set Resolvers');
				//$menu->add_link('whmadministration', 'choice=none.whm_showbw', '/images/whm/createacct.gif', 'Show Bandwidth');
				//$menu->add_link('whmadministration', 'choice=none.whm_nvset', '/images/whm/createacct.gif', 'Set a Non-Volatile Variable Value');
				//$menu->add_link('whmadministration', 'choice=none.whm_nvget', '/images/whm/createacct.gif', 'Retrieve a Non-Volatile Variable Value');

				// Services
				$menu->add_link('whmservices', 'choice=none.whm_restartservice', '/images/whm/restartservice.gif', 'Restart Service');
				$menu->add_link('whmservices', 'choice=none.whm_servicestatus', '/images/whm/servicestatus.gif', 'Service Status');
				//$menu->add_link('whmservices', 'choice=none.whm_configureservice', '/images/whm/configureservice.gif', 'Configure a Service');

				// SSL
				$menu->add_link('whmssl', 'choice=none.whm_fetchsslinfo', '/images/whm/fetchsslinfo.gif', 'Fetch SSL Certificate Information');
				//$menu->add_link('whmssl', 'choice=none.whm_generatessl', '/images/whm/generatessl.gif', 'Generate an SSL Certificate');
				//$menu->add_link('whmssl', 'choice=none.whm_installssl', '/images/whm/installssl.gif', 'Install an SSL Certificate');
				$menu->add_link('whmssl', 'choice=none.whm_listcrts', '/images/whm/listcrts.gif', 'List Available SSL Certificates');
			}
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_page_requirement('whm_get_accounts', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_api', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_choose_server', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_createacct', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_passwd', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_limitbw', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listaccts', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_modifyacct', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_editquota', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_accountsummary', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_suspendacct', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listsuspended', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_removeacct', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_unsuspendacct', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_changepackage', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_myprivs', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_domainuserdata', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setsiteip', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_restoreaccount', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_adddns', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_addzonerecord', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_editzonerecord', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_getzonerecord', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_killdns', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listzones', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_dumpzone', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_lookupnsip', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_removezonerecord', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_resetzone', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_resolvedomainname', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listmxs', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_savemxs', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_addpkg', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_killpkg', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_editpkg', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listpkgs', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_getfeaturelist', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setupreseller', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_saveacllist', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listacls', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listresellers', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_resellerstats', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_unsetupreseller', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setacls', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_terminatereseller', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresellerips', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresellerlimits', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresellermainip', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresellerpackagelimit', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_suspendreseller', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_unsuspendreseller', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_acctcounts', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresellernameservers', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_gethostname', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_version', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_loadavg', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_getdiskusage', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_systemloadavg', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_getlanglist', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_reboot', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_addip', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_delip', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listips', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_sethostname', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_setresolvers', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_showbw', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_nvset', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_nvget', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_restartservice', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_servicestatus', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_configureservice', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_fetchsslinfo', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_generatessl', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_installssl', '/webhosting/whmapi.functions.inc.php');
		$loader->add_page_requirement('whm_listcrts', '/webhosting/whmapi.functions.inc.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_cpanel_server', 'Default CPanel Setup Server', NEW_WEBSITE_CPANEL_SERVER, get_service_define('WEB_CPANEL'));
		$settings->add_select_master(self::$module, 'Default Servers', self::$module, 'new_website_wordpress_server', 'Default WordPress Setup Server', NEW_WEBSITE_WORDPRESS_SERVER, get_service_define('WEB_WORDPRESS'));
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_cpanel', 'Out Of Stock CPanel Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_CPANEL'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Out of Stock', 'outofstock_webhosting_wordpress', 'Out Of Stock WordPress Managed Webhosting', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_WEBHOSTING_WORDPRESS'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_ip', 'CPanel Package Defaults - IP', 'Enable/Disable Dedicated IP for new Sites', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_IP'), ['n', 'y'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_cgi', 'CPanel Package Defaults - CGI', 'Enable/Disable CGI Access', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_CGI'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_frontpage', 'CPanel Package Defaults - Frontpage', 'Enable/Disable Frontpage Extensions', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_FRONTPAGE'), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_hasshell', 'CPanel Package Defaults - Shell', 'Enable/Disable Shell', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_HASSHELL'), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_cpmod', 'CPanel Package Defaults - CP Mod', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_CPMOD'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxsql', 'CPanel Package Defaults - Max SQL', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXSQL'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxpop', 'CPanel Package Defaults - Max POP3', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXPOP'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxlst', 'CPanel Package Defaults - Max Mailing Lists', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXLST'));
		$settings->add_text_setting(self::$module, 'CPanel Defaults', 'cpanel_package_defaults_maxsub', 'CPanel Package Defaults - Max Subdomains', '', $settings->get_setting('CPANEL_PACKAGE_DEFAULTS_MAXSUB'));
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg', 'Reseller ACL Add PKG', 'Allow the creation of packages.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg_ip', 'Reseller ACL Add PKG IP', 'Allow the creation of packages with dedicated IPs.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_IP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_IP : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_add_pkg_shell', 'Reseller ACL Add PKG Shell', 'Allow the creation of packages with Shell access.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_SHELL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ADD_PKG_SHELL : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_all', 'Reseller ACL All', 'All features.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALL : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_addoncreate', 'Reseller ACL Allow Addoncreate', 'Allow the creation of packages with unlimited Addon domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_ADDONCREATE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_ADDONCREATE : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_parkedcreate', 'Reseller ACL Allow Parkedcreate', 'Allow the creation of packages with parked domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_PARKEDCREATE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_PARKEDCREATE : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_unlimited_disk_pkgs', 'Reseller ACL Allow Unlimited Disk PKGs', 'Allow the creation of packages with Unlimited Disk space.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_DISK_PKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_DISK_PKGS : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_allow_unlimited_pkgs', 'Reseller ACL Allow Unlimited PKGs', 'Allow the creation of packages with Unlimited bandwidth.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_PKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ALLOW_UNLIMITED_PKGS : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_clustering', 'Reseller ACL Clustering', 'Enable Clustering.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CLUSTERING') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CLUSTERING : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_create_acct', 'Reseller ACL Create Acct', 'Allow the reseller to Create a new Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_ACCT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_create_dns', 'Reseller ACL Create DNS', 'Allow the reseller to Add DNS zones.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_CREATE_DNS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_demo_setup', 'Reseller ACL Demo Setup', 'Allow the reseller to turn an Account into a Demo Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DEMO_SETUP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DEMO_SETUP : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_disallow_shell', 'Reseller ACL Disallow Shell', 'Never Allow creation of Accounts with Shell access.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DISALLOW_SHELL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_DISALLOW_SHELL : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_account', 'Reseller ACL Edit Account', 'Allow the reseller to modify an Account.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_ACCOUNT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_ACCOUNT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_dns', 'Reseller ACL Edit DNS', 'Allow editing of DNS zones.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_DNS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_mx', 'Reseller ACL Edit MX', 'Allow editing of MX entries,', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_MX') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_MX : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_edit_pkg', 'Reseller ACL Edit PKG', 'Allow editing of packages.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_PKG') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_EDIT_PKG : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_frontpage', 'Reseller ACL FrontPage', 'Allow the reseller to install and uninstall FrontPage extensions.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_FRONTPAGE') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_FRONTPAGE : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_kill_acct', 'Reseller ACL Kill Acct', 'Allow termination of Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_ACCT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_kill_dns', 'Reseller ACL Kill DNS', 'Allow the reseller to remove DNS entries.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_KILL_DNS : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_limit_bandwidth', 'Reseller ACL Limit bandwidth', 'Allow the reseller to modify bandiwdth Limits.   Warning: This will Allow the reseller to circumvent package bandwidth Limits, if you are not using resource Limits!', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIMIT_BANDWIDTH') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIMIT_BANDWIDTH : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_list_accts', 'Reseller ACL List Accts', 'Allow the reseller to list his or her Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIST_ACCTS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_LIST_ACCTS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_mailcheck', 'Reseller ACL Mailcheck', 'Allow the reseller to access the WHM Mail Troubleshooter.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MAILCHECK') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MAILCHECK : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_mod_subdomains', 'Reseller ACL Mod SubDomains', 'Allow the reseller to enable and disable SubDomains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MOD_SUBDOMAINS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_MOD_SUBDOMAINS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_news', 'Reseller ACL News', 'Allow the reseller to modify cPanel/WHM news.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_NEWS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_NEWS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_onlyselfandglobalpkgs', 'Reseller ACL Onlyselfandglobalpkgs', 'Prevent the creation of Accounts with packages that are neither global nor owned by this user.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ONLYSELFANDGLOBALPKGS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_ONLYSELFANDGLOBALPKGS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_park_dns', 'Reseller ACL Park DNS', 'Allow the reseller to park domains.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PARK_DNS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PARK_DNS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_passwd', 'Reseller ACL Passwd', 'Allow the reseller to modify Passwords.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PASSWD') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_PASSWD : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_quota', 'Reseller ACL Quota', 'Allow the reseller to modify Disk Quotas.   Warning: This will Allow resellers to circumvent package Limits for Disk space, if you are not using resource Limits!', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_QUOTA') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_QUOTA : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_rearrange_accts', 'Reseller ACL Rearrange Accts', 'Allow the reseller to rearrange Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_REARRANGE_ACCTS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_REARRANGE_ACCTS : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_res_cart', 'Reseller ACL Res Cart', 'Allow the reseller to reset the shopping Cart.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RES_CART') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RES_CART : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_status', 'Reseller ACL Status', 'Allow the reseller to view the Service Status feature in WHM.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATUS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATUS : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_resftp', 'Reseller ACL Resftp', 'Allow the reseller to synchronize FTP Passwords.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESFTP') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESFTP : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_restart', 'Reseller ACL Restart', 'Allow the reseller to restart services.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESTART') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_RESTART : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_show_bandwidth', 'Reseller ACL Show bandwidth', 'Allow the reseller to view Accounts bandwidth usage.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SHOW_BANDWIDTH') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SHOW_BANDWIDTH : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_ssl', 'Reseller ACL SSL', 'Allow the reseller to access the SSL Manager.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL : 0), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_ssl_gencrt', 'Reseller ACL SSL gencrt', 'Allow the reseller to access the SSL CSR/CRT generator.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL_GENCRT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SSL_GENCRT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_stats', 'Reseller ACL Stats', 'Allow the reseller to view Account Statistics.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATS') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_STATS : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_suspend_acct', 'Reseller ACL Suspend Acct', 'Allow the reseller to Suspend Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SUSPEND_ACCT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_SUSPEND_ACCT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_dropdown_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acl_upgrade_account', 'Reseller ACL Upgrade Account', 'Allow the reseller to upgrade and downgrade Accounts.', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_UPGRADE_ACCOUNT') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACL_UPGRADE_ACCOUNT : 1), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting(self::$module, 'Reseller ACLs', 'cpanel_package_defaults_reseller_acllist', 'CPanel Package Defaults Reseller - ACL List', '', (defined('CPANEL_PACKAGE_DEFAULTS_RESELLER_ACLLIST') ? CPANEL_PACKAGE_DEFAULTS_RESELLER_ACLLIST : 'reseller'));
	}

}
