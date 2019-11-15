# customization

PACKAGE_NAME = olvlvl/delayed-event-dispatcher
PACKAGE_VERSION = 3.0
PHPUNIT_VERSION = phpunit-8.0.phar
PHPUNIT_FILENAME = build/$(PHPUNIT_VERSION)
PHPUNIT = php $(PHPUNIT_FILENAME)
PHPUNIT_COVERAGE=phpdbg -qrr $(PHPUNIT_FILENAME) -d memory_limit=-1

# do not edit the following lines

usage:
	@echo "test:  Runs the test suite.\ndoc:   Creates the documentation.\nclean: Removes the documentation, the dependencies and the Composer files."

vendor:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer install

update:
	@COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer update

# testing

test-dependencies: vendor $(PHPUNIT_FILENAME)

$(PHPUNIT_FILENAME):
	mkdir -p build
	curl -sL https://phar.phpunit.de/$(PHPUNIT_VERSION) -o $(PHPUNIT_FILENAME)
	chmod +x $(PHPUNIT_FILENAME)

test: test-dependencies
	@$(PHPUNIT)

test-coverage: test-dependencies
	@mkdir -p build/coverage
	@$(PHPUNIT_COVERAGE) --coverage-html build/coverage

test-coveralls: test-dependencies
	@mkdir -p build/logs
	COMPOSER_ROOT_VERSION=$(PACKAGE_VERSION) composer require php-coveralls/php-coveralls
	@$(PHPUNIT_COVERAGE) --coverage-clover build/logs/clover.xml
	php vendor/bin/php-coveralls -v

#doc

doc: vendor
	@mkdir -p build/docs
	@apigen generate \
	--source lib \
	--destination build/docs/ \
	--title "$(PACKAGE_NAME) v$(PACKAGE_VERSION)" \
	--template-theme "bootstrap"

# utils

clean:
	@rm -fR build
	@rm -fR vendor
	@rm -f composer.lock

.PHONY: all autoload doc clean test test-coverage test-coveralls test-dependencies update
