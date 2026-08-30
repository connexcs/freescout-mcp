<?php

namespace Modules\McpServer\Http;

use Illuminate\Http\Response;
use Psr\Http\Message\ResponseInterface;

final class LaravelResponseFactory
{
    public function create(ResponseInterface $psrResponse): Response
    {
        $response = new Response((string) $psrResponse->getBody(), $psrResponse->getStatusCode());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $response->headers->set($name, $values, true);
        }

        return $response;
    }
}
