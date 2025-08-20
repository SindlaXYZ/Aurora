<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor;

use Symfony\Contracts\HttpClient\ResponseInterface;

class AuroraCookiesExtractor
{
    public function extractFromSymfonyResponseInterface(ResponseInterface $responseInterface): array
    {
        $cookies = [];

        $responseInterface->getInfo();

        if (!isset($response['response_headers']) || !is_array($response['response_headers'])) {
            return $cookies;
        }

        foreach ($response['response_headers'] as $header) {
            if (stripos($header, 'set-cookie:') !== 0) {
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
            if (method_exists($cookie, 'setName')) {
                $cookie->setName($name);
            }
            if (method_exists($cookie, 'setValue')) {
                $cookie->setValue($value);
            }

            // Known attributes (case-insensitive)
            $normalized = [];
            foreach ($attrs as $k => $v) {
                $normalized[strtolower($k)] = $v;
            }

            // Path
            if (isset($normalized['path']) && method_exists($cookie, 'setPath')) {
                $cookie->setPath($normalized['path']);
            }

            // Domain
            if (isset($normalized['domain']) && method_exists($cookie, 'setDomain')) {
                $cookie->setDomain($normalized['domain']);
            }

            // Expires
            if (isset($normalized['expires']) && method_exists($cookie, 'setExpires')) {
                // Try to parse the date; if it fails, return the raw string.
                $ts = strtotime($normalized['expires']);
                if ($ts !== false) {
                    // You can adjust according to the setExpires signature (DateTime|string|int).
                    // Below I pass timestamp (int); change if needed.
                    $cookie->setExpires($ts);
                } else {
                    $cookie->setExpires($normalized['expires']);
                }
            }

            // Max-Age
            if (isset($normalized['max-age']) && method_exists($cookie, 'setMaxAge')) {
                $cookie->setMaxAge((int)$normalized['max-age']);
            }

            // SameSite
            if (isset($normalized['samesite']) && method_exists($cookie, 'setSameSite')) {
                $cookie->setSameSite($normalized['samesite']);
            }

            // Flags Secure / HttpOnly
            if ((isset($normalized['secure']) && $normalized['secure'] === true) && method_exists($cookie, 'setSecure')) {
                $cookie->setSecure(true);
            }
            if ((isset($normalized['httponly']) && $normalized['httponly'] === true) && method_exists($cookie, 'setHttpOnly')) {
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
