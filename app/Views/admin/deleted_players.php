<div class="page-header">
    <h1>♻️ Joueurs supprimés</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/players/deleted" class="d-flex gap-1 flex-wrap align-center admin-filters">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Rechercher joueur, espace ou compte lié"
                   value="<?= e($search ?? '') ?>"
                   style="max-width:340px;">

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if (!empty($search)): ?>
                <a href="/admin/players/deleted" class="btn btn-outline">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted mb-2">
            Joueurs supprimés trouvés : <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
        </p>

        <div class="table-responsive">
            <table class="table table-mobile-cards">
                <thead>
                    <tr>
                        <th>Joueur</th>
                        <th>Espace</th>
                        <th>Compte lié</th>
                        <th>Supprimé le</th>
                        <th class="text-right">Restauration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr>
                            <td colspan="5" class="text-muted">Aucun joueur supprimé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <td data-label="Joueur">
                                    <strong><?= e($player['name']) ?></strong>
                                    <div class="text-muted text-small">ID #<?= (int) $player['id'] ?></div>
                                </td>
                                <td data-label="Espace">
                                    <a href="/spaces/<?= (int) $player['space_id'] ?>">
                                        <?= e($player['space_name']) ?>
                                    </a>
                                </td>
                                <td data-label="Compte lié">
                                    <?= !empty($player['linked_username']) ? e($player['linked_username']) : '<span class="text-muted">Aucun</span>' ?>
                                </td>
                                <td data-label="Supprimé le" class="text-muted text-small"><?= format_date($player['deleted_at']) ?></td>
                                <td class="text-right td-actions">
                                    <form method="POST" action="/admin/players/<?= (int) $player['id'] ?>/restore" class="d-flex gap-1 justify-end admin-actions" style="display:inline-flex;flex-wrap:wrap;">
                                        <?= csrf_field() ?>
                                        <input type="text"
                                               name="requested_by"
                                               class="form-control form-control-sm"
                                               placeholder="Demandeur (optionnel)"
                                               style="min-width:170px;">
                                        <input type="text"
                                               name="request_note"
                                               class="form-control form-control-sm"
                                               placeholder="Motif de demande (optionnel)"
                                               style="min-width:220px;">
                                        <button type="submit"
                                                class="btn btn-sm btn-primary"
                                                data-confirm="Restaurer ce joueur dans l'espace ?">
                                            Restaurer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (($pagination['lastPage'] ?? 1) > 1): ?>
    <?php
        $__paginationQuery = [];
        if (!empty($search)) {
            $__paginationQuery['q'] = $search;
        }
    ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= (int) $pagination['lastPage']; $i++): ?>
            <?php if ($i === (int) $pagination['page']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/players/deleted?<?= e(http_build_query(array_merge($__paginationQuery, ['page' => $i]))) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>
