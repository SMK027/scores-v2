<div class="page-header">
    <h1>📬 Tickets de contact</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/contact" class="d-flex gap-1 flex-wrap align-center">
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
                <table class="table">
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
                            <td><?= $t['id'] ?></td>
                            <td><?= e($t['space_name']) ?></td>
                            <td><?= e($t['author_username']) ?></td>
                            <td><?= e(\App\Models\ContactTicket::CATEGORIES[$t['category']] ?? $t['category']) ?></td>
                            <td>
                                <a href="/admin/contact/<?= $t['id'] ?>"><?= e($t['subject']) ?></a>
                            </td>
                            <td><span class="badge <?= $statusBadge ?>"><?= e(\App\Models\ContactTicket::STATUSES[$t['status']] ?? $t['status']) ?></span></td>
                            <td><?= (int) $t['message_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
