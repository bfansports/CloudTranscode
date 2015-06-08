APIKEYS="AWS_REGION" "AWS_ACCESS_KEY_ID" "AWS_SECRET_KEY" \
"CLIENT_NAME" "CLIENT_INPUT_QUEUE" "CLIENT_OUTPUT_QUEUE"
COMPOSER = ./composer.phar
COMPOSER_CONF = ./composer.json

.PRECIOUS: .env.%
.PHONY: all lint

# Rules for building environment

all: vendor/autoload.php .env.dev

# Rules for generating files

$(COMPOSER):
	curl -sS https://getcomposer.org/installer | php

vendor/autoload.php: $(COMPOSER) $(COMPOSER_CONF)
	$(COMPOSER) self-update
	$(COMPOSER) install -o --prefer-dist

.env.%:
	for item in $(APIKEYS); do \
		read -p "Please enter $${item} and press ENTER: " value; \
		echo "$${item}=$${value}" >> $@; \
	done;

lint: vendor/bin
	mkdir -p build/reports
	find src -name '*.php' -not -path 'vendor/' -print0 | xargs -0 -L 1 -P 8 php -l >build/reports/src-lint.log
	find tests -name '*.php' -not -path 'vendor/' -print0 | xargs -0 -L 1 -P 8 php -l >build/reports/tests-lint.log
	# Duplicate code
	vendor/bin/phpcpd --log-pmd build/reports/pmd-cpd.xml src/
	# Lines of code
	vendor/bin/phploc --log-csv build/reports/phploc.csv src
	# PHP Depend
	vendor/bin/pdepend --jdepend-xml=build/reports/jdepend.xml --jdepend-chart=build/reports/dependencies.svg --overview-pyramid=build/reports/pyramid.svg src
	# Code sniffer
	vendor/bin/phpcs --standard=PHPCS --extensions=php src
	vendor/bin/phpcs --standard=PHPCS --extensions=php tests
