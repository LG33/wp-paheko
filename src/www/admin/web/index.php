<?php

namespace Paheko;

use KD2\HTTP;
use Paheko\Utils;
use Paheko\Users\Session;

require_once __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_WEB, $session::ACCESS_WRITE);

$wp_admin_url = WP_SITEURL . '/wp-admin/';
$pages = get_pages();

foreach ($pages as $key => &$page) {
	$page->post_title = html_entity_decode($page->post_title);
}

if (!$_GET || !$_GET['id']) {
	Utils::redirect('?id=' . $pages[0]->ID);
	return;
}

$tpl->assign(compact('pages', 'wp_admin_url'));

$tpl->display('web/index.tpl');
