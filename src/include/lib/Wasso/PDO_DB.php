<?php
/*
	This file is part of KD2FW -- <http://dev.kd2.org/>

	Copyright (c) 2001-2020 BohwaZ <http://bohwaz.net/>
	All rights reserved.

	KD2FW is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Foobar is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with KD2FW.  If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * DB_SQLite3: a generic wrapper around SQLite3, adding easier access functions
 * Compatible API with DB, but instead of using PDO, uses SQLite3
 *
 * @author  bohwaz http://bohwaz.net/
 * @license AGPLv3
 */

namespace Wasso;

use KD2\DB\Date;
use KD2\DB\DB_Exception;
use KD2\DB\SQLite3;

use PDO;

class PDO_DB extends DB
{
	/**
	 * @var \PDO
	 */
	protected $db;

	/**
	 * @var int
	 */
	protected $transaction = 0;

	/**
	 * @var integer|null
	 */
	protected $flags = null;

	const DATE_FORMAT = 'Y-m-d';
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	static protected array $_compile_options;

	/**
	 * List of SQLite features and which version added support for it
	 * The key is the name of the feature, and the value is the version number
	 * if a compile option is required for that feature, it is mentioned after a plus sign
	 */
	const FEATURES = [
		// UPSERT
		// https://www.sqlite.org/lang_upsert.html
		'upsert' => '3.25.0',

		// UPDATE FROM
		// https://www.sqlite.org/lang_update.html#upfrom
		'update_from' => '3.33.0',

		// Generated columns
		// https://www.sqlite.org/gencol.html
		'generated_columns' => '3.31.0',

		// math functions
		// https://www.sqlite.org/lang_mathfunc.html
		'math' => '3.35.0+ENABLE_MATH_FUNCTIONS',

		// ALTER TABLE ... DROP COLUMN
		// https://www.sqlite.org/lang_altertable.html#altertabdropcol
		'drop_column' => '3.35.0',

		// ALTER TABLE ... RENAME COLUMN
		'rename_column' => '3.25.0',

		// FULL and RIGHT OUTER JOIN
		// https://www.sqlite.org/lang_select.html#rjoin
		'right_outer_join' => '3.39.0',
		'full_outer_join' => '3.39.0',

		// Window functions
		// Consider 3.28.0 instead of 3.25.0 as support is more extensive
		// https://www.sqlite.org/windowfunctions.html
		'window_functions' => '3.28.0',

		// FILTER for aggregates (eg. AVG(amount) FILTER (WHERE amount > 0))
		// https://www.sqlite.org/lang_aggfunc.html#aggfilter
		'aggregate_filter' => '3.30.1',

		// ORDER BY name DESC NULLS FIRST
		// https://www.sqlite.org/lang_select.html#nullslast
		'nulls_first_last' => '3.30.0',

		// VACUUM INTO
		// https://www.sqlite.org/lang_vacuum.html#vacuuminto
		'vacuum_into' => '3.27.0',

		// Common Table Expressions (WITH...)
		// https://www.sqlite.org/lang_with.html
		'cte' => '3.8.3',

		// PRAGMA table_list
		// https://www.sqlite.org/pragma.html#pragma_table_list
		'pragma_table_list' => '3.37.0',

		// unixepoch() date function
		// https://www.sqlite.org/lang_datefunc.html#uepch
		'function_unixepoch' => '3.38.0',

		// Use of date functions in CHECK constraints and in indexes on expressions
		// https://www.sqlite.org/deterministic.html#dtexception
		'date_functions_in_constraints' => '3.20.0',

		// INDEX on expressions
		// https://www.sqlite.org/expridx.html
		'index_expressions' => '3.9.0',

		// Basic json features
		// https://www.sqlite.org/json1.html#jquote
		'json' => '3.9.0+ENABLE_JSON1|3.38.0-OMIT_JSON',
		'json_quote' => '3.14.0+ENABLE_JSON1|3.38.0-OMIT_JSON',

		// json_patch function
		// https://www.sqlite.org/json1.html#jpatch
		'json_patch' => '3.18.0+ENABLE_JSON1|3.38.0-OMIT_JSON',

		// Support for -> and ->> operators
		// https://www.sqlite.org/json1.html#jptr
		'json2' => '3.38.0-OMIT_JSON',

		// Support for json_each AND read-only authorizer
		// See https://sqlite.org/forum/forumpost/d28110be11
		'json_each_readonly' => '3.41.0',

		// Support for JSONB
		// https://www.sqlite.org/json1.html
		'jsonb' => '3.45.0-OMIT_JSON',

		'fts3' => '3.5.0+ENABLE_FTS3',
		// ENABLE_FTS3 is also enabling FTS4, sometimes ENABLE_FTS4 does not exist but FTS4 is still supported
		'fts4' => '3.7.4+ENABLE_FTS3|3.7.4+ENABLE_FTS4',
		'fts5' => '3.9.0+ENABLE_FTS5',

		'dbstat' => '3.0.0+ENABLE_DBSTAT_VTAB',
	];

