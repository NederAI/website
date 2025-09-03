<?php
namespace Core;

/**
 * Class File
 *
 * A utility class providing methods for file system operations.
 * It offers functionality for reading, writing, copying, deleting, and moving files or directories.
 */
class File {
    /**
     * Project root defined as the parent directory of the current file.
     *
     * @var string
     */
    protected $projectRoot;

    /**
     * Dependency injection container.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * A public list of mime types mapped from file extensions to their MIME.
     *
     * @var array
     */
    public $mimeTypes = [
        'html'         => 'text/html',
        'htm'          => 'text/html',
        'css'          => 'text/css',
        'js'           => 'application/javascript',
        'json'         => 'application/json',
        'jsonld'       => 'application/ld+json',
        'xml'          => 'application/xml',
        'webmanifest'  => 'application/manifest+json',
        'txt'          => 'text/plain',
        'csv'          => 'text/csv',
        'jpg'          => 'image/jpeg',
        'jpeg'         => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'bmp'          => 'image/bmp',
        'ico'          => 'image/vnd.microsoft.icon',
        'svg'          => 'image/svg+xml',
        'webp'         => 'image/webp',
        'avif'         => 'image/avif',
        'pdf'          => 'application/pdf',
        'zip'          => 'application/zip',
        'rar'          => 'application/x-rar-compressed',
    ];

    /**
     * Constructor.
     *
     * @param Container $container Dependency injection container.
     */
    public function __construct(Container $container) {
        $this->projectRoot = realpath(__DIR__ . '/../');
        $this->container = $container;
    }

