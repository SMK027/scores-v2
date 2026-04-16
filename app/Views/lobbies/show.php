<?php
$isHost    = ((int) $lobby['created_by'] === $currentUserId);
$isMember  = false;
foreach ($lobby['members'] as $m) {
    if ((int) $m['user_id'] === $currentUserId) {
        $isMember = true;
        break;
    }
}
$game      = $games[$lobby['game_key']] ?? null;
$config    = $lobby['game_config'];
$maxPlayers = $config['max_players'] ?? 2;
$gridSize   = $config['grid_size'] ?? null;
$alignCount = $config['align_count'] ?? null;
?>

<div class="page-header">
    <div>
        <h1>🏠 <?= e($lobby['name']) ?></h1>
        <p class="text-muted text-small">
            <?= $game ? e($game['icon'] . ' ' . $game['name']) : e($lobby['game_key']) ?>
            — Créé par <?= e($lobby['creator_name']) ?>
            <?php if ($lobby['visibility'] === 'private'): ?>
                — <span class="badge badge-secondary">🔒 Privé</span>
            <?php else: ?>
                — <span class="badge badge-success">🌐 Public</span>
            <?php endif; ?>
            <?php if ($gridSize && $lobby['game_key'] === 'morpion'): ?>
                — Grille <?= $grids[$gridSize]['label'] ?? "{$gridSize}×{$gridSize}" ?>
                <?php if ($alignCount): ?> (<?= $alignCount ?> pour gagner)<?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-1">
        <a href="/spaces/<?= $currentSpace['id'] ?>/lobbies" class="btn btn-outline btn-sm">← Salons</a>
        <?php if ($isMember && !$isHost && $lobby['status'] === 'open'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $lobby['id'] ?>/leave" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" data-confirm="Quitter ce salon ?">🚪 Quitter</button>
            </form>
        <?php endif; ?>
        <?php if ($isHost): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $lobby['id'] ?>/close" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Fermer définitivement ce salon ?">🗑️ Fermer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Statut -->
<div id="lobby-status" class="card mb-3">
    <div class="card-body" style="text-align:center;padding:.75rem;">
        <?php if ($lobby['status'] === 'open'): ?>
            <span class="badge badge-success" style="font-size:1.1em;">✅ Salon ouvert — En attente de joueurs</span>
        <?php elseif ($lobby['status'] === 'in_game'): ?>
            <span class="badge badge-warning" style="font-size:1.1em;">🎮 Partie en cours</span>
            <?php if ($lobby['current_session_id']): ?>
                <div style="margin-top:.5rem;">
                    <a href="/spaces/<?= $currentSpace['id'] ?>/play/<?= $lobby['current_session_id'] ?>" class="btn btn-primary btn-sm">Rejoindre la partie</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <span class="badge badge-secondary" style="font-size:1.1em;">Salon fermé</span>
        <?php endif; ?>
    </div>
</div>

<div class="lobby-grid">
    <!-- Membres -->
    <div class="card">
        <div class="card-header">
            <h3>👥 Membres (<span id="member-count"><?= count($lobby['members']) ?></span>/<?= $maxPlayers ?>)</h3>
        </div>
        <div class="card-body" id="members-list">
            <?php if (empty($lobby['members'])): ?>
                <p class="text-muted">Aucun membre.</p>
            <?php else: ?>
                <?php foreach ($lobby['members'] as $m): ?>
                <div class="lobby-member" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
                    <?php if (!empty($m['avatar'])): ?>
                        <img src="<?= e($m['avatar']) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <span style="width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--white);font-size:0.85rem;font-weight:600;flex-shrink:0;"><?= strtoupper(substr($m['username'], 0, 1)) ?></span>
                    <?php endif; ?>
                    <span><?= e($m['username']) ?></span>
                    <?php if ((int) $m['user_id'] === (int) $lobby['created_by']): ?>
                        <span class="badge badge-primary" style="font-size:.7em;">👑 Hôte</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions / Invitations -->
    <div>
        <?php if ($isHost && $lobby['status'] === 'open'): ?>
        <!-- Lancer la partie -->
        <div class="card mb-3">
            <div class="card-header"><h3>🚀 Lancer la partie</h3></div>
            <div class="card-body" style="text-align:center;">
                <?php $memberCount = count($lobby['members']); ?>
                <?php if ($memberCount < ($game['min_players'] ?? 1)): ?>
                    <p class="text-muted">Il faut au moins <?= $game['min_players'] ?? 1 ?> joueurs pour lancer.</p>
                <?php else: ?>
                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $lobby['id'] ?>/launch">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary" id="btn-launch">🎮 Lancer la partie (<?= $memberCount ?> joueurs)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inviter -->
        <div class="card mb-3">
            <div class="card-header"><h3>📨 Inviter un joueur</h3></div>
            <div class="card-body">
                <div style="position:relative;">
                    <input type="text" id="invite-search" placeholder="Rechercher un membre…" autocomplete="off" style="width:100%;padding:.35rem .5rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);">
                    <div id="invite-results" style="position:absolute;top:100%;left:0;right:0;background:var(--bg-card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:0 0 4px 4px;display:none;max-height:200px;overflow-y:auto;z-index:10;"></div>
                </div>
                <?php if (!empty($lobby['invitations'])): ?>
                <div style="margin-top:.75rem;">
                    <p class="text-small text-muted">Invitations en attente :</p>
                    <?php foreach ($lobby['invitations'] as $inv): ?>
                    <span class="badge badge-warning" style="margin:.25rem;"><?= e($inv['invited_username']) ?> ⏳</span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isMember && $lobby['visibility'] === 'public' && $lobby['status'] === 'open'): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;">
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $lobby['id'] ?>/join">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Rejoindre ce salon</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isMember || $isHost): ?>
<script>
(function() {
    const spaceId = <?= (int) $currentSpace['id'] ?>;
    const lobbyId = <?= (int) $lobby['id'] ?>;
    const currentUserId = <?= $currentUserId ?>;
    const stateUrl = `/spaces/${spaceId}/lobbies/${lobbyId}/state`;
    const searchUrl = `/spaces/${spaceId}/lobbies/${lobbyId}/search-members`;
    const csrfToken = '<?= csrf_token() ?>';
    let lobbyStatus = '<?= $lobby['status'] ?>';

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Polling état du lobby
    const pollInterval = setInterval(async () => {
        if (lobbyStatus === 'closed') {
            clearInterval(pollInterval);
            return;
        }
        try {
            const res = await fetch(stateUrl);
            if (!res.ok) return;
            const data = await res.json();

            // Rediriger vers la partie si elle a démarré
            if (data.status === 'in_game' && data.current_session_id && lobbyStatus !== 'in_game') {
                window.location.href = `/spaces/${spaceId}/play/${data.current_session_id}`;
                return;
            }

            // Mettre à jour le compteur
            const countEl = document.getElementById('member-count');
            if (countEl) countEl.textContent = data.member_count;

            // Mettre à jour la liste des membres
            const membersList = document.getElementById('members-list');
            if (membersList && data.members) {
                let html = '';
                data.members.forEach(function(m) {
                    html += '<div class="lobby-member" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border,#e5e7eb);">';
                    if (m.avatar) {
                        html += '<img src="' + escapeHtml(m.avatar) + '" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">';
                    } else {
                        html += '<span style="width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--white);font-size:0.85rem;font-weight:600;flex-shrink:0;">' + escapeHtml(m.username.charAt(0).toUpperCase()) + '</span>';
                    }
                    html += '<span>' + escapeHtml(m.username) + '</span>';
                    if (m.user_id === <?= (int) $lobby['created_by'] ?>) {
                        html += ' <span class="badge badge-primary" style="font-size:.7em;">👑 Hôte</span>';
                    }
                    html += '</div>';
                });
                membersList.innerHTML = html || '<p class="text-muted">Aucun membre.</p>';
            }

            // Mettre à jour le bouton lancer
            const btnLaunch = document.getElementById('btn-launch');
            if (btnLaunch) {
                btnLaunch.textContent = '🎮 Lancer la partie (' + data.member_count + ' joueurs)';
            }

            lobbyStatus = data.status;
        } catch (e) {}
    }, 3000);

    // Recherche d'invitation
    const searchInput = document.getElementById('invite-search');
    const resultsDiv = document.getElementById('invite-results');
    if (searchInput && resultsDiv) {
        let debounce;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounce);
            const q = this.value.trim();
            if (q.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }
            debounce = setTimeout(async () => {
                try {
                    const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q));
                    const data = await res.json();
                    if (data.results && data.results.length > 0) {
                        let html = '';
                        data.results.forEach(function(u) {
                            html += '<div class="invite-result" style="padding:.5rem .75rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;border-bottom:1px solid var(--border,#e5e7eb);" data-user-id="' + u.id + '">';
                            if (u.avatar) {
                                html += '<img src="' + escapeHtml(u.avatar) + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">';
                            } else {
                                html += '<span style="width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--white);font-size:0.7rem;font-weight:600;flex-shrink:0;">' + escapeHtml(u.username.charAt(0).toUpperCase()) + '</span>';
                            }
                            html += '<span>' + escapeHtml(u.username) + '</span>';
                            html += '</div>';
                        });
                        resultsDiv.innerHTML = html;
                        resultsDiv.style.display = 'block';

                        resultsDiv.querySelectorAll('.invite-result').forEach(function(el) {
                            el.addEventListener('click', function() {
                                const userId = this.dataset.userId;
                                // Submit invite form
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = `/spaces/${spaceId}/lobbies/${lobbyId}/invite`;
                                form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="user_id" value="${userId}">`;
                                document.body.appendChild(form);
                                form.submit();
                            });
                            el.addEventListener('mouseenter', function() { this.style.background = 'var(--bg-hover,#f3f4f6)'; });
                            el.addEventListener('mouseleave', function() { this.style.background = ''; });
                        });
                    } else {
                        resultsDiv.innerHTML = '<div style="padding:.5rem .75rem;" class="text-muted text-small">Aucun résultat</div>';
                        resultsDiv.style.display = 'block';
                    }
                } catch (e) {}
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!resultsDiv.contains(e.target) && e.target !== searchInput) {
                resultsDiv.style.display = 'none';
            }
        });
    }
})();
</script>
<?php endif; ?>
