<?php

namespace Ruigweb\Cubes;

use Ruigweb\Needle\Contracts\Engine;
use Ruigweb\Cubes\Space;
use Ruigweb\Cubes\Cubes\Stream;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Exception;

class Finder implements \IteratorAggregate, \Countable
{
	protected $stream;
	protected $space;
	protected $finder;
	protected $engine;

	public function __construct(Space $space)
	{
		$this->space  = $space;
		$this->stream = $this->registerStream($space);
		$this->finder = (new SymfonyFinder)->ignoreUnreadableDirs()->in($this->space->getRealPath());
	}

	public function __call(string $method, array $arguments = [])
	{
		// Preventing of search in order directories than the current Space
		if (method_exists($this->finder, $method) && $method !== 'in') {
			$result = call_user_func_array([$this->finder, $method], $arguments);
			if ($result instanceof SymfonyFinder) {
				return $this;
			}

			return $result;
		}
	}

	public function getDirectories(...$args)
    {
        return $this->__call('directories', $args);
    }

	public function getSpace()
	{
		return $this->space;
	}

	public function count()
	{

	}

	public function getIterator()
	{
		return $this->finder->getIterator();
	}

	protected function registerStream(Space $space)
	{
		return Stream::register($this->getSpace()->getCube(), [
			'prefix' => $this->getSpace()->getPath()
		]);
	}
}
