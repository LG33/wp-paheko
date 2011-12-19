{include file="admin/_head.tpl" title="Action collective sur les membres" current="membres"}

{if $error}
    <p class="error">
        {$error|escape}
    </p>
{/if}

<form method="post" action="{$self_url|escape}">
    {foreach from=$selected item="id"}
        <input type="hidden" name="selected[]" value="{$id|escape}" />
    {/foreach}

    </fieldset>

    {if $action == 'move'}
    <fieldset>
        <legend>Changer la catégorie des {$nb_selected|escape} membres sélectionnés</legend>
        <dl>
            <dt><label for="f_cat">Nouvelle catégorie</label> <b title="(Champ obligatoire)">obligatoire</b></dt>
            <dd>
                <select name="id_categorie" id="f_cat">
                    <option value="0" selected="selected">-- Pas de changement</option>
                {foreach from=$membres_cats key="id" item="nom"}
                    <option value="{$id|escape}">{$nom|escape}</option>
                {/foreach}
                </select>
            </dd>
        </dl>
    </fieldset>

    <p class="submit">
        {csrf_field key="membres_action"}
        <input type="submit" name="move_ok" value="Enregistrer &rarr;" />
    </p>

    {elseif $action == 'delete'}
    <fieldset>
        <legend>Supprimer les membres sélectionnés ?</legend>
        <h3 class="warning">
            Êtes-vous sûr de vouloir supprimer les {$nb_selected|escape} membres sélectionnés ?
        </h3>
        <p class="alert">
            Attention : cette action est irréversible.
        </p>
    </fieldset>

    <p class="submit">
        {csrf_field key="membres_action"}
        <input type="submit" name="delete_ok" value="Oui, je suis sûr de chez sûr &rarr;" />
    </p>
    {/if}

</form>

{include file="admin/_foot.tpl"}