<?php

namespace Ruigweb\Cubes\Cubes;

use Ruigweb\Cubes\Cube;
use Illuminate\Support\Str;
use League\Flysystem\Util;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

class Stream
{
	protected static $cube = null;

	public const PROTOCOL = 'cube';

	protected static $defaultConfig = [
        'permissions' => [
            'dir' => [
                'private' => 0700,
                'public' => 0755,
            ],
            'file' => [
                'private' => 0600,
                'public' => 0644,
            ],
        ],
        'metadata' => ['timestamp', 'size', 'visibility'],
        'public_mask' => 0044,
        'prefix' => '',
    ];

    protected static $config = [];

    protected $path;

    protected $handle;

    protected $isAppendMode = false;

    protected $isReadOnly = false;

    protected $isWriteOnly = false;

    protected $dir;

    public function __construct()
    {

    }

	public static function register(Cube $cube, array $config = [], $flags = 0)
	{
		if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
			stream_wrapper_unregister(self::PROTOCOL);
		}

		self::$cube = $cube;
		self::$config = array_merge(self::$defaultConfig, $config);

		stream_wrapper_register(self::PROTOCOL, __CLASS__, $flags);

		return new static;
	}

	public function getCube() : ?Cube
	{
		return self::$cube;
	}

	public function mkdir($path, $mode, $options)
	{
		$this->path = $path;

		$dirname = Util::normalizePath($this->getPath());

        $adapter = $this->getCube()->getDisk()->getAdapter();


        $config = new Config();
        $config->setFallback($this->getCube()->getDisk()->getConfig());

        // If recursive, or a single level directory, just create it.
        if (($options & STREAM_MKDIR_RECURSIVE) || strpos($dirname, '/') === false) {
            return (bool) $adapter->createDir($dirname, $config);
        }

        if (!$adapter->has(dirname($dirname))) {
            throw new FileNotFoundException($dirname);
        }

        return (bool) $adapter->createDir($dirname, $config);
	}

	public function unlink($path)
    {
        $this->path = $path;
        return $this->getCube()->getDisk()->delete($this->getPath());
    }

    public function rename($path_from, $path_to)
    {
        $this->path = $path_from;

        $path = Util::normalizePath($this->getPath($path_from));
        $newpath = Util::normalizePath($this->getPath($path_to));

        // Ignore useless renames.
        if ($path === $newpath) {
            return true;
        }

        if (!$this->isValidRename($path, $newpath)) {
            return false;
        }

        return (bool) $this->getCube()->getDisk()->getAdapter()->rename($path, $newpath);
    }

    public function rmdir($path, $options)
    {
        $this->path = $path;

        $dirname = Util::normalizePath($this->getPath());

        if ($dirname === '') {
            throw new RootViolationException('Root directories can not be deleted.');
        }

        $adapter = $this->getCube()->getDisk()->getAdapter();

        if ($options & STREAM_MKDIR_RECURSIVE) {
            return (bool) $adapter->deleteDir($dirname);
        }

        $contents = $this->getCube()->getDisk()->listContents($dirname);

        if (!empty($contents)) {
            throw new DirectoryNotEmptyException();
        }

        return (bool) $adapter->deleteDir($dirname);
    }

	public function url_stat($path, $flags)
    {
        $this->path = $path;

        try {
            return $this->getCube()->getDisk()->stat($this->getPath(), $flags);
        } catch (FileNotFoundException $e) {
            // File doesn't exist.
            if ( ! ($flags & STREAM_URL_STAT_QUIET)) {
                throw new \Exception($e);
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }

        return false;
    }

	public function dir_opendir(string $path, int $options) : bool
	{
		$this->path = $path;
		$path = Util::normalizePath($this->getPath());

		if ($this->dir = self::$cube->getDisk()->listContents($path)) {
			reset($this->dir);
			return true;
		}

		return false;
	}

	public function dir_closedir() : bool
    {
        unset($this->dir);

        return true;
    }

    public function dir_readdir()
	{
		if ($this->dir) {
			$current = current($this->dir);
			next($this->dir);
			return $current;
		}

		return false;
	}

	public function dir_rewinddir() : bool
	{
		if ($this->dir) {
			reset($this->dir);
			return true;
		}

		return false;
	}

	public function stream_open($path, $mode, $options, &$opened_path)
    {
    	$this->path = $path;

		$this->isReadOnly = static::modeIsReadOnly($mode);
        $this->isWriteOnly = static::modeIsWriteOnly($mode);
        $this->isAppendMode = static::modeIsAppendable($mode);

    	$this->handle = $this->getStream($path, $mode);

    	if ($this->handle && $options & STREAM_USE_PATH) {
            $opened_path = $path;
        }

        return is_resource($this->handle);
    }

    public function stream_read($count)
    {
        if ($this->isWriteOnly) {
            return '';
        }

        return fread($this->handle, $count);
    }

    public function stream_write($data)
    {
        if ($this->isReadOnly) {
            return 0;
        }
        $this->needsFlush = true;
        $this->ensureWritableHandle();

        // Enforce append semantics.
        if ($this->isAppendMode) {
            static::trySeek($this->handle, 0, SEEK_END);
        }

        $written = fwrite($this->handle, $data);
        $this->bytesWritten += $written;

        if (isset($this->streamWriteBuffer) && $this->bytesWritten >= $this->streamWriteBuffer) {
            $this->stream_flush();
        }

        return $written;
    }

    public function stream_close()
    {
        $this->stream_flush();

        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function stream_eof()
    {
        return feof($this->handle);
    }

	public function stream_cast($cast_as)
    {
        return $this->handle;
    }

    public function stream_flush()
    {
        if ( ! $this->needsFlush) {
            return true;
        }

        $this->needsFlush = false;
        $this->bytesWritten = 0;

        $pos = ftell($this->handle);

        $args = [$this->getPath(), $this->handle];
        $success = $this->getCube()->getDisk()->putStream($args);

        if (is_resource($this->handle)) {
            fseek($this->handle, $pos);
        }

        return $success;
    }

    public function stream_lock($operation)
    {
        $operation = (int) $operation;

        if (($operation & \LOCK_UN) === \LOCK_UN) {
            return $this->releaseLock($operation);
        }

        if (is_resource($this->lockHandle)) {
            return flock($this->lockHandle, $operation);
        }

        $this->lockHandle = $this->openLockHandle();

        return is_resource($this->lockHandle) && flock($this->lockHandle, $operation);
    }

    public function stream_metadata($uri, $option, $value)
    {
        $this->uri = $uri;

        switch ($option) {
            case STREAM_META_ACCESS:
				$permissions = octdec(substr(decoct($value), -4));
				$is_public   = $permissions & $this->getConfiguration('public_mask');
				$visibility  =  $is_public ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;

                try {
                    return $this->getCube()->getDisk()->setVisibility($this->getTarget(), $visibility);
                } catch (\LogicException $e) {
                    // The adapter doesn't support visibility.
                } catch (\Exception $e) {
                    $this->triggerError('chmod', $e);

                    return false;
                }

                return true;

            case STREAM_META_TOUCH:
            	return $this->getCube()->getDisk()->touch($this->getPath());
            default:
                return false;
        }
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                // This works for the local adapter. It doesn't do anything for
                // memory streams.
                return stream_set_blocking($this->handle, $arg1);

            case STREAM_OPTION_READ_TIMEOUT:
                return  stream_set_timeout($this->handle, $arg1, $arg2);

            case STREAM_OPTION_READ_BUFFER:
                if ($arg1 === STREAM_BUFFER_NONE) {
                    return stream_set_read_buffer($this->handle, 0) === 0;
                }

                return stream_set_read_buffer($this->handle, $arg2) === 0;

            case STREAM_OPTION_WRITE_BUFFER:
                $this->streamWriteBuffer = $arg1 === STREAM_BUFFER_NONE ? 0 : $arg2;

                return true;
        }

        return false;
    }

    public function stream_stat()
    {
        // Get metadata from original file.
        $stat = $this->url_stat($this->uri, static::STREAM_URL_IGNORE_SIZE | STREAM_URL_STAT_QUIET) ?: [];

        // Newly created file.
        if (empty($stat['mode'])) {
            $stat['mode'] = 0100000 + $this->getConfiguration('permissions')['file']['public'];
            $stat[2] = $stat['mode'];
        }

        // Use the size of our handle, since it could have been written to or
        // truncated.
        $stat['size'] = $stat[7] = static::getStreamSize($this->handle);

        return $stat;
    }

    public function stream_tell()
    {
        if ($this->isAppendMode) {
            return 0;
        }

        return ftell($this->handle);
    }

    public function stream_truncate($new_size)
    {
        if ($this->isReadOnly) {
            return false;
        }
        $this->needsFlush = true;
        $this->ensureWritableHandle();

        return ftruncate($this->handle, $new_size);
    }

    protected function getProtocol()
    {
        return Sr::before($this->path, '://');
    }

    public function getPrefix() : string
    {
    	$prefix = $this->getConfiguration('prefix');
    	return !empty($prefix) ? trim($prefix, '/').'/' : '';
    }

    protected function getPath(string $path = null)
    {
        if (!isset($path)) {
            $path = $this->path;
        }

        $path = Str::after($path, '://');

        return self::getPrefix().$path;
    }

    protected function getStream($path, $mode)
    {
        switch ($mode[0]) {
            case 'r':
                $this->needsCowCheck = true;

                return $this->getCube()->getDisk()->readStream($path);

            case 'w':
                $this->needsFlush = true;

                return fopen('php://temp', 'w+b');

            case 'a':
                return $this->getAppendStream($path);

            case 'x':
                return $this->getXStream($path);

            case 'c':
                return $this->getWritableStream($path);
        }

        return false;
    }

    protected function getWritableStream($path)
    {
        try {
            $handle = $this->getCube()->getDisk()->readStream($path);
            $this->needsCowCheck = true;
        } catch (FileNotFoundException $e) {
            $handle = fopen('php://temp', 'w+b');
            $this->needsFlush = true;
        }

        return $handle;
    }

    protected function getAppendStream($path)
    {
        if ($handle = $this->getWritableStream($path)) {
            StreamUtil::trySeek($handle, 0, SEEK_END);
        }

        return $handle;
    }

    protected function getXStream($path)
    {
        if ($this->getCube()->getDisk()->has($path)) {
            trigger_error('fopen(): failed to open stream: File exists', E_USER_WARNING);

            return false;
        }

        $this->needsFlush = true;

        return fopen('php://temp', 'w+b');
    }

    protected function ensureWritableHandle()
    {
        if ( ! $this->needsCowCheck) {
            return;
        }

        $this->needsCowCheck = false;

        if (static::isWritable($this->handle)) {
            return;
        }

        $this->handle = static::copyStream($this->handle);
    }

    public static function copyStream($stream, $close = true)
    {
        $cloned = fopen('php://temp', 'w+b');
        $pos = ftell($stream);

        static::tryRewind($stream);
        stream_copy_to_stream($stream, $cloned);

        if ($close) {
            fclose($stream);
        } else {
            static::trySeek($stream, $pos);
        }

        fseek($cloned, $pos);

        return $cloned;
    }

    public static function getStreamSize($stream)
    {
        $stat = fstat($stream);

        return $stat['size'];
    }

    public static function isWritable($stream)
    {
        return static::modeIsWritable(static::getMetaDataKey($stream, 'mode'));
    }

    public static function modeIsAppendable($mode)
    {
        return $mode[0] === 'a';
    }

    public static function modeIsAppendOnly($mode)
    {
        return $mode[0] === 'a' && strpos($mode, '+') === false;
    }

    public static function modeIsReadable($mode)
    {
        return $mode[0] === 'r' || strpos($mode, '+') !== false;
    }

    protected static function modeIsReadOnly($mode)
    {
    	return $mode[0] === 'r' && strpos($mode, '+') === false;
    }

    public static function modeIsWritable($mode)
    {
        return !static::modeIsReadOnly($mode);
    }

    public static function modeIsWriteOnly($mode)
    {
        return static::modeIsWritable($mode) && !static::modeIsReadable($mode);
    }

    public static function getMetaDataKey($stream, $key)
    {
        $meta = stream_get_meta_data($stream);

        return isset($meta[$key]) ? $meta[$key] : null;
    }

    public static function isAppendable($stream)
    {
        return static::modeIsAppendable(static::getMetaDataKey($stream, 'mode'));
    }

    public static function isReadable($stream)
    {
        return static::modeIsReadable(static::getMetaDataKey($stream, 'mode'));
    }

    public static function isSeekable($stream)
    {
        return (bool) static::getMetaDataKey($stream, 'seekable');
    }

    public static function tryRewind($stream)
    {
        return ftell($stream) === 0 || static::isSeekable($stream) && rewind($stream);
    }

    public static function trySeek($stream, $offset, $whence = SEEK_SET)
    {
        $offset = (int) $offset;

        if ($whence === SEEK_SET && ftell($stream) === $offset) {
            return true;
        }

        return static::isSeekable($stream) && fseek($stream, $offset, $whence) === 0;
    }

    protected function openLockHandle()
    {
        // PHP allows periods, '.', to be scheme names. Normalize the scheme
        // name to something that won't cause problems. Also, avoid problems
        // with case-insensitive filesystems. We use bin2hex() rather than a
        // hashing function since most scheme names are small, and bin2hex()
        // only doubles the string length.
        $sub_dir = bin2hex($this->getProtocol());

        // Since we're flattening out whole filesystems, at least create a
        // sub-directory for each scheme to attempt to reduce the number of
        // files per directory.
        $temp_dir = sys_get_temp_dir() . '/cube-stream-wrapper/' . $sub_dir;

        // Race free directory creation. If @mkdir() fails, fopen() will fail
        // later, so there's no reason to test again.
        ! is_dir($temp_dir) && @mkdir($temp_dir, 0777, true);

        // Normalize paths so that locks are consistent.
        // We are using sha1() to avoid the file name limits, and case
        // insensitivity on Windows. This is not security sensitive.
        $lock_key = sha1(Util::normalizePath($this->getPath()));

        // Relay the lock to a real filesystem lock.
        return fopen($temp_dir . '/' . $lock_key, 'c');
    }

    protected function releaseLock($operation)
    {
        $exists = is_resource($this->lockHandle);

        $success = $exists && flock($this->lockHandle, $operation);

        $exists && fclose($this->lockHandle);
        $this->lockHandle = null;

        return $success;
    }

    protected function getConfiguration($key = null)
    {
        return $key ? static::$config[$key] : static::$defaultConfig;
    }

    protected function isValidRename($source, $dest)
    {
        $adapter = $this->getCube()->getDisk()->getAdapter();
        if (!$adapter->has($source)) {
            throw new FileNotFoundException($source);
        }

        $subdir = Util::dirname($dest);

        if (strlen($subdir) && !$adapter->has($subdir)) {
            throw new FileNotFoundException($source);
        }

        if (!$adapter->has($dest)) {
            return true;
        }

        return $this->compareTypes($source, $dest);
    }

    protected function compareTypes($source, $dest)
    {
        $adapter = $this->getCube()->getDisk()->getAdapter();

        $source_type = $adapter->getMetadata($source)['type'];
        $dest_type = $adapter->getMetadata($dest)['type'];

        // These three checks are done in order of cost to minimize Flysystem
        // calls.

        // Don't allow overwriting different types.
        if ($source_type !== $dest_type) {
            if ($dest_type === 'dir') {
                throw new DirectoryExistsException();
            }

            throw new NotADirectoryException();
        }

        // Allow overwriting destination file.
        if ($source_type === 'file') {
            return $adapter->delete($dest);
        }

        // Allow overwriting destination directory if not empty.
        $contents = $this->getCube()->getDisk()->listContents($dest);
        if (!empty($contents)) {
            throw new DirectoryNotEmptyException();
        }

        return $adapter->deleteDir($dest);
    }
}
