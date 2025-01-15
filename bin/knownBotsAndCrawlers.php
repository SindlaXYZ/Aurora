<?php

$outputFile = './../src/Utils/AuroraIP/KnownBotsAndCrawlers.php';
file_put_contents($outputFile, "<?php\n\nnamespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;\n\n// File auto-generated on " . date('Y-m-d H:i') . "\ntrait KnownBotsAndCrawlers\n{");

##############################################################################################################################################################################################
##############################################################################################################################################################################################

$googleBots = [];
// https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot
foreach ([
             'https://developers.google.com/static/search/apis/ipranges/googlebot.json',
             'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
             'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json',
             'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json'
         ] as $googleJsonURL) {
    $googleBotJson = file_get_contents($googleJsonURL);
    $googleBotIPS  = json_decode($googleBotJson, true);
    foreach ($googleBotIPS['prefixes'] as $prefix) {
        foreach ($prefix as $ipVersion => $ipAddress) {
            $googleBots[$ipAddress] = rtrim($ipVersion, 'Prefix');
        }
    }
}

ksort($googleBots);
file_put_contents($outputFile, writeVariable('public array', 'googleBotAndCrawlerIPS', $googleBots), FILE_APPEND);

##############################################################################################################################################################################################
##############################################################################################################################################################################################

$bingBots = [];
// https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26
foreach (['https://www.bing.com/toolbox/bingbot.json'] as $bingJsonURL) {
    $bingBotJson = file_get_contents($bingJsonURL);
    $bingBotIPS  = json_decode($bingBotJson, true);
    foreach ($bingBotIPS['prefixes'] as $prefix) {
        foreach ($prefix as $ipVersion => $ipAddress) {
            $bingBots[$ipAddress] = rtrim($ipVersion, 'Prefix');
        }
    }
}

ksort($bingBots);
file_put_contents($outputFile, "\n" . writeVariable('public array', 'bingBotAndCrawlerIPS', $bingBots), FILE_APPEND);

##############################################################################################################################################################################################
##############################################################################################################################################################################################

$appleBots = [];
https://support.apple.com/en-us/119829
foreach (['https://search.developer.apple.com/applebot.json'] as $appleJsonURL) {
    $appleBotJson = file_get_contents($appleJsonURL);
    $appleBotIPS  = json_decode($appleBotJson, true);
    foreach ($appleBotIPS['prefixes'] as $prefix) {
        foreach ($prefix as $ipVersion => $ipAddress) {
            $appleBots[$ipAddress] = rtrim($ipVersion, 'Prefix');
        }
    }
}

ksort($appleBots);
file_put_contents($outputFile, "\n" . writeVariable('public array', 'appleBotAndCrawlerIPS', $appleBots), FILE_APPEND);

##############################################################################################################################################################################################
##############################################################################################################################################################################################

file_put_contents($outputFile, "\n}\n", FILE_APPEND);

##############################################################################################################################################################################################
##############################################################################################################################################################################################

function writeVariable(string $type, string $name, mixed $values): string
{
    $variable = "\n\t{$type} \${$name}";

    if (is_array($values)) {
        $variable .= "\n\t\t= [";
        foreach ($values as $key => $value) {
            $variable .= "\n\t\t\t'{$key}' => '{$value}',";
        }
        $variable .= "\n\t\t];";
    } else {
        $variable .= " = '{$values}';";
    }

    return $variable;
}
