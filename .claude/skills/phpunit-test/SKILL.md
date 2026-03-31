---
name: phpunit-test
description: Creates PHPUnit test classes in tests/ following the project's reflection-based and source-assertion patterns. Uses PHPUnit 9 TestCase, bootstrap from tests/bootstrap.php. Use when user says 'add test', 'write test', 'test coverage', or creates new src/ files. Do NOT use for integration tests requiring live cPanel servers.
---
# PHPUnit Test Creation

## Critical

- **Never make live API calls** in tests. This project tests class structure, properties, method signatures, and source file contents — not runtime behavior against cPanel servers.
- **Namespace**: All test classes MUST use `namespace Detain\MyAdminCpanel\Tests;` — matching the `autoload-dev` PSR-4 mapping in `composer.json`.
- **PHPUnit version**: 9.x. Do NOT use PHPUnit 10+ attributes (`#[Test]`). Use PHPUnit 9 method-naming conventions (`testMethodName`).
- **File naming**: Test files MUST end with `Test.php` and live in `tests/`. The `phpunit.xml.dist` discovers tests by `<directory suffix="Test.php">tests</directory>`.
- **Bootstrap**: Tests rely on `tests/bootstrap.php` which loads Composer autoload and manually requires `src/xmlapi.php` for the global-namespace `xmlapi` class. Do NOT add separate `require` statements in test files for autoloaded classes.

## Instructions

### Step 1: Identify the source class to test

Read the source file in `src/` to understand:
- Its namespace (namespaced under `Detain\MyAdminCpanel\` or global namespace like `xmlapi`)
- Public/static methods, properties, constructor parameters
- Whether it's a class or procedural file (like `src/api.php`)

Verify the source file exists at `src/{ClassName}.php` before proceeding.

### Step 2: Create the test file

Create `tests/{ClassName}Test.php` following this exact template:

```php
<?php

namespace Detain\MyAdminCpanel\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class {ClassName}Test extends TestCase
{
    /**
     * Tests that the source file exists at the expected path.
     */
    public function testFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../src/{FileName}.php');
    }

    /**
     * Tests that the class exists and is loadable.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\Detain\MyAdminCpanel\{ClassName}::class));
    }

    /**
     * Tests that the class can be instantiated.
     */
    public function testCanBeInstantiated(): void
    {
        $instance = new \Detain\MyAdminCpanel\{ClassName}();
        $this->assertInstanceOf(\Detain\MyAdminCpanel\{ClassName}::class, $instance);
    }
}
```

Verify: The file uses `namespace Detain\MyAdminCpanel\Tests;` and `use PHPUnit\Framework\TestCase;`.

### Step 3: Add reflection-based structural tests

The project uses `ReflectionClass` extensively to verify class structure without executing business logic. Add these test categories:

**3a. Static property assertions** (for Plugin-style classes with static `$name`, `$description`, etc.):
```php
public function testHasExpectedStaticProperties(): void
{
    $reflection = new ReflectionClass(\Detain\MyAdminCpanel\{ClassName}::class);
    $staticProperties = $reflection->getStaticProperties();

    $expectedProperties = ['name', 'description', 'module', 'type'];
    foreach ($expectedProperties as $prop) {
        $this->assertArrayHasKey($prop, $staticProperties, "Missing static property: $prop");
    }
}
```

**3b. Method existence and visibility** (verify all public methods exist and are correctly scoped):
```php
public function testHasExpectedPublicMethods(): void
{
    $reflection = new ReflectionClass(\Detain\MyAdminCpanel\{ClassName}::class);
    $expectedMethods = ['methodA', 'methodB'];

    foreach ($expectedMethods as $method) {
        $this->assertTrue(
            $reflection->hasMethod($method),
            "Class is missing expected method: $method"
        );
    }
}
```

**3c. Method signature validation** (verify methods are public/static and accept correct parameter types):
```php
public function testMethodsArePublicAndStatic(): void
{
    $reflection = new ReflectionClass(\Detain\MyAdminCpanel\{ClassName}::class);
    $method = $reflection->getMethod('methodName');

    $this->assertTrue($method->isPublic(), "Method should be public");
    $this->assertTrue($method->isStatic(), "Method should be static");
}
```

**3d. Private property inspection via reflection** (for testing defaults without public getters):
```php
public function testPrivatePropertyDefaults(): void
{
    $instance = new \{ClassName}('127.0.0.1');
    $reflection = new ReflectionClass($instance);

    $prop = $reflection->getProperty('propertyName');
    $prop->setAccessible(true);
    $this->assertEquals('expectedDefault', $prop->getValue($instance));
}
```

Verify: Each reflection test uses fully qualified class references with leading backslash.

### Step 4: Add source-content assertion tests (for procedural files)

For procedural files like `src/api.php` that define functions rather than classes, test by reading the source file and asserting content:

```php
public function testFileDefinesExpectedFunction(): void
{
    $source = file_get_contents(__DIR__ . '/../src/api.php');
    $this->assertStringContainsString('function api_auto_cpanel_login', $source);
}

public function testFileContainsCorrectNamespace(): void
{
    $source = file_get_contents(__DIR__ . '/../src/{FileName}.php');
    $this->assertStringContainsString('namespace Detain\MyAdminCpanel;', $source);
}

