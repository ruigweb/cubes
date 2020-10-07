<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes\Objects;

use Illuminate\Filesystem\FilesystemAdapter as Disk;
use Illuminate\Support\Str;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\Obj;
use Ruigweb\Cubes\Contracts\Blob as Contract;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Hash;

class Blob extends File implements Contract
{
	protected $obj;

	public function __construct(Obj $obj)
	{
		$this->obj = $obj;

		parent::__construct($this->obj->space()->getObjsPath().'/'.$this->obj->uuid(), $this->obj->space()->getCube()->getDisk());
	}

	public function encrypt(string $key, string $contents)
	{
		return $this->touch((new Encrypter($key))->encrypt($contents));
	}

	public function decrypt(string $key)
	{
		return (new Encrypter($key))->decrypt($this->toString());
	}

	public function getHash() : ?string
	{
		if ($this->exists()) {
			return Hash::make($this->toString());
		}

		return null;
	}

	public function getMetadata($metadata = []) : array
	{
		return [
			'type'      => $this->mimeType(),
			'size'      => $this->size(),
			'created'   => (array_key_exists('created', $metadata)) ? Carbon::parse($metadata['created']) : Carbon::now(),
			'modified'  => $this->mtime(),
			'hash'      => $this->getHash(),
			'file'      => $this->name(),
			'name'      => null,
			'extension' => $this->extension(),
		];
	}
}
