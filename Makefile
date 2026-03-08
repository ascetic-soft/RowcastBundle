.PHONY: help test

help:
	@echo "Available commands:"
	@echo "  make test   - Run PHPUnit tests"

test:
	@vendor/bin/phpunit
