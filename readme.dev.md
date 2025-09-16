# Installation
If you are installing the plugin through a built zip file, then follow the standard installation from the [readme.txt](readme.txt) file.

If you are cloning the plugin directly from Github, or downloading it in any other way other then from a built resource, then you will need to run composer to install the plugins composer dependencies.

For this you will need to have the following:
* [PHP 8.2.*](https://www.php.net/manual/en/install.php). Make sure you have the necessary extensions installed, such as `curl`, `mbstring`, `xml`, and `zip`. For the scoping of dependencies, version 8.2 of PHP is required.
* [Composer](https://getcomposer.org/doc/00-intro.md).

After these are installed you can run `composer install` from the plugins directory in your CLI. The first command will install all packages, including the once only required for development and scoping of dependencies. After that you can run `composer install --no-dev` to only install the packages required for the plugin to run, but only after the plugin has been successfully scoped.
