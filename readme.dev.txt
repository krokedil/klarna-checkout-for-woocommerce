Docker

To initiate the project start with the command "docker-compose up -d".
This will setup the images and install WooCommerce and Storefront as well as changing some settings. This might take a minute or two the first time.
After the installation is done, you can find the plugin files in the Root folder, and the WordPress files if they are needed under the wp folder.
This will let you access and change other plugins as needed for potential compatibility debugging.
After the initial setup you can close the project with "docker-compose stop" and start it with "docker-compose start". The WordPress installation will be available under your localhost.


Install PHPUnit with Docker
First you need to run the command "docker-compose -f docker-compose.yml -f docker-compose.phpunit.yml up -d"
After the images are setup you need to install the WP test environment. Run this command "docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit ./tests/bin/install-wp-tests.sh wordpress_test root wordpress mysql_phpunit latest true"

Run PHPUnit with Docker
When this is done, you can run the PHPUnit tests with the following command "docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit phpunit -c phpunit-docker.xml"
This uses a separate xml file for PHPUnit that has the environment variable for docker set to true. This is to be able to include WooCommerce in the testing since this is a requirement of our Plugin.

Commands
Install the containers:
"docker-compose up -d"

Start the containers
"docker-compose start"

Stop the containers
"docker-compose stop"

Install PHPUnit containers:
"docker-compose -f docker-compose.yml -f docker-compose.phpunit.yml up -d"

Install WP Test suite
"docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit ./tests/bin/install-wp-tests.sh wordpress_test root wordpress mysql_phpunit latest true"

Run PHPUnit Tests with docker
"docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit phpunit -c phpunit-docker.xml"