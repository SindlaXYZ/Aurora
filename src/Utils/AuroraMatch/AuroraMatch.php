<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMatch;

class AuroraMatch
{
    public function matchDomain(string $needle, string $domain): bool
    {
        $parsedNeedle = parse_url($needle);
        if (is_array($parsedNeedle) && isset($parsedNeedle['scheme'], $parsedNeedle['host'])) {
            $needle = $parsedNeedle['host'];
        }

        $parsedDomain = parse_url($domain);
        if (is_array($parsedDomain) && isset($parsedDomain['scheme'], $parsedDomain['host'])) {
            $domain = $parsedDomain['host'];
        }

        $needle = strtolower($needle);
        $domain = strtolower($domain);

        preg_match('/(^|^[^:]+:\/\/|[^\.]+\.)' . preg_quote($domain, '/') . '$/i', $needle, $matches);

        return ((is_array($matches) && count($matches) > 0 && isset($matches[0]) && !empty($matches[0])) ? true : false);
    }

    public function matchAtLeastOneDomain(string $needle, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($this->matchDomain($needle, $domain)) {
                return true;
            }
        }

        return false;
    }

    public function matchCssUrls(string $css, bool $relativeUrlOnly = true): array
    {
        $pattern = $relativeUrlOnly
            ? '/url\((?![\'"]?(?:data|https|http):)[\'"]?([^\'"\)]*)[\'"]?\)/'
            : '/url\([\'"]?([^\'"\)]*)[\'"]?\)/';

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

