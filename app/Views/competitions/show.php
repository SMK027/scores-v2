<div class="page-header">
    <div>
        <h1>
            🏆 <?= e($competition['name']) ?>
            <?php
            $statusBadge = match ($competition['status']) {
                'planned' => 'badge-warning',
                'active'  => 'badge-success',
                'paused'  => 'badge-info',
                'closed'  => 'badge-secondary',
                default   => '',
            };
            $statusLabel = match ($competition['status']) {
                'planned' => 'Planifiée',
                'active'  => 'Active',
                'paused'  => 'En pause',
                'closed'  => 'Clôturée',
                default   => $competition['status'],
            };
            ?>
            <span class="badge <?= $statusBadge ?>" style="font-size:0.5em;vertical-align:middle;"><?= $statusLabel ?></span>
        </h1>
        <p class="text-muted text-small">
            Du <?= date('d/m/Y H:i', strtotime($competition['starts_at'])) ?>
            au <?= date('d/m/Y H:i', strtotime($competition['ends_at'])) ?>
            — Créée par <?= e($competition['creator_name'] ?? 'Inconnu') ?>
        </p>
        <?php if ($competition['description']): ?>
            <p><?= nl2br(e($competition['description'])) ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <a href="/spaces/<?= $currentSpace['id'] ?>/competitions" class="btn btn-outline btn-sm">← Retour</a>
        <?php if (!empty($assignedArbitrationSession)): ?>
            <form method="POST" action="/competition/sessions/<?= (int) $assignedArbitrationSession['id'] ?>/open" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary">Ouvrir la session d'arbitrage</button>
            </form>
        <?php endif; ?>
        <?php if ($isStaff): ?>
            <?php if ($competition['status'] !== 'closed'): ?>
                <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/edit" class="btn btn-sm btn-outline" title="Modifier">
                    <i class="bi bi-pencil"></i> Modifier
                </a>
            <?php endif; ?>
            <?php if ($competition['status'] === 'planned'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/activate" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-success" data-confirm="Activer la compétition ?">▶ Activer</button>
                </form>
            <?php endif; ?>
            <?php if ($competition['status'] === 'active'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/pause" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-info" data-confirm="Mettre en pause ? Les arbitres ne pourront plus saisir.">⏸ Pause</button>
                </form>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/close" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-warning" data-confirm="Clôturer ? Toutes les sessions seront désactivées.">⏹ Clôturer</button>
                </form>
            <?php endif; ?>
            <?php if ($competition['status'] === 'paused'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/resume" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-success" data-confirm="Reprendre la compétition ?">▶ Reprendre</button>
                </form>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/close" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-warning" data-confirm="Clôturer ? Toutes les sessions seront désactivées.">⏹ Clôturer</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/delete" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" data-confirm="Supprimer cette compétition ?"><i class="bi bi-trash"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Types de jeu autorisés (<?= count($allowedGameTypes ?? []) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($allowedGameTypes)): ?>
            <p class="text-muted">Aucun type de jeu autorisé.</p>
        <?php else: ?>
            <div class="d-flex gap-1 flex-wrap">
                <?php foreach ($allowedGameTypes as $gt): ?>
                    <span class="badge badge-primary"><?= e($gt['name']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-between align-center">
        <h3>Compétiteurs inscrits (<?= count($registeredPlayers ?? []) ?>)</h3>
        <?php if (!empty($canSelfRegister) && !$isSelfRegistered && $competition['status'] !== 'closed'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/register" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-success" data-confirm="Confirmer votre inscription à cette compétition ?">Je m'inscris</button>
            </form>
        <?php elseif (!empty($isSelfRegistered)): ?>
            <span class="badge badge-success">Vous êtes inscrit</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($isStaff && $competition['status'] !== 'closed'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/participants/add" class="mb-2" id="participant-add-form">
                <?= csrf_field() ?>
                <div class="d-flex gap-1 flex-wrap align-center" style="max-width:640px;">
                    <div class="autocomplete-wrapper" style="position:relative;flex:1;min-width:280px;">
                        <input type="text" id="participant_search" class="form-control form-control-sm" placeholder="Rechercher un membre/joueur de l'espace..." autocomplete="off">
                        <input type="hidden" name="player_id" id="participant_player_id" required>
                        <div id="participant_options" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:260px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    <button class="btn btn-sm btn-outline">+ Inscrire</button>
                </div>
            </form>
            <p class="text-muted text-small">Un joueur sans compte lié doit être inscrit ici par un membre de l'équipe.</p>
        <?php endif; ?>

        <?php if (empty($registeredPlayers)): ?>
            <p class="text-muted">Aucun compétiteur inscrit pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Joueur</th>
                            <th>Compte lié</th>
                            <th>Inscription</th>
                            <?php if ($isStaff): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredPlayers as $rp): ?>
                            <tr>
                                <td><strong><?= e($rp['name']) ?></strong></td>
                                <td>
                                    <?php if (!empty($rp['linked_username'])): ?>
                                        <span class="badge badge-info"><?= e($rp['linked_username']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-small"><?= !empty($rp['registered_at']) ? date('d/m/Y H:i', strtotime($rp['registered_at'])) : '—' ?></td>
                                <?php if ($isStaff): ?>
                                    <td>
                                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/participants/<?= (int) $rp['id'] ?>/remove" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline" data-confirm="Désinscrire ce joueur de la compétition ?">Retirer</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const participantInput = document.getElementById('participant_search');
    const participantHidden = document.getElementById('participant_player_id');
    const participantOptions = document.getElementById('participant_options');
    const participantForm = document.getElementById('participant-add-form');

    if (!participantInput || !participantHidden || !participantOptions || !participantForm) {
        return;
    }

    const players = <?= json_encode(array_map(fn($p) => [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'linked_username' => $p['linked_username'] ?? null,
    ], $allSpacePlayers ?? []), JSON_UNESCAPED_UNICODE) ?>;

    function hideOptions() {
        participantOptions.style.display = 'none';
    }

    function renderOptions(items) {
        if (items.length === 0) {
            participantOptions.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucun membre correspondant.</div>';
            participantOptions.style.display = 'block';
            return;
        }

        participantOptions.innerHTML = items.map((p) => (
            '<div class="participant-option" data-id="' + p.id + '" data-name="' + p.name.replace(/"/g, '&quot;') + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:pointer;display:flex;justify-content:space-between;gap:0.5rem;">'
            + '<span>' + p.name + '</span>'
            + (p.linked_username ? '<span class="text-muted text-small">compte: ' + p.linked_username + '</span>' : '<span class="text-muted text-small">sans compte</span>')
            + '</div>'
        )).join('');

        participantOptions.querySelectorAll('.participant-option').forEach((el) => {
            el.addEventListener('click', () => {
                participantHidden.value = el.dataset.id;
                participantInput.value = el.dataset.name;
                hideOptions();
            });
        });

        participantOptions.style.display = 'block';
    }

    participantInput.addEventListener('focus', () => {
        renderOptions(players);
    });

    participantInput.addEventListener('input', () => {
        const query = participantInput.value.trim().toLowerCase();
        participantHidden.value = '';
        const filtered = query === ''
            ? players
            : players.filter((p) =>
                p.name.toLowerCase().includes(query)
                || (p.linked_username && p.linked_username.toLowerCase().includes(query))
            );
        renderOptions(filtered);
    });

    participantForm.addEventListener('submit', (event) => {
        if (!participantHidden.value) {
            event.preventDefault();
            alert('Veuillez sélectionner un compétiteur via l\'auto-complétion.');
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            hideOptions();
        }
    });
})();
</script>

<!-- Sessions -->
<div class="card mb-3">
    <div class="card-header d-flex justify-between align-center">
        <h3>Sessions (<?= count($sessions) ?>)</h3>
        <?php if ($isStaff && $competition['status'] !== 'closed'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/sessions/add" class="d-flex gap-1">
                <?= csrf_field() ?>
                <div class="autocomplete-wrapper" style="position:relative;width:250px;">
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="show_referee_member_search"
                        placeholder="Membre de l'espace (optionnel)"
                        autocomplete="off"
                    >
                    <input type="hidden" name="referee_user_id" id="show_referee_member_id">
                    <div
                        id="show_referee_member_options"
                        class="autocomplete-list"
                        style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"
                    ></div>
                </div>
                <input type="text" name="referee_name" class="form-control form-control-sm" placeholder="Nom de l'arbitre" style="width:150px;">
                <div style="width:200px;">
                    <input type="email" name="referee_email" id="show_referee_email" class="form-control form-control-sm" placeholder="Email (optionnel)">
                    <div id="show_referee_email_warning" class="text-danger text-small" style="display:none;margin-top:0.25rem;"></div>
                </div>
                <button class="btn btn-sm btn-primary">+ Session</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($sessions)): ?>
            <p class="text-muted text-center">Aucune session.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Arbitre</th>
                            <?php if ($isStaff): ?>
                                <th>Mot de passe</th>
                            <?php endif; ?>
                            <th>Parties</th>
                            <th>Statut</th>
                            <?php if ($isStaff): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><strong><?= (int) $s['session_number'] ?></strong></td>
                            <td>
                                <?= e($s['referee_name']) ?>
                                <?php if (!empty($s['referee_user_username'])): ?>
                                    <span class="badge badge-info" style="margin-left:0.35rem;">compte lié: <?= e($s['referee_user_username']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($s['referee_email'])): ?>
                                    <span class="text-muted text-small">(<?= e($s['referee_email']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isStaff): ?>
                                <td>
                                    <span class="session-password" data-hidden="true" style="cursor:pointer;" onclick="this.dataset.hidden=this.dataset.hidden==='true'?'false':'true';this.querySelector('.pw-mask').style.display=this.dataset.hidden==='true'?'inline':'none';this.querySelector('.pw-value').style.display=this.dataset.hidden==='true'?'none':'inline';">
                                        <span class="pw-mask">••••••••••••</span>
                                        <code class="pw-value" style="display:none;"><?= e($s['password']) ?></code>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td><?= (int) $s['game_count'] ?></td>
                            <td>
                                <?php if ($s['is_locked']): ?>
                                    <span class="badge badge-danger">🔒 Verrouillée</span>
                                <?php elseif ($s['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isStaff): ?>
                                <td class="d-flex gap-1 flex-wrap">
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/sessions/<?= $s['id'] ?>/reset-password" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline" data-confirm="Réinitialiser le mot de passe de cette session ?" title="Réinitialiser MDP">🔑</button>
                                    </form>
                                    <?php if ($s['is_active']): ?>
                                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/sessions/<?= $s['id'] ?>/deactivate" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-warning" data-confirm="Interrompre cette session ? L'arbitre sera déconnecté." title="Interrompre">⏹</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/sessions/<?= $s['id'] ?>/reactivate" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success" data-confirm="Réactiver cette session ?" title="Réactiver">▶</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($isStaff && $competition['status'] === 'active'): ?>
                <div class="card mt-2" style="background:var(--bg-secondary); border:1px dashed var(--border-color);">
                    <div class="card-body text-small">
                        <strong>Codes de connexion arbitres :</strong>
                        <button type="button" class="btn btn-sm btn-outline" onclick="var el=this.nextElementSibling;el.style.display=el.style.display==='none'?'block':'none';" style="margin-left:0.5rem;">Consulter les mots de passe</button>
                        <div style="display:none;">
                            <p class="text-muted mt-1">Competition ID : <code><?= (int) $competition['id'] ?></code></p>
                            <p class="text-muted">URL de connexion : <code>/competition/login</code></p>
                            <ul style="list-style:none;padding:0;">
                                <?php foreach ($sessions as $s): ?>
                                    <?php if ($s['is_active']): ?>
                                    <li>
                                        Session <strong>#<?= (int) $s['session_number'] ?></strong>
                                        (<?= e($s['referee_name']) ?>) —
                                        Mot de passe : <code><?= e($s['password']) ?></code>
                                    </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const memberSearch = document.getElementById('show_referee_member_search');
    const memberHidden = document.getElementById('show_referee_member_id');
    const memberOptions = document.getElementById('show_referee_member_options');
    const emailInput = document.getElementById('show_referee_email');
    const emailWarning = document.getElementById('show_referee_email_warning');

    if (!memberSearch || !memberHidden || !memberOptions) {
        return;
    }

    const members = <?= json_encode(array_map(fn($m) => [
        'id' => (int) $m['id'],
        'username' => $m['username'],
        'email' => $m['email'] ?? '',
        'arbitration_restricted' => !empty($m['arbitration_restricted']),
    ], $spaceMembers ?? []), JSON_UNESCAPED_UNICODE) ?>;

    const restrictedRefereeEmailsData = <?= json_encode(array_map(fn($u) => [
        'username' => (string) ($u['username'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'normalized_email' => \App\Models\User::normalizeEmail((string) ($u['email'] ?? '')),
    ], $restrictedArbitrationUsers ?? []), JSON_UNESCAPED_UNICODE) ?>;
    const restrictedRefereeEmailMap = new Map(restrictedRefereeEmailsData.map((item) => [item.normalized_email, item]));

    function normalizeRefereeEmail(email) {
        const trimmed = String(email || '').trim().toLowerCase();
        const parts = trimmed.split('@');
        if (parts.length !== 2) {
            return trimmed;
        }

        let [local, domain] = parts;
        if (domain === 'gmail.com' || domain === 'googlemail.com') {
            local = local.split('+', 1)[0].replace(/\./g, '');
            domain = 'gmail.com';
        }
        return local + '@' + domain;
    }

    function validateRefereeEmail() {
        if (!emailInput || !emailWarning) {
            return;
        }

        const normalized = normalizeRefereeEmail(emailInput.value);
        const blocked = normalized ? restrictedRefereeEmailMap.get(normalized) : null;
        if (blocked) {
            emailInput.setCustomValidity('Cette adresse email est associée à un compte non autorisé à arbitrer.');
            emailWarning.textContent = 'Compte non autorisé à arbitrer : ' + blocked.email;
            emailWarning.style.display = 'block';
            return;
        }

        emailInput.setCustomValidity('');
        emailWarning.textContent = '';
        emailWarning.style.display = 'none';
    }

    function hideMemberOptions() {
        memberOptions.style.display = 'none';
    }

    function renderMemberOptions(items) {
        if (items.length === 0) {
            memberOptions.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucun membre correspondant.</div>';
            memberOptions.style.display = 'block';
            return;
        }

        memberOptions.innerHTML = items.map((m) => {
            const label = (m.username || '') + (m.email ? ' (' + m.email + ')' : '');
            return '<div class="member-option ' + (m.arbitration_restricted ? 'is-disabled' : '') + '" data-id="' + m.id + '" data-label="' + label.replace(/"/g, '&quot;') + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:' + (m.arbitration_restricted ? 'not-allowed' : 'pointer') + ';opacity:' + (m.arbitration_restricted ? '0.65' : '1') + ';">'
                + (m.arbitration_restricted ? label + ' - Compte non autorisé à arbitrer' : label)
                + '</div>';
        }).join('');

        memberOptions.querySelectorAll('.member-option').forEach((el) => {
            el.addEventListener('click', () => {
                const member = members.find((item) => item.id == el.dataset.id);
                if (!member || member.arbitration_restricted) {
                    return;
                }
                memberHidden.value = el.dataset.id;
                memberSearch.value = el.dataset.label;
                hideMemberOptions();
            });
        });

        memberOptions.style.display = 'block';
    }

    memberSearch.addEventListener('focus', () => {
        renderMemberOptions(members);
    });

    memberSearch.addEventListener('input', () => {
        const query = memberSearch.value.trim().toLowerCase();
        memberHidden.value = '';
        const filtered = query === ''
            ? members
            : members.filter((m) => {
                const label = ((m.username || '') + ' ' + (m.email || '')).toLowerCase();
                return label.includes(query);
            });
        renderMemberOptions(filtered);
    });

    if (emailInput) {
        emailInput.addEventListener('input', validateRefereeEmail);
        emailInput.addEventListener('change', validateRefereeEmail);
        validateRefereeEmail();
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            hideMemberOptions();
        }
    });
})();
</script>

<?php
$formatDuration = static function (int $seconds): string {
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %02dmin', $hours, $minutes);
    }
    if ($minutes > 0) {
        return sprintf('%dmin %02ds', $minutes, $remainingSeconds);
    }
    return sprintf('%ds', $remainingSeconds);
};
?>

<?php if (!empty($competitionSummary)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3>Bilan de fin de compétition</h3>
    </div>
    <div class="card-body">
        <div class="d-flex gap-1 flex-wrap">
            <span class="badge badge-primary">Parties: <?= (int) $competitionSummary['completed_games'] ?>/<?= (int) $competitionSummary['total_games'] ?></span>
            <span class="badge badge-info">Manches: <?= (int) $competitionSummary['total_rounds'] ?></span>
            <span class="badge badge-success">Temps total de jeu: <?= e($formatDuration((int) $competitionSummary['total_play_seconds'])) ?></span>
            <span class="badge badge-secondary">Joueurs classés: <?= (int) $competitionSummary['player_count'] ?></span>
            <span class="badge badge-warning">Taux moyen de victoire: <?= (float) $competitionSummary['avg_win_rate'] ?> %</span>
            <span class="badge badge-info">Moyenne manches/joueur: <?= (float) $competitionSummary['avg_rounds_per_player'] ?></span>
            <span class="badge badge-primary">Moyenne manches gagnées/joueur: <?= (float) $competitionSummary['avg_round_wins_per_player'] ?></span>
            <span class="badge badge-secondary">Sessions actives: <?= (int) $competitionSummary['sessions_used'] ?></span>
            <span class="badge badge-secondary">Types de jeu utilisés: <?= (int) $competitionSummary['game_types_used'] ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classement -->
<?php if (!empty($rankings)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3><?= !empty($competitionSummary) ? 'Classement final' : 'Classement' ?></h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Joueur</th>
                        <th>Manches jouées</th>
                        <th>Manches gagnées</th>
                        <th>Taux de victoire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $i => $r): ?>
                    <tr>
                        <td>
                            <?php if ($i === 0): ?>🥇
                            <?php elseif ($i === 1): ?>🥈
                            <?php elseif ($i === 2): ?>🥉
                            <?php else: ?><?= $i + 1 ?>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= (int) $r['rounds_played'] ?></td>
                        <td><?= (int) $r['rounds_won'] ?></td>
                        <td><?= $r['win_rate'] ?> %</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Parties de la compétition -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Parties (<?= count($games) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-muted text-center">Aucune partie enregistrée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Session</th>
                            <th>Joueurs</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><?= e($g['game_type_name']) ?></td>
                            <td>
                                #<?= (int) $g['session_number'] ?>
                                <span class="text-muted text-small">(<?= e($g['referee_name'] ?? '') ?>)</span>
                            </td>
                            <td><?= (int) $g['player_count'] ?></td>
                            <td>
                                <?php
                                $gStatus = match ($g['status']) {
                                    'pending'     => ['Attente', 'badge-secondary'],
                                    'in_progress' => ['En cours', 'badge-primary'],
                                    'paused'      => ['Pause', 'badge-warning'],
                                    'completed'   => ['Terminée', 'badge-success'],
                                    default       => [$g['status'], ''],
                                };
                                ?>
                                <span class="badge <?= $gStatus[1] ?>"><?= $gStatus[0] ?></span>
                            </td>
                            <td><?= date('d/m H:i', strtotime($g['created_at'])) ?></td>
                            <td>
                                <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $g['id'] ?>" class="btn btn-sm btn-outline" title="Voir">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
