.PHONY: updater.phar

updater.phar: updater.php lib/*.php buildVersionFile.php
	php buildVersionFile.php
	composer dump-autoload
	composer run box
	chmod +x updater.phar
	rm lib/Version.php

clean:
	rm updater.phar index.php

index.php: lib/UpdateException.php lib/LogException.php lib/RecursiveDirectoryIteratorWithoutData.php lib/Updater.php index.web.php
	# First put openining php tag and license
	awk '/^<\?php$$/,/\*\//' index.web.php > index.php
	# Then concat all files while filtering php tag and license
	cat lib/UpdateException.php lib/LogException.php lib/RecursiveDirectoryIteratorWithoutData.php lib/Updater.php index.web.php| grep -v "^namespace" | awk '/^<\?php$$/,/\*\//{next} 1' >> index.php

test/vendor:
	cd tests && composer install

test: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat

test-cli: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/cli.feature

test-stable24: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable24.feature

test-stable25: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable25.feature

test-stable26: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/stable26.feature

test-master: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat features/master.feature

check-same-code-base:
	cd tests && php checkSameCodeBase.php

build-and-local-test: updater.phar
	cp updater.phar tests/data/server/nextcloud/updater/updater
	cd tests/data/server/nextcloud/updater && ./updater
