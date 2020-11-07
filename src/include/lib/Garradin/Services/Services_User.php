<?php

namespace Garradin\Services;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Entities\Services\Service_User;
use KD2\DB\EntityManager;

class Services_User
{
	static public function get(int $id)
	{
		return EntityManager::findOneById(Service_User::class, $id);
	}

	static public function countForUser(int $user_id)
	{
		return DB::getInstance()->count(Service_User::TABLE, 'id_user = ?', $user_id);
	}

	static public function perUserList(int $user_id): DynamicList
	{
		$columns = [
			'id' => [
				'select' => 'su.id',
			],
			'label' => [
				'select' => 's.label',
				'label' => 'Activité',
			],
			'date' => [
				'label' => 'Date d\'inscription',
				'select' => 'su.date',
			],
			'status' => [
				'label' => 'Statut',
				'select' => 'CASE WHEN su.expiry_date < date() THEN -1 WHEN su.expiry_date >= date() THEN 1 ELSE 0 END',
			],
			'expiry' => [
				'label' => 'Date d\'expiration',
				'select' => 'MAX(su.expiry_date)',
			],
			'fee' => [
				'label' => 'Tarif',
				'select' => 'sf.label',
			],
			'paid' => [
				'label' => 'Payé',
				'select' => 'su.paid',
			],
			'amount' => [
				'label' => 'Reste à régler',
				'select' => 'expected_amount - SUM(tl.debit)',
			],
		];

		$tables = 'services_users su
			INNER JOIN services s ON s.id = su.id_service
			INNER JOIN services_fees sf ON sf.id = su.id_fee
			LEFT JOIN acc_transactions_users tu ON tu.id_service_user = su.id
			LEFT JOIN acc_transactions_lines tl ON tl.id_transaction = tu.id_transaction';
		$conditions = sprintf('su.id_user = %d', $user_id);

		$list = new DynamicList($columns, $tables, $conditions);
		$list->orderBy('date', true);
		$list->groupBy('su.id');
		$list->setCount('COUNT(*)');
		return $list;
	}
}