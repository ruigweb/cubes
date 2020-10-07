<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter;
use Ruigweb\Cubes\Contracts\Cube as Contract;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\HasDisk;
use Ruigweb\Cubes\UseSpaces;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class Cube implements Contract
{
	use HasDisk;
	use UseSpaces;

	public const METADATA_FILE = '.metadata';
	public const GROUP_PATH    = '.grp';
	public const USER_PATH     = '.usr';

	public function __construct(array $config = [])
	{
		$this->setDiskConfig($config);
	}

	protected function createRootDirectories() : bool
    {
    	$this->createRootMetadata();
    	$this->createGroupDirectory();
    	$this->createUserDirectory();
        return true;
    }

    protected function createRootMetadata() : void
	{
		if ($this->getDisk()->exists(trim($this->getMetadataPath(),'/')) === false) {
			$file = $this->file(trim($this->getMetadataPath(),'/'));
			$file->create(Crypt::encryptString(json_encode([
				'driver' => get_class($this),
			])));
		}
	}

	protected function createGroupDirectory()
    {
        if ($this->getDisk()->exists(trim($this->getGroupPath(),'/')) === false) {
            $this->getDisk()->makeDirectory(trim($this->getGroupPath(),'/'));
        }
    }

    protected function createUserDirectory()
    {
        if ($this->getDisk()->exists(trim($this->getUserPath(),'/')) === false) {
            $this->getDisk()->makeDirectory(trim($this->getUserPath(),'/'));
        }
    }

    public function getMetadataPath() : string
    {
        return '/'.self::METADATA_FILE;
    }

	public function getGroupPath() : string
	{
		return '/'.self::GROUP_PATH;
	}

	public function getUserPath() : string
	{
		return '/'.self::USER_PATH;
	}

	public function addUser(User $user)
    {

    }

    public function addGroup(Group $group)
    {

    }

}
