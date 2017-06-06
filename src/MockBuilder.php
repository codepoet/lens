<?php

/**
 * Copyright (C) 2017 Spencer Mortensen
 *
 * This file is part of testphp.
 *
 * Testphp is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Testphp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with testphp. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <spencer@testphp.org>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @copyright 2017 Spencer Mortensen
 */

namespace TestPhp;

class MockBuilder
{
	/** @var string */
	private $absoluteParentClass;

	/** @var string */
	private $childNamespace;

	/** @var string */
	private $childClass;

	/** @var boolean */
	private $isReplayMock;

	public function __construct($mockPrefix, $absoluteParentClass)
	{
		$absoluteChildClass = "{$mockPrefix}{$absoluteParentClass}";
		$slash = strrpos($absoluteChildClass, '\\');

		$this->absoluteParentClass = $absoluteParentClass;
		$this->childNamespace = substr($absoluteChildClass, 0, $slash);
		$this->childClass = substr($absoluteChildClass, $slash + 1);
	}

	public function getMock($isReplayMock)
	{
		$this->isReplayMock = $isReplayMock;

		$mockMethods = $this->getMockMethods();

		return <<<EOS
namespace {$this->childNamespace};

class {$this->childClass} extends \\{$this->absoluteParentClass}
{
{$mockMethods}
}
EOS;
	}

	private function getMockMethods()
	{
		$mockMethods = array(
			'__construct' => $this->getMockMethod('__construct', '')
		);

		$this->addMockMethods($mockMethods);

		return implode("\n\n", $mockMethods);
	}

	private function addMockMethods(array &$mockMethods)
	{
		$parentClass = new \ReflectionClass($this->absoluteParentClass);
		$methods = $parentClass->getMethods(\ReflectionMethod::IS_PUBLIC);

		/** @var \ReflectionMethod $method */
		foreach ($methods as $method) {
			if ($method->isStatic() || $method->isFinal()) {
				continue;
			}

			$name = $method->getName();
			$mockParameters = self::getMockParameters($method);

			$mockMethods[$name] = $this->getMockMethod($name, $mockParameters);
		}
	}

	private function getMockMethod($name, $parameters)
	{
		if ($this->isReplayMock) {
			return self::getReplayMockMethod($name, $parameters);
		}

		return self::getRecordMockMethod($name, $parameters);
	}

	private static function getReplayMockMethod($name, $parameters)
	{
		$code = <<<'EOS'
	public function %s(%s)
	{
		$callable = array($this, __FUNCTION__);
		$arguments = func_get_args();

		return \TestPhp\Agent::replay($callable, $arguments);
	}
EOS;

		return sprintf($code, $name, $parameters);
	}

	private static function getRecordMockMethod($name, $parameters)
	{
		$code = <<<'EOS'
	public function %s(%s)
	{
		$callable = array($this, __FUNCTION__);
		$arguments = func_get_args();
		$result = array(0, null);

		return \TestPhp\Agent::record($callable, $arguments, $result);
	}
EOS;

		return sprintf($code, $name, $parameters);
	}

	private static function getMockParameters(\ReflectionMethod $method)
	{
		$mockParameters = array();

		$parameters = $method->getParameters();

		foreach ($parameters as $parameter) {
			$mockParameters[] = self::getMockParameter($parameter);
		}

		return implode(', ', $mockParameters);
	}

	private static function getMockParameter(\ReflectionParameter $parameter)
	{
		$name = $parameter->getName();
		$hint = self::getParameterHint($parameter);

		$definition = '$' . $name;

		if ($hint !== null) {
			$definition = "{$hint} {$definition}";
		}

		if ($parameter->isOptional()) {
			$definition = "{$definition} = null";
		}

		return $definition;
	}

	private static function getParameterHint(\ReflectionParameter $parameter)
	{
		if ($parameter->isArray()) {
			return 'array';
		}

		if ($parameter->isCallable()) {
			return 'callable';
		}

		// TODO: support PHP-7 type hinting...

		$class = $parameter->getClass();

		if ($class === null) {
			return null;
		}

		$className = $class->getName();
		return '\\' . $className;
	}
}