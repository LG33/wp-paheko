<?php
namespace Paheko;

use Paheko\Users\Users;
use Paheko\Users\DynamicFields;
use Paheko\Services\Fees;
use Paheko\Services\Services;
use Paheko\Entities\Services\Service_User;

use Paheko\Plugin\HelloAsso_Checkout\API;

$csrf_key = 'helloasso_checkout_register';

if (!isset($_GET['service_id']))
    throw new UserException("Une activité (service_id) doit être spécifiée dans l'URL");

$service_id = (int) $_GET['service_id'];
$service = Services::get($service_id);
if ($service == null)
    throw new UserException("Aucune activité ne correspond au service_id $service_id");

$formFees = isset($_GET['fees']) ? (array) $_GET['fees'] : [];
$account = (array) $plugin->getConfig('account');

$form = new Form;
$tpl->assign_by_ref('form', $form);

$tpl->assign('layout', 'public');
$tpl->assign('status', 'validate');

$fields = DynamicFields::getInstance()->all();

$user = getUser($tpl, $form);
$tpl->assign_by_ref('user', $user);

$fees = array_filter(Fees::listAllByService(), fn($fee) => $fee->id_service == $service_id);
if (!empty($formFees))
    $fees = array_filter($fees, fn($fee) => in_array($fee->id, $formFees));
usort($fees, fn($a, $b) => $a->amount - $b->amount);

$selected_fee = $form('fee') != null ? current(array_filter($fees, fn($fee) => (string) $fee->id == (int) $form('fee'))) : null;

$tpl->assign(compact('csrf_key', 'service', 'fields', 'fees'));

/*$form->runIf('check', function () use ($tpl, $form, &$user, $service, $selected_fee) {
    $login_field = DynamicFields::getLoginField();
    $existing_user = Users::getFromLogin($form($login_field));
    $exists = !empty($existing_user);
    $tpl->assign('exists', $exists);

    if ($exists) {
        $user = $existing_user;
    } else {
        $user->{$login_field} = $form($login_field);
    }
});*/

$form->runIf('validate', function () use ($tpl, $form, $user, $service, $selected_fee) {
    $user->selfCheck();

    $first_name = "";
    $last_name = "";
    $name_fields = DynamicFields::getInstance()->getNameFields();
    foreach ($name_fields as $name_field) {
        $user->{$name_field} = trim(preg_replace("/[^a-zA-Z -]/", "", $form($name_field)));
        if ($name_field == 'first_name' || $name_field == 'prenom')
            $first_name = $form($name_field);
    }
    if (count($name_fields) == 1) {
        $splitted_names = explode(' ', $user->{$name_fields[0]});
        if (count($splitted_names) > 1) {
            $last_name = count($splitted_names) == 1 ? $splitted_names[1] : implode(' ', array_slice($splitted_names, 0, count($splitted_names) - 1));
            $first_name = $splitted_names[count($splitted_names) - 1];
        } else {
            $first_name = $last_name = $splitted_names[0];
        }
    }

    $payer = [
        'firstName' => $first_name,
        'lastName' => $last_name,
        'email' => $form('email')
    ];

    $checkout = API::getInstance()->createCheckout($selected_fee->amount, $service->label . ' - ' . $selected_fee->label, "service_id=$service->id", $payer);

    $tpl->assign('checkout', $checkout);
    $tpl->assign('status', 'checkout');
}, $csrf_key);

if (isset($_POST['success'])) {
    $checkout_id = (int) $form('checkout_id');

    $checkout = API::getInstance()->getCheckout($checkout_id);

    if (!isset($checkout) || !isset($checkout->order)) {
        throw new UserException("Le paiement n'a pas été jusqu'au bout. Vous n'évez pas été débité.");
    }
    //elseif ($status == 'success') {
    $user->setNumberIfEmpty();
    $user->save();

    $users = [$user->id => Users::getName($user->id)];
    $service_user_form = [
        'id_service' => $service_id,
        'id_fee' => $selected_fee->id,
        'amount' => $selected_fee->amount / 100,
        'create_payment' => 1,
        'account_selector' => $account,
        'notes' => "Commande n° " . $checkout->order->id,
        'paid' => 1,
        'date' => new \DateTime
    ];
    Service_User::createFromForm($users, null, false, $service_user_form);

    Utils::redirect(Utils::getSelfURI(['service_id' => $service_id, 'status' => 'success']));
} elseif (isset($_POST['canceled'])) {
    throw new UserException("Le paiement n'a pas été jusqu'au bout. Vous n'évez pas été débité.");

    /*$checkout_id = (int) $form('checkout_id');
    $checkout = API::getInstance()->getCheckout($checkout_id);

    $tpl->assign('checkout', $checkout);*/
} elseif (isset($_POST['error'])) {
    throw new UserException("Le paiement a échoué. Vous n'avez pas été débité. Si besoin, contactez l'association.");
}

function getUser($tpl, $form)
{
    $user = Users::create();
    $user->importForm();

    $login_field = DynamicFields::getLoginField();

    if (!empty($form($login_field))) {
        $existing_user = Users::getFromLogin($form($login_field));
        $exists = !empty($existing_user);
        $tpl->assign('exists', $exists);

        if ($exists) {
            $user = $existing_user;
        }
    }

    return $user;
}

$tpl->display(__DIR__ . '/../templates/register.tpl');