
all:
	@echo webback
	@echo - make tests

tests:
	php ./vendor/bin/phpunit

tests_dbg:
	php ./vendor/bin/phpunit --debug

.PHONY: tests