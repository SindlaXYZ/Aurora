parent=${PWD%/symfony*}
win_parent=$(echo "$parent" | sed -E 's|^/([a-z])/(.*)|\u\1:/\2|')
win_parent_installed="${win_parent}-symfony"
read -p "sindla/aurora path (default $win_parent): " aurora_path
aurora_path=${aurora_path:-$win_parent}

read -p "Symfony + sindla/aurora path (default $win_parent_installed): " aurora_installed_path
aurora_installed_path=${aurora_installed_path:-$win_parent_installed}

if [[ -d "$aurora_installed_path" ]]; then
  echo "Directory "$aurora_installed_path" already exists."
  cd "$aurora_installed_path"
  rm -rf .[!.]* ..?* *
else
  mkdir "$aurora_installed_path"
  cd "$aurora_installed_path"
fi

yes | composer create-project symfony/skeleton:7.3.x-dev . --no-cache
yes | composer require symfony/webapp-pack -W --no-progress
composer config repositories.aurora "{\"type\":\"path\",\"url\":\"$aurora_path\",\"options\":{\"symlink\":true}}"
yes | composer require sindla/aurora:7.3.x-dev -W --no-progress
yes | composer require phpunit/phpunit:12.3.* -W --dev --no-progress
yes | composer require dama/doctrine-test-bundle:8.3.* -W --dev --no-progress
cd vendor/sindla/aurora/
composer install
cd ../../../
php bin/console cache:clear --env=dev
clear; KERNEL_CLASS=App\\Kernel APP_ENV=test php vendor/bin/phpunit --no-coverage -c vendor/sindla/aurora/phpunit.xml.dist vendor/sindla/aurora/tests/
echo -e "\n\nSymfony 7.3 installation completed.\n"
read -n 1 -s -r -p "Press any key to continue"
