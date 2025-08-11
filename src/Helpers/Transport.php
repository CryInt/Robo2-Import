<?php
namespace Robo2Import\Helpers;

use Robo2Import\Interfaces\ConfigInterface;

class Transport
{
    protected ConfigInterface $config;

    public  function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function cUrl($params = [], $post = [])
    {
        $ch = curl_init();

        $params['api'] = $this->config->version;

        curl_setopt($ch, CURLOPT_URL, $this->config->host . '/api/?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 480);
        curl_setopt($ch, CURLOPT_TIMEOUT, 480);

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Login: ' . $this->config->prefix,
            'Auth: '.md5($this->config->prefix . '+' . $this->config->apiKey)
        ]);

        if (!empty($post) && is_array($post) && count($post) > 0) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300) ? $data : false;
    }
}