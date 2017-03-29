<?php

namespace G4\Config;


class Config
{
    private $cachingEnabled = false;

    private $cachePath;

    private $data;

    private $path;

    private $section;

    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    public function setSection($section)
    {
        $this->section = $section;
        return $this;
    }

    public function setCachingEnabled($flag)
    {
        $this->cachingEnabled = (bool) $flag;
        return $this;
    }

    public function setCachePath($cachePath)
    {
        $this->cachePath = (string) $cachePath;
        return $this;
    }

    private function formatCacheFilename()
    {
        $segments = array(
            $this->path,
            __NAMESPACE__,
            __CLASS__,
            $this->section,
        );

        // section can be empty, so remove it
        $filename = md5(implode('~', array_filter($segments)));

        if(!is_writable($this->cachePath)) {
            throw new \Exception('Cache file path is not writable');
        }

        return $this->cachePath . $filename;
    }

    private function getFromCache()
    {
        $realCachePath = realpath($this->formatCacheFilename());

        if(false === $realCachePath) {
            return null;
        }

        if(!is_readable($realCachePath)) {
            throw new \Exception('Cache file path is not readable');
        }

        return require($realCachePath);
    }

    private function setToCache()
    {
        $realCachePath = $this->formatCacheFilename();

        $toSave = "<?php return \n" . var_export($this->data, true) . ';';

        if(!touch($realCachePath)) {
            throw new \Exception('Cache file path is not writable');
        }

        file_put_contents($realCachePath, $toSave);

        return $this;
    }

    private function process()
    {
        $this->data = (new Processor($this->path, $this->section))->process();

        return $this;
    }

    public function getData($force = false)
    {
        if($force !== true && $this->cachingEnabled) {
            $this->data = $this->getFromCache();
        }

        if(null === $this->data) {
            $this->process();

            if($this->cachingEnabled) {
                $this->setToCache();
            }
        }

        return $this->data;
    }
}