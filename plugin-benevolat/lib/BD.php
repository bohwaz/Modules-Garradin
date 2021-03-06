<?php
/**
 * Created by PhpStorm.
 * User: Nicolas
 * Date: 28/09/2017
 * Time: 15:48
 */

namespace Garradin\Plugin\Benevolat;

use Garradin\DB;
use Garradin\Utils;
use Garradin\Compta\Journal;
use Garradin\UserException;

class BD
{
    public function __construct()
    {

    }

    protected function _checkFieldsContribution(&$data)
    {
        $db = DB::getInstance();

        if (empty($data['date']) || !Utils::checkDate($data['date']))
        {
            throw new UserException('Date vide ou invalide.');
        }
        elseif (!$db->get('SELECT 1 FROM compta_exercices WHERE cloture = 0
            AND debut <= :date AND fin >= :date;', ['date' => $data['date']]))
        {
            throw new UserException('La date ne correspond pas à l\'exercice en cours.');
        }

        if (isset($data['id_categorie']))
        {
            if (!$db->get('SELECT 1 FROM plugin_benevolat_categorie WHERE id = ?;', (int)$data['id_categorie']))
            {
                throw new UserException('Catégorie inconnue.');
            }

            $data['id_categorie'] = (int)$data['id_categorie'];
        }
        else
        {
            throw new UserException('Une catégorie doit être spécifiée.');
        }

        return true;
    }

    protected function _checkFieldsCategorie(&$data)
    {
        if (empty($data['nom']) || !trim($data['nom']))
        {
            throw new UserException('Le nom de peut pas être vide.');
        }

        $data['nom'] = trim($data['nom']);

        return true;
    }

    protected function getTauxHoraire($id)
    {
        $db = DB::getInstance();
        return $db->first('SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = :id;', $id)->taux_horaire;
    }

    protected function getIdCompta($id)
    {
        $db = DB::getInstance();
        return $db->first('SELECT id_compta FROM plugin_benevolat_enregistrement WHERE id = :id;', $id)->id_compta;
    }

    public function addBenevolat($data_benevolat, $data_journal)
    {
        $this->_checkFieldsContribution($data_benevolat);

        $data_journal['libelle'] = 'Contribution bénévole';
        $data_journal['montant'] = $data_benevolat['heures'] * $this->getTauxHoraire($data_benevolat['id_categorie']);
        $data_journal['compte_debit'] = '864';
        $data_journal['compte_credit'] = '870';
        $data_journal['remarques'] = 'Pour supprimer cette opération, utiliser le plugin bénévolat.';

        $compte = new Journal();
        $data_benevolat['id_compta'] = $compte->add($data_journal);

        $db = DB::getInstance();
        $db->insert('plugin_benevolat_enregistrement', $data_benevolat);
        return $db->lastInsertRowId();
    }

    public function editContribution($id, $data_benevolat, $data_journal)
    {
        $this->_checkFieldsContribution($data_benevolat);

        $data_journal['libelle'] = 'Contribution bénévole';
        $data_journal['montant'] = $data_benevolat['heures'] * $this->getTauxHoraire($data_benevolat['id_categorie']);
        $data_journal['compte_debit'] = '864';
        $data_journal['compte_credit'] = '870';
        $data_journal['remarques'] = 'Pour supprimer cette opération, utiliser le plugin bénévolat.';

        $journal = new Journal();
        $journal->edit($this->getIdCompta($id), $data_journal);

        $db = DB::getInstance();
        $db->update('plugin_benevolat_enregistrement', $data_benevolat, 'id = \''.trim($id).'\'');
        return true;
    }

    public function removeBenevolat($id)
    {
        $id_compta = $this->getIdCompta($id);

        $db = DB::getInstance();
        $db->delete('plugin_benevolat_enregistrement', $db->where('id', $id));
        $journal = new Journal();
        $journal->delete($id_compta);
        return true;
    }

    public function getEnregistrement($id)
    {
        $db = DB::getInstance();
        return $db->first("SELECT ben.*,
            (SELECT SUBSTR(ben.description,0,50)) AS description_courte,
            (SELECT nom FROM membres WHERE id = ben.id_benevole) AS nom_membre,
			(SELECT id_auteur FORM FROM compta_journal WHERE id = ben.id_compta) AS id_auteur,
            (SELECT nom FROM membres WHERE id = (SELECT id_auteur FROM compta_journal WHERE id = ben.id_compta)) AS nom_auteur,
            (SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS taux_horaire,
            (SELECT nom FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS categorie,
            (SELECT (taux_horaire * ben.heures) FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS valorise,
			(SELECT id_projet FROM compta_journal WHERE id = ben.id_compta) AS id_projet,
			(SELECT libelle FROM compta_projets WHERE id = (SELECT id_projet FROM compta_journal WHERE id = ben.id_compta)) AS libelle_projet,
			(SELECT id_exercice FROM compta_journal WHERE id = ben.id_compta) AS id_exercice
            FROM plugin_benevolat_enregistrement AS ben WHERE id = :id;", $id);
    }

    public function addCategorie($data)
    {
        $db = DB::getInstance(true);
        $db->insert('plugin_benevolat_categorie', $data);
    }

    public function editCategorie($id, $data)
    {
        $db = DB::getInstance();
        $db->update('plugin_benevolat_categorie', $data, 'id = \''.trim($id).'\'');
        return true;
    }

    public function removeCategorie($id)
    {
        $db = DB::getInstance();

        if($db->test('plugin_benevolat_enregistrement',$db->where('id_categorie', $id)))
        {
            throw new UserException('Cette catégorie ne peut être supprimée car des contributions bénévoles y sont associées.');
        }
        return $db->delete('plugin_benevolat_categorie', $db->where('id', $id));
    }

    public function getCategorie($id)
    {
        $db = DB::getInstance();
        return $db->first("SELECT * FROM plugin_benevolat_categorie WHERE id = :id;", $id);
    }

    public function getEnregistrements()
    {
        $db = DB::getInstance();
        return $db->get("SELECT ben.*,
            (SELECT SUBSTR(ben.description,0,50)) AS description_courte,
            (SELECT nom FROM membres WHERE id = ben.id_benevole) AS nom_membre,
            (SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS taux_horaire,
            (SELECT nom FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS categorie,
            (SELECT (taux_horaire * ben.heures) FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS valorise
            FROM plugin_benevolat_enregistrement AS ben;");
    }

    public function getLastsEnregistrements()
    {
        $db = DB::getInstance();
        return $db->get("SELECT ben.*,
            (SELECT SUBSTR(ben.description,0,50)) AS description_courte,
            (SELECT nom FROM membres WHERE id = ben.id_benevole) AS nom,
            (SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS taux_horaire,
            (SELECT nom FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS categorie
            FROM plugin_benevolat_enregistrement AS ben ORDER BY id DESC LIMIT 5;");
    }

    public function getStatsCategorie($id)
    {
        $db = DB::getInstance();
        return $db->first("SELECT cat.*,
            (SELECT SUM(heures) FROM plugin_benevolat_enregistrement WHERE id_categorie = cat.id) AS nb_heures
            FROM plugin_benevolat_categorie AS cat WHERE id = :id;", ['id' => (int) $id]);
    }

    public function getEnregistrementsCategorie($id)
    {
        $db = DB::getInstance();
        return $db->get("SELECT ben.*,
            (SELECT nom FROM membres WHERE id = ben.id_benevole) AS nom_membre,
            (SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS taux_horaire,
            (SELECT nom FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS categorie,
            (SELECT (taux_horaire * ben.heures) FROM plugin_benevolat_categorie WHERE id = ben.id_categorie) AS valorise
            FROM plugin_benevolat_enregistrement AS ben
            WHERE id_categorie = :id;",['id' => (int) $id]);
    }

    public function getListeCategories()
    {
        $db = DB::getInstance();
        return $db->get("SELECT cat.*,
            (SELECT TOTAL(heures) FROM plugin_benevolat_enregistrement WHERE id_categorie = cat.id) AS nb_heures
            FROM plugin_benevolat_categorie AS cat;");
    }

    protected $csv_header = [
        'id',
        'Date de début',
        'Date de fin',
        'Bénévole',
        'Heures',
        'Taux horaire €/h',
        'Nom catégorie horaire',
        'Valorisé €',
        'Description de l\'activité'
    ];

    public function toCSV()
    {
        $db = DB::getInstance();

        $res = $db->prepare('SELECT p.id,
            strftime(\'%d/%m/%Y\', date) AS date,
            strftime(\'%d/%m/%Y\', date_fin) AS date_fin,
            (SELECT nom FROM membres WHERE id = p.id_membre) AS benevole,
            p.heures,
            (SELECT taux_horaire FROM plugin_benevolat_categorie WHERE id = p.id_categorie) AS taux_horaire,
            (SELECT nom FROM plugin_benevolat_categorie WHERE id = p.id_categorie) AS nom_cat,
            (SELECT (taux_horaire * p.heures) FROM plugin_benevolat_categorie WHERE id = p.id_categorie) AS valorise,
            description
            FROM plugin_benevolat_enregistrement AS p
            ORDER BY date(date) ASC;')->execute();

        $fp = fopen('php://output', 'w');

        fputcsv($fp, $this->csv_header);

        while ($row = $res->fetchArray(SQLITE3_ASSOC))
        {
            fputcsv($fp, $row);
        }

        fclose($fp);

        return true;
    }

    public function getListeExercices()
    {
        $db = DB::getInstance();
        return $db->getAssoc('SELECT id, libelle, debut, fin
            FROM compta_exercices ORDER BY id DESC;');
    }

    public function getListeProjets()
    {
        $db = DB::getInstance();
        return $db->getAssoc('SELECT *
            FROM compta_projets ORDER BY libelle DESC;');
    }

    protected function getWhereClause(array $criterias)
    {
        $where = [];
        $db = DB::getInstance();

        foreach ($criterias as $name => $value)
        {
            $where[] = $db->where($name, $value);
        }

        return implode(' AND ', $where);
    }

    public function RAZ_table()
    {
        $db = DB::getInstance();
        $db->exec(file_get_contents(dirname(__FILE__) . "/../data/schema_remove.sql"));
        $db->exec(file_get_contents(dirname(__FILE__) . "/../data/schema.sql"));
    }


    public function compteResultatBenevolat(array $criterias)
    {
        $db = DB::getInstance();
        $where = $this->getWhereClause($criterias);

        $charges    = ['comptes' => [], 'total' => 0.0];
        $produits   = ['comptes' => [], 'total' => 0.0];
        $resultat   = 0.0;

        $res = $db->preparedQuery('SELECT compte, SUM(debit), SUM(credit)
            FROM
                (SELECT compte_debit AS compte, SUM(montant) AS debit, 0 AS credit
                    FROM compta_journal WHERE ' . $where . ' GROUP BY compte_debit
                UNION
                SELECT compte_credit AS compte, 0 AS debit, SUM(montant) AS credit
                    FROM compta_journal WHERE ' . $where . ' GROUP BY compte_credit)
            WHERE compte LIKE \'8%\'
            GROUP BY compte
            ORDER BY compte ASC;');

        while ($row = $res->fetchArray(SQLITE3_NUM))
        {
            list($compte, $debit, $credit) = $row;
            $classe = substr($compte, 0, 2);
            $parent = substr($compte, 0, 2);

            if ($classe == 86)
            {
                if (!isset($charges['comptes'][$parent]))
                {
                    $charges['comptes'][$parent] = ['comptes' => [], 'solde' => 0.0];
                }

                $solde = round($debit - $credit, 2);

                if (empty($solde))
                    continue;

                $charges['comptes'][$parent]['comptes'][$compte] = $solde;
                $charges['total'] += $solde;
                $charges['comptes'][$parent]['solde'] += $solde;
            }
            elseif ($classe == 87)
            {
                if (!isset($produits['comptes'][$parent]))
                {
                    $produits['comptes'][$parent] = ['comptes' => [], 'solde' => 0.0];
                }

                $solde = round($credit - $debit, 2);

                if (empty($solde))
                    continue;

                $produits['comptes'][$parent]['comptes'][$compte] = $solde;
                $produits['total'] += $solde;
                $produits['comptes'][$parent]['solde'] += $solde;
            }
        }

        $res->finalize();

        $resultat = $produits['total'] - $charges['total'];

        return ['charges' => $charges, 'produits' => $produits, 'resultat' => $resultat];
    }
}