<?php

namespace Claroline\PluginBundle\Service\PluginManager;

use Symfony\Component\Yaml\Yaml;

class FileHandler
{
    private $pluginNamespacesFile;
    private $pluginBundlesFile;
    private $pluginRoutingFile;
    private $yamlHandler;

    public function __construct($pluginNamespacesFile,
                                $pluginBundlesFile,
                                $pluginRoutingFile,
                                Yaml $yamlHandler)
    {
        $this->setPluginNamespacesFile($pluginNamespacesFile);
        $this->setPluginBundlesFile($pluginBundlesFile);
        $this->setPluginRoutingFile($pluginRoutingFile);
        $this->yamlHandler = $yamlHandler;
    }

    public function setPluginNamespacesFile($filePath)
    {
        $this->assertFileIsWriteable($filePath);
        $this->pluginNamespacesFile = $filePath;
    }

    public function setPluginBundlesFile($filePath)
    {
        $this->assertFileIsWriteable($filePath);
        $this->pluginBundlesFile = $filePath;
    }

    public function setPluginRoutingFile($filePath)
    {
        $this->assertFileIsWriteable($filePath);
        $this->pluginRoutingFile = $filePath;
    }

    public function getPluginNamespacesFile()
    {
        return $this->pluginNamespacesFile;
    }

    public function getPluginBundlesFile()
    {
        return $this->pluginBundlesFile;
    }

    public function getPluginRoutingFile()
    {
        return $this->pluginRoutingFile;
    }

    public function getRegisteredNamespaces()
    {
        return file($this->pluginNamespacesFile, FILE_IGNORE_NEW_LINES);
    }

    public function getRegisteredBundles()
    {
        return file($this->pluginBundlesFile, FILE_IGNORE_NEW_LINES);
    }

    public function getSharedVendorNamespaces()
    {
        $vendors = array();

        foreach ($this->getRegisteredBundles() as $bundleName)
        {
            $nameParts = explode('\\', $bundleName);
            $vendors[] = $nameParts[0];
        }

        $uniqueVendors = array_unique($vendors);
        $sharedVendors = array();

        foreach ($uniqueVendors as $uniqueVendor)
        {
            $vendorCount = 0;

            foreach ($vendors as $vendor)
            {
                if ($vendor == $uniqueVendor)
                {
                    $vendorCount++;
                    if ($vendorCount > 1)
                    {
                        $sharedVendors[] = $vendor;
                    }
                }
            }
        }

        return $sharedVendors;
    }

    public function getRoutingResources()
    {
        $resources = $this->yamlHandler->parse($this->pluginRoutingFile);

        return (array) $resources;
    }

    public function registerNamespace($namespace)
    {
        $this->doAddItem($this->pluginNamespacesFile, $namespace, 'Namespace');
    }

    public function removeNamespace($namespace)
    {
        if (! in_array($namespace, $this->getSharedVendorNamespaces()))
        {
            $this->doRemoveItem($this->pluginNamespacesFile, $namespace);
        }
    }

    public function addInstantiableBundle($pluginFQCN)
    {
        $this->doAddItem($this->pluginBundlesFile, $pluginFQCN, 'Plugin FQCN');
    }

    public function removeInstantiableBundle($pluginFQCN)
    {
        $this->doRemoveItem($this->pluginBundlesFile, $pluginFQCN);
    }

    public function importRoutingResources($pluginFQCN, $paths)
    {
        $nameParts = explode('\\', $pluginFQCN);
        $vendor = $nameParts[0];
        $bundleName = $nameParts[1];
        $className = $nameParts[2];
        $resources = array();

        foreach ((array) $paths as $pathKey => $path)
        {
            
            $pattern = '#^(.+)'
                . preg_quote(DIRECTORY_SEPARATOR)
                . $vendor
                . preg_quote(DIRECTORY_SEPARATOR) 
                . $bundleName
                . preg_quote(DIRECTORY_SEPARATOR)
                . '(.+)$#';
            preg_match($pattern, $path, $matches);
            $relativePath = $matches[2];
            $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $key = "{$className}_{$pathKey}";
            $value = "@{$className}/{$normalizedPath}";
            $resources[$key] = array ('resource' => $value);
        }

        $resources = array_merge($this->getRoutingResources(), $resources);
        $yaml = $this->yamlHandler->dump($resources);

        file_put_contents($this->pluginRoutingFile, $yaml);
    }

    public function removeRoutingResources($pluginFQCN)
    {
        $nameParts = explode('\\', $pluginFQCN);
        $className = $nameParts[2];
        $resources = $this->yamlHandler->parse($this->pluginRoutingFile);

        foreach (array_keys($resources) as $key )
        {
            if (substr($key, 0, strlen($className)) == $className)
            {
                unset($resources[$key]);
            }
        }

        $yaml = $this->yamlHandler->dump($resources);
        file_put_contents($this->pluginRoutingFile, $yaml);
    }

    private function assertFileIsWriteable($file)
    {
        if (! file_exists($file))
        {
            if (! touch($file))
            {
                throw new \Exception("File '{$file}' not found.");
            }
        }

        if (! is_writable($file))
        {
            throw new \Exception("File '{$file}' is not writable.");
        }
    }

    private function doAddItem($file, $item, $itemType)
    {
        if (empty($item))
        {
            throw new \Exception("{$itemType} argument cannot be empty.");
        }

        $items = file($file, FILE_IGNORE_NEW_LINES);

        if (! in_array($item, $items))
        {
            $items[] = $item;
            file_put_contents($file, implode("\n", $items));
        }
    }

    private function doRemoveItem($file, $item)
    {
        $items = file($file, FILE_IGNORE_NEW_LINES);

        foreach ($items as $key => $value)
        {
            if ($value === $item)
            {
                unset($items[$key]);
            }
        }

        file_put_contents($file, implode("\n", $items));
    }
}
