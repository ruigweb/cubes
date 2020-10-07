<?php

namespace Ruigweb\Cubes;

use Ruigweb\Cubes\Contracts\Cube;

class Manager
{
    protected $default;

    protected $cubes = [];

    public function __construct($cubes = null, string $default = null)
    {
        if ($cubes) {
            if (!is_array($cubes)) {
                $cubes = [$cubes];
            }

            foreach ($cubes as $cube) {
                $this->addCube($cube);
            }

            if ($default) {
                $this->setDefaultCube($default);
            }
        }
    }

    public function setDefaultCube($cube)
    {
        if ($this->hasCube($cube)) {
            $cube = $this->getCube($cube);
            $this->default = $cube->name;
        }

        return $this;
    }

    public function getDefaultStorage() :? Cube
    {
        return $this->getCube($this->default);
    }

    public function addCube(Cube $cube)
    {
        $this->cubes[] = $cube;
        return $this;
    }

    public function hasCube(string $name) : bool
    {
        return collect($this->cubes)->filter(function($cube) use($name) {
            return $cube->name === $name;
        })->count() > 0;
    }

    public function getCube(string $name) :? Cube
    {
        foreach ($this->cubes as $cube) {
            if ($cube->name === $name) {
                return $cube;
            }

            return null;
        }
    }
}