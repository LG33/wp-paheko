.PHONY: dev-server release deps publish check-dependencies test-syntax tests minify phpstan www htaccess modules installer plugins
SHELL := /bin/bash
KD2FW_BRANCH := trunk
MODULES_BRANCH := trunk
PLUGINS_BRANCH := trunk
KD2FW_URL := https://fossil.kd2.org/kd2fw/
MODULES_URL := https://fossil.kd2.org/paheko-modules/
PLUGINS_URL := https://fossil.kd2.org/paheko-plugins/

deps:
	$(eval TMP_KD2=$(shell mktemp -d))
	#cd ${TMP_KD2}

	wget ${KD2FW_URL}zip/${KD2FW_BRANCH}/kd2.zip -O ${TMP_KD2}/kd2.zip

	rm -rf "include/lib/KD2"
	unzip "${TMP_KD2}/kd2.zip" -d ${TMP_KD2}
	mv ${TMP_KD2}/kd2/src/lib/KD2 include/lib

	rm -rf ${TMP_KD2}

modules:
	wget ${MODULES_URL}zip/${MODULES_BRANCH}/modules.zip -O modules.zip
	unzip -u modules.zip -d src
	rm -f modules.zip

plugins:
	wget ${PLUGINS_URL}zip/${PLUGINS_BRANCH}/plugins.zip -O plugins.zip
	unzip -u plugins.zip -d src/data
	rm -f plugins.zip

dev-server:
	PHP_CLI_SERVER_WORKERS=4 php -S localhost:8082 -d upload_max_filesize=256M -d post_max_size=256M -t www www/_route.php

test-syntax:
	find . -name '*.php' -not -path './data/*' -print0 | xargs -0 -n1 php -l > /dev/null

tests:
	cd ../tests && php run.php

selenium-tests:
	cd ../tests/selenium && make

phpstan:
	phpstan.phar analyze -c ../tests/phpstan.neon include www

psalm:
	@# This is required by psalm, but useless
	@-mkdir vendor
	@-echo '{"require": {}}' > vendor/autoload.php
	psalm.phar -c ../tests/psalm.xml

doc:
	php ../tools/doc_md_to_html.php

htaccess:
	# Removing DOCUMENT_ROOT is important for the cache when using .htaccess, keep it!
	cat apache-vhost.conf \
		| sed 's/#RewriteBase/RewriteBase/' \
		| sed 's/RewriteCond %{DOCUMENT_ROOT}%{REQUEST_/RewriteCond %{REQUEST_/' \
		> www/.htaccess
	cat apache-htaccess.conf >> www/.htaccess

