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

namespace Lens_0_0_56\Lens\Commands;

use Lens_0_0_56\Lens\Arguments;
use Lens_0_0_56\Lens\Browser;
use Lens_0_0_56\Lens\Index;
use Lens_0_0_56\Lens\Environment;
use Lens_0_0_56\Lens\Evaluator\Evaluator;
use Lens_0_0_56\Lens\Filesystem;
use Lens_0_0_56\Lens\Finder;
use Lens_0_0_56\Lens\LensException;
use Lens_0_0_56\Lens\Reports\Tap;
use Lens_0_0_56\Lens\Reports\Text;
use Lens_0_0_56\Lens\Reports\XUnit;
use Lens_0_0_56\Lens\Settings;
use Lens_0_0_56\Lens\SuiteParser;
use Lens_0_0_56\Lens\Summarizer;
use Lens_0_0_56\Lens\Url;
use Lens_0_0_56\Lens\Web;
use Lens_0_0_56\SpencerMortensen\Parser\ParserException;
use Lens_0_0_56\SpencerMortensen\Paths\Paths;
use Lens_0_0_56\SpencerMortensen\RegularExpressions\Re;

class LensRunner implements Command
{
	/** @var Arguments */
	private $arguments;

	/** @var Paths */
	private $paths;

	/** @var Filesystem */
	private $filesystem;

	/** @var Finder */
	private $finder;

	public function __construct(Arguments $arguments)
	{
		$this->arguments = $arguments;
		$this->paths = Paths::getPlatformPaths();
		$this->filesystem = new Filesystem();
		$this->finder = new Finder($this->paths, $this->filesystem);
	}

	public function run(&$stdout = null, &$stderr = null, &$exitCode = null)
	{
		$options = $this->arguments->getOptions();
		$paths = $this->arguments->getValues();
		$reportType = $this->getReportType($options);

		// TODO: if there are any options other than "report", then throw a usage exception

		$this->finder->find($paths);

		$executable = $this->arguments->getExecutable();
		$project = $this->finder->getProject();
		$src = $this->finder->getSrc();
		$autoload = $this->finder->getAutoload();
		$cache = $this->finder->getCache();
		$tests = $this->finder->getTests();

		$suites = $this->getSuites($paths);

		$evaluator = new Evaluator($executable, $this->filesystem);
		list($suites, $code, $coverage) = $evaluator->run($project, $src, $autoload, $cache, $suites);

		$results = array(
			'name' => 'Lens', // TODO: let the user provide the project name in the configuration file
			'suites' => $this->useRelativePaths($tests, $suites)
		);

		$summarizer = new Summarizer();
		$summarizer->summarize($results);

		$report = $this->getReport($reportType, $autoload);
		$stdout = $report->getReport($results);
		$stderr = null;

		if ($this->isUpdateAvailable() && ($reportType === 'text')) {
			$stdout .= "\n\nA newer version of Lens is available:\n" . Url::LENS_INSTALLATION;
		}

		if (isset($code, $coverage)) {
			$web = new Web($this->filesystem);
			$coveragePath = $this->finder->getCoverage();
			$web->coverage($src, $coveragePath, $code, $coverage);
		}

		$isSuccessful = ($results['summary']['failed'] === 0);

		if ($isSuccessful) {
			$exitCode = 0;
		} else {
			$exitCode = LensException::CODE_FAILURES;
		}

		return true;
	}

	private function isUpdateAvailable()
	{
		$settingsPath = $this->finder->getSettings();

		if ($settingsPath === null) {
			return false;
		}

		$settings = new Settings($this->filesystem, $settingsPath);
		$checkForUpdates = $settings->get('checkForUpdates');

		if (!$checkForUpdates) {
			return false;
		}

		$environment = new Environment();
		$os = $environment->getOperatingSystemName();
		$php = $environment->getPhpVersion();
		$lens = $environment->getLensVersion();

		$data = array(
			'os' => $os,
			'php' => $php,
			'lens' => $lens
		);

		$url = Url::LENS_CHECK_FOR_UPDATES;
		$query = http_build_query($data);
		$latestVersion = file_get_contents("{$url}?{$query}");

		return Re::match('^[0-9]+\\.[0-9]+\\.[0-9]+$', $latestVersion) &&
			(LensVersion::VERSION !== $latestVersion);
	}

	private function getReportType(array $options)
	{
		$type = &$options['report'];

		if ($type === null) {
			return 'text';
		}

		switch ($type) {
			case 'xunit':
				return 'xunit';

			case 'tap':
				return 'tap';

			default:
				throw LensException::invalidReport($type);
		}
	}

	private function getReport($type, $autoload)
	{
		switch ($type) {
			case 'xunit':
				return new XUnit();

			case 'tap':
				return new Tap();

			default:
				return new Text($autoload);
		}
	}

	/*
	private function getCoverage()
	{
		$options = $this->options;
		$type = &$options['coverage'];

		switch ($type) {
			// TODO: case 'none'
			// TODO: case 'clover'
			// TODO: case 'crap4j'
			// TODO: case 'text'

			default:
				return 'html';
		}

	}
	*/

	private function getSuites(array $paths)
	{
		$browser = new Browser($this->filesystem, $this->paths);
		$files = $browser->browse($paths);

		$suites = array();
		$parser = new SuiteParser();

		foreach ($files as $path => $contents) {
			try {
				$suites[$path] = $parser->parse($contents);
			} catch (ParserException $exception) {
				throw LensException::invalidTestsFileSyntax($path, $contents, $exception);
			}
		}

		return $suites;
	}

	private function useRelativePaths($testsDirectory, array $input)
	{
		$output = array();

		foreach ($input as $absolutePath => $value) {
			$relativePath = $this->paths->getRelativePath($testsDirectory, $absolutePath);
			$output[$relativePath] = $value;
		}

		return $output;
	}
}
