.PHONY: dev-server release deps publish check-dependencies test-syntax tests minify phpstan www htaccess modules installer plugins doc
SHELL := /bin/bash
KD2FW_BRANCH := trunk
MODULES_BRANCH := trunk
PLUGINS_BRANCH := trunk
KD2FW_URL := https://fossil.kd2.org/kd2fw/
MODULES_URL := https://fossil.kd2.org/paheko-modules/
PLUGINS_URL := https://fossil.kd2.org/paheko-plugins/

PHP_BIN := $(shell which php8.2 || which php8.1 || which php8.3 || which php || echo /usr/local/bin/php)

deps:
	$(eval TMP_KD2=$(shell mktemp -d))
	curl -L ${KD2FW_URL}zip/${KD2FW_BRANCH}/kd2.zip -o ${TMP_KD2}/kd2.zip

	rm -rf "src/include/lib/KD2"
	unzip "${TMP_KD2}/kd2.zip" -d ${TMP_KD2}
	mv ${TMP_KD2}/kd2/src/lib/KD2 src/include/lib

	rm -rf ${TMP_KD2}

modules:
	curl -L ${MODULES_URL}zip/${MODULES_BRANCH}/modules.zip -o modules.zip
	unzip -nq modules.zip -d src
	rm -f modules.zip

plugins:
	curl -L ${PLUGINS_URL}zip/${PLUGINS_BRANCH}/plugins.zip -o plugins.zip
	unzip -nq plugins.zip -d src/data
	rm -f plugins.zip

dev-server:
	PHP_CLI_SERVER_WORKERS=4 ${PHP_BIN} -S localhost:8082 -d upload_max_filesize=256M -d post_max_size=256M -t src/www src/www/_route.php

test-syntax:
	find . -name '*.php' -not -path './data/*' -print0 | xargs -0 -n1 ${PHP_BIN} -l > /dev/null

tests:
	cd tests && ${PHP_BIN} run.php

selenium-tests:
	cd tests/selenium && make

phpstan:
	phpstan.phar analyze -c tests/phpstan.neon src/include src/www

psalm:
	@# This is required by psalm, but useless
	@-mkdir -p vendor
	@-echo '{"require": {}}' > vendor/autoload.php
	psalm.phar -c tests/psalm.xml

doc:
	${PHP_BIN} tools/doc_md_to_html.php

htaccess:
	cat apache-bots.conf > src/www/.htaccess
	# Removing DOCUMENT_ROOT is important for the cache when using .htaccess!
	cat apache-vhost.conf \
		| sed 's/#RewriteBase/RewriteBase/' \
		| sed 's/RewriteCond %{DOCUMENT_ROOT}%{REQUEST_/RewriteCond %{REQUEST_/' \
		>> src/www/.htaccess
	cat apache-htaccess.conf >> src/www/.htaccess

# Freeze versions before release
freeze:
	$(eval VERSION=$(shell cat VERSION))
	# Skip fossil hash for now, use branch name
	echo "${KD2FW_BRANCH}" > build/kd2fw.version
	echo "${MODULES_BRANCH}" > build/modules.version
	echo "${PLUGINS_BRANCH}" > build/plugins.version

verify:
	$(eval ROOT=$(shell pwd))
	@echo "Verifying Paheko... (Skipped Fossil check)"

release: minify
	$(eval VERSION=$(shell cat src/VERSION))
	
	mkdir -p build

	# Default to trunk/master if version files are missing
	$(eval KD2FW_VERSION=$(shell cat build/kd2fw.version 2>/dev/null || echo ${KD2FW_BRANCH}))
	$(eval MODULES_VERSION=$(shell cat build/modules.version 2>/dev/null || echo ${MODULES_BRANCH}))
	$(eval PLUGINS_VERSION=$(shell cat build/plugins.version 2>/dev/null || echo ${PLUGINS_BRANCH}))

	rm -rf /tmp/paheko-build
	mkdir -p /tmp/paheko-build/wp-paheko
	
	# Skip cache and data folders when copying
	rsync -rl src /tmp/paheko-build/wp-paheko/
	cp load.php /tmp/paheko-build/wp-paheko/

	# Download and package required KD2fw libraries
	cd /tmp/paheko-build && \
		curl -L ${KD2FW_URL}zip/${KD2FW_VERSION}/kd2.zip -o kd2.zip && \
		unzip -q kd2.zip && \
		cd wp-paheko/src/include/lib && \
		rsync --files-from=dependencies.list -r /tmp/paheko-build/kd2/src/lib/ /tmp/paheko-build/wp-paheko/src/include/lib/

	# Overwrite admin.css with united file
	mv /tmp/paheko-build/wp-paheko/src/www/admin/static/mini.css /tmp/paheko-build/wp-paheko/src/www/admin/static/admin.css

	# Remove useless files
	cd /tmp/paheko-build/wp-paheko/src/www/admin/static; \
		rm -f font/*.css font/*.json
	cd /tmp/paheko-build/wp-paheko/src; \
		rm -f data/error.log data/*.sqlite data/*.sqlite-journal *.asc 2>/dev/null || true
	
	# Handle data directory
	mkdir -p /tmp/paheko-build/wp-paheko/src/data
	
	# Download modules and only keep the stable ones
	cd /tmp/paheko-build/wp-paheko/src && \
		curl -L ${MODULES_URL}zip/${MODULES_VERSION}/modules.zip -o modules.zip && \
		unzip -q -o modules.zip && \
		rm -rf `find modules/ -name 'ignore' -type f -execdir pwd \;` && \
		rm -f modules.zip

	# Download plugins and only keep the stable ones
	cd /tmp/paheko-build/wp-paheko/src/data && \
		curl -L ${PLUGINS_URL}zip/${PLUGINS_VERSION}/plugins.zip -o plugins.zip && \
		unzip -q -o plugins.zip && \
		rm -rf `find plugins/ -name 'ignore' -type f -execdir pwd \;` && \
		rm -f plugins.zip
	
	# Add custom plugins
	cp -r src/data/plugins/helloasso_checkout /tmp/paheko-build/wp-paheko/src/data/plugins/ 2>/dev/null || true

	cd /tmp/paheko-build && \
		zip -qr wp-paheko.zip wp-paheko
	
	mv /tmp/paheko-build/wp-paheko.zip build/wp-paheko.zip

publish: installer release
	$(eval VERSION=$(shell cat src/VERSION))
	@echo "Publishing version ${VERSION} (Fossil sync skipped)"

check-dependencies:
	grep -hEo '^use \\?KD2\\[^; ]+|\\KD2\\[^\(:; ]+' -R src/include/lib/Paheko src/www | sed -r 's/^use \\?KD2\\|^\\KD2\\//' | sort | uniq

installer:
	cd tools && ${PHP_BIN} make_installer.php > install.php

minify:
	cat src/www/admin/static/styles/[0-9]*.css | sed 's/\.\.\///' > src/www/admin/static/mini.css

stable:
	@echo "Marking as stable (Fossil skipped)"
