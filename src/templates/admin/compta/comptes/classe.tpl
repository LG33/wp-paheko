{include file="admin/_head.tpl" title=$classe_compte.libelle current="compta/categories"}

<ul class="actions">
    <li><a href="{$admin_url}compta/comptes/">Liste des classes</a></li>
    <li><a href="{$admin_url}compta/comptes/ajouter.php?classe={$classe}">Ajouter un compte dans cette classe</a></li>
</ul>

<p class="help">
    Les comptes avec la mention <em>*</em> font partie du plan comptable standard
    et ne peuvent être modifiés ou supprimés.
</p>

{if !empty($liste)}
    <table class="list accountList">
    {foreach from=$liste item="compte"}
        <tr class="niveau_{$compte.id|strlen}">
            <th>{$compte.id}</th>
            <td class="libelle">{$compte.libelle}</td>
            <td>
                {if !empty($compte.desactive)}
                    <em>Désactivé</em>
                {else}
                    {$compte.position|get_position}
                {/if}
            </td>
            <td class="actions">
                {if empty($compte.desactive)}
                    {if !$compte.plan_comptable}
                        <a class="icn" href="{$admin_url}compta/comptes/modifier.php?id={$compte.id}" title="Modifier">✎</a>
                        <a class="icn" href="{$admin_url}compta/comptes/supprimer.php?id={$compte.id}" title="Supprimer">✘</a>
                    {else}
                        <em>*</em>
                    {/if}
                {/if}
            </td>
        </tr>
    {/foreach}
    </table>

{else}
    <p class="alert">
        Aucun compte trouvé.
    </p>
{/if}


{include file="admin/_foot.tpl"}