    /**
     * Resolve a Linux-style relative path to an absolute file system path.
     *
     * @param string $relativePath The relative path.
     * @return string The resolved absolute path.
     */
    protected function resolveFilePath(string $relativePath): string {
        $relativePath = ltrim($relativePath, '/\\');
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        return $this->projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path The relative path.
     * @return bool
     */
    public function exists(string $path): bool {
        return file_exists($this->resolveFilePath($path));
    }

    /**
     * Determine if the given path refers to a file.
     *
     * @param string $path The relative file path.
     * @return bool
     */
    public function isFile(string $path): bool {
        return is_file($this->resolveFilePath($path));
    }

    /**
     * Determine if the given path refers to a directory.
     *
     * @param string $path The relative path.
     * @return bool
     */
    public function isDirectory(string $path): bool {
        return is_dir($this->resolveFilePath($path));
    }

    /**
     * Read the contents of a file.
     *
     * @param string $path Relative path to the file.
     * @return string File contents.
     * @throws Error If the file does not exist, is not a file, or cannot be read.
     */
    public function read(string $path): string {
        $resolved = $this->resolveFilePath($path);
        if (!$this->exists($path)) {
            throw new Error(
                'system',
                "File not found",
                "The file at path '{$path}' could not be located.",
                ['path' => $path]
            );
        }
        if (!$this->isFile($path)) {
            throw new Error(
                'system',
                "Path is not a file",
                "The specified path '{$path}' is not a file.",
                ['path' => $path]
            );
        }
        $contents = file_get_contents($resolved);
        if ($contents === false) {
            throw new Error(
                'system',
                "Failed to read file",
                "An error occurred while reading the file at '{$path}'.",
                ['path' => $path]
            );
        }
        return $contents;
    }

    /**
     * Write data to a file (overwriting any existing content).
     *
     * @param string $path Relative path to the file.
     * @param string $data Data to write.
     * @return int Number of bytes written.
     * @throws Error If the directory cannot be created or file cannot be written.
     */
    public function write(string $path, string $data): int {
        $resolved = $this->resolveFilePath($path);
        // Ensure the directory exists.
        $dir = dirname($resolved);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Error(
                    'system',
                    "Failed to create directory for file",
                    "Unable to create directory for path '{$path}'.",
                    ['path' => $path],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        $written = file_put_contents($resolved, $data);
        if ($written === false) {
            throw new Error(
                'system',
                "Failed to write to file",
                "Could not write to file at path '{$path}'.",
                ['path' => $path]
            );
        }
        return $written;
    }

    /**
     * Append data to an existing file.
     *
     * @param string $path Relative path to the file.
     * @param string $data Data to append.
     * @return int Number of bytes written.
     * @throws Error If the directory cannot be created or file cannot be appended.
     */
    public function append(string $path, string $data): int {
        $resolved = $this->resolveFilePath($path);
        // Ensure the directory exists.
        $dir = dirname($resolved);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Error(
                    'system',
                    "Failed to create directory for file",
                    "Unable to create directory for path '{$path}' when appending.",
                    ['path' => $path],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        $written = file_put_contents($resolved, $data, FILE_APPEND);
        if ($written === false) {
            throw new Error(
                'system',
                "Failed to append to file",
                "Could not append to file at path '{$path}'.",
                ['path' => $path]
            );
        }
        return $written;
    }

    /**
     * Delete a file or recursively delete a directory.
     *
     * @param string $path Relative path to the file or directory.
     * @return bool True on success.
     * @throws Error If the file or directory does not exist or cannot be deleted.
     */
    public function delete(string $path): bool {
        $resolved = $this->resolveFilePath($path);
        if (!$this->exists($path)) {
            throw new Error(
                'system',
                "File or directory does not exist",
                "No file or directory found at path '{$path}'.",
                ['path' => $path]
            );
        }
        if ($this->isDirectory($path)) {
            $this->deleteDirectory($resolved);
        } else {
            if (!unlink($resolved)) {
                throw new Error(
                    'system',
                    "Failed to delete file",
                    "The file at path '{$path}' could not be deleted.",
                    ['path' => $path]
                );
            }
        }
        return true;
    }

    /**
     * Helper method to recursively delete a directory.
     *
     * @param string $dir Absolute path to the directory.
     * @throws Error If a file or subdirectory cannot be deleted.
     */
    protected function deleteDirectory(string $dir): void {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                if (!unlink($path)) {
                    throw new Error(
                        'system',
                        "Failed to delete file",
                        "Unable to delete file at '{$path}'.",
                        ['path' => $path]
                    );
                }
            }
        }
        if (!rmdir($dir)) {
            throw new Error(
                'system',
                "Failed to remove directory",
                "Could not remove directory '{$dir}'.",
                ['directory' => $dir]
            );
        }
    }

    /**
     * Rename a file or directory.
     *
     * @param string $oldPath Relative source path.
     * @param string $newPath Relative target path.
     * @return bool True on success.
     * @throws Error If the source does not exist, target directory cannot be created, or move fails.
     */
    public function rename(string $oldPath, string $newPath): bool {
        $resolvedOld = $this->resolveFilePath($oldPath);
        $resolvedNew = $this->resolveFilePath($newPath);
        if (!$this->exists($oldPath)) {
            throw new Error(
                'system',
                "Source file or directory does not exist",
                "No file or directory found at source path '{$oldPath}'.",
                ['source' => $oldPath]
            );
        }
        $newDir = dirname($resolvedNew);
        if (!is_dir($newDir)) {
            if (!mkdir($newDir, 0755, true)) {
                throw new Error(
                    'system',
                    "Failed to create directory for target",
                    "Unable to create directory for target path '{$newPath}'.",
                    ['target' => $newPath],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        if (!rename($resolvedOld, $resolvedNew)) {
            throw new Error(
                'system',
                "Failed to move",
                "Unable to move from '{$oldPath}' to '{$newPath}'.",
                ['source' => $oldPath, 'target' => $newPath]
            );
        }
        return true;
    }

    /**
     * Move a file or directory.
     *
     * @param string $oldPath Relative source path.
     * @param string $newPath Relative target path.
     * @return bool True on success.
     * @throws Error If the source does not exist, target directory cannot be created, or move fails.
     */
    public function move(string $oldPath, string $newPath): bool {
        return $this->rename($oldPath, $newPath);
    }

    /**
     * Copy a file or directory.
     *
     * @param string $sourcePath Relative source path.
     * @param string $destPath Relative destination path.
     * @return bool True on success.
     * @throws Error If the source does not exist, destination directory cannot be created, or copy fails.
     */
    public function copy(string $sourcePath, string $destPath): bool {
        $resolvedSource = $this->resolveFilePath($sourcePath);
        $resolvedDest = $this->resolveFilePath($destPath);
        if (!$this->exists($sourcePath)) {
            throw new Error(
                'system',
                "Source file or directory does not exist",
                "No file or directory found at source path '{$sourcePath}'.",
                ['source' => $sourcePath]
            );
        }
        $destDir = dirname($resolvedDest);
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                throw new Error(
                    'system',
                    "Failed to create directory for target",
                    "Unable to create directory for destination path '{$destPath}'.",
                    ['destination' => $destPath],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        if (is_dir($resolvedSource)) {
            $this->copyDirectory($resolvedSource, $resolvedDest);
        } else {
            if (!copy($resolvedSource, $resolvedDest)) {
                throw new Error(
                    'system',
                    "Failed to copy file",
                    "Could not copy file from '{$sourcePath}' to '{$destPath}'.",
                    ['source' => $sourcePath, 'destination' => $destPath]
                );
            }
        }
        return true;
    }

    /**
     * Helper method to recursively copy a directory.
     *
     * @param string $source Absolute source directory.
     * @param string $dest Absolute destination directory.
     * @throws Error If a target directory cannot be created or a file fails to copy.
     */
    protected function copyDirectory(string $source, string $dest): void {
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true)) {
                throw new Error(
                    'system',
                    "Failed to create target directory",
                    "Unable to create destination directory '{$dest}'.",
                    ['destination' => $dest],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcItem = rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
            $destItem = rtrim($dest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
            if (is_dir($srcItem)) {
                $this->copyDirectory($srcItem, $destItem);
            } else {
                if (!copy($srcItem, $destItem)) {
                    throw new Error(
                        'system',
                        "Failed to copy file",
                        "Could not copy file from '{$srcItem}' to '{$destItem}'.",
                        ['source' => $srcItem, 'destination' => $destItem]
                    );
                }
            }
        }
    }

    /**
     * Create a new directory.
     *
     * @param string $path Relative path where the directory should be created.
     * @param int $mode Permissions mode (default is 0755).
     * @param bool $recursive Whether to create directories recursively.
     * @return bool True if successful.
     * @throws Error If the directory cannot be created.
     */
    public function createDirectory(string $path, int $mode = 0755, bool $recursive = true): bool {
        $resolved = $this->resolveFilePath($path);
        if (!is_dir($resolved)) {
            if (!mkdir($resolved, $mode, $recursive)) {
                throw new Error(
                    'system',
                    "Failed to create directory",
                    "Could not create directory at path '{$path}'.",
                    ['path' => $path],
                    500,
                    'dir_mkdir_failure'
                );
            }
        }
        return true;
    }

    /**
     * List the contents of a directory (excluding "." and ".." entries).
     *
     * @param string $path Relative directory path.
     * @return array Array of items (files and directories) contained in the directory.
     * @throws Error If the directory does not exist or the path is not a directory.
     */
    public function listDirectory(string $path): array {
        $resolved = $this->resolveFilePath($path);
        if (!$this->exists($path)) {
            throw new Error(
                'system',
                "Directory does not exist",
                "No directory found at path '{$path}'.",
                ['path' => $path]
            );
        }
        if (!$this->isDirectory($path)) {
            throw new Error(
                'system',
                "Path is not a directory",
                "The path '{$path}' is not a directory.",
                ['path' => $path]
            );
        }
        $contents = array_diff(scandir($resolved), ['.', '..']);
        return array_values($contents);
    }

    /**
     * Get the MIME type of a file based on its extension.
     *
     * @param string $path Relative path to the file.
     * @return string MIME type.
     */
    public function getMime(string $path): string {
        $extension = substr(strrchr($path, '.'), 1);
        if (isset($this->mimeTypes[$extension])) {
            return $this->mimeTypes[$extension];
        }
        return 'application/octet-stream';
    }
}