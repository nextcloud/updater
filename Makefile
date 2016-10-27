box:
	wget https://github.com/box-project/box2/releases/download/2.7.4/box-2.7.4.phar -O box
	chmod +x box

updater.phar: box updater.php lib/*.php
	./box build -c box.json
	chmod +x updater.phar

clean:
	rm updater.phar

test: updater.phar
	cd tests && vendor/behat/behat/bin/behat
