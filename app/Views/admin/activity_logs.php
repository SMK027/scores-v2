<div class="page-header">
    <h1>📋 Journal d'activité</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/logs" class="card mb-3">
    <div class="card-body">
        <div class="d-flex gap-1 flex-wrap align-items-end">
            <div>
                <label class="form-label">Scope</label>
                <select name="scope" class="form-control" style="min-width:140px;">
                    <option value="">Tous</option>
                    <option value="space" <?= ($filters['scope'] ?? '') === 'space' ? 'selected' : '' ?>>Espace</option>
                    <option value="competition" <?= ($filters['scope'] ?? '') === 'competition' ? 'selected' : '' ?>>Compétition</option>
                    <option value="admin" <?= ($filters['scope'] ?? '') === 'admin' ? 'selected' : '' ?>>Administration</option>
                    <option value="auth" <?= ($filters['scope'] ?? '') === 'auth' ? 'selected' : '' ?>>Authentification</option>
                </select>
            </div>
            <div>
                <label class="form-label">Action</label>
                <input type="text" name="action" class="form-control" placeholder="ex: game.create" value="<?= e($filters['action'] ?? '') ?>" style="min-width:160px;">
            </div>
            <div>
                <label class="form-label">Utilisateur</label>
                <input type="text" name="user" class="form-control" placeholder="Nom d'utilisateur" value="<?= e($filters['user'] ?? '') ?>" style="min-width:160px;">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="/admin/logs" class="btn btn-outline">Réinitialiser</a>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Aucune entrée trouvée.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Scope</th>
                            <th>Action</th>
                            <th>Utilisateur</th>
                            <th>Entité</th>
                            <th>IP</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted text-small" style="white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <?php
                                        $scopeBadges = [
                                            'space'       => 'badge-info',
                                            'competition' => 'badge-warning',
                                            'admin'       => 'badge-danger',
                                            'auth'        => 'badge-secondary',
                                        ];
                                        $badgeClass = $scopeBadges[$log['scope']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>" style="font-size:0.7em;">
                                        <?= e($log['scope']) ?>
                                        <?php if ($log['scope_id']): ?>
                                            #<?= (int) $log['scope_id'] ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><code style="font-size:0.85em;"><?= e($log['action']) ?></code></td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <strong><?= e($log['username']) ?></strong>
                                    <?php elseif ($log['session_id']): ?>
                                        <span class="text-muted">Session #<?= (int) $log['session_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-small">
                                    <?php if ($log['entity_type']): ?>
                                        <?= e($log['entity_type']) ?>
                                        <?php if ($log['entity_id']): ?>
                                            #<?= (int) $log['entity_id'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-small"><?= e($log['ip_address'] ?? '—') ?></td>
                                <td class="text-small" style="max-width:250px;">
                                    <?php if ($log['details']): ?>
                                        <?php
                                            $details = json_decode($log['details'], true);
                                            if (is_array($details)):
                                                $parts = [];
                                                foreach ($details as $k => $v) {
                                                    if (is_bool($v)) {
                                                        $value = $v ? 'oui' : 'non';
                                                    } elseif (is_array($v)) {
                                                        $value = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                    } elseif ($v === null) {
                                                        $value = 'null';
                                                    } else {
                                                        $value = (string) $v;
                                                    }
                                                    $parts[] = e($k) . ': ' . e($value);
                                                }
                                        ?>
                                            <span title="<?= e($log['details']) ?>"><?= implode(', ', $parts) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= e(mb_substr($log['details'], 0, 80)) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pagination['lastPage'] > 1): ?>
    <?php
        $queryParams = array_filter($filters);
    ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['lastPage']; $i++): ?>
            <?php
                $params = array_merge($queryParams, ['page' => $i]);
                $url = '/admin/logs?' . http_build_query($params);
            ?>
            <?php if ($i === $pagination['page']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= $url ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>
