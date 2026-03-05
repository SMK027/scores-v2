<div class="page-header">
    <h1>🛡️ Configuration Fail2ban</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Protection contre les tentatives de connexion abusives</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">
            Les modifications sont appliquées immédiatement après validation.
        </p>

        <form method="POST" action="/admin/fail2ban">
            <?= csrf_field() ?>

            <!-- Activation -->
            <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;user-select:none;">
                    <input type="checkbox" name="enabled" value="1"
                           <?= ($config['enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                           style="width:18px;height:18px;accent-color:var(--primary,#4361ee);"
                           onchange="document.getElementById('f2bSettings').style.opacity=this.checked?1:.4">
                    <span>🛡️ <strong>Activer le Fail2ban</strong></span>
                </label>
            </div>

            <hr style="margin:1rem 0;border:none;border-top:1px solid var(--gray-light);">

            <div id="f2bSettings" style="opacity:<?= ($config['enabled'] ?? '0') === '1' ? '1' : '.4' ?>;">

                <!-- Seuil de tentatives -->
                <div class="form-group">
                    <label for="max_attempts" class="form-label">Nombre maximal de tentatives</label>
                    <input type="number" id="max_attempts" name="max_attempts" class="form-control"
                           min="1" max="100" value="<?= e($config['max_attempts'] ?? '3') ?>"
                           style="max-width:150px;">
                    <span class="form-hint">Nombre de tentatives échouées avant déclenchement du ban.</span>
                </div>

                <!-- Fenêtre de temps -->
                <div class="form-group">
                    <label for="window_minutes" class="form-label">Fenêtre de temps (minutes)</label>
                    <input type="number" id="window_minutes" name="window_minutes" class="form-control"
                           min="1" max="1440" value="<?= e($config['window_minutes'] ?? '15') ?>"
                           style="max-width:150px;">
                    <span class="form-hint">Les tentatives sont comptées sur cette durée glissante.</span>
                </div>

                <!-- Durée du ban -->
                <div class="form-group">
                    <label for="ban_duration" class="form-label">Durée du bannissement (minutes)</label>
                    <input type="number" id="ban_duration" name="ban_duration" class="form-control"
                           min="1" max="525600" value="<?= e($config['ban_duration'] ?? '30') ?>"
                           style="max-width:150px;">
                    <span class="form-hint">Durée du bannissement automatique. 1440 = 24h, 10080 = 7 jours.</span>
                </div>

                <hr style="margin:1rem 0;border:none;border-top:1px solid var(--gray-light);">

                <!-- Actions de ban -->
                <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;user-select:none;">
                        <input type="checkbox" name="ban_ip" value="1"
                               <?= ($config['ban_ip'] ?? '1') === '1' ? 'checked' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary,#4361ee);">
                        <span>🌐 Bannir l'adresse IP</span>
                    </label>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;user-select:none;">
                        <input type="checkbox" name="ban_account" value="1"
                               <?= ($config['ban_account'] ?? '1') === '1' ? 'checked' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary,#4361ee);">
                        <span>👤 Bannir le compte utilisateur</span>
                    </label>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.75rem;">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;user-select:none;">
                        <input type="checkbox" name="exempt_staff" value="1"
                               <?= ($config['exempt_staff'] ?? '1') === '1' ? 'checked' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary,#4361ee);">
                        <span>🔓 Exempter les comptes staff (modérateur, admin, superadmin) du bannissement de compte</span>
                    </label>
                </div>

            </div>

            <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--gray-light);">

            <!-- Aperçu -->
            <div class="card" style="background:rgba(255,255,255,.03);margin-bottom:1.5rem;border:1px solid var(--border,#2a3a5c);">
                <div class="card-body">
                    <h4 style="margin-top:0;">📋 Résumé de la configuration</h4>
                    <p id="f2bPreview" class="text-muted" style="margin-bottom:0;line-height:1.8;"></p>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">💾 Enregistrer la configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updatePreview() {
        const enabled = document.querySelector('[name="enabled"]').checked;
        if (!enabled) {
            document.getElementById('f2bPreview').innerHTML = '⚠️ Le fail2ban est <strong>désactivé</strong>.';
            return;
        }
        const max = document.getElementById('max_attempts').value;
        const win = document.getElementById('window_minutes').value;
        const dur = document.getElementById('ban_duration').value;
        const banIp = document.querySelector('[name="ban_ip"]').checked;
        const banAcc = document.querySelector('[name="ban_account"]').checked;
        const exempt = document.querySelector('[name="exempt_staff"]').checked;

        let actions = [];
        if (banIp) actions.push('IP');
        if (banAcc) actions.push('compte');

        let text = `Après <strong>${max}</strong> tentative(s) échouée(s) en <strong>${win}</strong> minute(s), `;
        if (actions.length === 0) {
            text += 'aucune action ne sera prise (aucune option de ban cochée).';
        } else {
            text += `le bannissement sera appliqué sur : <strong>${actions.join(' + ')}</strong> pendant <strong>${dur}</strong> minute(s).`;
        }
        if (banAcc && exempt) {
            text += '<br>🔓 Les comptes staff (modérateur, admin, superadmin) sont exemptés du bannissement de compte.';
        }
        document.getElementById('f2bPreview').innerHTML = text;
    }

    document.querySelectorAll('input').forEach(el => el.addEventListener('change', updatePreview));
    document.querySelectorAll('input[type="number"]').forEach(el => el.addEventListener('input', updatePreview));
    updatePreview();
});
</script>
