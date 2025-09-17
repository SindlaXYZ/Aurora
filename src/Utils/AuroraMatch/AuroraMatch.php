<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMatch;

class AuroraMatch
{
    public function matchDomain(string $needle, string $domain): bool
    {
        $parsedNeedle = parse_url($needle);
        if ($parsedNeedle !== false && isset($parsedNeedle['scheme'], $parsedNeedle['host'])) {
            $needle = $parsedNeedle['host'];
        }

        $parsedDomain = parse_url($domain);
        if ($parsedDomain !== false && isset($parsedDomain['scheme'], $parsedDomain['host'])) {
            $domain = $parsedDomain['host'];
        }

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
            $pattern = '/url\((?![\'\"]?(?:data:|https?:|\/\/))[\'\"]?([^\'\"\)]*)[\'\"]?\)/i';
        } else {
            $pattern = '/url\([\'\"]?([^\'\"\)]*)[\'\"]?\)/i';
        }

        preg_match_all($pattern, $css, $matches);

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
        $match = '/^';

        if ($min1LowerCase) {
            $match .= '(?=.*[a-z])';
        }

        if ($min1UpperCase) {
            $match .= '(?=.*[A-Z])';
        }

        if ($min1number) {
            $match .= '(?=.*[\d])';
        }

        if ($min1Symbol && ctype_alnum($password)) {
            return false;
        }

        $match .= ".{{$minLength},{$maxLength}}";
        $match .= '$/';

        return (bool)preg_match($match, $password);
    }
}
