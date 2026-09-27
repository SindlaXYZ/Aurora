<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMatch;

class AuroraMatch
{
    public function matchDomain(string $needle, string $domain): bool
    {
        // Browsers (WHATWG URL) treat "\" as "/" in http(s) URLs, parse_url() does not: "https://evil.com\@example.com/" is
        // "evil.com" for a browser, but parse_url() reads the host "example.com" (with the user "evil.com\")
        $needle = str_replace('\\', '/', $needle);

        $parsedNeedle = parse_url($needle);
        if (false !== $parsedNeedle) {
            if (isset($parsedNeedle['host'])) {
                $needle = $parsedNeedle['host'];
            } elseif (!isset($parsedNeedle['scheme']) && isset($parsedNeedle['path'])) {
                $needle = $parsedNeedle['path'];

                $fallbackNeedle = parse_url('http://' . ltrim($needle, '/'));
                if (false !== $fallbackNeedle && isset($fallbackNeedle['host'])) {
                    $needle = $fallbackNeedle['host'];
                }
            }
        }

        $parsedDomain = parse_url($domain);
        if (false !== $parsedDomain) {
            if (isset($parsedDomain['host'])) {
                $domain = $parsedDomain['host'];
            } elseif (!isset($parsedDomain['scheme']) && isset($parsedDomain['path'])) {
                $domain = $parsedDomain['path'];

                $fallbackDomain = parse_url('http://' . ltrim($domain, '/'));
                if (false !== $fallbackDomain && isset($fallbackDomain['host'])) {
                    $domain = $fallbackDomain['host'];
                }
            }
        }

        $needle = rtrim($needle, '.');
        $domain = rtrim($domain, '.');

        $needle = $this->unbracketIPv6(strtolower($needle));
        $domain = $this->unbracketIPv6(strtolower($domain));

        if ('' === $needle || '' === $domain) {
            return false;
        }

        // An IP address only matches itself ("1.2.3.4" must not match "2.3.4", "foo.1.2.3.4" must not match "1.2.3.4")
        if (false !== filter_var($needle, FILTER_VALIDATE_IP) || false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return $needle === $domain;
        }

        // A host name contains only letters, digits, dots, hyphens and underscores. Anything else must not match, e.g. browsers
        // treat "\" as "/", so "http://evil.com\.example.com" is "evil.com" and must not match "example.com"
        if (1 !== preg_match('/^[\p{L}\p{N}._-]+$/Du', $needle)) {
            return false;
        }

        return 1 === preg_match('/(^|\.)' . preg_quote($domain, '/') . '$/Du', $needle);
    }

    /** @param string[] $domains */
    public function matchAtLeastOneDomain(string $needle, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($this->matchDomain($needle, $domain)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<int, string>> */
    public function matchCssUrls(string $css, bool $relativeUrlOnly = true): array
    {
        if ($relativeUrlOnly) {
            $pattern = '/url\(\s*(?!\s*[\'\"]?(?:data:|https?:|\/\/))\s*[\'\"]?([^\'\"\)]*)[\'\"]?\s*\)/i';
        } else {
            $pattern = '/url\(\s*[\'\"]?([^\'\"\)]*)[\'\"]?\s*\)/i';
        }

        preg_match_all($pattern, $css, $matches);

        if (!empty($matches[1])) {
            $matches[1] = array_map(static fn(string $url): string => trim($url), $matches[1]);
        }

        return $matches;
    }

    /**
     * Check password strength
     */
    public function passwordStrength(
        mixed $password,
        bool  $min1LowerCase = true,
        bool  $min1UpperCase = true,
        bool  $min1number = true,
        bool  $min1Symbol = false,
        int   $minLength = 1,
        int   $maxLength = 999
    ): bool
    {
        $password = (string) $password;
        $match    = '/^';

        if ($min1LowerCase) {
            $match .= '(?=.*[a-z])';
        }

        if ($min1UpperCase) {
            $match .= '(?=.*[A-Z])';
        }

        if ($min1number) {
            $match .= '(?=.*[\d])';
        }

        if ($min1Symbol) {
            if (preg_match('/^[\p{L}\p{N}]+$/u', $password)) {
                return false;
            }

            $match .= '(?=.*[^\p{L}\p{N}])';
        }

        $match .= ".{{$minLength},{$maxLength}}";
        $match .= '$/u';

        return (bool)preg_match($match, $password);
    }

    /**
     * IPv6 literals are bracketed in URLs ("http://[2001:db8::1]/" => "[2001:db8::1]" => "2001:db8::1")
     */
    private function unbracketIPv6(string $host): string
    {
        $unbracketed = substr($host, 1, -1);

        if (str_starts_with($host, '[') && str_ends_with($host, ']') && false !== filter_var($unbracketed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $unbracketed;
        }

        return $host;
    }
}
