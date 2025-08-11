<?php
namespace Robo2Import;

use Robo2Import\Interfaces\ConfigInterface;

class Config implements ConfigInterface
{
    public string $version = '1.0';
    public string $host = 'https://robo2.x03.ru';
    public string $prefix;
    public string $apiKey;

    public function __construct(?string $prefix = null, ?string $apiKey = null)
    {
        if (!empty($prefix)) {
            $this->prefix = $prefix;
        }
        if (!empty($apiKey)) {
            $this->apiKey = $apiKey;
        }
    }
}