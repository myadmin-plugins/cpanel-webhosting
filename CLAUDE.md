# MyAdmin cPanel Webhosting Plugin

Composer package `detain/myadmin-cpanel-webhosting` — MyAdmin plugin providing cPanel/WHM integration for webhosting account lifecycle management.

## Commands

```bash
composer install                          # install dependencies
vendor/bin/phpunit                        # run all tests
vendor/bin/phpunit --coverage-text        # run tests with coverage
vendor/bin/phpunit tests/PluginTest.php   # run single test file
```

## Architecture

**Namespace**: `Detain\MyAdminCpanel\` → `src/` · **Tests**: `Detain\MyAdminCpanel\Tests\` → `tests/`

**Core files**:
- `src/Plugin.php` — main plugin class, registers Symfony EventDispatcher hooks via `getHooks()`
- `src/xmlapi.php` — cPanel/WHM XML-API client class (global namespace `xmlapi`), supports curl/fopen
- `src/api.php` — procedural API: `api_auto_cpanel_login($id)` for cPanel session token login
- `composer.json` — PSR-4 autoload, requires `symfony/event-dispatcher` ^5.0, `ext-soap`, PHPUnit 9 dev
- `phpunit.xml.dist` — test config, bootstrap `tests/bootstrap.php`, coverage on `src/`

**Plugin hooks** (`Plugin::getHooks()`): `webhosting.settings` · `webhosting.activate` · `webhosting.reactivate` · `webhosting.deactivate` · `webhosting.terminate` · `api.register` · `function.requirements` · `ui.menu`

**Plugin lifecycle** in `src/Plugin.php`:
- `getActivate()` — creates cPanel account via `xmlapi::xmlapi_query('createacct', $options)`, handles reseller setup with `setupreseller()`, `saveacllist()`, `setacls()`, `setresellerlimits()`, optional Softaculous script install
- `getReactivate()` — calls `unsuspendacct()` or `unsuspendreseller()`
- `getDeactivate()` — calls `suspendacct()` or `suspendreseller()`
- `getTerminate()` — calls `removeacct()` or `terminatereseller()`, cleans up DNS via `dumpzone()`/`killdns()`
- `getChangeIp()` — uses `Detain\Cpanel\Cpanel` to call `editIp()`

**XML-API client** (`src/xmlapi.php`):
- Constructor: `new xmlapi($host, $user?, $pass?)` — default port `2087`, protocol `https`, output `simplexml`
- Auth: `hash_auth($user, $hash)` or `password_auth($user, $pass)` · `set_output('json'|'xml'|'simplexml'|'array')`
- HTTP: `set_http_client('curl'|'fopen')` · query methods: `xmlapi_query()`, `api1_query()`, `api2_query()`
- Account ops: `createacct()`, `removeacct()`, `suspendacct()`, `unsuspendacct()`, `listaccts()`, `accountsummary()`
- DNS: `adddns()`, `killdns()`, `dumpzone()`, `listzones()`, `resetzone()`, `addzonerecord()`, `editzonerecord()`
- Packages: `addpkg()`, `killpkg()`, `editpkg()`, `listpkgs()`
- Reseller: `setupreseller()`, `listresellers()`, `terminatereseller()`, `setacls()`, `saveacllist()`
- SSL: `fetchsslinfo()`, `generatessl()`, `installssl()`, `listcrts()`
- Server: `gethostname()`, `version()`, `loadavg()`, `listips()`, `showbw()`

**CLI scripts** (`bin/`): 38 scripts wrapping `xmlapi` methods — all follow identical pattern:
1. `require_once __DIR__.'/../../../../include/functions.inc.php'`
2. `get_module_db('webhosting')` to query `website_masters` table
3. `function_requirements('whm_api')` then `whm_api($db->Record['website_id'])`
4. Switch on `website_type` (`WEB_CPANEL`, `WEB_PLESK`, `WEB_VESTA`, `WEB_PPA`)
5. Call corresponding `xmlapi` method, `json_decode()` and `print_r()` the response

Examples: `bin/listaccts.php <hostname>` · `bin/suspendacct.php <hostname> <username>` · `bin/dumpzone.php <hostname> <domain>`

## Tests

- `tests/bootstrap.php` — loads autoloader, requires `src/xmlapi.php` if `xmlapi` class missing
- `tests/PluginTest.php` — reflection-based tests on `Plugin` class: hooks, methods, static properties, `GenericEvent` params
- `tests/XmlapiTest.php` — unit tests for `xmlapi` class: constructor, getters/setters, port/protocol/output validation, auth methods, exception cases
- `tests/ApiTest.php` — source-level assertions on `src/api.php`: checks function signature, curl usage, error handling

## Conventions

- DB access: `get_module_db('webhosting')` → `$db->query()` · `$db->next_record(MYSQL_ASSOC)` · `$db->Record`
- Logging: `myadmin_log(self::$module, $level, $msg, __LINE__, __FILE__, self::$module, $id)`
- Request logging: `request_log(self::$module, $custid, __FUNCTION__, 'cpanel', $action, $params, $response, $id)`
- Service helpers: `get_service($id, $module)` · `get_module_settings($module)` · `get_service_master($serverId, $module)`
- WHM init pattern: `new \xmlapi($ip)` → `set_port('2087')` → `set_protocol('https')` → `set_output('json')` → `set_auth_type('hash')` → `set_user('root')` → `set_hash($hash)`
- Event handling: check `$event['category']` or `$event['type']` against `get_service_define('WEB_CPANEL')`, call `$event->stopPropagation()` after processing
- Commit messages: lowercase, descriptive

## CI & Analysis

- `.scrutinizer.yml` — PHP 7.0 build, PHPUnit with coverage, static analysis checks
- `.travis.yml` — PHP 5.4-7.1 matrix, CodeClimate/Codecov/Coveralls integration
- `.codeclimate.yml` — duplication/phpmd engines, excludes `tests/`
- `.bettercodehub.yml` — PHP language config

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
