<div class="page-header">
    <h1>🚫 Bannir un compte</h1>
    <a href="/admin/bans/users" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/bans/users/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="user_id" class="form-label">Utilisateur à bannir</label>
                <?php if (!empty($targetUser)): ?>
                    <input type="hidden" name="user_id" value="<?= $targetUser['id'] ?>">
                    <input type="text" class="form-control" value="<?= e($targetUser['username']) ?> (<?= e($targetUser['email']) ?>)" readonly>
                <?php else: ?>
                    <input type="number" id="user_id" name="user_id" class="form-control"
                           placeholder="ID de l'utilisateur" required min="1">
                    <span class="form-hint">Entrez l'ID de l'utilisateur à bannir.</span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="reason" class="form-label">Raison du bannissement</label>
                <textarea id="reason" name="reason" class="form-control" rows="3"
                          placeholder="Expliquez la raison du bannissement..." required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Type de bannissement</label>
                <div class="d-flex gap-2 flex-wrap">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="radio" name="duration_type" value="temporary" checked
                               onchange="document.getElementById('durationFields').style.display='flex'">
                        Temporaire
                    </label>
                    <?php if (in_array(current_global_role(), ['admin', 'superadmin'])): ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                            <input type="radio" name="duration_type" value="permanent"
                                   onchange="document.getElementById('durationFields').style.display='none'">
                            Permanent
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div id="durationFields" class="d-flex gap-1 flex-wrap align-center" style="display:flex;">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="duration_value" class="form-label">Durée</label>
                    <input type="number" id="duration_value" name="duration_value" class="form-control"
                           min="1" value="24" style="width:100px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="duration_unit" class="form-label">Unité</label>
                    <select id="duration_unit" name="duration_unit" class="form-control" style="width:auto;">
                        <option value="minutes">Minute(s)</option>
                        <option value="hours" selected>Heure(s)</option>
                        <option value="days">Jour(s)</option>
                        <option value="weeks">Semaine(s)</option>
                        <option value="months">Mois</option>
                    </select>
                </div>
            </div>

            <div class="form-group mt-2">
                <button type="submit" class="btn btn-danger" data-confirm="Confirmer le bannissement ?">🚫 Bannir le compte</button>
            </div>
        </form>
    </div>
</div>
