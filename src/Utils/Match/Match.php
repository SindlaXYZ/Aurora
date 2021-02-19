<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Match;

class Match
{
    /**
     * @param string $needle
     * @param string $domain
     * @return bool
     */
    public function matchDomain(string $needle, string $domain): bool
    {
        $parsedNeedle = parse_url($needle);
        if(array_key_exists('scheme', $parsedNeedle) && array_key_exists('host', $parsedNeedle)) {
            $needle = $parsedNeedle['host'];
        }

        $parsedDomain = parse_url($domain);
        if(array_key_exists('scheme', $parsedDomain) && array_key_exists('host', $parsedDomain)) {
            $domain = $parsedNeedle['host'];
        }

        preg_match('/(^|^[^:]+:\/\/|[^\.]+\.)'. preg_quote($domain) .'$/', $needle, $matches);

        return ((is_array($matches) && count($matches) > 0 && isset($matches[0]) && !empty($matches[0])) ? true : false);
    }

    /**
     * @param string $needle
     * @param array  $domains
     * @return bool
     */
    public function matchAtLeastOneDomain(string $needle, array $domains): bool
    {
        $matched = false;

        foreach ($domains as $domain) {
            if($this->matchDomain($needle, $domain)) {
                $matched = true;
            }
        }

        return $matched;
    }

    public function matchCssUrls(string $css, $relativeUrlOnly = true)
    {
        preg_match_all("/url\((?!['\"]?(?:data|https|http):)['\"]?([^'\"\)]*)['\"]?\)/", $css, $matches);

        return $matches;
    }

    /**
     * Check a password strength
     *
     * @param mixed $password
     * @param bool  $min1LowerCase
     * @param bool  $min1UpperCase
     * @param bool  $min1number
     * @param false $min1Symbol
     * @param int   $minLength
     * @param int   $maxLength
     * @return bool
     */
    public function passwordStrength($password, $min1LowerCase = true, $min1UpperCase = true, $min1number = true, $min1Symbol = false, int $minLength = 1, int $maxLength = 999): bool
    {
        $match = '/^';

        if($min1LowerCase) {
            $match .= '(?=.*[a-z])';
        }

        if($min1UpperCase) {
            $match .= '(?=.*[A-Z])';
        }

        if($min1number) {
            $match .= '(?=.*[\d])';
        }

        if($min1Symbol && ctype_alnum($password)) {
            return false;
        }

        $match .= ".{{$minLength},{$maxLength}}";
        $match .= '+$/';

        return (bool)preg_match($match, $password);
    }
}