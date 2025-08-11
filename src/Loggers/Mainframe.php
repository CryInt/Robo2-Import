<?php
namespace Robo2Import\Loggers;

use Robo2Import\Helpers\Transport;
use Robo2Import\Interfaces\LoggerInterface;

class Mainframe implements LoggerInterface
{
    protected Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    public function log(string $message, ?int $code = null): void
    {
        $this->transport->cUrl(['type' => 'log'], ['text' => $message, 'code' => $code]);
    }
}