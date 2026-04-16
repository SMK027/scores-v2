<div class="page-header">
    <h1>📬 Tickets de contact</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/contact" class="d-flex gap-1 flex-wrap align-center admin-filters">
            <select name="status" class="form-control" style="max-width:200px;">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category" class="form-control" style="max-width:250px;">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <?php if ($filterStatus !== '' || $filterCategory !== ''): ?>
                <a href="/admin/contact" class="btn btn-outline">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($tickets)): ?>
    <div class="empty-state">
        <div class="empty-icon">📬</div>
        <p>Aucun ticket trouvé.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-mobile-cards">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Espace</th>
                            <th>Auteur</th>
                            <th>Catégorie</th>
                            <th>Sujet</th>
                            <th>Statut</th>
                            <th>Messages</th>
                            <th>Mis à jour</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t):
                            $statusBadge = match ($t['status']) {
                                'open'        => 'badge-success',
                                'in_progress' => 'badge-warning',
                                'closed'      => 'badge-secondary',
                                default       => '',
                            };
                        ?>
                        <tr>
                            <td data-label="#"><?= $t['id'] ?></td>
                            <td data-label="Espace"><?= e($t['space_name']) ?></td>
                            <td data-label="Auteur"><?= e($t['author_username']) ?></td>
                            <td data-label="Catégorie"><?= e(\App\Models\ContactTicket::CATEGORIES[$t['category']] ?? $t['category']) ?></td>
                            <td data-label="Sujet">
                                <a href="/admin/contact/<?= $t['id'] ?>"><?= e($t['subject']) ?></a>
                            </td>
                            <td data-label="Statut"><span class="badge <?= $statusBadge ?>"><?= e(\App\Models\ContactTicket::STATUSES[$t['status']] ?? $t['status']) ?></span></td>
                            <td data-label="Messages"><?= (int) $t['message_count'] ?></td>
                            <td data-label="Mis à jour"><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
