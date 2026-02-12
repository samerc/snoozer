<?php

class Logger
{
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';

    private static $logFile = null;
    private static $minLevel = self::INFO;

    private static $levelPriority = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4
    ];

    /**
     * Initialize the logger with a log file path.
     * If not called, logs go to PHP's error_log.
     *
     * @param string $logFile Path to log file
     * @param string $minLevel Minimum level to log (default: INFO)
     */
    public static function init($logFile = null, $minLevel = self::INFO)
    {
        self::$logFile = $logFile;
        self::$minLevel = $minLevel;

        // Create log directory if it doesn't exist
        if ($logFile) {
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Log a message with context.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function log($level, $message, array $context = [])
    {
        // Check if this level should be logged
        if (self::$levelPriority[$level] < self::$levelPriority[self::$minLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        // Format: [timestamp] [LEVEL] message {context}
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        if (self::$logFile) {
            file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        } else {
            error_log(trim($logLine));
        }
    }

    /**
     * Log a debug message.
     */
    public static function debug($message, array $context = [])
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     */
    public static function info($message, array $context = [])
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning($message, array $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error($message, array $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log a critical message.
     */
    public static function critical($message, array $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Log an exception with full details.
     *
     * @param Exception $e The exception
     * @param string $message Additional message
     * @param array $context Additional context
     */
    public static function exception($e, $message = '', array $context = [])
    {
        $context['exception'] = get_class($e);
        $context['exception_message'] = $e->getMessage();
        $context['exception_file'] = $e->getFile();
        $context['exception_line'] = $e->getLine();

        $fullMessage = $message ? "{$message}: {$e->getMessage()}" : $e->getMessage();
        self::error($fullMessage, $context);
    }
}
