#!/usr/bin/env bash
if [[ ${RUN_PHPCS} == 1 ]]; then
	CHANGED_FILES=`git diff --name-only --diff-filter=ACMR $TRAVIS_COMMIT_RANGE | grep \\\\.php | awk '{print}' ORS=' '`
	IGNORE="/tests"

	if [ "$CHANGED_FILES" != "" ]; then
		# Install wpcs globally:
    	composer require woocommerce/woocommerce-sniffs

		echo "Running Code Sniffer."
		./vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=$IGNORE $CHANGED_FILES
	fi
fi