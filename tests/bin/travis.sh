#!/usr/bin/env bash
# usage: travis.sh before|after

if [[ "$2" == "7.0" ]]
then
  wget -c https://phar.phpunit.de/phpunit-5.7.phar
  chmod +x phpunit-5.7.phar
  mv phpunit-5.7.phar `which phpunit`
fi

if [ $1 == 'before' ]; then

	# place a copy of woocommerce where the unit tests etc. expect it to be
	git clone --depth 1 --branch $WC_VERSION git@github.com:woocommerce/woocommerce.git '../woocommerce'

fi