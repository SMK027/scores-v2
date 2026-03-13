<div class="page-header">
    <h1>Mes espaces</h1>
    <a href="/spaces/create" class="btn btn-primary">+ Créer un espace</a>
</div>

<?php if (!empty($pendingInvitations)): ?>
    <div class="card mb-3" style="border-color:var(--primary);">
        <div class="card-header">
            <h3>📩 Invitations reçues (<?= count($pendingInvitations) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Espace</th>
                            <th>Invité par</th>
                            <th>Rôle proposé</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvitations as $inv): ?>
                            <tr>
                                <td><strong><?= e($inv['space_name']) ?></strong></td>
                                <td><?= e($inv['invited_by_name']) ?></td>
                                <td><span class="badge badge-primary"><?= space_role_label($inv['role']) ?></span></td>
                                <td class="text-muted text-small"><?= format_date($inv['created_at'], 'd/m/Y H:i') ?></td>
                                <td class="text-right">
                                    <div class="d-flex gap-1 justify-end">
                                        <form method="POST" action="/invitations/<?= $inv['id'] ?>/accept">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-primary">Accepter</button>
                                        </form>
                                        <form method="POST" action="/invitations/<?= $inv['id'] ?>/decline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Refuser cette invitation ?">Refuser</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                        <?php if (!empty($space['restrictions']) && is_array(json_decode($space['restrictions'], true)) && array_filter(json_decode($space['restrictions'], true))): ?>
                        <div class="mt-1" style="padding:0.4rem 0.6rem;border-radius:var(--border-radius);background:rgba(255,140,0,0.1);border:1px solid rgba(255,140,0,0.35);">
                            <span style="font-size:0.85em;color:#b36200;font-weight:600;">⚠️ Espace restreint</span>
                            <?php if (!empty($space['restriction_reason'])): ?>
                                <br><span class="text-muted text-small"><?= e($space['restriction_reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($space['scheduled_deletion_at'])):
                            $__sDt = new DateTimeImmutable($space['scheduled_deletion_at'], new DateTimeZone('Europe/Paris'));
                        ?>
                        <div class="mt-1" style="padding:0.4rem 0.6rem;border-radius:var(--border-radius);background:rgba(239,71,111,0.12);border:1px solid rgba(239,71,111,0.3);">
                            <span style="font-size:0.85em;color:var(--danger,#dc3545);font-weight:600;">
                                💣 Suppression prévue le <?= $__sDt->format('d/m/Y à H:i') ?>
                            </span>
                            <br><span class="space-countdown" data-target="<?= $__sDt->format('c') ?>" style="font-size:0.85em;font-weight:bold;color:var(--danger,#dc3545);"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <script>
    (function(){
        var els = document.querySelectorAll('.space-countdown');
        if(!els.length) return;
        function update(){
            els.forEach(function(el){
                var diff = new Date(el.dataset.target).getTime() - Date.now();
                if(diff <= 0){ el.textContent = '⏰ Délai expiré — suppression imminente'; return; }
                var d = Math.floor(diff/86400000);
                var h = Math.floor((diff%86400000)/3600000);
                var m = Math.floor((diff%3600000)/60000);
                var s = Math.floor((diff%60000)/1000);
                var p = [];
                if(d > 0) p.push(d + 'j');
                p.push(h + 'h');
                p.push(m + 'min');
                p.push(s + 's');
                el.textContent = '⏳ ' + p.join(' ');
            });
        }
        update();
        setInterval(update, 1000);
    })();
    </script>
<?php endif; ?>
