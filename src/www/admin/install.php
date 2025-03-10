<?php
namespace Paheko;

use KD2\HTTP;
use Paheko\Users\Session;
use Paheko\Entities\Accounting\Chart;
use Paheko\Plugins;
use Paheko\Extensions;

const SKIP_STARTUP_CHECK = true;

require_once __DIR__ . '/../../include/test_required.php';
require_once __DIR__ . '/../../include/init.php';

$exists = file_exists(DB_FILE);

if ($exists && !filesize(DB_FILE)) {
	@unlink(DB_FILE);
	$exists = false;
}

if ($exists) {
	throw new UserException('Paheko est déjà installé');
}

Install::checkAndCreateDirectories();
Install::checkReset();

if (DISABLE_INSTALL_FORM) {
	throw new \RuntimeException('Install form has been disabled');
}

function f($key)
{
	return \KD2\Form::get($key);
}

$tpl = Template::getInstance();
$tpl->assign('admin_url', ADMIN_URL);

$form = new Form;
$tpl->assign_by_ref('form', $form);
$csrf_key = 'install';

require_once ABSPATH . '/wp-load.php';
$wp_user = wp_get_current_user();

$form->runIf('save', function () use ($wp_user) {
	if (!isset($wp_user->user_email)) {
		throw new UserException("L'adresse email de l'administrateur Wordpress n'a pas été trouvée. Vérifiez là dans l'onglet Utilisateurs de Wordpress puis réessayez.");
	}

	$_POST['user_email'] = $wp_user->user_email;
	$_POST['password'] = $_POST['password_confirmed'] = Utils::suggestPassword();

	Install::installFromForm();

	$default_plugins = ['helloasso_checkout', 'caisse', 'usermap'];

	foreach ($default_plugins as $key => $plugin) {
		if (Plugins::exists($plugin) && Plugins::isAllowed($plugin)) {
			Extensions::toggle($plugin, true);
		}
	}

	$default_modules = ['helloasso_checkout_snippets', 'expenses_claims', 'receipt', 'receipt_donation', 'recus_fiscaux', 'transactions_templates'];

	foreach ($default_modules as $key => $module) {
		Extensions::toggle($module, true);
	}
}, $csrf_key, ADMIN_URL);

$tpl->assign('name', htmlspecialchars_decode(get_option('blogname')));
$tpl->assign('user_name', $wp_user->display_name);

$tpl->assign('countries', Chart::COUNTRY_LIST);
$tpl->assign('require_admin_account', !is_array(LOCAL_LOGIN));

$tpl->assign(compact('csrf_key'));

$tpl->display('install.tpl');
