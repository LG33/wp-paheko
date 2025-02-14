<?php

namespace Paheko;

use Paheko\Utils;

require_once __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_WEB, $session::ACCESS_WRITE);

$pages = get_pages();

if (!$_GET || !$_GET['id']) {
	Utils::redirect('?id=' . $pages[0]->ID);
}

foreach ($pages as $key => &$page) {
	$page = [
		'label' => html_entity_decode($page->post_title),
		'value' => $page->ID,
		'href' => '?id=' . $page->ID,
	];
}

$pages[] = [
	'label' => "Créer une nouvelle page",
	'value' => 'new',
	'href' => '?id=new',
];

$tpl->assign('pages', $pages);

$tpl->display('web/index.tpl');
