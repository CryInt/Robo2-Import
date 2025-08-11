<?php
namespace Robo2Import\Loggers;

use Robo2Import\Interfaces\LoggerInterface;

class Stdout implements LoggerInterface
{
    public function log(string $message, ?int $code = null): void
    {
        echo '[' . date('Y-m-d H:i:s') . '] [' . ($code ?? '-') . '] ' . $message . PHP_EOL;
    }
}