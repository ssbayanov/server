<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\AppFramework\Db;

use OCP\AppFramework\Attribute\Consumable;

/**
 * Implementation of the snowflake id generator for database primary indexes.
 *
 * @since 33.0.0
 */
#[Consumable(since: '33.0.0')]
interface ISnowflake {
	/**
	 * Get a snowflake id. Each call to this method is guaranteed to return a different id.
	 *
	 * @return string The id as a string but is actually a numeric number.
	 */
	public function id(): string;

	/**
	 * Parse snowflake id.
	 *
	 * @param bool $transform Whether to transform the returned parts as string or int.
	 *
	 * @return array{
	 *     timestamp: int|float|string,
	 *     sequence: int|float|string,
	 *     workerid: int|float|string,
	 *     datacenter: int|float|string,
	 * }
	 */
	public function parseId(string $id, bool $transform = false): array;
}
