#!/bin/sh
set -e

if [ ! -f composer.json ]; then
		composer create-project symfony/skeleton tmp --stability=stable --prefer-dist --no-progress --no-interaction --no-install

		cd tmp
		composer require "php:>=$PHP_VERSION"
		composer config --json extra.symfony.docker 'true'
		cp -Rp . ..
		cd -

		rm -Rf tmp/
fi

setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

# Load env params into system wide config
echo "Loading env parameters into system wide configuration"
env >> /etc/environment

# Let supervisord start nginx, cron & php-fpm
echo "Start supervisor daemon"
/usr/bin/supervisord -c /etc/supervisord.conf
