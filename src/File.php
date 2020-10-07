<?php

namespace Ruigweb\Cubes;

use Illuminate\Filesystem\FilesystemAdapter as Disk;
use Illuminate\Support\Str;
use SplFileInfo;
use Carbon\Carbon;

class File extends SplFileInfo
{
    protected $disk;

    public function __construct(string $file_path, Disk $disk = null)
    {
        parent::__construct($file_path);

        $this->disk = $disk;
    }

    public function name() : string
    {
        $filename = $this->getFilename();
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    public function extension() : ?string
    {
        $extension = pathinfo($this->getFileName(), PATHINFO_EXTENSION);
        return !empty($extension) ? $extension : null;
    }

    public function exists()
    {
        return $this->disk()->exists($this->getPathName());
    }

    public function contents()
    {
        return $this->toString();
    }

    public function json() : ?array
    {
        $contents = $this->toString();
        $data     = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        return null;
    }

    public function create(string $contents = null)
    {
        if (!$this->exists()) {
            return $this->disk()->put($this->getPathName(), $contents);
        }

        return false;
    }

    public function put(string $contents = '')
    {
        if ($this->exists()) {
            return $this->disk()->put($this->getPathName(), $contents);
        }

        return false;
    }

    public function touch(string $contents = null) : bool
    {
        if (!$this->exists()) {
            return $this->create($contents);
        } else {
            return $this->put($contents);
        }
    }

    public function append(string $contents) : bool
    {
        return $this->disk()->append($this->getPathName(), $contents);
    }

    public function delete()
    {
        if ($this->exists()) {
            return $this->disk()->delete($this->getPathName());
        }

        return true;
    }

    public function mimeType() : ?string
    {
        return $this->exists() ? $this->disk()->mimeType($this->getPathName()) : null;
    }

    public function mtime() : ?Carbon
    {
        return $this->exists() ? Carbon::parse($this->disk()->lastModified($this->getPathName())) : null;
    }

    public function size() : ?int
    {
        return $this->exists() ? $this->disk()->size($this->getPathName()) : null;
    }

    public function disk()
    {
        return $this->disk;
    }

    public function toString() : string
    {
        return $this->disk()->get($this->getPathName());
    }
}
