<?php

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


file_put_contents('./../src/Utils/AuroraIP/Google.php', "<?php\n\nnamespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;\n\n// File auto-generated on " . date('Y-m-d H:i') . "\ntrait Google\n{");
file_put_contents('./../src/Utils/AuroraIP/Google.php', writeVariable('public array', 'googleBotAndCrawlerIPS', $googleBots), FILE_APPEND);
file_put_contents('./../src/Utils/AuroraIP/Google.php', "\n}\n", FILE_APPEND);

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
