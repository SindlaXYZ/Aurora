echo -e "Aurora short version (short version - X.Y, eg: 5.2, 6.3, 7.0, etc):" ;\
read auroraVersion ;\
composer create-project symfony/skeleton:${auroraVersion}.x-dev --no-cache . ;\
yes | composer require symfony/webapp-pack ;\
yes | composer require symfony/phpunit-bridge:${auroraVersion}.* ;\
yes | composer require phpunit/phpunit:^10.5 ;\
yes | composer require sindla/aurora:${auroraVersion}.x-dev --no-progress ;\
curl -o phpunit.xml -H 'Cache-Control: no-cache, no-store' https://raw.githubusercontent.com/SindlaXYZ/Aurora/${auroraVersion}/phpunit.xml ;\
curl -o config/packages/aurora.yaml -H 'Cache-Control: no-cache, no-store' https://raw.githubusercontent.com/SindlaXYZ/Aurora/${auroraVersion}/src/Resources/schema/packages/aurora.yaml ;\
curl -H 'Cache-Control: no-cache, no-store' https://raw.githubusercontent.com/SindlaXYZ/Aurora/${auroraVersion}/src/Resources/schema/routes.append.yaml >> config/routes.yaml ;\
composer clear-cache ;\
composer dump-autoload ;\
php bin/phpunit -c phpunit.xml ./vendor/sindla/aurora/tests/FakeTest.php --no-coverage ;\
php bin/phpunit -c phpunit.xml ./vendor/sindla/aurora/tests/RequirementsTest.php --no-coverage ;\
php bin/phpunit -c phpunit.xml ./vendor/sindla/aurora/tests/Utils/Client/AuroraClientTest.php --no-coverage ;\
php bin/phpunit -c phpunit.xml ./vendor/sindla/aurora/tests/Controller/BlackHoleControllerTest.php --no-coverage ;\
echo -e "\n" ;\
php -v ;\
bin/phpunit --version ;\
vendor/bin/phpunit --version


