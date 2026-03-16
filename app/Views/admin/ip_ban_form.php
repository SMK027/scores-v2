<div class="page-header">
    <h1>🌐 Bannir une adresse IP</h1>
    <a href="/admin/bans/ips" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/bans/ips/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="ip_address" class="form-label">Adresse IP</label>
                <input type="text" id="ip_address" name="ip_address" class="form-control"
                       placeholder="ex: 192.168.1.100 ou 2001:db8::1" required
                       pattern="[0-9a-fA-F.:]+">
                <span class="form-hint">IPv4 ou IPv6. Entrez l'adresse IP exacte à bannir.</span>
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
                    <?php if (!empty($canBanPermanently)): ?>
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="radio" name="duration_type" value="permanent"
                               onchange="document.getElementById('durationFields').style.display='none'">
                        Permanent
                    </label>
                    <?php else: ?>
                    <span class="badge badge-secondary" title="Réservé aux administrateurs">Permanent (admin uniquement)</span>
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
                <button type="submit" class="btn btn-danger" data-confirm="Confirmer le bannissement IP ?">🌐 Bannir cette IP</button>
            </div>
        </form>
    </div>
</div>
