<?php

namespace Garradin\Accounting;

use Garradin\Entities\Accounting\Line;
use Garradin\Entities\Accounting\Transaction;
use Garradin\Entities\Accounting\Year;
use KD2\DB\EntityManager;
use Garradin\CSV;
use Garradin\DB;
use Garradin\Utils;
use Garradin\UserException;

class Transactions
{
	const EXPECTED_CSV_COLUMNS_SELF = ['id', 'type', 'status', 'label', 'date', 'notes', 'reference',
		'line_id', 'account', 'credit', 'debit', 'line_reference', 'line_label', 'reconciled'];

	const POSSIBLE_CSV_COLUMNS = [
		'id'             => 'Numéro d\'écriture',
		'label'          => 'Libellé',
		'date'           => 'Date',
		'notes'          => 'Notes',
		'reference'      => 'Numéro pièce comptable',
		'p_reference'    => 'Référence paiement',
		'debit_account'  => 'Compte de débit',
		'credit_account' => 'Compte de crédit',
		'amount'         => 'Montant',
	];

	const MANDATORY_CSV_COLUMNS = ['id', 'label', 'date', 'credit_account', 'debit_account', 'amount'];

	static public function get(int $id)
	{
		return EntityManager::findOneById(Transaction::class, $id);
	}

	static public function saveReconciled(\Generator $journal, ?array $checked)
	{
		if (!is_array($checked)) {
			return;
		}

		$db = DB::getInstance();
		$db->begin();

		// Synchro des trucs cochés
		$st = $db->prepare('UPDATE acc_transactions_lines SET reconciled = :r WHERE id = :id;');

		foreach ($journal as $row)
		{
			if (!isset($row->id_line)) {
				continue;
			}

			$st->bindValue(':id', (int)$row->id_line, \SQLITE3_INTEGER);
			$st->bindValue(':r', !empty($checked[$row->id_line]) ? 1 : 0, \SQLITE3_INTEGER);
			$st->execute();
		}

		$db->commit();
	}

	static public function saveDeposit(Transaction $transaction, \Generator $journal, array $checked)
	{
		$db = DB::getInstance();
		$db->begin();

		try {
			$ids = [];
			foreach ($journal as $row) {
				if (!array_key_exists($row->id, $checked)) {
					continue;
				}

				$ids[] = (int)$row->id;

				$line = new Line;
				$line->importForm([
					'reference'  => $row->line_reference,
					'id_account' => $row->id_account,
				]);
				$line->credit = $row->debit;

				$transaction->addLine($line);
			}

			$transaction->save();
			$ids = implode(',', $ids);
			$db->exec(sprintf('UPDATE acc_transactions SET status = (status | %d) WHERE id IN (%s);', Transaction::STATUS_DEPOSIT, $ids));
			$db->commit();
		}
		catch (\Exception $e) {
			$db->rollback();
			throw $e;
		}
	}

	static public function countForUser(int $user_id): int
	{
		return DB::getInstance()->count('acc_transactions_users', 'id_user = ?', $user_id);
	}

	static public function countForCreator(int $user_id): int
	{
		return DB::getInstance()->count('acc_transactions', 'id_creator = ?', $user_id);
	}

	/**
	 * Return all transactions from year
	 */
	static public function export(int $year_id): \Generator
	{
		$sql = 'SELECT t.id, t.type, t.status, t.label, t.date, t.notes, t.reference,
			l.id AS line_id, a.code AS account, l.debit AS debit, l.credit AS credit,
			l.reference AS line_reference, l.label AS line_label, l.reconciled
			FROM acc_transactions t
			INNER JOIN acc_transactions_lines l ON l.id_transaction = t.id
			INNER JOIN acc_accounts a ON a.id = l.id_account
			WHERE t.id_year = ? ORDER BY t.date, t.id, l.id;';

		$res = DB::getInstance()->iterate($sql, $year_id);

		$previous_id = null;

		foreach ($res as $row) {
			if ($previous_id === $row->id) {
				$row->id = $row->type = $row->status = $row->label = $row->date = $row->notes = $row->reference = null;
			}
			else {
				$row->type = Transaction::TYPES_NAMES[$row->type];

				$status = [];

				foreach (Transaction::STATUS_NAMES as $k => $v) {
					if ($row->status & $k) {
						$status[] = $v;
					}
				}
			}

			$row->status = implode(', ', $status);
			$row->credit = Utils::money_format($row->credit);
			$row->debit = Utils::money_format($row->debit);

			$previous_id = $row->id;
			yield $row;
		}
	}

	static public function importArray(Year $year, array $table, array $translation_table, int $skip_lines, int $user_id)
	{
		if ($year->closed) {
			throw new \InvalidArgumentException('Closed year');
		}

		unset($v);

		$db = DB::getInstance();
		$db->begin();

		$nb_columns = count($translation_table);

		if ($skip_lines)
		{
			$table = array_slice($table, $skip_lines, null, true);
		}

		$transaction = null;
		$accounts = $year->accounts();

		try {
			foreach ($table as $l => $row)
			{
				if (!count($row)) {
					continue;
				}

				$row = (object) array_combine($translation_table, $row);

				$has_transaction = !empty($row->id) || !empty($row->type) || !empty($row->status) || !empty($row->label) || !empty($row->date) || !empty($row->notes) || !empty($row->reference);

				if (null !== $transaction && $has_transaction) {
					$transaction->save();
					$transaction = null;
				}

				if (null === $transaction) {
					if (!$has_transaction) {
						throw new UserException(sprintf('Ligne %d : ligne reliée à aucune écriture', $l));
					}

					if ($row->id) {
						$transaction = self::get((int)$row->id);

						if (!$transaction) {
							throw new UserException(sprintf('Ligne %d : l\'écriture n°%d est introuvable', $l, $row->id));
						}

						if ($transaction->validated) {
							throw new UserException(sprintf('Ligne %d : l\'écriture n°%d est validée et ne peut être modifiée', $l, $row->id));
						}

						$transaction->resetLines();
					}
					else {
						$transaction = new Transaction;
						$transaction->type = Transaction::TYPE_ADVANCED;
					}

					$fields = array_intersect_key((array)$row, array_flip(['label', 'date', 'notes', 'reference']));

					$transaction->importForm($fields);
				}

				$row->credit_account = $accounts->getIdFromCode($row->credit_account);
				$row->debit_account = $accounts->getIdFromCode($row->debit_account);

				$line = new Line;
				$line->importForm([
					'credit'     => $row->amount,
					'debit'      => 0,
					'id_account' => $row->credit_account,
					'reference'  => $row->p_reference,
				]);
				$transaction->addLine($line);

				$line = new Line;
				$line->importForm([
					'credit'     => 0,
					'debit'      => $row->amount,
					'id_account' => $row->debit_account,
					'reference'  => $row->p_reference,
				]);
				$transaction->addLine($line);
			}

			if (null !== $transaction) {
				$transaction->save();
			}
		}
		catch (UserException $e) {
			$db->rollback();
			throw new UserException(sprintf('Erreur sur la ligne %d : %s', $l, $e->getMessage()));
		}

		$db->commit();
	}
}