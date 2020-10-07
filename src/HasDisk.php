<?php

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter;
use Ruigweb\Cubes\Contracts\Cube as Contract;
use Ruigweb\Cubes\File;
use Illuminate\Support\Collection;

trait HasDisk
{
	protected static $storageDisks = [];

	public $diskName = 'local';

	public $diskConfig = [];

	public function exists(string $path) : bool
	{
		return $this->getDisk()->exists($path);
	}

	public function file(string $file) : File
    {
        return new File($file, $this->getDisk());
    }

    public function files(string $directory = null) : Collection
    {
        return collect($this->getDisk()->files($directory))->map(function($file) {
            return new File($file, $this->getDisk());
        });
    }

    public function directories(string $directory = null) : Collection
    {
        return collect($this->getDisk()->directories($directory));
    }

    public function put(File $file, $contents, $visibility = 'public') : bool
    {

    }

    public function delete(...$files) : bool
    {

    }

	public function setDisk(FilesystemAdapter $disk) : Contract
	{
		self::$storageDisks[$this->diskName] = $disk;

		return $this;
	}

	public function getDisk() : FilesystemAdapter
	{
		if (empty(self::$storageDisks[$this->diskName])) {
			switch ($this->getDiskConfig()['driver']) {
				case 'local' :
					$this->setDisk(\Storage::createLocalDriver($this->getDiskConfig()));
					$this->createRootDirectories();
					$this->createRootMetadata();
					break;
			}
		}

		return self::$storageDisks[$this->diskName];
	}

	public function setDiskConfig(array $config) : Contract
	{
		$this->diskConfig = $config;

		assert(!empty($this->diskConfig['driver']));
		assert(!empty($this->diskConfig['root']));

		return $this;
	}

	public function getDiskConfig() : array
	{
		return $this->diskConfig;
	}

	public function getDiskRoot() : string
	{
		return $this->getDiskConfig()['root'];
	}

    protected function createRootDirectories() : bool
    {
        return true;
    }
}
