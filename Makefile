COMPOSER = ./composer.phar
COMPOSER_CONF = ./composer.json

.PHONY: all

all: vendor/autoload.php

$(COMPOSER):
	curl -sS https://getcomposer.org/installer | php

vendor/autoload.php: $(COMPOSER) $(COMPOSER_CONF)
	$(COMPOSER) self-update
	$(COMPOSER) install --ignore-platform-reqs --no-interaction --optimize-autoloader --prefer-dist
