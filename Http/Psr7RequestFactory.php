<?php

namespace Modules\McpServer\Http;

use Illuminate\Http\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;

final class Psr7RequestFactory
{
    public function create(Request $request): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        $psrRequest = $factory->createServerRequest(
            $request->getMethod(),
            $request->fullUrl(),
            $request->server->all()
        );

        foreach ($request->headers->all() as $name => $values) {
            $psrRequest = $psrRequest->withHeader($name, $values);
        }

        return $psrRequest
            ->withBody($factory->createStream($request->getContent()))
            ->withQueryParams($request->query->all())
            ->withCookieParams($request->cookies->all());
    }
}
