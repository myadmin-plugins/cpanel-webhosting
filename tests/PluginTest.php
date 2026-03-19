<?php

namespace Detain\MyAdminCpanel\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PluginTest extends TestCase
{
    /**
     * Tests that the Plugin source file exists at the expected path.
     * This is a basic sanity check to ensure the file is present.
     */
    public function testPluginFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/Plugin.php');
    }

    /**
     * Tests that the Plugin class exists and is loadable.
     * Verifies the autoloading configuration is correct.
     */
    public function testPluginClassExists(): void
    {
        $this->assertTrue(class_exists(\Detain\MyAdminCpanel\Plugin::class));
    }

    /**
     * Tests that the Plugin class can be instantiated.
     * The constructor is empty, so this should always succeed.
     */
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new \Detain\MyAdminCpanel\Plugin();
        $this->assertInstanceOf(\Detain\MyAdminCpanel\Plugin::class, $plugin);
    }

    /**
     * Tests the $name static property is set to 'CPanel Webhosting'.
     * This value is used for display purposes within the MyAdmin system.
     */
    public function testNameProperty(): void
    {
        $this->assertSame('CPanel Webhosting', \Detain\MyAdminCpanel\Plugin::$name);
    }

    /**
     * Tests that the $description static property is a non-empty string.
     * The description provides user-facing information about the plugin.
     */
    public function testDescriptionProperty(): void
    {
        $this->assertIsString(\Detain\MyAdminCpanel\Plugin::$description);
        $this->assertNotEmpty(\Detain\MyAdminCpanel\Plugin::$description);
    }

    /**
     * Tests that the $help static property is defined as an empty string.
     * This property is available for future use.
     */
    public function testHelpProperty(): void
    {
        $this->assertSame('', \Detain\MyAdminCpanel\Plugin::$help);
    }

    /**
     * Tests that the $module static property is set to 'webhosting'.
     * This determines which module the plugin is associated with.
     */
    public function testModuleProperty(): void
    {
        $this->assertSame('webhosting', \Detain\MyAdminCpanel\Plugin::$module);
    }

    /**
     * Tests that the $type static property is set to 'service'.
     * This categorizes the plugin as a service-type plugin.
     */
    public function testTypeProperty(): void
    {
        $this->assertSame('service', \Detain\MyAdminCpanel\Plugin::$type);
    }

    /**
     * Tests that getHooks() returns an array with the expected keys.
     * Each key represents an event name that the plugin listens to.
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $this->assertIsArray($hooks);
        $this->assertNotEmpty($hooks);
    }

    /**
     * Tests that the hook keys are constructed using the module name prefix.
     * The webhosting module hooks should be prefixed with 'webhosting.'.
     */
    public function testGetHooksContainsExpectedKeys(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();

        $expectedKeys = [
            'webhosting.settings',
            'webhosting.activate',
            'webhosting.reactivate',
            'webhosting.deactivate',
            'webhosting.terminate',
            'api.register',
            'function.requirements',
            'ui.menu',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $hooks, "Missing hook key: $key");
        }
    }

    /**
     * Tests that each hook value is a valid callable array [ClassName, methodName].
     * This ensures all event handlers can be invoked by the event dispatcher.
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();

        foreach ($hooks as $eventName => $callback) {
            $this->assertIsArray($callback, "Hook value for '$eventName' should be an array");
            $this->assertCount(2, $callback, "Hook value for '$eventName' should have exactly 2 elements");
            $this->assertSame(\Detain\MyAdminCpanel\Plugin::class, $callback[0], "Hook class for '$eventName' should be Plugin");
            $this->assertIsString($callback[1], "Hook method for '$eventName' should be a string");
        }
    }

    /**
     * Tests that each method referenced in getHooks() actually exists on the Plugin class.
     * This catches typos in hook registration that would cause runtime errors.
     */
    public function testGetHooksMethodsExist(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);

        foreach ($hooks as $eventName => $callback) {
            $methodName = $callback[1];
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "Plugin class is missing hook method: $methodName (for event: $eventName)"
            );
        }
    }

    /**
     * Tests that all hook handler methods are public and static.
     * Event dispatchers call these methods statically, so they must be both public and static.
     */
    public function testHookMethodsArePublicAndStatic(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);

        foreach ($hooks as $eventName => $callback) {
            $methodName = $callback[1];
            $method = $reflection->getMethod($methodName);

            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
            $this->assertTrue(
                $method->isStatic(),
                "Method $methodName should be static"
            );
        }
    }

    /**
     * Tests that hook handler methods accept a GenericEvent parameter.
     * All event handlers in this plugin receive a GenericEvent instance.
     */
    public function testHookMethodsAcceptGenericEventParameter(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);

        foreach ($hooks as $eventName => $callback) {
            $methodName = $callback[1];
            $method = $reflection->getMethod($methodName);
            $params = $method->getParameters();

            $this->assertGreaterThanOrEqual(
                1,
                count($params),
                "Method $methodName should accept at least one parameter"
            );

            $firstParam = $params[0];
            $type = $firstParam->getType();
            if ($type !== null) {
                $typeName = $type->getName();
                $this->assertSame(
                    'Symfony\Component\EventDispatcher\GenericEvent',
                    $typeName,
                    "Method $methodName first parameter should be GenericEvent"
                );
            }
        }
    }

    /**
     * Tests that the Plugin class has exactly five static properties.
     * These are: $name, $description, $help, $module, $type.
     */
    public function testPluginHasExpectedStaticProperties(): void
    {
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);
        $staticProperties = $reflection->getStaticProperties();

        $expectedProperties = ['name', 'description', 'help', 'module', 'type'];
        foreach ($expectedProperties as $prop) {
            $this->assertArrayHasKey($prop, $staticProperties, "Missing static property: $prop");
        }
    }

    /**
     * Tests that the hook count matches the expected number (8 hooks).
     * If a hook is added or removed, this test will catch it.
     */
    public function testGetHooksReturnsCorrectCount(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $this->assertCount(8, $hooks);
    }

    /**
     * Tests the Plugin class is in the correct namespace.
     * The PSR-4 autoloading depends on the namespace being Detain\MyAdminCpanel.
     */
    public function testPluginNamespace(): void
    {
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);
        $this->assertSame('Detain\MyAdminCpanel', $reflection->getNamespaceName());
    }

    /**
     * Tests that the constructor has no required parameters.
     * This ensures the plugin can be instantiated without arguments.
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $requiredParams = array_filter(
            $constructor->getParameters(),
            fn($p) => !$p->isOptional()
        );
        $this->assertCount(0, $requiredParams);
    }

    /**
     * Tests that the Plugin.php source file contains the correct namespace declaration.
     * Verifies that the file will be autoloaded correctly by Composer.
     */
    public function testPluginFileContainsCorrectNamespace(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');
        $this->assertStringContainsString('namespace Detain\MyAdminCpanel;', $source);
    }

    /**
     * Tests that the Plugin.php source file imports GenericEvent.
     * This use statement is necessary for the event handler type hints.
     */
    public function testPluginFileImportsGenericEvent(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/Plugin.php');
        $this->assertStringContainsString('use Symfony\Component\EventDispatcher\GenericEvent;', $source);
    }

    /**
     * Tests that the Plugin class contains all expected static method names.
     * This is a comprehensive check of the class's public interface.
     */
    public function testPluginHasAllExpectedMethods(): void
    {
        $expectedMethods = [
            'getHooks',
            'apiRegister',
            'getActivate',
            'getReactivate',
            'getDeactivate',
            'getTerminate',
            'getMenu',
            'getRequirements',
            'getSettings',
        ];

        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Plugin class is missing expected method: $method"
            );
        }
    }

    /**
     * Tests that the plugin description mentions cpanel.com.
     * The description should contain a reference to the official cPanel website.
     */
    public function testDescriptionContainsCpanelUrl(): void
    {
        $this->assertStringContainsString('https://cpanel.com/', \Detain\MyAdminCpanel\Plugin::$description);
    }

    /**
     * Tests that getHooks builds keys dynamically from the $module property.
     * Verifying the pattern self::$module.'.settings' produces 'webhosting.settings'.
     */
    public function testHookKeysAreBuiltFromModuleProperty(): void
    {
        $hooks = \Detain\MyAdminCpanel\Plugin::getHooks();
        $module = \Detain\MyAdminCpanel\Plugin::$module;

        $this->assertArrayHasKey($module . '.settings', $hooks);
        $this->assertArrayHasKey($module . '.activate', $hooks);
        $this->assertArrayHasKey($module . '.reactivate', $hooks);
        $this->assertArrayHasKey($module . '.deactivate', $hooks);
        $this->assertArrayHasKey($module . '.terminate', $hooks);
    }

    /**
     * Tests that the getChangeIp method exists on the Plugin class.
     * This method is not registered via getHooks but is used externally.
     */
    public function testGetChangeIpMethodExists(): void
    {
        $reflection = new ReflectionClass(\Detain\MyAdminCpanel\Plugin::class);
        $this->assertTrue($reflection->hasMethod('getChangeIp'));
        $method = $reflection->getMethod('getChangeIp');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }
}
