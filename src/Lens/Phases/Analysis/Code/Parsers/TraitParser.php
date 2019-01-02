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

namespace _Lens\Lens\Phases\Analysis\Code\Parsers;

use _Lens\Lens\Phases\Analysis\Code\Input;
use _Lens\Lens\Php\Lexer;

class TraitParser
{
	/** @var MethodParser */
	private $methodParser;

	/** @var Input */
	private $input;

	public function __construct(MethodParser $methodParser)
	{
		$this->methodParser = $methodParser;
	}

	public function parse(Input $input, &$output)
	{
		$this->input = $input;
		$iBegin = $input->getPosition();

		$classPaths = [];
		$functionPaths = [];

		if (
			$this->input->get(Lexer::TRAIT_) &&
			$this->input->get(Lexer::IDENTIFIER_, $name) &&
			$this->input->get(Lexer::BRACE_LEFT_) &&
			$this->getOptionalMethods($methods, $classPaths, $functionPaths) &&
			$this->input->get(Lexer::BRACE_RIGHT_)
		) {
			$iEnd = $this->input->getPosition() - 1;

			$output = [
				'name' => $name,
				'range' => [$iBegin => $iEnd],
				'signatureRange' => [$iBegin => $iBegin + 1],
				'methods' => $methods,
				'classPaths' => $classPaths,
				'functionPaths' => $functionPaths
			];

			return true;
		}

		$this->input->setPosition($iBegin);
		return false;
	}

	private function getOptionalMethods(&$methods, &$classPaths, &$functionPaths)
	{
		$methods = [];

		while (true) {
			if ($this->methodParser->parse($this->input, $method, $classPaths, $functionPaths)) {
				$methods[] = $method;
				continue;
			}

			if ($this->input->read($type) && ($type !== Lexer::BRACE_RIGHT_)) {
				$this->input->move(1);
				continue;
			}

			break;
		}

		return true;
	}
}