<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso_Checkout\API;

$plugin->registerSignal('cron', [API::class, 'refreshTokenIfExipired']);

Utils::deleteRecursive(ROOT . '/modules/helloasso_checkout_snippets');
recursive_copy(__DIR__ . '/module', ROOT . '/modules/helloasso_checkout_snippets');

Extensions::toggle('helloasso_checkout_snippets', true);

function recursive_copy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursive_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}