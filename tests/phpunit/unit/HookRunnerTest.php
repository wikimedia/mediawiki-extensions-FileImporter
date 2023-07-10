<?php

namespace FileImporter\Tests;

use FileImporter\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \FileImporter\HookRunner
 * @license GPL-2.0-or-later
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}

}
