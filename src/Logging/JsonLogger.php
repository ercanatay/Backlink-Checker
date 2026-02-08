<?php

declare(strict_types=1);

namespace BacklinkChecker\Logging;

final class JsonLogger
{
    public function __construct(private readonly string $logFile)
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        file_put_contents($this->logFile, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
}