	public function close(): void
	{
		$this->__destruct();

		$this->db = null;
	}

	public function __construct(string $driver, array $params)
	{
		if (!defined('\SQLITE3_OPEN_READWRITE'))
		{
			throw new \Exception('SQLite3 PHP module is not installed.');
		}

		if (isset($params['flags'])) {
			$this->flags = $params['flags'];
		}

		parent::__construct($driver, $params);
	}

	public function __destruct()
	{
		if ($this->db) {
			try {
				foreach ($this->statements as $st) {
					$st->closeCursor();
				}
			}
			catch (\Exception $e) {
				// Ignore errors
			}
		}

		if ($this->db && ($this->flags & \SQLITE3_OPEN_READWRITE) && (time() % 20) == 0) {
			// https://www.sqlite.org/pragma.html#pragma_optimize
			// To achieve the best long-term query performance without the need to do
			// a detailed engineering analysis of the application schema and SQL,
			// it is recommended that applications run "PRAGMA optimize" (with no arguments)
			// just before closing each database connection.
			$this->db->exec('PRAGMA optimize;');
		}

		parent::__destruct();

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, null, $this);
		}
	}

	public function connect(): void
	{
		parent::connect();

		$this->db->setAttribute(PDO::ATTR_TIMEOUT, $this->pdo_attributes[PDO::ATTR_TIMEOUT] * 1000);

		// Force to rollback any outstanding transaction
		register_shutdown_function(function () {
			if ($this->db && $this->inTransaction())
			{
				$this->rollback();
			}
		});
	}

	public function createFunction(string $name, callable $callback): bool
	{
		if ($this->db)
		{
			return $this->db->sqliteCreateFunction($name, $callback);
		}
		else
		{
			$this->sqlite_functions[$name] = $callback;
			return true;
		}
	}

	public function createCollation(string $name, callable $callback): bool
	{
		if ($this->db)
		{
			return $this->db->sqliteCreateCollation($name, $callback);
		}
		else
		{
			$this->sqlite_collations[$name] = $callback;
			return true;
		}
	}

	public function escapeString(string $str): string
	{
		// escapeString is not binary safe: https://bugs.php.net/bug.php?id=62361
		$str = str_replace("\0", "\\0", $str);

		return \SQLite3::escapeString($str);
	}

	public function quote($value, int $parameter_type = 0): string
	{
		if (is_int($value)) {
			return $value;
		}

		return '\'' . $this->escapeString($value) . '\'';
	}

	public function begin()
	{
		$this->transaction++;

		if ($this->transaction == 1) {
			$this->connect();

			if ($this->callback) {
				call_user_func($this->callback, __FUNCTION__, null, $this, ... func_get_args());
			}

			return $this->db->exec('BEGIN;');
		}

		return true;
	}

	public function inTransaction()
	{
		return $this->transaction > 0;
	}

	public function commit()
	{
		if ($this->transaction == 0) {
			throw new \LogicException('Cannot commit a transaction: no transaction is running');
		}

		$this->transaction--;

		if ($this->transaction == 0) {
			$this->connect();

			$return = $this->db->exec('END;');

			if ($this->callback) {
				call_user_func($this->callback, __FUNCTION__, null, $this, ... func_get_args());
			}

			return $return;
		}

		return true;
	}

	public function rollback()
	{
		if ($this->transaction == 0) {
			throw new \LogicException('Cannot rollback a transaction: no transaction is running');
		}

		$this->transaction = 0;
		$this->connect();
		try {
			$this->db->exec('ROLLBACK;');
		} catch (\Exception $e) {

		}

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, null, $this, ... func_get_args());
		}

		return true;
	}

	public function getArgType(&$arg, string $name = ''): int
	{
		switch (gettype($arg))
		{
			case 'double':
				return \SQLITE3_FLOAT;
			case 'integer':
			case 'boolean':
				return \SQLITE3_INTEGER;
			case 'NULL':
				return \SQLITE3_NULL;
			case 'string':
				return \SQLITE3_TEXT;
			case 'array':
				if (count($arg) == 2
					&& in_array($arg[0], [\SQLITE3_FLOAT, \SQLITE3_INTEGER, \SQLITE3_NULL, \SQLITE3_TEXT, \SQLITE3_BLOB]))
				{
					$type = $arg[0];
					$arg = $arg[1];

					return $type;
				}
			case 'object':
				if ($arg instanceof Date) {
					$arg = $arg->format(self::DATE_FORMAT);
				}
				elseif ($arg instanceof \DateTime) {
					$arg = $arg->format(self::DATETIME_FORMAT);
				}

				return \SQLITE3_TEXT;
			default:
				throw new \InvalidArgumentException('Argument '.$name.' is of invalid type '.gettype($arg));
		}
	}

	/**
	 * Returns a statement after having checked a query is a SELECT,
	 * doesn't seem to contain anything that could help an attacker,
	 * and if $allowed is not NULL, will try to restrict the query to tables
	 * specified as array keys, and to columns (PHP8+ only) of these tables.
	 *
	 * Note that before PHP8+ this is less secure and doesn't restrict columns.
	 *
	 * @param  array  $allowed List of allowed tables and columns
	 * @param  string $query   SQL query
	 * @return \SQLite3Stmt
	 */
	public function protectSelect(?array $allowed, string $query)
	{
		$query = trim($query, "\n\t\r ;");

		if (preg_match('/;\s*(.+?)$/', $query, $match))
		{
			throw new DB_Exception('Only one single statement can be executed at the same time: ' . $match[0]);
		}

		// Forbid use of some strings that could give hints to an attacker:
		// PRAGMA, sqlite_version(), sqlite_master table, comments
		if (preg_match('/PRAGMA\s+|sqlite_version|sqlite_master|load_extension|ATTACH\s+|randomblob|sqlite_compileoption_|sqlite_offset|sqlite_source_|zeroblob|X\'\w|0x\w|sqlite_dbpage|fts3_tokenizer/i', $query, $match))
		{
			throw new DB_Exception('Invalid SQL query.');
		}

		if (null !== $allowed) {
			// PHP 8+
			if (method_exists($this->db, 'setAuthorizer')) {
				$this->setAuthorizer(function (int $action, ...$args) use ($allowed) {
					if ($action === \SQLite3::SELECT || $action === \SQLite3::FUNCTION) {
						return \SQLite3::OK;
					}

					// SQLite is triggering UPDATEs in Authorizer before version 3.41
					// when using json_each for example, allow for this case
					// @see https://sqlite.org/forum/forumpost/e86edcafc4ea6fcf
					if ($action === \SQLite3::UPDATE && $args[0] === 'sqlite_master') {
						return \SQLite3::OK;
					}

					if ($action !== \SQLite3::READ) {
						return \SQLite3::DENY;
					}

					list($table, $column) = $args;

					if (!array_key_exists($table, $allowed) && !array_key_exists('*', $allowed)) {
						return \SQLite3::DENY;
					}

					if (array_key_exists('!' . $table, $allowed)) {
						return \SQLite3::DENY;
					}

					if (isset($allowed[$table]) && in_array('~' . $column, $allowed[$table])) {
						return \SQLite3::IGNORE;
					}

					if (isset($allowed[$table]) && in_array('-' . $column, $allowed[$table])) {
						return \SQLite3::DENY;
					}

					return \SQLite3::OK;
				});
			}
			else {
				static $forbidden = ['ALTER', 'ADD', 'ATTACH', 'CREATE', 'COMMIT', 'CREATE', 'DELETE', 'DETACH', 'DROP', 'INSERT', 'PRAGMA', 'REINDEX', 'RENAME', 'REPLACE', 'ROLLBACK', 'SAVEPOINT', 'SET', 'TRIGGER', 'UPDATE', 'VACUUM', 'WITH'];

				$parsed = $this->parseQuery($query);

				foreach ($parsed as $keyword) {
					if (in_array($keyword, $forbidden)) {
						throw new DB_Exception('Unauthorized keyword: ' . $keyword);
					}

					foreach ($keyword->tables as $table) {
						if (!array_key_exists($table, $allowed) && !array_key_exists('*', $allowed)) {
							throw new DB_Exception('Unauthorized table: ' . $table);
						}

						if (array_key_exists('!' . $table, $allowed)) {
							throw new DB_Exception('Unauthorized table: ' . $table);
						}

						//if (null !== $allowed[$table]) {
						//	throw new \InvalidArgumentException('Cannot protect columns without PHP 8+');
						//}
					}
				}
			}
		}

		try {
			$st = $this->prepare($query);
		}
		finally {
			$this->setAuthorizer(null);
		}

		if (substr($st->queryString, 0, 6) !== "SELECT")
		{
			throw new DB_Exception('Only read-only queries are accepted.');
		}

		return $st;
	}

	public function setAuthorizer(?callable $fn): bool
	{
		/*if (method_exists(\SQLite3::class, 'setAuthorizer')) {
			$this->connect();
			$this->db->setAuthorizer($fn);
			return true;
		}*/

		return false;
	}

	public function setReadOnly(bool $enable): void
	{
		// Make sure the database is always read-only
		// @see https://www.sqlite.org/pragma.html#pragma_query_only
		$this->exec(sprintf('PRAGMA query_only = %d;', $enable));
	}

	public function parseQuery(string $query): array
	{
		static $keywords_string = 'ABORT ACTION ADD AFTER ALL ALTER ALWAYS ANALYZE AND AS ASC ATTACH AUTOINCREMENT BEFORE BEGIN BETWEEN BY CASCADE CASE CAST CHECK COLLATE COLUMN COMMIT CONFLICT CONSTRAINT CREATE CROSS CURRENT CURRENT_DATE CURRENT_TIME CURRENT_TIMESTAMP DATABASE DEFAULT DEFERRABLE DEFERRED DELETE DESC DETACH DISTINCT DO DROP EACH ELSE END ESCAPE EXCEPT EXCLUDE EXCLUSIVE EXISTS EXPLAIN FAIL FILTER FIRST FOLLOWING FOR FOREIGN FROM FULL GENERATED GLOB GROUP GROUPS HAVING IF IGNORE IMMEDIATE IN INDEX INDEXED INITIALLY INNER INSERT INSTEAD INTERSECT INTO IS ISNULL JOIN KEY LAST LEFT LIKE LIMIT MATCH NATURAL NO NOT NOTHING NOTNULL NULL NULLS OF OFFSET ON OR ORDER OTHERS OUTER OVER PARTITION PLAN PRAGMA PRECEDING PRIMARY QUERY RAISE RANGE RECURSIVE REFERENCES REGEXP REINDEX RELEASE RENAME REPLACE RESTRICT RIGHT ROLLBACK ROW ROWS SAVEPOINT SELECT SET TABLE TEMP TEMPORARY THEN TIES TO TRANSACTION TRIGGER UNBOUNDED UNION UNIQUE UPDATE USING VACUUM VALUES VIEW VIRTUAL WHEN WHERE WINDOW WITH WITHOUT';

		$keywords = explode(' ', $keywords_string);
		$keywords = str_replace(' ', '|', $keywords);

		$query = rtrim($query, ';');

		preg_match_all('/((["\'])(?:\\\2|.)*?\2|\b(?:' . implode('|', $keywords) . ')\b|[\w]+(?:\s*\.\s*[\w]+)*)/ims', $query, $match);

		$current = null;
		$query = [];

		foreach ($match[0] as $v) {
			$kw = strtoupper($v);

			if (in_array($kw, $keywords)) {
				$query[$kw] = (object) ['tables' => [], 'content' => []];
				$current = $kw;
			}
			elseif (null !== $current) {
				if ($current == 'FROM' || $current == 'JOIN') {
					$query[$current]->tables[] = $v;
				}
				else {
					$query[$current]->content[] = $v;
				}
			}
		}

		return $query;
	}

	/**
	 * Executes a prepared query using $args array
	 * @return \SQLite3Stmt|boolean Returns a boolean if the query is writing
	 * to the database, or a statement if it's a read-only query.
	 *
	 * The fact that this method returns a boolean is voluntary, to avoid a bug
	 * in SQLite3/PHP where you can re-run a query by calling fetchResult
	 * on a statement. This could cause double writing.
	 */
	public function preparedQuery(string $query, ...$args)
	{
		return parent::preparedQuery($query, ...$args);
	}

	public function query(string $statement)
	{
		$this->connect();
		$statement = $this->applyTablePrefix($statement);

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'before', $this, ... func_get_args());
		}

		$return = $this->db->query($statement);

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'after', $this, ... func_get_args());
		}

		return $return;
	}

	public function iterate(string $query, ...$args): iterable
	{
		$res = $this->preparedQuery($query, ...$args);

		
		foreach ($res->fetchAll(PDO::FETCH_OBJ) as $key => $row) {
			yield (object) $row;
		}

		return;
	}

	public function get(string $query, ...$args): array
	{
		$res = $this->preparedQuery($query, ...$args);
		$out = [];

		foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $key => $row) {
			$out[$key] = (object) $row;
		}

		return $out;
	}

	public function getAssoc(string $query, ...$args): array
	{
		$res = $this->preparedQuery($query, ...$args);
		$out = [];

		foreach ($res->fetchAll(PDO::FETCH_NUM) as $key => $row) {
			$out[$row[0]] = $row[1];
		}

		return $out;
	}

	public function getGrouped(string $query, ...$args): array
	{
		$res = $this->preparedQuery($query, ...$args);
		$out = [];

		foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $key => $row) {
			$out[current($row)] = (object) $row;
		}

		return $out;
	}

	/**
	 * Executes multiple queries in a transaction
	 */
	public function execMultiple(string $statement)
	{
		$this->begin();

		try {
			$statement = $this->applyTablePrefix($statement);
			$this->db->exec($statement);
		}
		catch (\Exception $e)
		{
			$this->rollback();

			if ($this->db->errorCode()) {
				throw new DB_Exception($this->db->errorInfo()[2], $this->db->errorInfo()[1], $e);
			}

			throw $e;
		}

		return $this->commit();
	}

	public function exec(string $statement)
	{
		$this->connect();
				foreach ($this->statements as $st) {
					$st->closeCursor();
				}
		$statement = $this->applyTablePrefix($statement);

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'before', $this, ... func_get_args());
		}

		try {
			$return = $this->db->exec($statement);
		}
		catch (\Exception $e) {
			if ($this->db->errorCode()) {
				throw new DB_Exception($this->db->errorInfo()[2], $this->db->errorInfo()[1], $e);
			}

			throw $e;
		}

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'after', $this, ... func_get_args());
		}

		return $return;
	}

	/**
	 * Runs a query and returns the first row from the result
	 * @param  string $query
	 * @return object|bool
	 *
	 * Accepts one or more arguments for the prepared query
	 */
	public function first(string $query, ...$args)
	{
		$res = $this->preparedQuery($query, ...$args);

		return $res->fetch();
	}

	/**
	 * Runs a query and returns the first column of the first row of the result
	 * @param  string $query
	 * @return object
	 *
	 * Accepts one or more arguments for the prepared query
	 */
	public function firstColumn(string $query, ...$args)
	{
		$res = $this->preparedQuery($query, ...$args);

		$row = $res->fetchColumn();

		return $row;
	}

	public function upsert(string $table, array $params, array $conflict_columns)
	{
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s;',
			$table,
			implode(', ', array_keys($params)),
			':' . implode(', :', array_keys($params)),
			implode(', ', $conflict_columns),
			implode(', ', array_map(fn($a) => $a . ' = :' . $a, array_keys($params)))
		);

		return $this->preparedQuery($sql, $params);
	}

	public function countRows(\SQLite3Result $result): int
	{
		$i = 0;

		while ($result->fetchAll(PDO::FETCH_NUM))
		{
			$i++;
		}

		$result->reset();

		return $i;
	}

	public function lastInsertId($name = null): string
	{
		return $this->db->lastInsertId();
	}

	public function lastInsertRowId(): string
	{
		return $this->db->lastInsertId();
	}

	public function prepare(string $statement, array $driver_options = [])
	{
		$this->connect();
		$statement = $this->applyTablePrefix($statement);

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'before', $this, ... func_get_args());
		}

		try {
			$return = $this->db->prepare($statement);
		}
		catch (\Exception $e) {
			if ($this->db->errorCode()) {
				throw new DB_Exception($this->db->errorInfo()[2], $this->db->errorInfo()[1], $e);
			}

			throw $e;
		}

		if ($this->callback) {
			call_user_func($this->callback, __FUNCTION__, 'after', $this, ... func_get_args());
		}

		return $return;
	}

	public function openBlob(string $table, string $column, int $rowid, string $dbname = 'main', int $flags = \SQLITE3_OPEN_READONLY)
	{
		$this->close();
		
		$file = str_replace('sqlite:', '', $this->driver->url);
		$sqlite = new SQLite3('sqlite', ['file' => $file]);
		$sqlite->begin();

			
		$stmt = $sqlite->prepare(sprintf('INSERT OR REPLACE INTO files_contents (id, content) VALUES (?, zeroblob(?));',
		$rowid, $file->size));
		$stmt->execute();
			
		return $sqlite->openBlob($table, $column, $rowid, $dbname, $flags);

		/*$blob = null;
		$stmt = $this->prepare("SELECT `$column` FROM `$table` WHERE `id` = $rowid");
		if ($stmt->execute()) {
			$stmt->bindColumn(1, $blob, \PDO::PARAM_LOB);
			if ($stmt->fetch(\PDO::FETCH_BOUND))
				return $blob;
		}*/
	}

	/**
	 * Import a file containing SQL commands
	 * Allows to use the statement ".read other_file.sql" to load other files
	 * Also supported is the ".import file.csv table"
	 * @param  string $file Path to file containing SQL commands
	 * @return boolean
	 */
	public function import(string $file)
	{
		$sql = file_get_contents($file);
		$sql = str_replace("\r\n", "\n", $sql);
		$sql = preg_split("/\n{2,}/", $sql, -1, PREG_SPLIT_NO_EMPTY);

		$statement = '';

		$dir = realpath(dirname($file));

		foreach ($sql as $line) {
			$line = trim($line);

			// Sub-import statements
			if (preg_match('/^\.read (.+\.sql)$/', $line, $match)) {
				$this->import($dir . DIRECTORY_SEPARATOR . $match[1]);
				$statement = '';
				continue;
			}
			elseif (preg_match('/^\.import (.+\.csv) (\w+)$/', $line, $match)) {
				$fp = fopen($dir . DIRECTORY_SEPARATOR . $match[1], 'r');
				$st = null;

				while ($row = fgetcsv($fp)) {
					if (null === $st) {
						$columns = substr(str_repeat('?, ', count($row)), 0, -2);
						$st = $this->db->prepare(sprintf('INSERT INTO %s VALUES (%s);', $this->quoteIdentifier($match[2]), $columns));
					}

					foreach ($row as $i => $value) {
						$st->bindValue($i + 1, $value);
					}

					$st->execute();
					$st->reset();
					$st->clear();
				}

				$statement = '';
				continue;
			}

			$statement .= $line . "\n";

			if (substr($line, -1) !== ';') {
				continue;
			}

			try {
				$this->exec($statement);
			}
			catch (\Exception $e) {
				throw new \Exception(sprintf("Error in '%s': %s\n%s", basename($file), $e->getMessage(), $statement), 0, $e);
			}

			$statement = '';
		}

		return true;
	}

	/**
	 * Performs a foreign key check and throws an exception if any error is found
	 * @return void
	 * @throws \LogicException
	 * @see https://www.sqlite.org/pragma.html#pragma_foreign_key_check
	 */
	public function foreignKeyCheck(): void
	{
		$result = $this->get('PRAGMA foreign_key_check;');

		// No error
		if (!count($result)) {
			return;
		}

		$errors = [];
		$tables = [];
		$ref = null;

		foreach ($result as $row) {
			if (!array_key_exists($row->table, $tables)) {
				$tables[$row->table] = $this->get(sprintf('PRAGMA foreign_key_list(%s);', $row->table));
			}

			// Findinf the referenced foreign key
			foreach ($tables[$row->table] as $fk) {
				if ($fk->id == $row->fkid) {
					$ref = $fk;
					break;
				}
			}

			$data = $this->first(sprintf('SELECT * FROM %s WHERE rowid = ?;', $row->table), $row->rowid);
			$errors[] = sprintf("%s (%s): row %d has an invalid reference to %s (%s)\n%s", $row->table, $ref->from, $row->rowid, $row->parent, $ref ? $ref->to : null, json_encode($data));
		}

		throw new \LogicException(sprintf("Foreign key check: %d errors found\n", count($errors)) . implode("\n", $errors));
	}

	public function backup($destination, string $sourceDatabase = 'main' , string $destinationDatabase = 'main'): bool
	{
		if (is_a($destination, self::class)) {
			$destination = $destination->db;
		}

		return $this->db->backup($destination, $sourceDatabase, $destinationDatabase);
	}

	static public function getDatabaseDetailsFromString(string $source_string): array
	{
		if (substr($source_string, 0, 16) !== "SQLite format 3\0" || strlen($source_string) < 100) {
			return null;
		}

		$user_version = bin2hex(substr($source_string, 60, 4));
		$application_id = bin2hex(substr($source_string, 68, 4));

		return compact('user_version', 'application_id');
	}

	/**
	 * Returns compile options
	 */
	public function getCompileOptions(): array
	{
		if (!isset(self::$_compile_options)) {
			self::$_compile_options = [];
			$db = new \SQLite3(':memory:');
			$res = $db->query('PRAGMA compile_options;');

			while ($row = $res->fetchArray(\SQLITE3_NUM)) {
				self::$_compile_options[] = $row[0];
			}

			$db->close();
		}

		return self::$_compile_options;
	}

	/**
	 * Returns a list of supported features
	 */
	public function getFeatures(): array
	{
		$version = \SQLite3::version()['versionString'];
		$compile_options = $this->getCompileOptions();

		foreach (self::FEATURES as $feature => $test_string) {
			$tests = explode('|', $test_string);

			foreach ($tests as $test) {
				if (!preg_match('/^([\d\.]+)(?:\+([A-Z0-9_]+))?(?:-([A-Z0-9_]+))?$/', $test, $match)) {
					throw new \LogicException('Invalid test string: ' . $test);
				}

				if (!version_compare($version, $match[1], '>=')) {
					continue;
				}

				if (!empty($match[2]) && !in_array($match[2], $compile_options)) {
					continue;
				}

				// if this option is present, then the feature is disabled
				if (!empty($match[3]) && in_array($match[3], $compile_options)) {
					continue;
				}

				$out[] = $feature;
			}
		}

		return $out;
	}

	/**
	 * Check for features
	 * ->hasFeatures('json', 'update_from')
	 */
	public function hasFeatures(...$features): bool
	{
		$all = $this->getFeatures();
		$found = array_intersect($all, $features);
		return count($found) == count($features);
	}

	public function requireFeatures(...$features): void
	{
		$missing_features = array_diff($features, $this->getFeatures());

		if (count($missing_features)) {
			$version = \SQLite3::version()['versionString'];
			throw new DB_Exception(sprintf('The required SQLite features (%s) are not available in the installed SQLite version (%s).', implode(', ', $missing_features), $version));
		}
	}

	public function getTableForeignKeys(string $name): array
	{
		$fk = [];

		$r = $this->db->query(sprintf('PRAGMA foreign_key_list(%s);', $name));

		foreach ($r->fetchAll(PDO::FETCH_NAMED) as $row) {
			$columns = explode(',', $row['from']);
			$columns = array_map('trim', $columns);

			foreach ($columns as $c) {
				$fk[$c] = $row;
			}
		}

		return $fk;
	}

	public function getTableSchema(string $name): array
	{
		$fk = $this->getTableForeignKeys($name);
		$table = ['name' => $name, 'comment' => null, 'columns' => []];

		$name = $this->quote($name);
		$schema = $this->db->query(sprintf('SELECT sql FROM sqlite_master WHERE name = %s AND type = \'table\';', $name))->fetchColumn();

		if (preg_match('/CREATE\s+TABLE\s+(?s:(?!\(|--).*?)--[ ]*(.+)$\s*\(/m', $schema, $match)) {
			$table['comment'] = trim($match[1]);
		}

		$r = $this->db->query(sprintf('PRAGMA table_info(%s);', $name));
		
		foreach($r->fetchAll(PDO::FETCH_NAMED) as $row) {
			$row['fk'] = $fk[$row['name']] ?? null;
			$row['comment'] = null;

			$regexp = sprintf('/\b%s\s+.*?--(.*?)$/m', preg_quote($row['name'], '/'));

			if (preg_match($regexp, $schema, $match)) {
				$row['comment'] = trim($match[1]);
			}

			$table['columns'][$row['name']] = $row;
		}

		$table['schema'] = $schema;

		return $table;
	}

	public function getTableIndexes(string $name): array
	{
		$columns = [];

		$r = $this->db->query(sprintf('PRAGMA index_list(%s);', $name));

		foreach($r->fetchAll(PDO::FETCH_NAMED) as $row) {
			$r2 = $this->db->query(sprintf('PRAGMA index_xinfo(%s);', $row['name']));
			$row['columns'] = [];

			foreach($r2->fetchAll(PDO::FETCH_NAMED) as $row2) {
				$row['columns'][$row2['name']] = $row2;
			}

			$columns[] = $row;
		}

		return $columns;
	}

	public function getTableSize(string $name): ?int
	{
		if (!$this->hasFeatures('dbstat')) {
			return null;
		}

		return (int) $this->db->query(sprintf('SELECT SUM(pgsize) FROM dbstat WHERE name = %s;', $this->quote($name)), false)->fetchColumn();
	}
}
