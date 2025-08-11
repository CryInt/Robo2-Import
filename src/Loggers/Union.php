<?php
namespace Robo2Import\Loggers;

use Robo2Import\Interfaces\LoggerInterface;

class Union implements LoggerInterface
{
    protected array $loggers = [];

    public function setLogger(LoggerInterface $logger): void
    {
        $this->loggers[] = $logger;
    }

    public function log(string $message, ?int $code = null): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($message, $code);
        }
    }
}