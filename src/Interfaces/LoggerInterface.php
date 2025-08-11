<?php
namespace Robo2Import\Interfaces;

interface LoggerInterface
{
    public function log(string $message, ?int $code = null): void;
}