#!/usr/bin/env bash
apt-get update > /dev/null
which svn > /dev/null
if [ $? -eq 1 ]; then
    echo Installing subversion...
    apt-get install subversion -y
fi
echo Installing phpunit 7.5
wget https://phar.phpunit.de/phpunit-7.5.phar > /dev/null
chmod +x phpunit-7.5.phar
mv phpunit-7.5.phar /usr/local/bin/phpunit