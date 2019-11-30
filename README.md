Run `composer require sindla/aurora`


#### How to work-around without releasing new versions

Open your project and run

```bash
mkdir -p ./vendor/sindla/aurora; \
cd ./vendor/sindla/aurora; \
rm -rf .git \
&& git init \
&& git remote add origin git@github.com+Sindla:SindlaXYZ/Aurora.git \
&& git fetch --all \
&& git clean -df \
&& git reset --hard \
&& git pull origin master; \
cd ./../../../
```