<?php

namespace Garradin;

if ($user['droits']['membres'] < Membres::DROIT_ECRITURE)
{
    throw new UserException("Vous n'avez pas le droit d'accéder à cette page.");
}

$benevolat = new Plugin\Benevolat\BD();

$categorie = $benevolat->getCategorie((int)Utils::get('id'));

if(empty($categorie))
{
    throw new UserException('Categorie inexistante.');
}

$error = false;

if (!empty($_POST['delete']))
{
    if (!Utils::CSRF_check('cat_supprimer_'.$categorie['id']))
    {
        $error = 'Une erreur est survenue, merci de renvoyer le formulaire.';
    }
    else
    {
        try
        {
            $benevolat->removeCategorie($categorie['id']);
            utils::redirect(PLUGIN_URL . 'index.php?suppr_cat_ok');
        }
        catch (UserException $e)
        {
            $error = $e->getMessage();
        }
    }
}

$tpl->assign('error', $error);
$tpl->assign('categorie', $categorie);
$tpl->display(PLUGIN_ROOT . '/templates/cat_supprimer.tpl');
