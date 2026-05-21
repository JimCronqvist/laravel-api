<?php

namespace Cronqvist\Tenancy\Identification;

class Http
{
    public static function host()
    {
        return request()->getHttpHost();
    }

    public static function refererHost()
    {
        $referer = request()->headers->get('referer');

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if(!is_string($refererHost)) {
            return null;
        }

        $refererScheme = parse_url($referer, PHP_URL_SCHEME);
        $refererPort = parse_url($referer, PHP_URL_PORT);

        if(($refererScheme == 'http' && $refererPort == 80) || ($refererScheme == 'https' && $refererPort == 443)) {
            return $refererHost;
        }

        return $refererHost . ':' . $refererPort;
    }

    public static function header($header = 'X-Tenant')
    {
        return request()->header($header, null);
    }

    public static function query($param = 'tenant')
    {
        return request()->query($param, null);
    }
}
