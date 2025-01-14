<?php

$googleBotJson = file_get_contents('https://developers.google.com/static/search/apis/ipranges/googlebot.json');
$googleBotIPS  = json_decode($googleBotJson, true);
$googleBots    = [];

foreach ($googleBotIPS['prefixes'] as $prefix) {
    foreach ($prefix as $ipVersion => $ipAddress) {
        $googleBots[$ipAddress] = rtrim($ipVersion, 'Prefix');
    }
}

file_put_contents('./../src/Utils/AuroraIP/Google.php', "<?php\n\nnamespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;\n\n// File auto-generated on " . date('Y-m-d H:i') . "\ntrait Google\n{");
file_put_contents('./../src/Utils/AuroraIP/Google.php', writeVariable('public array', 'googleBotIPS', $googleBots), FILE_APPEND);
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
