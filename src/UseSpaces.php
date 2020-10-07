<?php

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter;
use Ruigweb\Cubes\Contracts\Cube as Contract;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\Space;
use Illuminate\Support\Collection;

trait UseSpaces
{
	use HasDisk;

	public function createSpacesRoot()
	{
		$this->getDisk()->makeDirectory($this->getDiskRoot().'/'.Space::SPACE_PREFIX);
	}

	public function attach(Space $space) : Cube
	{
		$space->attachTo($this);
		return $this;
	}

	public function getSpaces() : Collection
	{
		return collect($this->directories(Space::SPACE_PREFIX))->map(function($directory) {
			return new Space($directory, $this);
		});
	}

	public function getSpace(string $uuid) : ?Space
	{
		if ($this->exists(Space::SPACE_PREFIX.'/'.$uuid)) {
			return new Space($uuid, $this);
		}

		return null;
	}
}
