<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter as Disk;
use Illuminate\Support\Str;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\Contracts\Cube;
use Ramsey\Uuid\Uuid;

/**
 * @todo Allow multiple (backup) cubes
 */
class Space
{
	protected $cube;

	protected $uuid;

	const SPACE_PREFIX = '.spaces';

	public function __construct(string $uuid = null, Cube $cube = null)
	{
		$this->uuid = $uuid ?: Str::uuid()->toString();

		assert(Uuid::isValid($this->uuid));

		if (!empty($cube)) {
			$this->attachTo($cube);
		}
	}

	public function store(Obj $obj)
	{
		$obj->on($this)->touch();

		return $this;
	}

	public function has(string $uuid) : bool
	{
		return $this->getCube()->getDisk()->exists($this->getPath().$this->getObjsPath());
	}

	public function get(string $uuid) : ?Obj
	{
		if ($this->has($uuid)) {
			return new Obj($uuid, $this);
		}

		return null;
	}

	public function attachTo(Cube $cube) : Space
	{
		$this->cube = $cube;
		$this->createSpaceRoot($cube);

		return $this;
	}

	public function getCube() : ?Cube
	{
		return $this->cube;
	}

	public function getRealPath()
	{
		return $this->getCube()->getDisk()->path($this->getPath());
	}

	public function getPath() : string
	{
		return self::SPACE_PREFIX.'/'.$this->uuid;
	}

	public function getMetadataPath() : string
	{
		return $this->getPath().'/.metadata';
	}

	public function getObjsPath() : string
	{
		return $this->getPath().'/objs';
	}

	protected function createSpaceRoot()
	{
		if ($this->getCube()->getDisk()->exists($this->getPath()) === false) {
			$this->getCube()->getDisk()->makeDirectory($this->getPath());
			$this->createRootDirectories();
		}

		return $this;
	}

	protected function createRootDirectories()
	{
		$this->getCube()->getDisk()->makeDirectory($this->getMetadataPath());
		$this->getCube()->getDisk()->makeDirectory($this->getObjsPath());

		return $this;
	}
}
