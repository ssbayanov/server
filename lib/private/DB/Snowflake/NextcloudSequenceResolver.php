<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\DB\Snowflake;

use OCP\ICacheFactory;
use OCP\IMemcache;

class NextcloudSequenceResolver {
	private ?IMemCache $localCache = null;

	public function __construct(
		ICacheFactory $cache,
	) {
		$localCache = $cache->createLocal('snowflake');

		if ($localCache instanceof IMemcache) {
			$this->localCache = $localCache;
		}
	}

	public function isAvailable(): bool {
		return $this->localCache instanceof IMemcache;
	}

	public function sequence(float $currentTime): int {
		if ($this->localCache->add((string)$currentTime, 1, 10)) {
			return 0;
		}

		return $this->localCache->inc((string)$currentTime, 1) | 0;
	}
}
