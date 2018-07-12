<?php

/**
 * Copyright (C) 2017 Spencer Mortensen
 *
 * This file is part of Lens.
 *
 * Lens is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Lens is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Lens. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@lens.guide>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2017 Spencer Mortensen
 */

namespace _Lens\Lens\Php;

class Node
{
	/** @var mixed */
	private $value;

	/** @var array */
	private $tags;

	public function __construct($value, array $tags = [])
	{
		$this->value = $value;
		$this->tags = self::getMap($tags);
	}

	private static function getMap(array $keys)
	{
		$map = [];

		foreach ($keys as $key) {
			$map[$key] = true;
		}

		return $map;
	}

	public function setTag($tag)
	{
		$this->tags[$tag] = true;
	}

	public function hasTag($tag)
	{
		return isset($this->tags[$tag]);
	}
}
