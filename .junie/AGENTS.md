# Project Guidelines for libspech

This file contains project-specific instructions for advanced development and debugging.

## Build/Configuration Instructions
- Requires a custom PHP 8.4+ binary built with Swoole, bcg729, opus, and psampler extensions (from https://github.com/berzersks/pcg729/releases).
- Install via: `wget https://github.com/spechshop/pcg729/releases/download/PCG729/php && sudo mv php /usr/local/bin/php && sudo chmod +x /usr/local/bin/php`.
- Always set `ini_set('memory_limit', '1024M');` (or higher) before including autoloader for audio processing.
- .env file (loaded automatically by autoloader.php) must contain SIP_USERNAME, SIP_PASSWORD, SIP_HOST for integration work.
- No Composer; pure include-based autoloading via plugins/configInterface.json and plugins/autoloader.php.
- Project root must be cwd when running scripts.

## Testing Information
- **No automated test framework** (PHPUnit etc.) is present; README explicitly states "Atualmente não há testes automatizados no repositório".
- Existing manual tests: `testBilling.php`, `testAudioCache.php`, `testSecureSleep.php`, and various `extra/*.php` validation scripts.
- **Running tests**: Execute directly with the custom `php` binary, e.g. `php testBilling.php`. Always run from project root.
- **Adding new tests**: Create `test_*.php` scripts that:
  1. Call `ini_set('memory_limit', '256M');`
  2. `require 'plugins/autoloader.php';`
  3. Use `class_exists` or instantiate non-network classes for unit checks.
  4. Exit with 0 on success, non-zero on failure; print "PASS"/"FAIL".
- Demo process (verified working):
  - Created `test_demo_simple.php` (autoloader + class checks for cli/trunkController).
  - Ran `php test_demo_simple.php` → exit 0, "ALL PASS".
  - Deleted after verification.
- For SIP integration tests, configure .env and use `example.php` or trunkController flows; expect real SIP server.

## Additional Development Information
- **Namespaces**: All classes under `libspech\*` (e.g. `libspech\Sip\trunkController`, `libspech\Rtp\MediaChannel`).
- **Code style**: Heavy use of public properties (not just methods); Portuguese comments in many files; mixed English/PT logs; K&R-style braces; no strict types on many params. Follow patterns in MediaChannel.php and trunkController.php.
- **Key extensions/classes**: `bcg729Channel`, `opusChannel`, `psampler` stubs in `stubs/`. Use `Swoole\Coroutine` everywhere for async.
- **Stubs folder**: Contains PHP stub declarations (bcg729Channel.php, opusChannel.php, LPCM.php, psampler/functions.php and subdirs bcg729/, opus/, psampler/ for Swoole coroutine/http overrides) to support IDE autocompletion and static analysis. Generated via `stubGen.php` (functions: generateFunctionStubs, generateClassStubs, etc.). Use these stubs when referencing extension APIs in code like MediaChannel.php.
- **Debugging tips**: Enable `error_reporting(E_ALL); ini_set('display_errors',1);` and attach callbacks for onRinging/onAnswer/onReceivePcm. High memory usage common with audio buffers.
- **Useful docs**: SIGNALING_ARRAYS.md (SIP array structures), TRUNK_CONTROLLER.md, EXTRA_AUDIO_TOOLS.md, example.php (9 progressive sessions).
- **Cleanup rule**: After creating temp test/demo files, always delete them (except updates to .junie/AGENTS.md).
