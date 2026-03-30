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
    <a href="/admin/contact" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex gap-1 flex-wrap mb-2">
            <div><strong>Espace :</strong> <?= e($ticket['space_name']) ?></div>
            <div><strong>Auteur :</strong> <?= e($ticket['author_username']) ?></div>
        </div>
        <p><strong>Catégorie :</strong> <?= e(\App\Models\ContactTicket::CATEGORIES[$ticket['category']] ?? $ticket['category']) ?></p>
        <p><strong>Sujet :</strong> <?= e($ticket['subject']) ?></p>
        <p><strong>Statut :</strong> <span class="badge <?= $statusBadge ?>"><?= e(\App\Models\ContactTicket::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span></p>
        <p><strong>Créé le :</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>

        <!-- Modifier le statut -->
        <form method="POST" action="/admin/contact/<?= $ticket['id'] ?>/status" class="d-flex gap-1 align-center mt-2">
            <?= csrf_field() ?>
            <select name="status" class="form-control" style="width:auto;">
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $ticket['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline">Mettre à jour</button>
        </form>
    </div>
</div>

<h2>Conversation</h2>

<div class="mb-3">
    <?php foreach ($messages as $msg):
        $isStaff = in_array($msg['global_role'], ['moderator', 'admin', 'superadmin'], true);
    ?>
        <div class="card mb-2" style="border-left: 4px solid <?= $isStaff ? 'var(--primary, #4361ee)' : 'var(--gray, #6c757d)' ?>;">
            <div class="card-body">
                <div class="d-flex justify-between align-center mb-1">
                    <strong>
                        <?= e($msg['username']) ?>
                        <?php if ($isStaff): ?>
                            <span class="badge badge-info">Modération</span>
                        <?php endif; ?>
                    </strong>
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
            <form method="POST" action="/admin/contact/<?= $ticket['id'] ?>/reply">
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
    <div class="alert alert-info">Ce ticket est fermé.</div>
<?php endif; ?>
