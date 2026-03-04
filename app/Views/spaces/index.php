<div class="page-header">
    <h1>Mes espaces</h1>
    <a href="/spaces/create" class="btn btn-primary">+ Créer un espace</a>
</div>

<?php if (empty($spaces)): ?>
    <div class="empty-state">
        <div class="empty-icon">🏠</div>
        <p>Vous n'avez pas encore d'espace.<br>Créez-en un pour commencer à enregistrer vos parties !</p>
        <a href="/spaces/create" class="btn btn-primary">Créer mon premier espace</a>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($spaces as $space): ?>
            <a href="/spaces/<?= $space['id'] ?>" class="card-link">
                <div class="card">
                    <div class="card-body">
                        <h3><?= e($space['name']) ?></h3>
                        <?php if ($space['description']): ?>
                            <p class="text-muted text-small"><?= e(truncate($space['description'], 120)) ?></p>
                        <?php endif; ?>
                        <div class="d-flex align-center gap-1 flex-wrap mt-1">
                            <span class="badge badge-primary"><?= space_role_label($space['user_role']) ?></span>
                            <span class="text-muted text-small">👥 <?= $space['member_count'] ?> membre(s)</span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
