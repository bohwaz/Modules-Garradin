<?php

namespace Garradin;

use Garradin\Plugin\SMS\SMS;

$cats = new Membres\Categories;
$recherche = new Recherche;

$user = $session->getUser();

if (f('send'))
{
    $form->check('send_message_co', [
        'sujet'      => 'required|string',
        'message'    => 'required|string',
        'recipients' => 'required|string',
    ]);

    if (preg_match('/^(categorie|recherche)_(\d+)$/', f('recipients'), $match))
    {
        if ($match[1] == 'categorie')
        {
            $recipients = $membres->listAllByCategory($match[2]);
        }
        else
        {
            $recipients = $recherche->search($match[2], 'id, email');
        }

        if (!count($recipients) || !isset($recipients[0]->email))
        {
            $form->addError('Aucun membre dans la liste.');
        }
    }
    else
    {
        throw new UserException('Destinataires invalides : ' . f('recipients'));
    }

    if (!$form->hasErrors())
    {
        try {
            $membres->sendMessage($recipients, f('sujet'),
                f('message'), (bool) f('copie'));

            Utils::redirect(ADMIN_URL . 'membres/?sent');
        }
        catch (UserException $e)
        {
            $form->addError($e->getMessage());
        }
    }
}

$tpl->assign('categories', $cats->listNotHidden());
$tpl->assign('recherches', $recherche->getList($user->id, 'membres'));

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
