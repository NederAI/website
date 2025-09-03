<?php
namespace Core;

abstract class BaseFactory
{
    // The factory type. Each extending factory must set this, e.g. "MyType" so that
    // its files are under app/MyType/
    protected $factoryType;

    public function __construct()
    {
        if (empty($this->factoryType)) {
            throw new \Exception('Factory type not set. Please set the $factoryType property in your factory.');
        }
    }

    // Returns the absolute path to the factory base folder, i.e. app/{FactoryType}/.
    protected function getBaseFolder(): string
    {
        return realpath(__DIR__ . '/../' . $this->factoryType . '/');
    }

    // Lists the items (files and directories) in the given subdirectory within the factory folder.
    // Pass an empty string to list the root items in the factory folder.
    public function listItems(string $directory = ''): array
    {
        $folder = $this->getBaseFolder() . $directory;

        if (!is_dir($folder)) {
            throw new \Exception("Directory not found: {$folder}");
        }

        // scandir returns '.' and '..' among its results so we filter them out.
        $items = scandir($folder);
        $list  = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $list[] = $item;
        }

        return $list;
    }

    // Retrieves the contents of a file.
    // The supplied $filePath may either start with the factory type or not.
    public function getFile(string $filePath): string
    {
        // Normalize: if the file path starts with the factory type (e.g. "MyType/"),
        // remove it so we don’t end up with a duplicate folder segment.
        $factoryPrefix = $this->factoryType . '/';
        if (strpos($filePath, $factoryPrefix) === 0) {
            $filePath = substr($filePath, strlen($factoryPrefix));
        }

        $fullPath = $this->getBaseFolder() . $filePath;

        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$fullPath}");
        }

        return file_get_contents($fullPath);
    }
}