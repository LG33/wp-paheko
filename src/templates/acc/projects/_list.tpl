	<table class="list projects">
		{if !empty($caption)}<caption>{$caption}</caption>{/if}
		<thead>
			<tr>
				<td>Projet</td>
				<td></td>
				<td class="money">Charges</td>
				<td class="money">Produits</td>
				<td class="money">Débits</td>
				<td class="money">Crédits</td>
				<td class="money">Solde</td>
			</tr>
		</thead>
		{foreach from=$list item="parent"}
			<tbody{if $parent.archived} class="archived"{/if}>
				<tr class="title">
					<th colspan="7">
						<h2 class="ruler">{$parent.label}{if $parent.archived} <em>(archivé)</em>{/if}</h2>
						{if $parent.description}<p class="help">{$parent.description|escape|nl2br}</p>{/if}
					{if !$export && !$by_year && $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_ADMIN)}
					<p class="actions">
						{linkbutton shape="edit" label="Modifier" href="edit.php?id=%d"|args:$parent.id target="_dialog"}
						{linkbutton shape="delete" label="Supprimer" href="delete.php?id=%d"|args:$parent.id target="_dialog"}
					</p>
					{/if}
					{if !$export && $by_year}
					<p class="actions">
						{linkbutton href="!acc/reports/ledger.php?projects_only=1&year=%d"|args:$parent.id_year label="Grand livre analytique"}
					</p>
					{/if}
					</th>
				</tr>
			{foreach from=$parent.items item="item"}
				<tr class="{if $item.label == 'Total'}total{/if} {if $item.archived}archived{/if}">
					<th>{$item.label}{if $item.archived} <em>(archivé)</em>{/if}</th>
					<td>
					{if !$export}
					<span class="noprint">
						<a href="{$admin_url}acc/reports/graphs.php?project={$item.id_project}&amp;year={$item.id_year}">Graphiques</a>
						| <a href="{$admin_url}acc/reports/trial_balance.php?project={$item.id_project}&amp;year={$item.id_year}">Balance générale</a>
						| <a href="{$admin_url}acc/reports/journal.php?project={$item.id_project}&amp;year={$item.id_year}">Journal général</a>
						| <a href="{$admin_url}acc/reports/ledger.php?project={$item.id_project}&amp;year={$item.id_year}">Grand livre</a>
						| <a href="{$admin_url}acc/reports/statement.php?project={$item.id_project}&amp;year={$item.id_year}">Compte de résultat</a>
						| <a href="{$admin_url}acc/reports/balance_sheet.php?project={$item.id_project}&amp;year={$item.id_year}">Bilan</a>
					</span>
					{/if}
					</td>
					<td class="money">{$item.sum_expense|raw|money}</td>
					<td class="money">{$item.sum_revenue|raw|money}</td>
					<td class="money">{$item.debit|raw|money:false}</td>
					<td class="money">{$item.credit|raw|money:false}</td>
					<td class="money">{$item.sum|raw|money:false}</td>
				</tr>
			{/foreach}
			</tbody>
		{/foreach}
	</table>