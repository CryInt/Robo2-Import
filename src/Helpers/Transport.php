<?php
namespace Robo2Import\Helpers;

use CryCMS\CURL\CURL;
use Robo2Import\Interfaces\ConfigInterface;

class Transport
{
    protected ConfigInterface $config;

    public  function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function cUrl($params = [], $post = []): ?string
    {
        $location = $this->config->host . '/api/?' . http_build_query($params);

        if (!empty($post)) {
            $query = CURL::post($location)->data($post);
        }
        else {
            $query = CURL::get($location);
        }

        $query = $query->timeout(480)
            ->connectTimeout(480)
            ->ssl(false)
            ->header('Login', $this->config->prefix)
            ->header('Auth', md5($this->config->prefix . '+' . $this->config->apiKey));

        $response = $query->send();
        if ($response->isSuccess) {
            return $response->body;
        }

        return null;
    }
}