<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter as Disk;
use Illuminate\Support\Str;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\Objects\Blob;
use Ruigweb\Cubes\Objects\Metadata;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Crypt;

class Obj
{
	protected $uuid;

	protected $space;

	protected $blob;

	protected $metadata;

	public function __construct(string $uuid = null, Space $space = null)
    {
    	$this->uuid  = $uuid ?: Str::uuid()->__toString();

    	assert(Uuid::isValid($this->uuid));

    	if (isset($space)) {
    		$this->on($space);
    	}
    }

    public function __call(string $method, array $arguments = [])
    {
    	if ($this->metadata) {
    		if (method_exists($this->metadata, $method)) {
    			$return = call_user_func_array([$this->metadata, $method], $arguments);
    			return ($return instanceof Metadata) ? $this : $return;
			}
    	}

    	// throw invalid method exception
    }

    public function __set(string $name , $value)
    {
    	$this->metadata[$name] = $value;
    	return $this;
    }

    public function __get(string $name)
    {
    	if (array_key_exists($name, $this->metadata)) {
    		return $this->metadata[$name];
    	}

    }

    public function on(Space $space)
    {
    	$this->space    = $space;
		$this->blob     = new Blob($this);
        $this->metadata = new Metadata($this);

        return $this;
    }

    public function encrypted() : bool
    {
    	return $this->metadata['encrypted'];
    }

	public function uuid() : string
	{
		return $this->uuid;
	}

	public function space() : ?Space
	{
		return $this->space;
	}

	public function metadata() : ?Metadata
	{
		return $this->metadata;
	}

	public function blob() : ?Blob
	{
		return $this->blob;
	}

	public function save($contents = null) : bool
	{
		if ($this->space()) {
			if ($this->blob->exists()) {
				return $this->update($contents);
			} else {
				return $this->create($contents);
			}
		}

		return false;
	}

	public function create($contents = null) : bool
	{
		if ($this->space()) {
			if ($this->blob->create($contents)) {
				if ($this->metadata->write()) {
					return true;
				}
			}
		}

		return false;
	}

	public function update($contents = null) : bool
	{
		if ($this->space()) {
			if ($this->blob->exists()) {
				if ($this->blob->put($contents)) {
					if ($this->metadata->write()) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public function delete() : bool
	{

	}

	public function contents() : ?string
	{
		if ($this->blob->exists()) {
			return $this->blob->contents();
		}

		return null;
	}
}
