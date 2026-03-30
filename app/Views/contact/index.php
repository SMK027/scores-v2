<div class="page-header">
    <h1>📬 Contact modération</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/contact/create" class="btn btn-primary">+ Nouveau ticket</a>
</div>

<?php if (empty($tickets)): ?>
    <div class="empty-state">
        <div class="empty-icon">📬</div>
        <p>Aucun ticket de contact.</p>
        <a href="/spaces/<?= $currentSpace['id'] ?>/contact/create" class="btn btn-primary">Contacter la modération</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Catégorie</th>
                            <th>Sujet</th>
                            <th>Statut</th>
                            <th>Messages</th>
                            <th>Date</th>
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
                            <td><?= e(\App\Models\ContactTicket::CATEGORIES[$t['category']] ?? $t['category']) ?></td>
                            <td>
                                <a href="/spaces/<?= $currentSpace['id'] ?>/contact/<?= $t['id'] ?>">
                                    <?= e($t['subject']) ?>
                                </a>
                            </td>
                            <td><span class="badge <?= $statusBadge ?>"><?= e(\App\Models\ContactTicket::STATUSES[$t['status']] ?? $t['status']) ?></span></td>
                            <td><?= (int) $t['message_count'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
