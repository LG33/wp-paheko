<?php

namespace Garradin\Accounting;

use Garradin\DynamicList;
use Garradin\Users\DynamicFields;
use Garradin\AdvancedSearch as A_S;
use Garradin\DB;
use Garradin\Accounting\Years;
use Garradin\Entities\Accounting\Transaction;

use function Garradin\qg;

class AdvancedSearch extends A_S
{
	/**
	 * Returns list of columns for search
	 * @return array
	 */
	public function columns(): array
	{
		$db = DB::getInstance();

		$types = 'CASE t.type ';

		foreach (Transaction::TYPES_NAMES as $num => $name) {
			$types .= sprintf('WHEN %d THEN %s ', $num, $db->quote($name));
		}

		$types .= 'END';

		return [
			'id' => [
				'label'    => 'Numéro écriture',
				'type'     => 'integer',
				'null'     => false,
				'select'   => 't.id',
			],
			'date' => [
				'label'    => 'Date',
				'type'     => 'date',
				'null'     => false,
				'select'   => 't.date',
			],
			'label' => [
				'label'    => 'Libellé écriture',
				'type'     => 'text',
				'null'     => false,
				'select'   => 't.label',
				'order'    => 't.label COLLATE U_NOCASE %s',
			],
			'reference' => [
				'label'    => 'Numéro pièce comptable',
				'type'     => 'text',
				'null'     => true,
				'select'   => 't.reference',
				'order'    => 't.reference COLLATE U_NOCASE %s',
			],
			'notes' => [
				'label'    => 'Remarques',
				'type'     => 'text',
				'null'     => true,
				'select'   => 't.notes',
				'order'    => 't.notes COLLATE U_NOCASE %s',
			],
			'account_code' => [
				'textMatch'=> true,
				'label'    => 'Numéro de compte',
				'type'     => 'text',
				'null'     => false,
				'select'   => 'a.code',
			],
			'debit' => [
				'label'    => 'Débit',
				'type'     => 'text',
				'null'     => false,
				'select'   => 'l.debit',
				'normalize' => 'money',
			],
			'credit' => [
				'label'    => 'Crédit',
				'type'     => 'text',
				'null'     => false,
				'select'   => 'l.credit',
				'normalize' => 'money',
			],
			'line_label' => [
				'label'    => 'Libellé ligne',
				'type'     => 'text',
				'null'     => true,
				'select'   => 'l.label',
				'order'    => 'l.label COLLATE U_NOCASE %s',
			],
			'line_reference' => [
				'textMatch'=> true,
				'label'    => 'Référence ligne écriture',
				'type'     => 'text',
				'null'     => true,
				'select'   => 'l.reference',
			],
			'type' => [
				'textMatch'=> false,
				'label'    => 'Type d\'écriture',
				'type'     => 'enum',
				'null'     => false,
				'values'   => Transaction::TYPES_NAMES,
				'select'   => $types,
				'where'    => 't.type',
			],
			'id_year' => [
				'textMatch'=> false,
				'label'    => 'Exercice',
				'type'     => 'enum',
				'null'     => false,
				'values'   => $db->getAssoc('SELECT id, label FROM acc_years ORDER BY end_date;'),
				'select'   => 'y.label',
				'where'    => 't.id_year',
			],
			'project_code' => [
				'textMatch'=> true,
				'label'    => 'N° de compte projet',
				'type'     => 'text',
				'null'     => true,
				'select'   => 'a2.code',
			],
		];
	}

	public function simple(string $text, ?int $id_year = null): \stdClass
	{
		$query = [];

		$text = trim($text);

		if ($id_year) {
			$query[] = [
				'operator' => 'AND',
				'conditions' => [
					[
						'column'   => 'id_year',
						'operator' => '= ?',
						'values'   => [$id_year],
					],
				],
			];
		}

		// Match number: find transactions per credit or debit
		if (preg_match('/^=\s*\d+([.,]\d+)?$/', $text))
		{
			$text = ltrim($text, "\n\t =");
			$query[] = [
				'operator' => 'OR',
				'conditions' => [
					[
						'column'   => 'debit',
						'operator' => '= ?',
						'values'   => [$text],
					],
					[
						'column'   => 'credit',
						'operator' => '= ?',
						'values'   => [$text],
					],
				],
			];
		}
		// Match account number
		elseif ($id_year && preg_match('/^[0-9]+[A-Z]*$/', $text)
			&& ($year = Years::get($id_year))
			&& ($id = (new Accounts($year->id_chart))->getIdFromCode($text))) {
			Utils::redirect(sprintf('!acc/accounts/journal.php?id=%d&year=%d', $id, $id_year));
		}
		// Match date
		elseif (preg_match('!^\d{2}/\d{2}/\d{4}$!', $text) && ($d = Utils::get_datetime($text)))
		{
			$query[] = [
				'operator' => 'OR',
				'conditions' => [
					[
						'column'   => 'date',
						'operator' => '= ?',
						'values'   => [$d->format('Y-m-d')],
					],
				],
			];
		}
		// Match transaction ID
		elseif (preg_match('/^#[0-9]+$/', $text)) {
			return sprintf('!acc/transactions/details.php?id=%d', (int)substr($text, 1));
		}
		// Or search in label or reference
		else
		{
			$operator = 'LIKE %?%';
			$query[] = [
				'operator' => 'OR',
				'conditions' => [
					[
						'column'   => 'label',
						'operator' => $operator,
						'values'   => [$text],
					],
					[
						'column'   => 'reference',
						'operator' => $operator,
						'values'   => [$text],
					],
					[
						'column'   => 'reference',
						'operator' => $operator,
						'values'   => [$text],
					],
				],
			];
		}

		return (object) [
			'groups' => $query,
			'order' => 'id',
			'desc' => true,
		];
	}

	public function schema(): array
	{
		$db = DB::getInstance();
		$sql = sprintf('SELECT name, sql FROM sqlite_master WHERE %s ORDER BY name;', $db->where('name', ['acc_transactions', 'acc_transactions_lines', 'acc_accounts', 'acc_years']));
		return $db->getAssoc($sql);
	}

	public function make(string $query): DynamicList
	{
		$tables = 'acc_transactions AS t
			INNER JOIN acc_transactions_lines AS l ON l.id_transaction = t.id
			INNER JOIN acc_accounts AS a ON l.id_account = a.id
			INNER JOIN acc_years AS y ON t.id_year = y.id
			LEFT JOIN acc_accounts AS a2 ON l.id_analytical = a2.id';
		return $this->makeList($query, $tables, 'id', true, ['id', 'account_code', 'debit', 'credit']);
	}

	public function defaults(): \stdClass
	{
		$group = [
			'operator' => 'AND',
			'conditions' => [
				[
					'column'   => 'id_year',
					'operator' => '= ?',
					'values'   => [(int)qg('year') ?: Years::getCurrentOpenYearId()],
				],
				[
					'column'   => 'label',
					'operator' => 'LIKE %?%',
					'values'   => [''],
				],
			],
		];

		if (null !== qg('type')) {
			$group['conditions'][] = [
				'column' => 'type',
				'operator' => '= ?',
				'values' => [(int)qg('type')],
			];
		}

		if (null !== qg('account')) {
			$group['conditions'][] = [
				'column' => 'account_code',
				'operator' => '= ?',
				'values' => [qg('account')],
			];
		}

		return (object) ['groups' => [$group]];
	}
}
