if [[ -d 7.3 ]]; then
    echo "Directory 7.3 already exists."
    cd 7.3
    rm -rf .[!.]* ..?* *
else
    mkdir 7.3
    cd 7.3
fi
yes | composer create-project symfony/skeleton:7.3.x-dev . --no-cache
yes | composer require symfony/webapp-pack -W --no-progress
parent=${PWD%/symfony*}
win_parent=$(echo "$parent" | sed -E 's|^/([a-z])/(.*)|\u\1:/\2|')
# read -p "sindla/aurora path (default $win_parent): " aurora_path
aurora_path=${aurora_path:-$win_parent}
composer config repositories.aurora "{\"type\":\"path\",\"url\":\"$aurora_path\",\"options\":{\"symlink\":true}}"
yes | composer require sindla/aurora:7.3.x-dev -W --no-progress
yes | composer require phpunit/phpunit:12.3.* -W --dev --no-progress
yes | composer require dama/doctrine-test-bundle:8.3.* -W --dev --no-progress
cd vendor/sindla/aurora/
composer install
cd ../../../
php bin/console cache:clear --env=dev
echo -e "\n\nSymfony 7.3 installation completed.\n"
read -n 1 -s -r -p "Press any key to continue"
