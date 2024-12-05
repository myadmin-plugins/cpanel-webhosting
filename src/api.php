<?php
/**
* API Functions
* @author    Joe Huss <detain@interserver.net>
* @copyright 2025
* @package   MyAdmin
* @category  API
*/

/**
 * Automatic CPanel Login to Client Web Interface
 *
 * @param int $id id of website
 * @return array array containing status of error or ok and status_text with description of error or the unique logged in url
 * @throws \Exception
 */
function api_auto_cpanel_login($id)
{
    $return = ['status' => 'error', 'status_text' => ''];
    $module = 'webhosting';
    $custid = get_custid($GLOBALS['tf']->session->account_id, 'vps');
    $settings = get_module_settings($module);
    $db = get_module_db($module);
    $id = (int)$id;
    $service = get_service($id, $module);
    if ($service === false) {
        $return['status_text'] = 'Invalid Website Passed';
        return $return;
    }
    $username = $service[$settings['PREFIX'].'_username'];
    $hostname = $service[$settings['PREFIX'].'_hostname'];
    $service_master = get_service_master($service[$settings['PREFIX'].'_server'], $module);
    $host = $service_master[$settings['PREFIX'].'_name'];
    if ($host && $username) {
        function_requirements('whm_api');
        $serverdata = whm_get_server($service[$settings['PREFIX'].'_server']);
        $apiDetails = whm_api();
        $hash = $serverdata['hash'];
        $whmusername = 'root';
        $whmpassword = $hash;
        // The user on whose behalf the API call runs.
        $query = "https://{$host}:2087/json-api/create_user_session?api.version=1&user={$username}&service=cpaneld";
        $curl = curl_init();                                     // Create Curl Object.
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);       // Allow self-signed certificates...
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);       // and certificates that don't match the hostname.
        curl_setopt($curl, CURLOPT_HEADER, false);               // Do not include header in output
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);        // Return contents of transfer on curl_exec.
        $header[0] = "Authorization: WHM $whmusername:" . preg_replace("'(\r|\n)'", '', $hash);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);         // Set the username and password.
        curl_setopt($curl, CURLOPT_URL, $query);                 // Execute the query.
        $result = curl_exec($curl);
        if ($result == false) {
            error_log("curl_exec threw error '" . curl_error($curl) . "' for {$query}");
        }	// log error if curl exec fails
        $decodedResponse = json_decode($result, true);
        if (isset($decodedResponse['data']) && isset($decodedResponse['data']['url'])) {
            $return['status'] = 'ok';
            $return['status_text'] = $decodedResponse['data']['url'];
            return $return;
        }
    }
    $return['status_text'] = 'Sorry! something went wrong, couldn\'t able to connect to cPanel!';
    return $return;
}
