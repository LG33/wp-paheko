<?php

namespace Garradin\Users;

use Garradin\DB;
use Garradin\Entities\Users\Category;
use KD2\DB\EntityManager as EM;

class Categories
{
	const HIDDEN_ONLY = 1;
	const WITHOUT_HIDDEN = 0;

	static public function get(int $id): ?Category
	{
		return EM::findOneById(Category::class, $id);
	}

	static protected function getHiddenClause(?int $hidden = null): string
	{
		if (self::HIDDEN_ONLY === $hidden) {
			return 'AND hidden = 1';
		}
		elseif (self::WITHOUT_HIDDEN === $hidden) {
			return 'AND hidden = 0';
		}

		return '';
	}

	static public function listAssoc(?int $hidden = null): array
	{
		return DB::getInstance()->getAssoc(sprintf('SELECT id, name FROM %s WHERE 1 %s ORDER BY name COLLATE U_NOCASE;',
			Category::TABLE, self::getHiddenClause($hidden)
		));
	}

	static public function listWithStats(?int $hidden = null): array
	{
		return DB::getInstance()->getGrouped(sprintf('SELECT c.id, c.*,
			(SELECT COUNT(*) FROM users WHERE id_category = c.id) AS count
			FROM %s c WHERE 1 %s ORDER BY c.name COLLATE U_NOCASE;',
			Category::TABLE, self::getHiddenClause($hidden)
		));
	}
}
