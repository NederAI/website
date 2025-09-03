<?php
namespace Core;

class Container {
    protected $namespaces = [];
    protected $instances = []; // Storage for singleton instances
    protected $mocks = [];     // Storage for mock overrides (always strings)

    /**
     * Register a mapping of namespace prefixes to file system paths.
     */
    public function registerNamespaces(array $namespaces) {
        // Store keys in lower-case for case-insensitive matching.
        $this->namespaces = array_change_key_case($namespaces, CASE_LOWER);
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Autoload classes based on the registered namespace mappings.
     */
    public function autoload($class) {
        // Use the original and lower-cased version as needed.
        $classLower = strtolower($class);
        foreach ($this->namespaces as $ns => $baseDir) {
            // Make sure to compare lower-case (the registered keys are lower-case).
            if (strpos($classLower, $ns . '\\') === 0) {
                // Get the relative class name.
                $relativeClass = substr($class, strlen($ns) + 1);
                // Replace namespace separators with directory separators
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                // Build the expected file path
                $file = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
                // Use our new function to resolve the real path irrespective of case.
                if ($foundFile = $this->findCaseInsensitivePath($file)) {
                    require_once $foundFile;
                }
                return;
            }
        }
    }

    /**
     * Recursively resolve a file path in a case-insensitive way.
     *
     * For each part of the given path, scan the directory for a matching entry 
     * regardless of case. If every path segment is found, returns the actual path.
     *
     * @param string $path The full path to resolve.
     * @return string|false The resolved path if found, false if not.
     */
    protected function findCaseInsensitivePath($path) {
        // Normalize path separators.
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        // Explode the path into parts.
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        // If the file path starts with a drive letter (Windows) or a leading slash,
        // keep that in the beginning.
        $resolved = '';
        if (strpos($parts[0], ':') !== false || ($parts[0] === '' && isset($parts[1]))) {
            $resolved = array_shift($parts) . DIRECTORY_SEPARATOR;
        }
        // Traverse through each part.
        foreach ($parts as $part) {
            if ($resolved === '') {
                $currentDir = '.';
            } else {
                $currentDir = $resolved;
            }
            if (!is_dir($currentDir) && !file_exists($currentDir)) {
                return false;
            }
            $found = false;
            // Scan the current directory for a matching entry.
            foreach (scandir($currentDir) as $entry) {
                if (strtolower($entry) === strtolower($part)) {
                    // Append the found directory or file.
                    $resolved = rtrim($currentDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return $resolved;
    }

    /**
     * Register a mock override for a class.
     *
     * The $class argument is the original class name and 
     * $mock must be a fully qualified class name (string).
     */
    public function setMock($class, string $mock) {
        $this->mocks[$class] = $mock;
        // If a singleton instance was already created for this class,
        // remove it so that subsequent get() calls create a new mock instance.
        if (isset($this->instances[$class])) {
            unset($this->instances[$class]);
        }
    }

    /**
     * Retrieve the class name to instantiate.
     *
     * This helper returns either the mock (if one has been set)
     * or the original class name.
     */
    protected function resolveClass($class) {
        if (isset($this->mocks[$class])) {
            return $this->mocks[$class];
        }
        return $class;
    }

    /**
     * Retrieve an instance of a class.
     *
     * By default, if no second parameter is specified, the class is treated as a singleton.
     * Use $singleton = false to get a new instance.
     *
     * Throws an error if the class cannot be found.
     */
    public function get($class, $singleton = true) {
        $resolved = $this->resolveClass($class);
        
        if (!class_exists($resolved)) {
            // Build a human-friendly and machine-readable error message.
            $humanDetails = "The requested class '{$class}' (resolved as '{$resolved}') was not found. " .
                             "Check your class name, namespaces, or mock configuration.";
            $machineDetails = [
                "called_class" => $class,
                "resolved_class" => $resolved,
                "registered_namespaces" => array_keys($this->namespaces)
            ];
            throw new Error('system', "Class not found", $humanDetails, $machineDetails);
        }

        if ($singleton) {
            if (!isset($this->instances[$class])) {
                $this->instances[$class] = new $resolved($this);
            }
            return $this->instances[$class];
        }
        // Create a new instance (non-singleton).
        return new $resolved($this);
    }
}