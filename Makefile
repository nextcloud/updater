.PHONY: updater.phar

box:
	curl -L https://github.com/box-project/box2/releases/download/2.7.4/box-2.7.4.phar -o box
	chmod +x box

updater.phar: box updater.php lib/*.php buildVersionFile.php
	php buildVersionFile.php
	composer dump-autoload
	./box build -c box.json
	chmod +x updater.phar
	rm lib/Version.php

clean:
	rm updater.phar

test/vendor:
	cd tests && composer install

test: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat

test-cli: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/cli.feature

test-stable14: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable14.feature

test-stable15: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable15.feature

test-stable16: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable16.feature

test-master: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/master.feature

check-same-code-base:
	cd tests && php checkSameCodeBase.php

build-and-local-test: updater.phar
	cp updater.phar tests/data/server/nextcloud/updater/updater
	cd tests/data/server/nextcloud/updater && ./updater
