<?php

namespace Core;

/**
 * Production Error Handler
 * Logs errors to files for analysis using a standardized (JSON) format.
 */
class ErrorHandler {

    /**
     * Logs a message to the given log file.
     *
     * @param string $fileName The file to which the log is written.
     * @param string $message  The standardized (JSON) log message.
     */
    protected function handleMessage(string $fileName, string $message): void {
        // Sanitize file name to allow only letters, numbers, hyphens, and underscores.
        $safeFileName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $fileName);

        // Resolve the logs directory.
        $logsDir = realpath(__DIR__ . '/../logs');
        if ($logsDir === false) {
            // Fallback to relative path if realpath fails.
            $logsDir = __DIR__ . '/../logs';
        }

        // If directory doesn't exist, create it.
        if (!is_dir($logsDir)) {
            // Create the directory recursively with default permissions.
            mkdir($logsDir, 0777, true);
        }

        // Build the full file path.
        $path = $logsDir . DIRECTORY_SEPARATOR . $safeFileName . '.log';

        // Append the JSON message to the file (each record on its own line).
        file_put_contents($path, $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Converts an absolute path to a path relative to the project root.
     *
     * @param string $absolutePath The absolute file path.
     * @return string              The relative file path.
     */
    protected function relativePath(string $absolutePath): string {
        $projectRoot = $this->getProjectRoot();
        if (strpos($absolutePath, $projectRoot) === 0) {
            return substr($absolutePath, strlen($projectRoot) + 1);
        }
        return $absolutePath;
    }

    /**
     * Returns the project root directory.
     *
     * @return string The project root.
     */
    protected function getProjectRoot(): string {
        $root = realpath(__DIR__ . '/../');
        if ($root === false) {
            $root = __DIR__ . '/../';
        }
        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    /**
     * Generates a standardized JSON log message for an exception.
     *
     * @param \Throwable $ex The exception or error.
     * @return string The JSON formatted log message.
     */
    protected function generateExceptionLogMessage(\Throwable $ex): string {
        $timestamp = date('Y-m-d H:i:s');

        // Build the basic error details.
        $log = [
            "timestamp" => $timestamp,
            "type"      => "exception",
            "error"     => [
                "class"   => get_class($ex),
                "message" => $ex->getMessage(),
                "file"    => $this->relativePath($ex->getFile()),
                "line"    => $ex->getLine(),
                "stack"   => []
            ]
        ];

        // Format the stack trace so that file paths are relative.
        $trace = $ex->getTrace();
        if (!empty($trace)) {
            foreach ($trace as $index => $frame) {
                $file = isset($frame['file']) ? $this->relativePath($frame['file']) : '[internal function]';
                $line = $frame['line'] ?? null;
                $function = $frame['function'] ?? null;
                $class = $frame['class'] ?? null;

                // Build a structured frame record.
                $frameRecord = [
                    "index"    => $index,
                    "file"     => $file,
                    "line"     => $line,
                    "function" => $class ? $class . "->" . $function : $function,
                ];
                $log["error"]["stack"][] = $frameRecord;
            }
        } else {
            $log["error"]["stack"][] = "No stack trace available.";
        }

        // Include extra details if the exception is an instance of our custom Error.
        if ($ex instanceof Error) {
            $log["error"]["blame"] = $ex->getBlame();

            $humanDetails = $ex->getHumanDetails();
            if (!empty($humanDetails)) {
                $log["error"]["humanDetails"] = $humanDetails;
            }

            $log["error"]["machineCode"] = $ex->getMachineCode();

            $machineDetails = $ex->getMachineDetails();
            if (!empty($machineDetails)) {
                $log["error"]["machineDetails"] = $machineDetails;
            }
        }

        return json_encode($log, JSON_PRETTY_PRINT);
    }

    /**
     * Logs an exception in a structured manner.
     *
     * @param \Throwable $ex       The exception or error.
     * @param string     $fileName The file to which the log is written.
     */
    public function handleException(\Throwable $ex, string $fileName): void {
        $message = $this->generateExceptionLogMessage($ex);
        $this->handleMessage($fileName, $message);
    }

    /**
     * Logs a PHP error as a standardized JSON object.
     *
     * @param int    $errno   Error level.
     * @param string $errstr  Error message.
     * @param string $errfile Filename in which the error occurred.
     * @param int    $errline Line number on which the error occurred.
     */
    public function handlePhpError(int $errno, string $errstr, string $errfile, int $errline): void {
        $timestamp = date('Y-m-d H:i:s');
        $log = [
            "timestamp" => $timestamp,
            "type"      => "php_error",
            "error"     => [
                "code"    => $errno,
                "message" => $errstr,
                "file"    => $this->relativePath($errfile),
                "line"    => $errline,
            ]
        ];
        $this->handleMessage('php', json_encode($log, JSON_PRETTY_PRINT));
    }

    /**
     * Lets the application report what it is doing.
     *
     * @param string $message Message.
     */
    public function verbose(string $message): void {
        // This can be used by a mock or to output verbose logs.
    }
}