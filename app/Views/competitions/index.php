<div class="page-header">
    <h1>🏆 Compétitions</h1>
    <?php if ($isStaff): ?>
        <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/create" class="btn btn-primary btn-sm">+ Nouvelle compétition</a>
    <?php endif; ?>
</div>

<?php if (empty($competitions)): ?>
    <div class="card">
        <div class="card-body text-center text-muted">
            <p>Aucune compétition pour le moment.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Statut</th>
                    <th>Sessions</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Créateur</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($competitions as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong></td>
                    <td>
                        <?php
                        $statusBadge = match ($c['status']) {
                            'planned' => 'badge-warning',
                            'active'  => 'badge-success',
                            'closed'  => 'badge-secondary',
                            default   => '',
                        };
                        $statusLabel = match ($c['status']) {
                            'planned' => 'Planifiée',
                            'active'  => 'Active',
                            'closed'  => 'Clôturée',
                            default   => $c['status'],
                        };
                        ?>
                        <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
                    </td>
                    <td><?= (int) $c['session_count'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($c['starts_at'])) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($c['ends_at'])) ?></td>
                    <td><?= e($c['creator_name'] ?? 'Inconnu') ?></td>
                    <td>
                        <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Voir">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
