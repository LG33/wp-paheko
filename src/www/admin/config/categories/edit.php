<?php
namespace Paheko;

use Paheko\Entities\Users\Category;
use Paheko\Users\Categories;
use Paheko\Users\Session;

use KD2\Security;

require_once __DIR__ . '/../_inc.php';

$cat = Categories::get((int) qg('id'));

if (!$cat) {
	throw new UserException("Cette catégorie n'existe pas.");
}

$user = $session->getUser();

$csrf_key = 'cat_edit_' . $cat->id();
$admin_safe = $session->isAdmin() && $cat->id == $user->id_category;

$form->runIf('save', function () use ($cat, $session) {
	$user = $session->getUser();
	$cat->importForm();

	// Ne pas permettre de modifier la connexion, l'accès à la config et à la gestion des membres
	// pour la catégorie du membre qui édite les catégories, sinon il pourrait s'empêcher
	// de se connecter ou n'avoir aucune catégorie avec le droit de modifier les catégories !
	if ($cat->id() === $user->id_category) {
		$cat->set('perm_connect', Session::ACCESS_READ);
		$cat->set('perm_config', Session::ACCESS_ADMIN);

		if ($cat->force_otp && !$user->otp_secret) {
			throw new UserException('Vous ne pouvez pas forcer le second facteur car vous n\'avez pas configuré de second facteur pour votre compte.');
		}

		if ($cat->force_otp && !$user->otp_recovery_codes) {
			throw new UserException('Vous ne pouvez pas forcer le second facteur car vous n\'avez pas généré de codes de récupération.');
		}
	}

	$cat->save();

	if ($cat->id() == $user->id_category) {
		$session->refresh();
	}
}, $csrf_key, '!config/categories/');


$permissions = Category::PERMISSIONS;

foreach ($permissions as $key => &$config) {
	if ($admin_safe && in_array($key, [Session::SECTION_CONFIG, Session::SECTION_CONNECT])) {
		$config['disabled'] = true;
	}
}

unset($config);

$has_encryption = Security::canUseEncryption();
$tpl->assign(compact('csrf_key', 'cat', 'permissions', 'has_encryption'));

$tpl->display('config/categories/edit.tpl');
