{include file="_head.tpl" title="Démarrer avec Paheko" menu=false}

<p class="help">
	<strong>Bienvenue dans Paheko !</strong><br />
	Veuillez remplir les informations suivantes pour démarrer la gestion de votre association.
</p>

{form_errors}

<form method="post" action="{$self_url}">

<fieldset>
	<legend>Informations sur l'association</legend>
	<dl>
		{input type="select" required=true label="Pays (pour la comptabilité)" options=$countries default="FR" help="Ce choix permet de configurer les règles comptables en fonction du pays. Il sera possible de choisir plus tard un autre pays dans la configuration." name="country"}
		{input type="text" label="Nom de l'association" required=true name="name" default=$name autocomplete="organization"}
	</dl>
</fieldset>

{if $require_admin_account}
	<fieldset>
		<legend>Création du compte administrateur</legend>
		<dl>
			{input type="text" label="Nom et prénom" required=true name="user_name" default=$user_name autocomplete="name"}
			{* input type="email" label="Adresse E-Mail" required=true name="user_email" *}
		</dl>
		{* include file="users/_password_form.tpl" field="password" required=true *}
	</fieldset>
{/if}

<p class="submit">
	{csrf_field key=$csrf_key}
	{button type="submit" name="save" label="Commencer à gérer mon association" shape="right" class="main"}
</p>

</form>


{include file="_foot.tpl"}