<div class="page-header">
    <h1>🔐 Politique de mot de passe</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Configuration des exigences de mot de passe</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">
            Ces règles s'appliquent immédiatement à l'inscription et au changement de mot de passe pour tous les utilisateurs.
        </p>

        <form method="POST" action="/admin/password-policy">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="min_length" class="form-label">Longueur minimale</label>
                <input type="number" id="min_length" name="min_length" class="form-control"
                       min="1" max="128" style="max-width: 200px;"
                       value="<?php
                           foreach ($settings as $s) {
                               if ($s['setting_key'] === 'min_length') {
                                   echo e($s['setting_value']);
                               }
                           }
                       ?>">
                <span class="form-hint">Nombre minimum de caractères requis.</span>
            </div>

            <?php
            $booleanSettings = [
                'require_lowercase' => ['label' => 'Exiger au moins une lettre minuscule (a-z)', 'icon' => '🔡'],
                'require_uppercase' => ['label' => 'Exiger au moins une lettre majuscule (A-Z)', 'icon' => '🔠'],
                'require_digit'     => ['label' => 'Exiger au moins un chiffre (0-9)',           'icon' => '🔢'],
                'require_special'   => ['label' => 'Exiger au moins un caractère spécial (!@#…)', 'icon' => '✳️'],
            ];
            ?>

            <?php foreach ($booleanSettings as $key => $meta): ?>
                <?php
                    $currentValue = '0';
                    foreach ($settings as $s) {
                        if ($s['setting_key'] === $key) {
                            $currentValue = $s['setting_value'];
                        }
                    }
                ?>
                <div class="form-group" style="display: flex; align-items: center; gap: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="<?= $key ?>" value="1"
                               <?= $currentValue === '1' ? 'checked' : '' ?>
                               style="width: 18px; height: 18px; accent-color: var(--primary, #4361ee);">
                        <span><?= $meta['icon'] ?> <?= $meta['label'] ?></span>
                    </label>
                </div>
            <?php endforeach; ?>

            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">

            <!-- Aperçu en temps réel -->
            <div class="card" style="background: var(--gray-lighter, #f8f9fa); margin-bottom: 1.5rem;">
                <div class="card-body">
                    <h4 style="margin-top:0;">📋 Aperçu de la politique</h4>
                    <p id="policyPreview" class="text-muted" style="margin-bottom:0;"></p>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">💾 Enregistrer la politique</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updatePreview() {
        var parts = [];
        var minLen = document.getElementById('min_length').value || '8';
        parts.push(minLen + ' caractères minimum');
        
        var checks = {
            'require_lowercase': '1 minuscule',
            'require_uppercase': '1 majuscule',
            'require_digit':     '1 chiffre',
            'require_special':   '1 caractère spécial'
        };
        
        for (var key in checks) {
            var cb = document.querySelector('input[name="' + key + '"]');
            if (cb && cb.checked) {
                parts.push(checks[key]);
            }
        }
        
        document.getElementById('policyPreview').textContent = parts.join(', ');
    }
    
    // Écouter les changements
    document.getElementById('min_length').addEventListener('input', updatePreview);
    document.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        cb.addEventListener('change', updatePreview);
    });
    
    updatePreview();
});
</script>
