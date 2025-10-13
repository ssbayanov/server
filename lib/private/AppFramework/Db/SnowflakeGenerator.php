<?php

declare(strict_types=1);

/**
 * This file is based on the package: godruoyi/php-snowflake.
 * SPDX-FileCopyrightText: 2024 Godruoyi <g@godruoyi.com>
 * SPDX-License-Identifier: MIT
 */

namespace OC\AppFramework\Db;

class SnowflakeGenerator {
	public const MAX_TIMESTAMP_LENGTH = 41;
	public const MAX_DATACENTER_LENGTH = 5;
	public const MAX_WORKID_LENGTH = 5;
	public const MAX_SEQUENCE_LENGTH = 12;
	public const MAX_SEQUENCE_SIZE = (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH));

	/**
	 * The data center id.
	 */
	protected int $datacenter;

	/**
	 * The worker id.
	 */
	protected int $workerId;

	/**
	 * The start timestamp.
	 */
	protected ?int $startTime = null;

	/**
	 * The last timestamp for the random generator.
	 */
	protected int $lastTimeStamp = -1;

	/**
	 * The sequence number for the random generator.
	 */
	protected int $sequence = 0;

	/**
	 * Build Snowflake Instance.
	 */
	public function __construct(
		int $datacenter,
		int $workerId,
		private readonly NextcloudSequenceResolver $sequenceResolver,
	) {
		$maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
		$maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

		// If not set datacenter or workid, we will set a default value to use.
		$this->datacenter = $datacenter < 0 || $datacenter > $maxDataCenter ? random_int(0, 31) : $datacenter;
		$this->workerId = $workerId < 0 || $workerId > $maxWorkId ? random_int(0, 31) : $workerId;
	}

	/**
	 * Get snowflake id.
	 */
	public function id(): string {
		$currentTime = $this->getCurrentMillisecond();
		while (($sequence = $this->callResolver($currentTime)) > (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH))) {
			usleep(1);
			$currentTime = $this->getCurrentMillisecond();
		}

		$workerLeftMoveLength = self::MAX_SEQUENCE_LENGTH;
		$datacenterLeftMoveLength = self::MAX_WORKID_LENGTH + $workerLeftMoveLength;
		$timestampLeftMoveLength = self::MAX_DATACENTER_LENGTH + $datacenterLeftMoveLength;

		return (string)((($currentTime - $this->getStartTimeStamp()) << $timestampLeftMoveLength)
			| ($this->datacenter << $datacenterLeftMoveLength)
			| ($this->workerId << $workerLeftMoveLength)
			| ($sequence));
	}

	/**
	 * Parse snowflake id.
	 */
	public static function parseId(string $id, bool $transform = false): array {
		$id = decbin((int)$id);

		$data = [
			'timestamp' => substr($id, 0, -22),
			'sequence' => substr($id, -12),
			'workerid' => substr($id, -17, 5),
			'datacenter' => substr($id, -22, 5),
		];

		return $transform ? array_map(static function ($value) {
			return bindec($value);
		}, $data) : $data;
	}

	/**
	 * Get current millisecond time.
	 */
	public function getCurrentMillisecond(): int {
		return (int)floor(microtime(true) * 1000) | 0;
	}

	/**
	 * Set start time (millisecond).
	 * @throw \InvalidArgumentException
	 */
	public function setStartTimeStamp(int $millisecond): self {
		$missTime = $this->getCurrentMillisecond() - $millisecond;

		if ($missTime < 0) {
			throw new \InvalidArgumentException('The start time cannot be greater than the current time');
		}

		$maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

		if ($missTime > $maxTimeDiff) {
			throw new \InvalidArgumentException(sprintf('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this', self::MAX_TIMESTAMP_LENGTH));
		}

		$this->startTime = $millisecond;

		return $this;
	}

	/**
	 * Get start timestamp (millisecond), If not set default to 2019-08-08 08:08:08.
	 */
	public function getStartTimeStamp(): float|int {
		if (! is_null($this->startTime)) {
			return $this->startTime;
		}

		// We set a default start time if you not set.
		$defaultTime = '2019-08-08 08:08:08';

		return strtotime($defaultTime) * 1000;
	}

	/**
	 * Call resolver.
	 */
	protected function callResolver(int $currentTime): int {
		// Memcache based resolver
		if ($this->sequenceResolver->isAvailable()) {
			return $this->sequenceResolver->sequence($currentTime);
		}

		// random fallback
		if ($this->lastTimeStamp === $currentTime) {
			$this->sequence++;
			$this->lastTimeStamp = $currentTime;

			return $this->sequence;
		}

		$this->sequence = crc32(uniqid((string)random_int(0, PHP_INT_MAX), true)) % self::MAX_SEQUENCE_SIZE;
		$this->lastTimeStamp = $currentTime;

		return $this->sequence;
	}
}
