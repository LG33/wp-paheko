<?php

namespace Paheko;

$plugin->unregisterSignal('cron');

Extensions::toggle('helloasso_checkout_snippets', false);

Utils::deleteRecursive(ROOT . '/modules/helloasso_checkout_snippets');