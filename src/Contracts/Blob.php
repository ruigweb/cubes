<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes\Contracts;

use Illuminate\Filesystem\FilesystemAdapter;
use Ruigweb\Cubes\File;
use Illuminate\Support\Collection;

interface Blob
{
	public function getMetadata() : array;
}
