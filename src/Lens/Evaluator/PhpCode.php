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

namespace Lens_0_0_56\Lens\Evaluator;

class PhpCode
{
	public static function getRequirePhp($file)
	{
		if ($file === null) {
			return null;
		}

		$stringPhp = var_export($file, true);

		return "require {$stringPhp};";
	}

	public static function getContextPhp($namespace, array $uses)
	{
		$namespacePhp = self::getNamespacePhp($namespace);
		$usesPhp = self::getUsesPhp($uses);

		return self::combine($namespacePhp, $usesPhp);
	}

	public static function getNamespacePhp($namespace)
	{
		if ($namespace === null) {
			return null;
		}

		return "namespace {$namespace};";
	}

	private static function getUsesPhp(array $uses)
	{
		if (count($uses) === 0) {
			return null;
		}

		$usesPhp = array();

		foreach ($uses as $name => $path) {
			$usesPhp[] = self::getUsePhp($name, $path);
		}

		return implode("\n", $usesPhp);
	}

	private static function getUsePhp($name, $path)
	{
		$usePhp = "use {$path}";

		if ($name !== self::getTail($path, '\\')) {
			$usePhp .= " as {$name}";
		}

		$usePhp .= ';';

		return $usePhp;
	}

	private static function getTail($haystack, $needle)
	{
		$position = strrpos($haystack, $needle);

		if (is_integer($position)) {
			return substr($haystack, $position + 1);
		}

		return $haystack;
	}

	private static function combine()
	{
		return implode("\n\n", array_filter(func_get_args(), 'is_string'));
	}
}
