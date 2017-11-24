<?php

namespace Example;

use Closure;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Filesystem
{
	const TYPE_DIRECTORY = 1;
	const TYPE_FILE = 2;

	/** @var Closure */
	private $errorHandler;

	public function __construct()
	{
		// Suppress built-in PHP warnings that are inappropriately generated
		// under normal operating conditions
		$this->errorHandler = function () {};
	}

	public function read($path)
	{
		if (is_dir($path)) {
			return $this->readDirectory($path);
		}

		return $this->readFile($path);
	}

	public function listFiles($path)
	{
		$contents = array();

		set_error_handler($this->errorHandler);
		$this->listFilesInternal($path, '', $contents);
		restore_error_handler();

		return $contents;
	}

	private function listFilesInternal($baseDirectory, $relativePath, array &$contents)
	{
		$absolutePath = rtrim("{$baseDirectory}/{$relativePath}", '/');

		$files = scandir($absolutePath, SCANDIR_SORT_NONE);

		if ($files === false) {
			// TODO: throw exception
			return null;
		}

		foreach ($files as $file) {
			if (($file === '.') || ($file === '..')) {
				continue;
			}

			$childRelativePath = ltrim("{$relativePath}/{$file}", '/');
			$childAbsolutePath = "{$baseDirectory}/{$childRelativePath}";

			if (is_dir($childAbsolutePath)) {
				$this->listFilesInternal($baseDirectory, $childRelativePath, $contents);
			} else {
				$contents[] = $childRelativePath;
			}
		}
	}

	private function readDirectory($path)
	{
		set_error_handler($this->errorHandler);

		$files = scandir($path, SCANDIR_SORT_NONE);

		if ($files === false) {
			// TODO: throw exception
			restore_error_handler();
			return null;
		}

		$contents = array();

		foreach ($files as $file) {
			if (($file === '.') || ($file === '..')) {
				continue;
			}

			$childPath = "{$path}/{$file}";
			$contents[$file] = $this->read($childPath);
		}

		restore_error_handler();
		return $contents;
	}

	private function readFile($path)
	{
		set_error_handler($this->errorHandler);

		$contents = self::getString(file_get_contents($path));

		restore_error_handler();
		return $contents;
	}

	public function write($path, $contents)
	{
		return $this->writeFile($path, $contents);
	}

	private function writeFile($path, $contents)
	{
		set_error_handler($this->errorHandler);

		$result = self::writeFileContents($path, $contents) ||
			(
				self::createDirectory(dirname($path)) &&
				self::writeFileContents($path, $contents)
			);

		restore_error_handler();
		return $result;
	}

	private static function createDirectory($path)
	{
		return mkdir($path, 0777, true);
	}

	private static function writeFileContents($path, $contents)
	{
		return file_put_contents($path, $contents) === strlen($contents);
	}

	public function delete($path)
	{
		if (!is_string($path) || (strlen($path) === 0)) {
			return false;
		}

		if (!file_exists($path)) {
			return true;
		}

		if (is_dir($path)) {
			return $this->deleteDirectory($path);
		}

		return $this->deleteFile($path);
	}

	private function deleteFile($path)
	{
		return unlink($path);
	}

	private function deleteDirectory($directoryPath)
	{
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $file) {
			$isDirectory = $file->isDir();
			$childPath = $file->getRealPath();

			if ($isDirectory) {
				$isDeleted = rmdir($childPath);
			} else {
				$isDeleted = unlink($childPath);
			}

			if (!$isDeleted) {
				return false;
			}
		}

		return rmdir($directoryPath);
	}

	public function search($expression)
	{
		return glob($expression);
	}

	public function isDirectory($path)
	{
		return is_dir($path);
	}

	public function isFile($path)
	{
		return is_file($path);
	}

	public function getCurrentDirectory()
	{
		return self::getString(getcwd());
	}

	public function getAbsolutePath($path)
	{
		return self::getString(realpath($path));
	}

	private static function getString($value)
	{
		if (is_string($value)) {
			return $value;
		}

		return null;
	}
}