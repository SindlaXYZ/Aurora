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
        preg_match('/(^|^[^:]+:\/\/|[^\.]+\.)'. preg_quote($domain) .'$/', $needle, $matches);

        return (is_array($matches) && count($matches) > 0 && isset($matches[2]) && !empty($matches[2])) ? true : false;
    }

    /**
     * @param string $needle
     * @param array  $domains
     * @return bool
     */
    public function matchDomains(string $needle, array $domains): bool
    {
        foreach ($domains as $domain) {
            if(!$this->matchDomain($needle, $domain)) {
                return false;
            }
        }

        return true;
    }
}