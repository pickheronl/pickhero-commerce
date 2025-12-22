<?php

namespace pickhero\commerce\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;

/**
 * Logging service for PickHero plugin operations
 * 
 * Logs are written to storage/logs/pickhero.log
 */
class Log extends Component
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_INFO = 'info';
    public const LEVEL_DEBUG = 'debug';

    private ?string $logFile = null;

    /**
     * Get the path to the log file
     */
    protected function getLogFile(): string
    {
        if ($this->logFile === null) {
            $this->logFile = Craft::$app->getPath()->getLogPath() . '/pickhero.log';
        }
        return $this->logFile;
    }

    /**
     * Log a message
     */
    public function log(string $message, string $level = self::LEVEL_INFO): void
    {
        $this->writeLog($message, $level);
    }

    /**
     * Log an error with optional exception details
     */
    public function error(string $message, ?\Throwable $exception = null): void
    {
        if ($exception !== null) {
            $message .= sprintf(
                "\n  Exception: [%s] %s\n  File: %s:%d\n  Trace: %s",
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }
        
        $this->writeLog($message, self::LEVEL_ERROR);
    }

    /**
     * Log a warning
     */
    public function warning(string $message): void
    {
        $this->writeLog($message, self::LEVEL_WARNING);
    }

    /**
     * Log a trace/debug message
     */
    public function trace(string $message): void
    {
        // Only log trace messages in devMode
        if (App::devMode()) {
            $this->writeLog($message, self::LEVEL_DEBUG);
        }
    }

    /**
     * Write a log entry to the file
     */
    protected function writeLog(string $message, string $level): void
    {
        $logFile = $this->getLogFile();
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            FileHelper::createDirectory($logDir);
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        $logEntry = "[{$timestamp}] [{$levelUpper}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get the log file path (for display in admin)
     */
    public function getLogFilePath(): string
    {
        return $this->getLogFile();
    }
}
