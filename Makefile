box:
	curl -L https://github.com/box-project/box2/releases/download/2.7.4/box-2.7.4.phar -o box
	chmod +x box

updater.phar: box updater.php lib/*.php buildVersionFile.php
	php buildVersionFile.php
	./box build -c box.json
	chmod +x updater.phar
	rm lib/Version.php

clean:
	rm updater.phar

test/vendor:
	cd tests && composer install

test: updater.phar test/vendor
	cd tests && vendor/behat/behat/bin/behat

check-same-code-base:
	cd tests && php checkSameCodeBase.php
