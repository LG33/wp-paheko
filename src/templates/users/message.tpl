{include file="_head.tpl" title="Contacter un membre" current="membres"}

{form_errors}

<form method="post" action="{$self_url}">
	<fieldset class="message">
		<legend>Message</legend>
		<dl>
			<dt>Destinataire</dt>
			<dd>{$recipient->getNameAndEmail()}</dd>
			{input type="radio-btn" name="sender" value="self" default="self" required=true label="Membre" help=$self->getNameAndEmail() prefix_label="Expéditeur" prefix_required=true}
			{input type="radio-btn" name="sender" value="org" default="self" required=true label="Association" help="%s  <%s>"|args:$config.org_name:$config.org_email}
			{input type="text" name="subject" required=true label="Sujet" class="full-width"}
			{input type="textarea" name="message" required=true label="Message" rows=15 class="full-width"}
			{input type="checkbox" name="send_copy" value=1 label="Recevoir par e-mail une copie du message envoyé"}
		</dl>
	</fieldset>

	<p class="submit">
		{csrf_field key=$csrf_key}
		{button type="submit" name="send" label="Envoyer" shape="mail" class="main"}
	</p>
</form>


{include file="_foot.tpl"}