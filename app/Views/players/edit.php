<div class="page-header">
    <h1>Modifier le joueur</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/players" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/edit">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name" class="form-label">Nom du joueur</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($player['name']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="user_id" class="form-label">Raccorder à un membre de l'espace (optionnel)</label>
                <select id="user_id" name="user_id" class="form-control">
                    <option value="">— Aucun compte lié —</option>
                    <?php foreach ($members as $member): ?>
                        <?php
                            $alreadyLinked = in_array($member['user_id'], $linkedUserIds);
                            $isCurrentLink = ((int)($player['user_id'] ?? 0) === (int)$member['user_id']);
                        ?>
                        <option value="<?= $member['user_id'] ?>"
                            <?= $isCurrentLink ? 'selected' : '' ?>
                            <?= ($alreadyLinked && !$isCurrentLink) ? 'disabled style="color:var(--gray);"' : '' ?>>
                            <?= e($member['username']) ?> (<?= space_role_label($member['role']) ?>)
                            <?= ($alreadyLinked && !$isCurrentLink) ? '— déjà raccordé' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Associez ce joueur au compte d'un membre de l'espace. Chaque compte ne peut être lié qu'à un seul joueur.</span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
