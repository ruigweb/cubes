<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes\Contracts;

use Illuminate\Filesystem\FilesystemAdapter;
use Ruigweb\Cubes\File;
use Illuminate\Support\Collection;

interface Cube
{
	public function file(string $file) : File;

	public function files(string $directory = null) : Collection;

	public function directories(string $directory = null) : Collection;

	public function put(File $file, $contents, $visibility = 'public') : bool;

	public function delete(...$files) : bool;

	public function setDisk(FilesystemAdapter $disk) : Cube;

	public function getDisk() : FilesystemAdapter;

	public function setDiskConfig(array $config) : Cube;

	public function getDiskConfig() : array;

	public function getDiskRoot() : string;
}