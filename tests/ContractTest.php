<?php

declare(strict_types=1);

namespace Detain\MyAdminCpanel\Tests;

use Detain\MyAdminCpanel\Plugin;
use MyAdmin\Plugins\Testing\Contract\TierA5HooksAreIdempotent;
use MyAdmin\Plugins\Testing\ServicePluginTestCase;

/**
 * Shared contract assertions for this plugin, plus the identity pin the shared
 * harness cannot provide.
 *
 * ---------------------------------------------------------------------------------
 * WHY THIS FILE IS ADDITIVE
 * ---------------------------------------------------------------------------------
 * This is a new file, not a replacement. Every pre-existing test in this package is
 * kept exactly as it was: the catalogue below runs *alongside* them, so the package
 * gains the 18 fleet-wide contract inspectors without giving up a single assertion it
 * already had. Some coverage is therefore duplicated -- deliberately, because losing
 * an assertion nobody has re-read is the more expensive mistake.
 *
 * ---------------------------------------------------------------------------------
 * WHAT THE CATALOGUE ADDS
 * ---------------------------------------------------------------------------------
 * {@see ServicePluginTestCase} executes this plugin rather than reading it: it primes
 * the bare constants the class body references, resolves every requirement path it
 * registers against the filesystem, checks each hook key is one core actually
 * dispatches, and runs getSettings()/getMenu()/apiRegister() for real. A dangling
 * registration or an undispatched hook key fails here even though it is invisible to
 * an assertion that only reads the registration table. Because this is a `type=service` plugin, the base class is
 * {@see ServicePluginTestCase}: the same eighteen inspectors, plus the assertions that drive
 * getActivate()/getDeactivate()/getChangeIp()/getQueue() for a service type this plugin owns
 * and again for one it does not, checking it acts on the first and stays inert for the second.
 *
 * ---------------------------------------------------------------------------------
 * WHAT THE IDENTITY PIN ADDS
 * ---------------------------------------------------------------------------------
 * Every catalogue assertion is conditional on the registration existing, so an
 * emptied getHooks() would leave the shared suite green. The pin below is the part
 * only this repo can state: which hooks this plugin is supposed to register, and that
 * $type still selects the assertions intended for it.
 *
 * ---------------------------------------------------------------------------------
 * WHY THIS CLASS RUNS IN ITS OWN PROCESS
 * ---------------------------------------------------------------------------------
 * Inspecting a plugin defines real constants and calls register_module(). PHP cannot
 * undefine a constant and register_module() has no inverse, so this class cannot be
 * unwound once it has run: whatever executes after it in the same process sees primed
 * constants and a registered module it did not ask for. That is why the fleet matrix
 * generator spawns one process per package, and it is why this class is isolated here
 * -- without it, adding this file would change the outcome of the tests that were
 * already in this repo, which is precisely what an additive conversion must not do.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ContractTest extends ServicePluginTestCase
{
    /**
     * The class under contract.
     *
     * @return string
     */
    protected function pluginClass()
    {
        return Plugin::class;
    }

    /**
     * Pins this plugin's identity and the shape of its hook table.
     *
     * @return void
     */
    public function testRegistersItsIdentityAndHooks(): void
    {
        // Prime FIRST, before anything touches the plugin class. A static property
        // initializer may itself reference a bare constant -- $settings holding
        // REPEAT_BILLING_METHOD => PRORATE_BILLING is the common shape -- and that is
        // evaluated when the class loads, so even reading ::service fatals on an unprimed
        // class. Priming before the first mention is what keeps this pin readable.
        $this->primeConstants();

        $this->assertSame(
            'service',
            Plugin::$type,
            'changing $type silently changes which contract assertions apply'
        );

        $this->assertSame(
            'webhosting',
            Plugin::$module,
            'changing $module detaches this plugin from the webhosting events it handles'
        );

        // Read the table the way every inspector reads it. Calling getHooks() directly here
        // would be a second, independent answer to "can this plugin's hook table be
        // evaluated?" -- and a plugin whose getHooks() body references a bare constant
        // (PRORATE_BILLING and friends) throws for a direct caller while the inspectors
        // handle it. A-5 owns that question; this pin consumes its answer.
        $hooks = TierA5HooksAreIdempotent::hookTable($this->contractSubject());

        $this->assertNotNull(
            $hooks,
            'getHooks() could not be evaluated at all -- assertion A-5 reports the root cause'
        );

        $this->assertSame(
            [
            'webhosting.settings',
            'webhosting.activate',
            'webhosting.reactivate',
            'webhosting.deactivate',
            'webhosting.terminate',
            'api.register',
            'function.requirements',
            'ui.menu',
        ],
            array_keys($hooks),
            'the hook table changed shape -- a key was added, removed or renamed'
        );

        foreach ($hooks as $key => $handler) {
            $this->assertIsCallable($handler, $key.' no longer resolves to anything callable');
        }
    }
}
