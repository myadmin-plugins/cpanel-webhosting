<?php

namespace Detain\MyAdminCpanel\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class XmlapiTest extends TestCase
{
    /**
     * Tests that the xmlapi source file exists at the expected path.
     * This is a basic sanity check for file presence.
     */
    public function testXmlapiFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/xmlapi.php');
    }

    /**
     * Tests that the xmlapi class exists after including the source file.
     * The class is in the global namespace (no namespace declaration).
     */
    public function testXmlapiClassExists(): void
    {
        require_once __DIR__ . '/../src/xmlapi.php';
        $this->assertTrue(class_exists('xmlapi'));
    }

    /**
     * Tests that the xmlapi class can be instantiated with a host parameter.
     * The constructor requires at minimum a host argument.
     */
    public function testXmlapiCanBeInstantiatedWithHost(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertInstanceOf(\xmlapi::class, $api);
    }

    /**
     * Tests that get_host returns the host passed to the constructor.
     * Verifies host is stored correctly during instantiation.
     */
    public function testGetHostReturnsConstructorHost(): void
    {
        $api = new \xmlapi('10.0.0.1');
        $this->assertSame('10.0.0.1', $api->get_host());
    }

    /**
     * Tests that set_host changes the host value.
     * After calling set_host, get_host should return the new value.
     */
    public function testSetHostChangesHost(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_host('192.168.1.1');
        $this->assertSame('192.168.1.1', $api->get_host());
    }

    /**
     * Tests that the default port is 2087 (WHM SSL port).
     * This is the standard port for WHM API connections.
     */
    public function testDefaultPortIs2087(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertEquals(2087, $api->get_port());
    }

    /**
     * Tests that set_port changes the port value.
     * Ports must be valid integers between 1 and 65535.
     */
    public function testSetPortChangesPort(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_port(2086);
        $this->assertEquals(2086, $api->get_port());
    }

    /**
     * Tests that set_port converts string values to integers.
     * The method should handle string port numbers gracefully.
     */
    public function testSetPortConvertsStringToInt(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_port('2083');
        $this->assertEquals(2083, $api->get_port());
    }

    /**
     * Tests that setting a non-SSL port automatically switches protocol to http.
     * Ports 2082, 2086, 80, and 2095 are non-SSL cPanel ports.
     */
    public function testSetPortChangesProtocolForNonSslPorts(): void
    {
        $nonSslPorts = [2082, 2086, 80, 2095];
        foreach ($nonSslPorts as $port) {
            $api = new \xmlapi('127.0.0.1');
            $api->set_port($port);
            $this->assertSame(
                'http',
                $api->get_protocol(),
                "Port $port should set protocol to http"
            );
        }
    }

    /**
     * Tests that the default protocol is https.
     * SSL connections should be used by default for security.
     */
    public function testDefaultProtocolIsHttps(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertSame('https', $api->get_protocol());
    }

    /**
     * Tests that set_protocol accepts 'http' and 'https'.
     * Only these two protocols are valid for the XML-API.
     */
    public function testSetProtocolAcceptsValidValues(): void
    {
        $api = new \xmlapi('127.0.0.1');

        $api->set_protocol('http');
        $this->assertSame('http', $api->get_protocol());

        $api->set_protocol('https');
        $this->assertSame('https', $api->get_protocol());
    }

    /**
     * Tests that set_protocol throws an exception for invalid values.
     * Only 'http' and 'https' are acceptable protocol values.
     */
    public function testSetProtocolThrowsExceptionForInvalidValue(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_protocol('ftp');
    }

    /**
     * Tests that the default output format is 'simplexml'.
     * SimpleXML is the default response format for cPanel API calls.
     */
    public function testDefaultOutputIsSimplexml(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertSame('simplexml', $api->get_output());
    }

    /**
     * Tests that set_output accepts all four valid output formats.
     * The class supports json, xml, simplexml, and array formats.
     */
    public function testSetOutputAcceptsValidValues(): void
    {
        $api = new \xmlapi('127.0.0.1');

        foreach (['json', 'xml', 'simplexml', 'array'] as $format) {
            $api->set_output($format);
            $this->assertSame($format, $api->get_output());
        }
    }

    /**
     * Tests that set_output throws an exception for invalid formats.
     * This prevents misconfiguration that would cause API parsing failures.
     */
    public function testSetOutputThrowsExceptionForInvalidValue(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_output('yaml');
    }

    /**
     * Tests that the default auth_type is null (not yet configured).
     * Authentication must be explicitly configured before making API calls.
     */
    public function testDefaultAuthTypeIsNull(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertNull($api->get_auth_type());
    }

    /**
     * Tests that set_auth_type accepts 'hash' and 'pass'.
     * These are the only two authentication methods supported by the XML-API.
     */
    public function testSetAuthTypeAcceptsValidValues(): void
    {
        $api = new \xmlapi('127.0.0.1');

        $api->set_auth_type('hash');
        $this->assertSame('hash', $api->get_auth_type());

        $api->set_auth_type('pass');
        $this->assertSame('pass', $api->get_auth_type());
    }

    /**
     * Tests that set_auth_type throws an exception for invalid values.
     * Only 'hash' and 'pass' are valid authentication types.
     */
    public function testSetAuthTypeThrowsExceptionForInvalidValue(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_auth_type('token');
    }

    /**
     * Tests that set_password sets the auth_type to 'pass'.
     * Password authentication automatically configures the auth type.
     */
    public function testSetPasswordSetsAuthTypeToPass(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_password('secret123');
        $this->assertSame('pass', $api->get_auth_type());
    }

    /**
     * Tests that set_hash sets the auth_type to 'hash'.
     * Hash authentication automatically configures the auth type.
     */
    public function testSetHashSetsAuthTypeToHash(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_hash('abcdef1234567890');
        $this->assertSame('hash', $api->get_auth_type());
    }

    /**
     * Tests that set_hash strips newlines and whitespace from the hash.
     * WHM access hashes often contain newlines that must be removed.
     */
    public function testSetHashStripsWhitespace(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $reflection = new ReflectionClass($api);
        $authProp = $reflection->getProperty('auth');
        $authProp->setAccessible(true);

        $api->set_hash("abc\ndef\r\n ghi");
        $this->assertSame('abcdefghi', $authProp->getValue($api));
    }

    /**
     * Tests that the default user is null (not yet configured).
     * A user must be set before making API calls.
     */
    public function testDefaultUserIsNull(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertNull($api->get_user());
    }

    /**
     * Tests that set_user changes the user value.
     * The user is the WHM account used for authentication.
     */
    public function testSetUserChangesUser(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_user('root');
        $this->assertSame('root', $api->get_user());
    }

    /**
     * Tests that hash_auth sets both user and hash in one call.
     * This is a convenience method combining set_user and set_hash.
     */
    public function testHashAuthSetsBothUserAndHash(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->hash_auth('root', 'myhash123');
        $this->assertSame('root', $api->get_user());
        $this->assertSame('hash', $api->get_auth_type());
    }

    /**
     * Tests that password_auth sets both user and password in one call.
     * This is a convenience method combining set_user and set_password.
     */
    public function testPasswordAuthSetsBothUserAndPassword(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->password_auth('admin', 'pass123');
        $this->assertSame('admin', $api->get_user());
        $this->assertSame('pass', $api->get_auth_type());
    }

    /**
     * Tests that return_xml sets the output to 'xml'.
     * This is a convenience method equivalent to set_output('xml').
     */
    public function testReturnXmlSetsOutputToXml(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->return_xml();
        $this->assertSame('xml', $api->get_output());
    }

    /**
     * Tests that return_object sets the output to 'simplexml'.
     * This is a convenience method equivalent to set_output('simplexml').
     */
    public function testReturnObjectSetsOutputToSimplexml(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_output('json'); // change first
        $api->return_object();
        $this->assertSame('simplexml', $api->get_output());
    }

    /**
     * Tests that debug is disabled by default.
     * Debug output should only be enabled explicitly.
     */
    public function testDebugIsDisabledByDefault(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertFalse($api->get_debug());
    }

    /**
     * Tests that set_debug enables debug mode.
     * When called without arguments, it defaults to true (1).
     */
    public function testSetDebugEnablesDebug(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_debug(1);
        $this->assertEquals(1, $api->get_debug());
    }

    /**
     * Tests that set_debug(0) disables debug mode.
     * Passing 0 or false should disable debug output.
     */
    public function testSetDebugCanDisableDebug(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_debug(1);
        $api->set_debug(0);
        $this->assertEquals(0, $api->get_debug());
    }

    /**
     * Tests that the default HTTP client is 'curl'.
     * Curl is preferred over fopen for HTTP connections.
     */
    public function testDefaultHttpClientIsCurl(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->assertSame('curl', $api->get_http_client());
    }

    /**
     * Tests that set_http_client accepts 'curl' and 'fopen'.
     * These are the only two supported HTTP client implementations.
     */
    public function testSetHttpClientAcceptsValidValues(): void
    {
        $api = new \xmlapi('127.0.0.1');

        $api->set_http_client('curl');
        $this->assertSame('curl', $api->get_http_client());

        $api->set_http_client('fopen');
        $this->assertSame('fopen', $api->get_http_client());
    }

    /**
     * Tests that set_http_client throws an exception for invalid values.
     * Only curl and fopen are valid HTTP client implementations.
     */
    public function testSetHttpClientThrowsExceptionForInvalidValue(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_http_client('guzzle');
    }

    /**
     * Tests that the constructor accepts user and password parameters.
     * When all three parameters are provided, user and password should be set.
     */
    public function testConstructorWithUserAndPassword(): void
    {
        $api = new \xmlapi('127.0.0.1', 'root', 'secret');
        $this->assertSame('root', $api->get_user());
        $this->assertSame('pass', $api->get_auth_type());
    }

    /**
     * Tests that the constructor throws an exception when no host is provided.
     * A host is required to establish API connections.
     */
    public function testConstructorThrowsExceptionWithoutHost(): void
    {
        $this->expectException(\Exception::class);
        new \xmlapi(null);
    }

    /**
     * Tests that xmlapi_query throws an exception when no function is provided.
     * The function parameter determines which API endpoint to call.
     */
    public function testXmlapiQueryThrowsExceptionWithoutFunction(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_user('root');
        $api->set_password('pass');
        $this->expectException(\Exception::class);
        $api->xmlapi_query('');
    }

    /**
     * Tests that xmlapi_query throws an exception when no user is set.
     * Authentication requires a user to be configured.
     */
    public function testXmlapiQueryThrowsExceptionWithoutUser(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_password('pass');
        $this->expectException(\Exception::class);
        $api->xmlapi_query('listaccts');
    }

    /**
     * Tests that xmlapi_query throws an exception when no auth is set.
     * Either a password or hash must be configured for authentication.
     */
    public function testXmlapiQueryThrowsExceptionWithoutAuth(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $api->set_user('root');
        $this->expectException(\Exception::class);
        $api->xmlapi_query('listaccts');
    }

    /**
     * Tests that the xmlapi class has all expected public methods using reflection.
     * This verifies the complete API surface of the class.
     */
    public function testXmlapiHasExpectedPublicMethods(): void
    {
        $reflection = new ReflectionClass(\xmlapi::class);
        $expectedMethods = [
            // Accessor methods
            'get_debug', 'set_debug',
            'get_host', 'set_host',
            'get_port', 'set_port',
            'get_protocol', 'set_protocol',
            'get_output', 'set_output',
            'get_auth_type', 'set_auth_type',
            'set_password', 'set_hash',
            'get_user', 'set_user',
            'hash_auth', 'password_auth',
            'return_xml', 'return_object',
            'set_http_client', 'get_http_client',
            // Query methods
            'xmlapi_query', 'api1_query', 'api2_query',
            // Account functions
            'applist', 'createacct', 'passwd', 'limitbw',
            'listaccts', 'modifyacct', 'editquota',
            'accountsummary', 'suspendacct', 'listsuspended',
            'removeacct', 'unsuspendacct', 'changepackage',
            'myprivs', 'domainuserdata', 'setsiteip',
            // DNS functions
            'adddns', 'addzonerecord', 'editzonerecord',
            'getzonerecord', 'killdns', 'listzones',
            'dumpzone', 'lookupnsip', 'removezonerecord', 'resetzone',
            // Package functions
            'addpkg', 'killpkg', 'editpkg', 'listpkgs',
            // Reseller functions
            'setupreseller', 'saveacllist', 'listacls',
            'listresellers', 'resellerstats', 'unsetupreseller',
            'setacls', 'terminatereseller', 'setresellerips',
            'setresellerlimits', 'setresellermainip',
            'setresellerpackagelimits', 'suspendreseller',
            'unsuspendreseller', 'acctcounts', 'setresellernameservers',
            // Server info
            'gethostname', 'version', 'loadavg', 'getlanglist',
            // Server admin
            'reboot', 'addip', 'delip', 'listips',
            'sethostname', 'setresolvers', 'showbw',
            'nvset', 'nvget',
            // Service functions
            'restartsrv', 'servicestatus', 'configureservice',
            // SSL functions
            'fetchsslinfo', 'generatessl', 'installssl', 'listcrts',
            // cPanel API1/API2 functions
            'addpop', 'park', 'unpark',
            'getdiskusage', 'listftpwithdisk', 'listftp',
            'listparkeddomains', 'listaddondomains', 'stat',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "xmlapi class is missing expected method: $method"
            );
        }
    }

    /**
     * Tests that the xmlapi class has private properties with correct default values.
     * Uses reflection to verify internal state without calling methods.
     */
    public function testXmlapiPrivatePropertyDefaults(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $reflection = new ReflectionClass($api);

        $props = [
            'debug' => false,
            'host' => '127.0.0.1',
            'port' => '2087',
            'protocol' => 'https',
            'output' => 'simplexml',
            'auth_type' => null,
            'auth' => null,
            'user' => null,
            'http_client' => 'curl',
        ];

        foreach ($props as $name => $expected) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $actual = $prop->getValue($api);
            $this->assertEquals(
                $expected,
                $actual,
                "Property '$name' expected " . var_export($expected, true) . " but got " . var_export($actual, true)
            );
        }
    }

    /**
     * Tests that set_port throws an exception for invalid port ranges.
     * Ports must be between 1 and 65535.
     */
    public function testSetPortThrowsExceptionForInvalidPort(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_port(0);
    }

    /**
     * Tests that set_port throws an exception for negative port numbers.
     * Negative ports are not valid.
     */
    public function testSetPortThrowsExceptionForNegativePort(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_port(-1);
    }

    /**
     * Tests that set_port throws an exception for port numbers exceeding 65535.
     * Valid port range is 1-65535.
     */
    public function testSetPortThrowsExceptionForPortAboveMax(): void
    {
        $api = new \xmlapi('127.0.0.1');
        $this->expectException(\Exception::class);
        $api->set_port(65536);
    }

    /**
     * Tests that the xmlapi class is in the global namespace.
     * The class does not use a namespace declaration.
     */
    public function testXmlapiIsInGlobalNamespace(): void
    {
        $reflection = new ReflectionClass(\xmlapi::class);
        $this->assertSame('', $reflection->getNamespaceName());
    }

    /**
     * Tests that the constructor only accepts usernames with fewer than 9 characters.
     * WHM usernames are limited in length, so longer usernames are ignored.
     */
    public function testConstructorIgnoresLongUsernames(): void
    {
        $api = new \xmlapi('127.0.0.1', 'verylongusername', 'pass');
        $this->assertNull($api->get_user());
    }

    /**
     * Tests that the constructor accepts short usernames (under 9 chars).
     * Valid WHM usernames are stored by the constructor.
     */
    public function testConstructorAcceptsShortUsernames(): void
    {
        $api = new \xmlapi('127.0.0.1', 'root', 'pass');
        $this->assertSame('root', $api->get_user());
    }

    /**
     * Tests that the xmlapi source file does not declare a namespace.
     * The class is intentionally in the global namespace.
     */
    public function testXmlapiFileHasNoNamespace(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/xmlapi.php');
        $this->assertStringNotContainsString('namespace ', $source);
    }

    /**
     * Tests that the xmlapi class has private query methods.
     * curl_query and fopen_query are private implementation details.
     */
    public function testXmlapiHasPrivateQueryMethods(): void
    {
        $reflection = new ReflectionClass(\xmlapi::class);

        $curlQuery = $reflection->getMethod('curl_query');
        $this->assertTrue($curlQuery->isPrivate());

        $fopenQuery = $reflection->getMethod('fopen_query');
        $this->assertTrue($fopenQuery->isPrivate());
    }

    /**
     * Tests that the xmlapi class has a private unserialize_xml method.
     * This is an internal helper for converting XML to arrays.
     */
    public function testXmlapiHasPrivateUnserializeXmlMethod(): void
    {
        $reflection = new ReflectionClass(\xmlapi::class);
        $method = $reflection->getMethod('unserialize_xml');
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Tests that the constructor accepts hostname strings as the host parameter.
     * Both IP addresses and hostnames are valid host values.
     */
    public function testConstructorAcceptsHostname(): void
    {
        $api = new \xmlapi('server.example.com');
        $this->assertSame('server.example.com', $api->get_host());
    }

    /**
     * Tests that all public methods that wrap xmlapi_query exist and are public.
     * This verifies the wrapper methods are accessible to consumers.
     */
    public function testWrapperMethodsArePublic(): void
    {
        $reflection = new ReflectionClass(\xmlapi::class);

        $wrapperMethods = [
            'applist', 'listaccts', 'listsuspended',
            'listacls', 'listresellers', 'listzones',
            'listpkgs', 'listips', 'listcrts',
            'gethostname', 'version', 'loadavg', 'getlanglist',
            'myprivs',
        ];

        foreach ($wrapperMethods as $method) {
            $m = $reflection->getMethod($method);
            $this->assertTrue($m->isPublic(), "Method $method should be public");
        }
    }
}
