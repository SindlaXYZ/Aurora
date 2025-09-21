<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMatch;

class AuroraMatch
{
    public function matchDomain(string $needle, string $domain): bool
    {
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

        $needle = strtolower($needle);
        $domain = strtolower($domain);

        preg_match('/(^|^[^:]+:\/\/|[^\.]+\.)' . preg_quote($domain, '/') . '$/i', $needle, $matches);

        return isset($matches[0]) && $matches[0] !== '';
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
}
