<?php

declare(strict_types = 1);

namespace Ruigweb\Cubes\Objects;

use ArrayAccess;
use Illuminate\Filesystem\FilesystemAdapter as Disk;
use Illuminate\Support\Str;
use Ruigweb\Cubes\File;
use Ruigweb\Cubes\Group;
use Ruigweb\Cubes\Obj;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Ruigweb\Cubes\User;

class Metadata extends File implements ArrayAccess
{
	protected $obj;

	protected $data;

	public const ACCESS_PRIVATE = 'private';
	public const ACCESS_PUBLIC = 'public';

	protected $indexer;

	public function __construct(Obj $obj)
	{
		$this->obj = $obj;

		parent::__construct($this->obj->space()->getMetadataPath().'/'.$this->obj->uuid(), $this->obj->space()->getCube()->getDisk());

		$this->read();
	}

	public function offsetExists($offset) : bool
	{
		return (
			(array_key_exists($offset, $this->data) && $offset !== 'custom') ||
			(array_key_exists($offset, $this->data['custom']))
		);
	}

	public function offsetGet($offset )
	{
		if (array_key_exists($offset, $this->data) && $offset !== 'custom') {
			return $this->data[$offset];
		} else if (array_key_exists($offset, $this->data['custom'])) {
			return $this->data['custom'][$offset];
		}

		// throw exception
	}

	public function offsetSet($offset , $value ) : void
	{
		$this->data['custom'][$offset] = $value;
	}

	public function offsetUnset($offset ) : void
	{
		if (array_key_exists($offset, $this->data['custom'])) {
			unset($this->data['custom'][$offset]);
		}

		// throw exception
	}

	public function read() : void
	{
		if ($this->exists()) {
			$this->data = json_decode($this->contents(), true);
			$this->data = array_merge($this->data, $this->obj->blob()->getMetadata($this->data));
		} else {
			$this->data = array_merge($this->obj->blob()->getMetadata(), [
				'encrypted' => false,
				'access'    => self::ACCESS_PRIVATE,
				'tags'      => [],
				'custom'    => [
				],
			]);
		}
	}

	public function write() : bool
	{
		$this->data = array_merge($this->data, $this->obj->blob()->getMetadata($this->data));

		if ($this->touch($this->toJson())) {
			return $this->updateIndex();
		}

		return false;
	}

	public function encrypt(bool $encrypt = true)
	{
		$this->data['encrypted'] = $encrypt;
		return $this;
	}

	public function public()
	{
		$this->data['access'] = self::ACCESS_PUBLIC;
		return $this;
	}

	public function private(Group $group = null, User $user = null)
	{
		$access = self::ACCESS_PRIVATE;
		if (!empty($group)) {
			$access += '[.gr:'.$group.']';
		}

		if (!empty($user)) {
			$access += '[.usr:'.$user.']';
		}

		$this->data['access'] = self::ACCESS_PRIVATE;
		return $this;
	}

	public function access(string $user) : bool
	{
		if ($this->data['access'] !== self::ACCESS_PUBLIC) {
			return false;
		}

		return true;
	}

	public function getHeaders() : array
	{
		return [
			'Content-Length' => $this->data['size'],
		];
	}

	public function toArray() : array
	{
		return $this->data;
	}

	public function toJson() : string
	{
		return json_encode($this->toArray());
	}

	protected function getGroupPath() : string
	{

	}

	protected function getUserPath() : string
	{

	}

	public function indexer($indexer)
	{
		$this->indexer = $indexer;
		return $this;
	}

	protected function updateIndex() : bool
	{
		if (!empty($this->indexer)) {
			if (is_string($this->indexer) && class_exists($this->indexer)) {
				return app()->makeWith($this->indexer, [$this->obj->uuid(), $this->toArray()]);
			} elseif ($this->indexer instanceof \Closure) {
				return $this->indexer->call($this, $this->obj->uuid(), $this->toArray());
			} elseif (is_callable($this->indexer)) {
				return call_user_func_array($this->indexer, [$this->obj->uuid(), $this->toArray()]);
			} else {
				return false;
			}
		}

		return true;
	}
}
