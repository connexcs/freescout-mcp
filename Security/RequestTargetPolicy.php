<?php

namespace Modules\McpServer\Security;

final class RequestTargetPolicy
{
    /** @var string[] */
    private $hosts;
    /** @var string[] */
    private $origins;

    /** @param string[] $hosts @param string[] $origins */
    public function __construct(array $hosts, array $origins)
    {
        $this->hosts = array_values(array_unique(array_filter(array_map([$this, 'normalizeConfiguredHost'], $hosts))));
        $this->origins = array_values(array_unique(array_filter(array_map([$this, 'normalizeOrigin'], $origins))));
    }

    public function allowsHost(string $host): bool
    {
        if ('' === trim($host) || 1 === preg_match('/[\s,]/', $host)) {
            return false;
        }

        $parts = parse_url('http://'.$host);
        if (false === $parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535))
        ) {
            return false;
        }

        return in_array(strtolower(trim($parts['host'], '[]')), $this->hosts, true);
    }

    public function allowsOrigin(?string $origin): bool
    {
        if (null === $origin || '' === $origin) {
            return true;
        }
        if (in_array('*', $this->origins, true)) {
            return null !== $this->normalizeOrigin($origin);
        }

        $normalized = $this->normalizeOrigin($origin);

        return null !== $normalized && in_array($normalized, $this->origins, true);
    }

    private function normalizeConfiguredHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ('' === $host || '*' === $host || 1 === preg_match('/[\s,\/]/', $host)) {
            return null;
        }
        $parts = parse_url('http://'.$host);
        if (false === $parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535))
        ) {
            return null;
        }

        return strtolower(trim($parts['host'], '[]'));
    }

    private function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ('*' === $origin) {
            return '*';
        }
        if ('' === $origin || 1 === preg_match('/[\x00-\x20\x7f,]/', $origin)) {
            return null;
        }
        $parts = parse_url($origin);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['path']) && !in_array($parts['path'], ['', '/'], true))
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (false !== strpos($host, ':')) {
            $host = '['.trim($host, '[]').']';
        }

        return strtolower($parts['scheme']).'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
