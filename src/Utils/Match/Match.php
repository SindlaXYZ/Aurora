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
}