<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test\DB;

use OC\DB\Snowflake\NextcloudSequenceResolver;
use OC\DB\Snowflake\SnowflakeGenerator;
use PHPUnit\Framework\Attributes\TestWith;
use Test\TestCase;

class SnowflakeTest extends TestCase {
	#[TestWith(data: [true, true, 42.0])] // 42ms
	#[TestWith(data: [true, false, 42.0])]
	#[TestWith(data: [false, true, 42.0])]
	#[TestWith(data: [true, false, 42.0])]
	#[TestWith(data: [false, true, 1000.0 * 60 * 60 * 24 * 365 * 10])] // ~10 years
	#[TestWith(data: [false, false, 1000.0 * 60 * 60 * 24 * 365 * 10])]
	public function testLayout(bool $isCLIExpected, bool $is32BitsSystem, float $timeDiff): void {
		$baseTimestamp = strtotime('2025-01-01') * 1000.0;
		$resolver = $this->createMock(NextcloudSequenceResolver::class);
		$resolver->method('isAvailable')->willReturn(true);
		$resolver->method('sequence')->willReturnCallback(function ($time) use ($baseTimestamp, $timeDiff) {
			$this->assertEqualsWithDelta($baseTimestamp + $timeDiff, $time, 0.01);
			return 42;
		});

		$snowFlake = $this->getMockBuilder(SnowflakeGenerator::class)
			->setConstructorArgs([21, 22, $resolver, $isCLIExpected])
			->onlyMethods(['getCurrentMillisecond', 'is32BitsSystem'])
			->getMock();

		$snowFlake->method('getCurrentMillisecond')
			->willReturn($baseTimestamp + $timeDiff);

		$snowFlake->method('is32BitsSystem')
			->willReturn($is32BitsSystem);

		$snowFlake->setStartTimeStamp($baseTimestamp);

		$id = $snowFlake->nextId();

		[
			'sequence' => $sequence,
			'timestamp' => $timestamp,
			'workerid' => $workerId,
			'datacenter' => $datacenter,
			'iscli' => $isCLI,
		] = $snowFlake->parseId($id);

		$this->assertEquals(22, $workerId);
		$this->assertEquals(21, $datacenter);
		$this->assertEquals($isCLIExpected, $isCLI);
		$this->assertEquals($timeDiff, $timestamp);
		$this->assertEquals(42, $sequence);
	}
}
