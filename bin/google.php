<?php

$googleBotJson = file_get_contents('https://developers.google.com/static/search/apis/ipranges/googlebot.json');
$googleBotIPS  = json_decode($googleBotJson, true);
$googleBots    = [];

foreach ($googleBotIPS['prefixes'] as $prefix) {
    foreach ($prefix as $ipVersion => $ipAddress) {
        $googleBots[$ipAddress] = rtrim($ipVersion, 'Prefix');
    }
}

file_put_contents('./../src/Utils/AuroraIP/Google.php', "<?php\n\nnamespace Sindla\Bundle\AuroraBundle\Utils\AuroraIP;\n\n// File auto-generated on ". date('Y-m-d H:i') ."\ntrait Google\n{\n");
file_put_contents('./../src/Utils/AuroraIP/Google.php', "\tpublic array \$googleBotIPS\n\t\t= [", FILE_APPEND);
foreach ($googleBots as $googleBotIP => $googleBotIPVersion) {
    file_put_contents('./../src/Utils/AuroraIP/Google.php', "\n\t\t\t'$googleBotIP' => '$googleBotIPVersion',", FILE_APPEND);
}
file_put_contents('./../src/Utils/AuroraIP/Google.php', "\n\t\t];", FILE_APPEND);
file_put_contents('./../src/Utils/AuroraIP/Google.php', "\n}\n", FILE_APPEND);
