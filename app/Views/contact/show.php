<?php
    $statusBadge = match ($ticket['status']) {
        'open'        => 'badge-success',
        'in_progress' => 'badge-warning',
        'closed'      => 'badge-secondary',
        default       => '',
    };
?>

<div class="page-header">
    <h1>📬 Ticket #<?= $ticket['id'] ?></h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/contact" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <p><strong>Catégorie :</strong> <?= e(\App\Models\ContactTicket::CATEGORIES[$ticket['category']] ?? $ticket['category']) ?></p>
        <p><strong>Sujet :</strong> <?= e($ticket['subject']) ?></p>
        <p><strong>Statut :</strong> <span class="badge <?= $statusBadge ?>"><?= e(\App\Models\ContactTicket::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span></p>
        <p><strong>Créé par :</strong> <?= e($ticket['author_username']) ?> — <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
    </div>
</div>

<h2>Conversation</h2>

<div class="mb-3">
    <?php foreach ($messages as $msg):
        $isStaff = in_array($msg['global_role'], ['moderator', 'admin', 'superadmin'], true);
        $roleLabel = match ($msg['global_role']) {
            'superadmin' => 'Super-admin',
            'admin'      => 'Admin',
            'moderator'  => 'Modérateur',
            default      => null,
        };
    ?>
        <div class="card mb-2" style="border-left: 4px solid <?= $isStaff ? 'var(--primary, #4361ee)' : 'var(--gray, #6c757d)' ?>;">
            <div class="card-body">
                <div class="d-flex justify-between align-center mb-1">
                    <div class="d-flex align-center gap-1">
                        <?php if (!empty($msg['avatar'])): ?>
                            <img src="<?= e($msg['avatar']) ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <span style="width:28px;height:28px;border-radius:50%;background:var(--primary,#4361ee);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:600;"><?= strtoupper(mb_substr($msg['username'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <strong>
                            <?= e($msg['username']) ?>
                            <?php if ($isStaff): ?>
                                <span class="badge badge-info"><?= $roleLabel ?></span>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></small>
                </div>
                <div style="white-space: pre-wrap;"><?= e($msg['body']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($ticket['status'] !== 'closed'): ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/contact/<?= $ticket['id'] ?>/reply">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="body" class="form-label">Répondre</label>
                    <textarea name="body" id="body" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Envoyer</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">Ce ticket est fermé. Vous ne pouvez plus y répondre.</div>
<?php endif; ?>