public function testFileStartsWithPhpTag(): void
{
    $source = file_get_contents(__DIR__ . '/../src/{FileName}.php');
    $this->assertStringStartsWith('<?php', $source);
}
```

Verify: File paths use `__DIR__ . '/../src/'` relative pattern, never absolute paths.

### Step 5: Add getter/setter round-trip tests (for classes with accessors)

For classes like `xmlapi` with getter/setter pairs:

```php
public function testSetterChangesValue(): void
{
    $api = new \xmlapi('127.0.0.1');
    $api->set_host('192.168.1.1');
    $this->assertSame('192.168.1.1', $api->get_host());
}

public function testSetterThrowsExceptionForInvalidValue(): void
{
    $api = new \xmlapi('127.0.0.1');
    $this->expectException(\Exception::class);
    $api->set_protocol('ftp');
}
```

Verify: Use `assertSame` (strict) for string comparisons, `assertEquals` for loosely-typed values.

### Step 6: Add namespace verification test

```php
public function testClassNamespace(): void
{
    $reflection = new ReflectionClass(\Detain\MyAdminCpanel\{ClassName}::class);
    $this->assertSame('Detain\MyAdminCpanel', $reflection->getNamespaceName());
}
```

For global-namespace classes like `xmlapi`:
```php
public function testIsInGlobalNamespace(): void
{
    $reflection = new ReflectionClass(\xmlapi::class);
    $this->assertSame('', $reflection->getNamespaceName());
}
```

### Step 7: Run tests and verify

```bash
vendor/bin/phpunit tests/{ClassName}Test.php
```

Verify: All tests pass. Then run the full suite:
```bash
vendor/bin/phpunit
```

Verify: No existing tests broke.

## Examples

### Example: User says "add tests for Plugin.php"

**Actions taken:**
1. Read `src/Plugin.php` to catalog static properties (`$name`, `$description`, `$help`, `$module`, `$type`), methods (`getHooks`, `getActivate`, etc.), and hook registration pattern.
2. Create `tests/PluginTest.php` with namespace `Detain\MyAdminCpanel\Tests`.
3. Add file-existence test: `assertFileExists(__DIR__ . '/../src/Plugin.php')`
4. Add class-existence test: `assertTrue(class_exists(\Detain\MyAdminCpanel\Plugin::class))`
5. Add instantiation test with `assertInstanceOf`
6. Add static property tests for each of `$name`, `$description`, `$help`, `$module`, `$type` using `assertSame` for exact values, `assertIsString`/`assertNotEmpty` for dynamic content.
7. Add `getHooks()` tests: returns array, contains expected keys (`webhosting.settings`, `webhosting.activate`, etc.), values are callable arrays of `[Plugin::class, 'methodName']`.
8. Add reflection tests: hook methods exist on class, are public and static, accept `GenericEvent` parameter.
9. Add namespace test: `assertSame('Detain\MyAdminCpanel', $reflection->getNamespaceName())`
10. Add source-content test: file contains correct namespace declaration and GenericEvent import.

**Result:** `tests/PluginTest.php` with ~20 test methods covering structure, properties, hooks, method signatures, and source content — zero live API calls.

### Example: User says "write tests for a new procedural file src/helpers.php"

**Actions taken:**
1. Read `src/helpers.php` to find function names and key implementation details.
2. Create `tests/HelpersTest.php` following the `ApiTest.php` pattern — source-content assertions only.
3. Add tests: file exists, starts with `<?php`, defines each expected function (`assertStringContainsString('function helper_name', $source)`), contains expected string literals, uses expected PHP functions.

**Result:** `tests/HelpersTest.php` with source-content assertions, no function execution (avoiding dependency on MyAdmin runtime).

## Common Issues

### "Class Detain\MyAdminCpanel\Tests\XyzTest not found"
1. Verify the test file has `namespace Detain\MyAdminCpanel\Tests;` at the top.
2. Verify `composer.json` has `autoload-dev` mapping: `"Detain\\MyAdminCpanel\\Tests\\": "tests/"`.
3. Run `composer dump-autoload` to regenerate the autoloader.

### "Class xmlapi not found" in tests
The `xmlapi` class is in the global namespace and not autoloaded via PSR-4. The bootstrap at `tests/bootstrap.php` handles this with `require_once __DIR__ . '/../src/xmlapi.php'`. If testing a new global-namespace class, add a similar require to the bootstrap:
```php
if (!class_exists('NewClassName')) {
    require_once __DIR__ . '/../src/newclass.php';
}
```

### Tests pass locally but fail in CI with "Risky test" warnings
`phpunit.xml.dist` has `failOnRisky="true"`. PHPUnit marks tests as risky if they have no assertions. Every test method MUST contain at least one `$this->assert*()` call.

### "Failed asserting that two strings are identical" on property tests
Use `assertSame` (strict `===`) for string and type-sensitive comparisons. Use `assertEquals` only when comparing loosely-typed values (e.g., int port from a string property). Check the source for the exact value — copy-paste string literals rather than typing them.

### ReflectionProperty::setAccessible() not working
In PHP 8.1+, `setAccessible(true)` is a no-op (all properties are accessible via reflection). For PHP 7.4–8.0, you must call `$prop->setAccessible(true)` before `getValue()`. This project requires PHP >= 5.0 but tests run on modern PHP, so always include the `setAccessible(true)` call for compatibility.

### Adding a new test file but `vendor/bin/phpunit` doesn't pick it up
The file must:
1. Be in the `tests/` directory (not a subdirectory unless configured)
2. Have filename ending in `Test.php` (case-sensitive)
3. Contain a class extending `TestCase` with method names starting with `test`