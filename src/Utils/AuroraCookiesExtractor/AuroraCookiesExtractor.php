<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor;

use Symfony\Contracts\HttpClient\ResponseInterface;

class AuroraCookiesExtractor
{
    public function extractFromSymfonyResponseInterface(ResponseInterface $responseInterface): array
    {
        $cookies = [];

        $response = $responseInterface->getInfo();

        if (!isset($response['response_headers']) || !is_array($response['response_headers'])) {
            return $cookies;
        }

        foreach ($response['response_headers'] as $header) {
            if (!str_starts_with($header, 'set-cookie:')) {
                continue;
            }

            // Remove the prefix "Set-Cookie:" and trim the spaces
            $cookieLine = trim(substr($header, strlen('set-cookie:')));

            // Split on ';'
            $parts     = array_map('trim', explode(';', $cookieLine));
            $nameValue = array_shift($parts);

            if ($nameValue === '' || !str_contains($nameValue, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $nameValue, 2);

            // Parse the attributes
            $attrs = [];
            foreach ($parts as $part) {
                if ($part === '') continue;

                if (str_contains($part, '=')) {
                    [$k, $v] = explode('=', $part, 2);
                    $attrs[trim($k)] = trim($v);
                } else {
                    // Simple flag (Secure, HttpOnly)
                    $attrs[trim($part)] = true;
                }
            }

            // Create the object and set it via setters.
            $cookie = new Cookie();

            // Name & Value
            $cookie->setName($name);
            $cookie->setValue($value);

            // Known attributes (case-insensitive)
            $normalized = [];
            foreach ($attrs as $k => $v) {
                $normalized[strtolower($k)] = $v;
            }

            // Path
            if (isset($normalized['path'])) {
                $cookie->setPath($normalized['path']);
            }

            // Domain
            if (isset($normalized['domain'])) {
                $cookie->setDomain($normalized['domain']);
            }

            // Expires
            if (isset($normalized['expires'])) {
                // Try to parse the date; if it fails, return the raw string.
                $ts = strtotime($normalized['expires']);
                if ($ts !== false) {
                    // You can adjust according to the setExpires signature (DateTime|string|int).
                    // Below I pass timestamp (int); change if needed.
                    $cookie->setExpires(new \DateTimeImmutable('@' . $ts, new \DateTimeZone('UTC')));
                } else {
                    $cookie->setExpires($normalized['expires']);
                }
            } else if (isset($normalized['max-age'])) {
                $cookie->setExpires(new \DateTimeImmutable('@' . (int)$normalized['max-age'], new \DateTimeZone('UTC')));
            } else if ('PHPSESSID' == $name) {
                // Set expires to 1440 seconds from now (default PHP session.gc_maxlifetime)
                $cookie->setExpires(new \DateTimeImmutable('+1440 seconds', new \DateTimeZone('UTC')));
            }

            // SameSite
            if (isset($normalized['samesite'])) {
                $cookie->setSameSite($normalized['samesite']);
            }

            // Flags Secure / HttpOnly
            if ((isset($normalized['secure']) && $normalized['secure'] === true)) {
                $cookie->setSecure(true);
            }
            if ((isset($normalized['httponly']) && $normalized['httponly'] === true)) {
                $cookie->setHttpOnly(true);
            }

            // Fallback: if setAttributes(array) or setAttribute(k,v) exists, set the rest
            $leftovers = $attrs;

            // Remove those already handled
            foreach (['path', 'domain', 'expires', 'max-age', 'samesite', 'secure', 'httponly'] as $done) {
                foreach (array_keys($leftovers) as $k) {
                    if (strcasecmp($k, $done) === 0) {
                        unset($leftovers[$k]);
                    }
                }
            }

            if (!empty($leftovers)) {
                if (method_exists($cookie, 'setAttributes')) {
                    $cookie->setAttributes($leftovers);
                } else if (method_exists($cookie, 'setAttribute')) {
                    foreach ($leftovers as $k => $v) {
                        $cookie->setAttribute($k, $v);
                    }
                }
            }

            $cookies[] = $cookie;
        }

        return $cookies;
    }
}
