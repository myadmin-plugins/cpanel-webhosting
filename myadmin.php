<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_cpanel define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Cpanel Webhosting',
	'description' => 'Allows selling of Cpanel Server and VPS License Types.  More info at https://www.netenberg.com/cpanel.php',
	'help' => 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a cpanel license. Allow 10 minutes for activation.',
	'module' => 'licenses',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-cpanel-webhosting',
	'repo' => 'https://github.com/detain/myadmin-cpanel-webhosting',
	'version' => '1.0.0',
	'type' => 'licenses',
	'hooks' => [
		/*'function.requirements' => ['Detain\MyAdminCpanel\Plugin', 'Requirements'],
		'licenses.settings' => ['Detain\MyAdminCpanel\Plugin', 'Settings'],
		'licenses.activate' => ['Detain\MyAdminCpanel\Plugin', 'Activate'],
		'licenses.change_ip' => ['Detain\MyAdminCpanel\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminCpanel\Plugin', 'Menu'] */
	],
];
