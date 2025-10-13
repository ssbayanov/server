<?php

declare(strict_types=1);

/**
 * This file is based on tourze/symfony-snowflake-bundle
 * SPDX-FileCopyrightText: tourze <https://github.com/tourze>
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: MIT
 */

namespace OC\AppFramework\Db;

use OCP\AppFramework\Db\ISnowflake;

class Snowflake implements ISnowflake {

	/**
	 * @var SnowflakeGenerator[]
	 */
	private static array $generators = [];

	public static function getGenerator(int $datacenter, int $workerId, NextcloudSequenceResolver $resolver): SnowflakeGenerator {
		$key = "{$datacenter}-{$workerId}";
		if (!isset(self::$generators[$key])) {
			$generator = new SnowflakeGenerator(
				$datacenter,
				$workerId,
				$resolver,
			);
			$generator->setStartTimeStamp(strtotime('2025-01-01') * 1000);
			self::$generators[$key] = $generator;
		}
		return self::$generators[$key];
	}

	public static function generateWorkerId(string $hostname, int $maxWorkerId = 31): int {
		$hash = crc32($hostname);
		return $hash % ($maxWorkerId + 1);
	}

	private SnowflakeGenerator $generator;

	public function __construct(
		NextcloudSequenceResolver $nextcloudSequenceResolver,
	) {
		$this->generator = static::getGenerator(
			-1, // ATM set randomely
			self::generateWorkerId(gethostname()),
			$nextcloudSequenceResolver
		);
	}

	public function id(): string {
		return $this->generator->id();
	}

	public function parseId(string $id, bool $transform = false): array {
		return SnowflakeGenerator::parseId($id, $transform);
	}
}