# Freeze versions before release
freeze:
	$(eval VERSION=$(shell cat VERSION))
	php ../tools/fossil_get_branch_hash.php ${KD2FW_URL} ${KD2FW_BRANCH} > ../build/kd2fw.version
	php ../tools/fossil_get_branch_hash.php ${MODULES_URL} ${MODULES_BRANCH} > ../build/modules.version
	php ../tools/fossil_get_branch_hash.php ${PLUGINS_URL} ${PLUGINS_BRANCH} > ../build/plugins.version
	fossil commit ../build/*.version --tag ${VERSION} || true

verify:
	$(eval ROOT=$(shell pwd))
	@echo "Verifying Paheko..."
	@cd .. && bash tools/fossil_verify.sh
	@echo "Verifying Plugins..."
	@cd ${ROOT}/data/plugins && bash ${ROOT}/../tools/fossil_verify.sh ${PLUGINS_BRANCH} ${PLUGINS_URL}
	@echo "Verifying Modules..."
	@cd ${ROOT}/modules && bash ${ROOT}/../tools/fossil_verify.sh ${MODULES_BRANCH} ${MODULES_URL}
	@echo "Verifying KD2fw..."
	@cd ${ROOT}/include/lib/KD2 && bash ${ROOT}/../tools/fossil_verify.sh ${KD2FW_BRANCH} ${KD2FW_URL} src/lib/KD2

release: minify
	$(eval VERSION=$(shell cat VERSION))
	$(eval KD2FW_VERSION=$(shell cat ../build/kd2fw.version))
	$(eval MODULES_VERSION=$(shell cat ../build/modules.version))
	$(eval PLUGINS_VERSION=$(shell cat ../build/plugins.version))

	rm -rf /tmp/paheko-build
	mkdir -p /tmp/paheko-build
	zip -r /tmp/paheko-build/src.zip src load.php
	unzip -d /tmp/paheko-build/wp-paheko /tmp/paheko-build/src.zip

	# Download and package required KD2fw libraries
	cd /tmp/paheko-build && \
		wget ${KD2FW_URL}zip/${KD2FW_VERSION}/kd2.zip && \
		unzip kd2.zip && \
		cd wp-paheko/src/include/lib && \
		mkdir /tmp/paheko-build/kd2/src/lib/Wasso && \
		cp Wasso/DB.php /tmp/paheko-build/kd2/src/lib/Wasso/DB.php && \
		cp Wasso/PDO_DB.php /tmp/paheko-build/kd2/src/lib/Wasso/PDO_DB.php && \
		rsync --files-from=dependencies.list -r /tmp/paheko-build/kd2/src/lib/ /tmp/paheko-build/wp-paheko/src/include/lib/

	# Overwrite admin.css with united file
	mv src/www/admin/static/mini.css /tmp/paheko-build/wp-paheko/src/www/admin/static/admin.css

	# Generate .htaccess file
	#cd /tmp/paheko-build/wp-paheko && make htaccess

	# Remove useless files
	cd /tmp/paheko-build/wp-paheko/src/www/admin/static; \
		rm -f font/*.css font/*.json
	cd /tmp/paheko-build/wp-paheko/src; \
		rm -f include/lib/KD2/data/countries.en.json data/error.log data/*.sqlite data/*.sqlite-journal *.asc
		#rm -r uploads data/cache

	# Download modules and only keep the stable ones
	cd /tmp/paheko-build/wp-paheko && \
		wget ${MODULES_URL}zip/${MODULES_VERSION}/modules.zip && \
		unzip -o modules.zip && \
		rm -rf `find modules/ -name 'ignore' -type f -execdir pwd \;` && \
		rm -f modules.zip
	#cp -r modules/helloasso_checkout_snippets /tmp/paheko-build/wp-paheko/modules

	# Download plugins and only keep the stable ones
	cd /tmp/paheko-build/wp-paheko/src/data && \
		wget ${PLUGINS_URL}zip/${PLUGINS_VERSION}/plugins.zip && \
		unzip -o plugins.zip && \
		rm -rf `find plugins/ -name 'ignore' -type f -execdir pwd \;` && \
		rm -f plugins.zip
	cp -r src/data/plugins/helloasso_checkout /tmp/paheko-build/wp-paheko/src/data/plugins

	#cp ../README.md /tmp/paheko-build/wp-paheko/readme.txt
	#chown -R www-data /tmp/paheko-build/wp-paheko

	#mv /tmp/paheko-build/src /tmp/paheko-build/wp-paheko
	#tar czvfh ../build/wp-paheko-${VERSION}.tar.gz --hard-dereference -C /tmp/paheko-build wp-paheko
	cd /tmp/paheko-build && \
		zip -r wp-paheko.zip wp-paheko/src wp-paheko/load.php
		mv /tmp/paheko-build/wp-paheko.zip build/wp-paheko.zip

deb:
	cd ../build/debian; ./makedeb.sh

windows:
	cd ../build/windows; make installer

publish: installer release deb windows
	$(eval VERSION=$(shell cat VERSION))
	cd ../build && gpg --armor -u dev@paheko.cloud --detach-sign paheko-${VERSION}.tar.gz
	fossil uv sync
	#fossil uv ls | fgrep -v 'paheko-0.8.5' | grep '^paheko-.*\.(tar\.bz2|deb)' | xargs fossil uv rm
	cd ../build && \
		fossil uv add paheko-${VERSION}.tar.gz && \
		fossil uv add paheko-${VERSION}.tar.gz.asc
	cd ../build/debian && fossil uv add paheko-${VERSION}.deb
	cd ../tools && fossil uv add install.php && rm install.php
	fossil uv sync
	cd ../build/windows && make publish

check-dependencies:
	grep -hEo '^use \\?KD2\\[^; ]+|\\KD2\\[^\(:; ]+' -R include/lib/Garradin www | sed -r 's/^use \\?KD2\\|^\\KD2\\//' | sort | uniq

installer:
	cd ../tools && php make_installer.php > install.php

minify:
	cat `ls src/www/admin/static/styles/[0-9]*.css` | sed 's/\.\.\///' > src/www/admin/static/mini.css
	@# Minify is only gaining 500 gzipped bytes (4kB uncompressed) but making things hard to read/hack
	@#yui-compressor --nomunge src/www/admin/static/mini.css -o src/www/admin/static/mini.css

stable:
	@echo -n "Checking branch... "; \
	if [[ ! -n $$(fossil status | grep -E 'tags:\s*trunk') ]]; \
	then echo ; \
		echo "!!! FAIL: not in trunk !!!"; \
		echo && exit 1; \
	else echo "OK"; \
	fi
	fossil tag add stable trunk
	fossil sync
