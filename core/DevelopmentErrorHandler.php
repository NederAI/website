<?php

namespace Core;

class DevelopmentErrorHandler extends ErrorHandler {
    /**
     * Overrides final ErrorHandler method
     * Instead of logging the error, echo the message.
     *
     * @param string $fileName The file to which the log would normally be written.
     * @param string $message  The log message.
     */
    protected function handleMessage(string $fileName, string $message): void {
        $date = date('Y-m-d H:i:s');
        echo "<pre class=\"dev-error\">\n[$date] in $fileName\n$message\n</pre>\n";
    }
}