<?php

namespace G4\Config;

use Zend\Config\Reader\Ini as Reader;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\ZendConfigProvider;

class Processor
{

    /**
     * @var array
     */
    private $data;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $section;

    /**
     * @var array
     */
    private $sections;

    private $useAggregator;

    /**
     * Processor constructor.
     * @param $path
     * @param $section
     */
    public function __construct($path, $section, $useAggregator)
    {
        $this->path             = $path;
        $this->section          = $section;
        $this->useAggregator    = $useAggregator;
    }

    public function process()
    {
        $this->useAggregator
            ? $this->readAggregation()
            : $this->readFromFile();

        $this->data = null !== $this->section
            ? $this->getSection($this->section)
            : $this->mergeSections();

        return $this->data;
    }

    private function getSection($name)
    {
        $this->processSections();

        if(!array_key_exists($name, $this->sections)) {
            throw new \Exception("Section '{$name}' missing");
        }

        $extends = false;
        $parentData = array();

        if($this->sections[$name] !== null) {
            $extends = true;
            $func = __FUNCTION__;
            $parentData = $this->$func($this->sections[$name]);
        }

        $sectionName = $extends === true
            ? $name . ':' . $this->sections[$name]
            : $name;

        $sectionData = $this->data[$sectionName];

        $newData = array_replace_recursive($parentData, $sectionData);

        ksort($newData);

        return $newData;
    }

    private function getSectionName($item)
    {
        if(substr_count($item, ":") > 1) { }
        $segments = explode(':', $item);
        return $segments[0];
    }

    private function mergeSections()
    {
        $tmpData = [];
        foreach($this->data as $sectionName => $data){
            $name = $this->getSectionName($sectionName);
            $tmpData[$name] = $this->getSection($name);
        }
        return $tmpData;
    }

    private function processSections()
    {
        $tmp = array_keys($this->data);

        foreach($tmp as $item) {
            if(substr_count($item, ":") > 1) { }

            $segments = explode(':', $item);

            $first = trim(array_shift($segments));

            $this->sections[$first] = !empty($segments)
                ? trim(array_shift($segments))
                : null;
        }

        return $this;
    }

    private function readAggregation()
    {
        $aggregator = new ConfigAggregator(
            [
                new ZendConfigProvider($this->path),
            ]
        );
        $this->data = $aggregator->getMergedConfig();
    }

    private function readFromFile()
    {
        $path = realpath($this->path);

        if(false === $path || !is_readable($path)) {
            throw new \Exception('Configuration file is not readable');
        }

        $reader = new Reader();
        $this->data = $reader->fromFile($path);
    }
}