<?php

namespace G4\Config;

use Laminas\Config\Reader\Ini as Reader;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\LaminasConfigProvider;

class Processor
{
    const VAULT_KEYWORD = 'vault';
    const VAULT_URL_KEYWORD = 'url';
    const VAULT_API_TOKEN_KEYWORD = 'token';

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
     * @var VaultRepository
     */
    private $vaultRepository;
    /**
     * @var array
     */
    private $vaultVariables;


    /**
     * Processor constructor.
     * @param $path
     * @param $section
     * @param $useAggregator
     */
    public function __construct($path, $section, $useAggregator)
    {
        $this->path = $path;
        $this->section = $section;
        $this->useAggregator = $useAggregator;
    }

    public function process()
    {
        $this->useAggregator
            ? $this->readAggregation()
            : $this->readFromFile();

        $this->data = null !== $this->section
            ? $this->getSection($this->section)
            : $this->mergeSections();

        $this->getVaultVariables();

        if (empty($this->vaultVariables)) {
            return $this->data;
        }

        $this->setVaultRepo();

        $this->replaceVaultVariablesWithData($this->data, $this->getValuesFromVault());

        return $this->data;
    }

    private function getSection($name)
    {
        $this->processSections();

        if (!array_key_exists($name, $this->sections)) {
            throw new \Exception("Section '{$name}' missing");
        }

        $extends = false;
        $parentData = array();

        if ($this->sections[$name] !== null) {
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
        foreach ($this->data as $sectionName => $data) {
            $name = $this->getSectionName($sectionName);
            $tmpData[$name] = $this->getSection($name);
        }
        return $tmpData;
    }

    private function processSections()
    {
        $tmp = array_keys($this->data);

        foreach ($tmp as $item) {
            if (substr_count($item, ":") > 1) {
            }

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
                new LaminasConfigProvider($this->path),
            ]
        );
        $this->data = $aggregator->getMergedConfig();
    }

    private function readFromFile()
    {
        $path = realpath($this->path);

        if (false === $path || !is_readable($path)) {
            throw new \Exception('Configuration file is not readable');
        }

        $reader = new Reader();
        $this->data = $reader->fromFile($path);
    }

    private function getVaultVariables()
    {
        $this->vaultVariables = $this->filterMultiArray($this->data);
    }

    public function filterMultiArray(array $data)
    {
        $filteredArray = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                $filteredSubArray = $this->filterMultiArray($value);
                $filteredArray = array_merge($filteredArray, $filteredSubArray);
            } elseif (substr($value, 0, strlen(self::VAULT_KEYWORD)) === self::VAULT_KEYWORD) {
                $filteredArray[] = $value;
            }
        }

        return $filteredArray;
    }

    private function sortVariablesByRouteAndKey()
    {
        //todo maybe remove strtolower when variables are stored in apper case in vault.
        $sorted = [];
        foreach ($this->vaultVariables as $vaultVariable) {
            $array = explode('/', strtolower($vaultVariable));
            $secretKey = array_pop($array);
            array_shift($array);
            $secretRoute = implode('/', $array);
            $sorted[$secretRoute][] = $secretKey;
        }

        return $sorted;
    }

    private function getValuesFromVault()
    {
        $sorted = $this->sortVariablesByRouteAndKey();

        $data = [];

        foreach ($sorted as $section => $secretKeys) {
            $valuesForSection = $this->vaultRepository->getValueBySection($section);
            foreach ($secretKeys as $secretKey) {
                $data[self::VAULT_KEYWORD . '/' . $section . '/' . $secretKey] = $valuesForSection[$secretKey] ?? '';
            }
        }

        return $data;
    }

    public function replaceVaultVariablesWithData(&$array, $searchArray)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->replaceVaultVariablesWithData($value, $searchArray);
            } else {
                if (array_key_exists($value, $searchArray)) {
                    $value = $searchArray[$value];
                }
            }
        }
    }

    private function setVaultRepo()
    {
        $message = 'No config found for Vault %s ';

        if(!isset($this->data[self::VAULT_KEYWORD][self::VAULT_URL_KEYWORD])){
            throw new \RuntimeException(sprintf($message, self::VAULT_URL_KEYWORD));
        }
        if(!isset($this->data[self::VAULT_KEYWORD][self::VAULT_API_TOKEN_KEYWORD])){
            throw new \RuntimeException(sprintf($message, self::VAULT_API_TOKEN_KEYWORD));
        }

        $this->vaultRepository =  new VaultRepository(
            $this->data[self::VAULT_KEYWORD][self::VAULT_URL_KEYWORD],
            $this->data[self::VAULT_KEYWORD][self::VAULT_API_TOKEN_KEYWORD]);
    }
}