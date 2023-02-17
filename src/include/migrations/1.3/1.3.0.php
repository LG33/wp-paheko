<?php

namespace Garradin;

use Garradin\Files\Files;
use Garradin\Entities\Files\File;

$db->beginSchemaUpdate();

// Get old keys
$config = (object) $db->getAssoc('SELECT key, value FROM config WHERE key IN (\'champs_membres\', \'champ_identifiant\', \'champ_identite\');');

// Create config_users_fields table
$db->exec('
CREATE TABLE IF NOT EXISTS config_users_fields (
    id INTEGER NOT NULL PRIMARY KEY,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL,
    type TEXT NOT NULL,
    label TEXT NOT NULL,
    help TEXT NULL,
    required INTEGER NOT NULL DEFAULT 0,
    read_access INTEGER NOT NULL DEFAULT 0,
    write_access INTEGER NOT NULL DEFAULT 1,
    list_table INTEGER NOT NULL DEFAULT 0,
    options TEXT NULL,
    default_value TEXT NULL,
    sql TEXT NULL,
    system TEXT NULL
);');

// Migrate users table
$df = \Garradin\Users\DynamicFields::fromOldINI($config->champs_membres, $config->champ_identifiant, $config->champ_identite, 'numero');
$df->save(false);

// Migrate other stuff
$db->import(ROOT . '/include/migrations/1.3/1.3.0.sql');

// Update searches
foreach ($db->iterate('SELECT * FROM searches;') as $row) {
	if ($row->type == 'json') {
		$json = json_decode($row->content);

		if (!$json) {
			$db->delete('searches', 'id = ?', $row->id);
			continue;
		}

		$json->groups = $json->query;
		unset($json->query, $json->limit);

		$content = json_encode($json);
	}
	else {
		$content = preg_replace('/\bmembres\b/', 'users', $row->content);
	}

	$db->update('searches', ['content' => $content], 'id = ' . (int) $row->id);
}

// Add signature to files
$files = $db->firstColumn('SELECT value FROM config WHERE key = \'files\';');
$files = json_decode($files);
$files->signature = null;
$db->exec(sprintf('REPLACE INTO config (key, value) VALUES (\'files\', %s);', $db->quote(json_encode($files))));

// Move skeletons from skel/ to skel/web/
// Don't use Files::get to get around validatePath security
$list = Files::list(File::CONTEXT_SKELETON);

foreach ($list as $file) {
	if ($file->name == 'web') {
		continue;
	}

	$file->move(File::CONTEXT_SKELETON . '/web');

	if ($file->type == $file::TYPE_DIRECTORY) {
		continue;
	}

	// Prepend "./" to includes functions file parameter
	foreach (Files::list(File::CONTEXT_SKELETON . '/web') as $file) {
		if ($file->type != File::TYPE_FILE || !preg_match('/\.(?:txt|css|js|html|htm)$/', $file->name)) {
			continue;
		}

		$file->setContent(preg_replace('/(\s+file=")(\w+)/', '$1./$2', $file->fetch()));
	}
}

$db->commitSchemaUpdate();