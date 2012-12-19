<?php

class Garradin_Compta_Comptes
{
    const CAISSE = 530;

    const PASSIF = 0x01;
    const ACTIF = 0x02;
    const PRODUIT = 0x04;
    const CHARGE = 0x08;

    public function importPlan()
    {
        $plan = json_decode(file_get_contents(GARRADIN_ROOT . '/include/data/plan_comptable.json'), true);

        $db = Garradin_DB::getInstance();
        $db->exec('BEGIN;');
        $ids = array();

        foreach ($plan as $id=>$compte)
        {
            $ids[] = $id;

            if ($db->simpleQuerySingle('SELECT 1 FROM compta_comptes WHERE id = ?;', false, $id))
            {
                $db->simpleUpdate('compta_comptes', array(
                    'parent'    =>  $compte['parent'],
                    'libelle'   =>  $compte['nom'],
                    'position'  =>  $compte['position'],
                    'plan_comptable' => 1,
                ), 'id = \''.$db->escapeString($id).'\'');
            }
            else
            {
                $db->simpleInsert('compta_comptes', array(
                    'id'        =>  $id,
                    'parent'    =>  $compte['parent'],
                    'libelle'   =>  $compte['nom'],
                    'position'  =>  $compte['position'],
                    'plan_comptable' => 1,
                ));
            }
        }

        $db->exec('DELETE FROM compta_comptes WHERE id NOT IN(\''.implode('\', \'', $ids).'\') AND plan_comptable = 1;');

        $db->exec('END;');

        return true;
    }

    public function add($data)
    {
        $this->_checkFields($data, true);

        $db = Garradin_DB::getInstance();

        if (empty($data['id']))
        {
            $new_id = $data['parent'];
            $nb_sous_comptes = $db->simpleQuerySingle('SELECT COUNT(*) FROM compta_comptes WHERE parent = ?;', false, $new_id);

            // Pas plus de 26 sous-comptes par compte, parce que l'alphabet s'arrête à 26 lettres
            if ($nb_sous_comptes >= 26)
            {
                throw new UserException('Nombre de sous-comptes maximal atteint pour ce compte parent-ci.');
            }

            $new_id .= chr(65+(int)$nb_sous_comptes);
        }
        else
        {
            $new_id = $data['id'];
        }

        if (isset($data['position']))
        {
            $position = (int) $data['position'];
        }
        else
        {
            $position = $db->simpleQuerySingle('SELECT position FROM compta_comptes WHERE id = ?;', false, $data['parent']);
        }

        $db->simpleInsert('compta_comptes', array(
            'id'        =>  $new_id,
            'libelle'   =>  trim($data['libelle']),
            'parent'    =>  $data['parent'],
            'plan_comptable' => 0,
            'position'  =>  (int)$position,
        ));

        return $new_id;
    }

    public function edit($id, $data)
    {
        $db = Garradin_DB::getInstance();

        // Vérification que l'on peut éditer ce compte
        if ($db->simpleQuerySingle('SELECT plan_comptable FROM compta_comptes WHERE id = ?;', false, $id))
        {
            throw new UserException('Ce compte fait partie du plan comptable et n\'est pas modifiable.');
        }

        if (isset($data['position']) && empty($data['position']))
        {
            throw new UserException('Aucune position du compte n\'a été indiquée.');
        }

        $this->_checkFields($data);

        $update = array(
            'libelle'   =>  trim($data['libelle']),
        );

        if (isset($data['position']))
        {
            $update['position'] = (int) trim($data['position']);
        }

        $db->simpleUpdate('compta_comptes', $update, 'id = \''.$db->escapeString(trim($id)).'\'');

        return true;
    }

    public function delete($id)
    {
        $db = Garradin_DB::getInstance();

        // Ne pas supprimer un compte qui est utilisé !
        if ($db->simpleQuerySingle('SELECT 1 FROM compta_journal WHERE compte_debit = ? OR compte_debit = ? LIMIT 1;', false, $id, $id))
        {
            throw new UserException('Ce compte ne peut être supprimé car des opérations comptables y sont liées.');
        }

        $db->simpleExec('DELETE FROM compta_comptes WHERE id = ?;', trim($id));

        return true;
    }

    public function get($id)
    {
        $db = Garradin_DB::getInstance();
        return $db->simpleQuerySingle('SELECT * FROM compta_comptes WHERE id = ?;', true, trim($id));
    }

    public function getList($parent = 0)
    {
        $db = Garradin_DB::getInstance();
        return $db->simpleStatementFetchAssocKey('SELECT id, * FROM compta_comptes WHERE parent = ? ORDER BY id;', SQLITE3_ASSOC, $parent);
    }

    public function getListAll($parent = 0)
    {
        $db = Garradin_DB::getInstance();
        return $db->queryFetchAssoc('SELECT id, libelle FROM compta_comptes ORDER BY id;');
    }

    public function listTree($parent = 0, $include_children = true)
    {
        $db = Garradin_DB::getInstance();

        if ($include_children)
        {
            $parent = $parent ? 'WHERE parent LIKE \''.$db->escapeString($parent).'%\' ' : '';
        }
        else
        {
            $parent = $parent ? 'WHERE parent = \''.$db->escapeString($parent).'\' ' : 'WHERE parent = 0';
        }

        return $db->simpleStatementFetch('SELECT * FROM compta_comptes '.$parent.' ORDER BY id;');
    }

    protected function _checkFields(&$data, $force_parent_check = false)
    {
        $db = Garradin_DB::getInstance();

        if (empty($data['libelle']) || !trim($data['libelle']))
        {
            throw new UserException('Le libellé ne peut rester vide.');
        }

        $data['libelle'] = trim($data['libelle']);

        if (isset($data['id']))
        {
            $force_parent_check = true;
            $data['id'] = trim($data['id']);

            if ($db->simpleQuerySingle('SELECT 1 FROM compta_comptes WHERE id = ?;', false, $data['id']))
            {
                throw new UserException('Le compte numéro '.$data['id'].' existe déjà.');
            }
        }

        if (isset($data['parent']) || $force_parent_check)
        {
            if (empty($data['parent']) && !trim($data['parent']))
            {
                throw new UserException('Le compte ne peut pas ne pas avoir de compte parent.');
            }

            if (!($id = $db->simpleQuerySingle('SELECT id FROM compta_comptes WHERE id = ?;', false, $data['parent'])))
            {
                throw new UserException('Le compte parent indiqué n\'existe pas.');
            }

            $data['parent'] = trim($id);
        }

        if (isset($data['id']))
        {
            if (strncmp($data['id'], $data['parent'], strlen($data['parent'])) !== 0)
            {
                throw new UserException('Le compte '.$data['id'].' n\'est pas un sous-compte de '.$data['parent'].'.');
            }
        }

        return true;
    }

    public function getPositions()
    {
        return array(
            self::ACTIF     =>  'Actif',
            self::PASSIF    =>  'Passif',
            self::ACTIF | self::PASSIF      =>  'Actif et passif',
            self::CHARGE    =>  'Charge',
            self::PRODUIT   =>  'Produit',
            self::CHARGE | self::PRODUIT    =>  'Charge et produit',
        );
    }
}

?>