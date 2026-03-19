<?php

namespace Detain\MyAdminCpanel\Tests;

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /**
     * Tests that the api.php source file exists at the expected path.
     * This is a basic sanity check for file presence.
     */
    public function testApiFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/api.php');
    }

    /**
     * Tests that the api.php file defines the api_auto_cpanel_login function.
     * This function provides automatic cPanel login functionality via the API.
     */
    public function testApiFileDefinesCpanelLoginFunction(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('function api_auto_cpanel_login', $source);
    }

    /**
     * Tests that api_auto_cpanel_login accepts an $id parameter.
     * The function signature should accept a website ID parameter.
     */
    public function testApiAutoLoginAcceptsIdParameter(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('function api_auto_cpanel_login($id)', $source);
    }

    /**
     * Tests that the api.php file initializes the return array with error status.
     * The default return status should be 'error' to handle failure cases.
     */
    public function testApiFileInitializesReturnWithErrorStatus(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString("'status' => 'error'", $source);
    }

    /**
     * Tests that the api.php file references the webhosting module.
     * The function operates on the webhosting module for cPanel sites.
     */
    public function testApiFileReferencesWebhostingModule(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString("'webhosting'", $source);
    }

    /**
     * Tests that the api.php file uses JSON decoding for the API response.
     * The cPanel API returns JSON that needs to be decoded.
     */
    public function testApiFileUsesJsonDecode(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('json_decode', $source);
    }

    /**
     * Tests that the api.php file uses curl for HTTP requests.
     * The function uses curl to connect to the cPanel API.
     */
    public function testApiFileUsesCurl(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('curl_init', $source);
        $this->assertStringContainsString('curl_exec', $source);
        $this->assertStringContainsString('curl_setopt', $source);
    }

    /**
     * Tests that the api.php file handles the success case with 'ok' status.
     * A successful login should return status 'ok' with the login URL.
     */
    public function testApiFileHandlesSuccessCase(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString("\$return['status'] = 'ok'", $source);
    }

    /**
     * Tests that the api.php file contains an 'Invalid Website Passed' error message.
     * This message is returned when the provided website ID is not found.
     */
    public function testApiFileContainsInvalidWebsiteError(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('Invalid Website Passed', $source);
    }

    /**
     * Tests that the api.php file casts the id to integer for safety.
     * Preventing SQL injection by casting the ID to int.
     */
    public function testApiFileCastsIdToInteger(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('(int)$id', $source);
    }

    /**
     * Tests that the api.php file connects to port 2087 for WHM API.
     * Port 2087 is the standard SSL port for the WHM API.
     */
    public function testApiFileConnectsToPort2087(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString(':2087/', $source);
    }

    /**
     * Tests that the api.php file uses create_user_session API endpoint.
     * The cPanel API v1 create_user_session endpoint is used for auto-login.
     */
    public function testApiFileUsesCreateUserSessionEndpoint(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('create_user_session', $source);
    }

    /**
     * Tests that the api.php file contains proper PHP opening tag.
     * The file should start with <?php for proper PHP parsing.
     */
    public function testApiFileStartsWithPhpTag(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringStartsWith('<?php', $source);
    }

    /**
     * Tests that the api.php file contains error_log for curl failures.
     * Curl failures should be logged for debugging purposes.
     */
    public function testApiFileLogsErrors(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('error_log', $source);
    }

    /**
     * Tests that the api.php file disables SSL verification.
     * Self-signed certificates are common in cPanel environments.
     */
    public function testApiFileDisablesSslVerification(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/api.php');
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST', $source);
    }
}
