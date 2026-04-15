<?php
// ── Labels de scope ──
$scopeLabels = [
    'space'       => '🎮 Espace',
    'competition' => '⚽ Compétition',
    'admin'       => '🔧 Admin',
    'auth'        => '🔐 Auth',
];
$scopeBadges = [
    'space'       => 'badge-info',
    'competition' => 'badge-warning',
    'admin'       => 'badge-danger',
    'auth'        => 'badge-secondary',
];

// ── Labels d'action ──
$actionLabels = [
    // Auth
    'account.deletion.cancelled'     => 'Suppression de compte annulée',
    'account.deletion.finalized'     => 'Compte supprimé définitivement',
    'account.deletion.requested'     => 'Demande de suppression de compte',
    'email.verified'                 => 'Email vérifié',
    'login.remember'                 => 'Connexion par cookie',
    'login.success'                  => 'Connexion réussie',
    'login.success.api'              => 'Connexion API réussie',
    'logout'                         => 'Déconnexion',
    'password.change'                => 'Changement de mot de passe',
    'password.change.api'            => 'Changement de mot de passe (API)',
    'password.reset'                 => 'Réinitialisation de mot de passe',
    'register'                       => 'Inscription',
    'register.api'                   => 'Inscription (API)',
    // Admin
    'contact_ticket_reply'           => 'Réponse à un ticket',
    'contact_ticket_status'          => 'Changement de statut de ticket',
    'fail2ban.update'                => 'Mise à jour Fail2Ban',
    'global_game_type.create'        => 'Création type de jeu global',
    'global_game_type.delete'        => 'Suppression type de jeu global',
    'global_game_type.update'        => 'Modification type de jeu global',
    'ip.ban'                         => 'Bannissement IP',
    'ip.unban'                       => 'Débannissement IP',
    'leaderboard_criteria.update'    => 'MAJ critères classement',
    'password_policy.update'         => 'MAJ politique mots de passe',
    'player.restore'                 => 'Restauration d\'un joueur',
    'space.auto_deleted'             => 'Espace supprimé (auto)',
    'space.deletion_cancelled'       => 'Suppression d\'espace annulée',
    'space.deletion_scheduled'       => 'Suppression d\'espace programmée',
    'space.restrictions_removed'     => 'Restrictions d\'espace retirées',
    'space.restrictions_updated'     => 'Restrictions d\'espace MAJ',
    'user.ban'                       => 'Bannissement utilisateur',
    'user.bypass_email_verification' => 'Bypass vérification email',
    'user.create'                    => 'Création de compte (admin)',
    'user.impersonate_start'         => 'Prise de contrôle de compte',
    'user.impersonate_stop'          => 'Fin de contrôle de compte',
    'user.password_reset_sent'       => 'Lien de réinit. envoyé',
    'user.restrictions_removed'      => 'Restrictions utilisateur retirées',
    'user.restrictions_updated'      => 'Restrictions utilisateur MAJ',
    'user.role_update'               => 'Modification de rôle',
    'user.unban'                     => 'Débannissement utilisateur',
    // Space
    'contact_ticket_create'          => 'Création de ticket',
    'game.comment_add'               => 'Ajout de commentaire',
    'game.comment_delete'            => 'Suppression de commentaire',
    'game.create'                    => 'Création de partie',
    'game.delete'                    => 'Suppression de partie',
    'game.status_change'             => 'Changement de statut de partie',
    'game.update'                    => 'Modification de partie',
    'game_type.create'               => 'Création de type de jeu',
    'game_type.delete'               => 'Suppression de type de jeu',
    'game_type.replace'              => 'Remplacement de type de jeu',
    'game_type.update'               => 'Modification de type de jeu',
    'invite.create'                  => 'Création d\'invitation',
    'invite.create_link'             => 'Création de lien d\'invitation',
    'invite.revoke'                  => 'Révocation d\'invitation',
    'member.accept_invite'           => 'Invitation acceptée',
    'member.cancel_invite'           => 'Invitation annulée',
    'member.decline_invite'          => 'Invitation déclinée',
    'member.invite'                  => 'Invitation d\'un membre',
    'member.join'                    => 'Arrivée d\'un membre',
    'member.leave'                   => 'Départ d\'un membre',
    'member.remove'                  => 'Retrait d\'un membre',
    'member.role_update'             => 'Modification de rôle membre',
    'member_card.activate'           => 'Activation carte membre',
    'member_card.deactivate'         => 'Désactivation carte membre',
    'member_card.delete'             => 'Suppression carte membre',
    'member_card.generate'           => 'Génération carte membre',
    'member_card.regenerate'         => 'Regénération carte membre',
    'player.create'                  => 'Création de joueur',
    'player.delete'                  => 'Suppression de joueur',
    'player.link_self'               => 'Association profil-joueur',
    'player.update'                  => 'Modification de joueur',
    'round.create'                   => 'Création de manche',
    'round.delete'                   => 'Suppression de manche',
    'round.scores_corrected'         => 'Correction de scores',
    'round.scores_saved'             => 'Enregistrement de scores',
    'round.status_change'            => 'Changement statut de manche',
    'space.create'                   => 'Création d\'espace',
    'space.delete'                   => 'Suppression d\'espace',
    'space.export'                   => 'Export d\'espace',
    'space.import'                   => 'Import d\'espace',
    'space.import.auto_restriction'  => 'Restriction auto après import',
    'space.update'                   => 'Modification d\'espace',
    // Compétition
    'competition.create'                  => 'Création de compétition',
    'competition.update'                  => 'Modification de compétition',
    'competition.activate'                => 'Activation de compétition',
    'competition.pause'                   => 'Mise en pause de compétition',
    'competition.resume'                  => 'Reprise de compétition',
    'competition.close'                   => 'Clôture de compétition',
    'competition.delete'                  => 'Suppression de compétition',
    'competition.member_card.verify'      => 'Vérification carte membre',
    'competition.player_register_self'    => 'Inscription joueur (auto)',
    'competition.player_register_staff'   => 'Inscription joueur (staff)',
    'competition.player_unregister'       => 'Désinscription joueur',
    'session.add'                         => 'Ajout de session',
    'session.login'                       => 'Connexion session',
    'session.logout'                      => 'Déconnexion session',
    'session.login.user'                  => 'Connexion utilisateur session',
    'session.login.user.api'              => 'Connexion utilisateur session (API)',
    'session.password_reset'              => 'Réinit. mot de passe session',
    'session.deactivate'                  => 'Désactivation session',
    'session.reactivate'                  => 'Réactivation session',
    'session.pause.self'                  => 'Pause session (auto)',
    'session.close.self'                  => 'Clôture session (auto)',
    'session.game_create'                 => 'Création partie (session)',
    'session.game_complete'               => 'Partie terminée (session)',
    'session.round_create'                => 'Création manche (session)',
    'session.round_status_change'         => 'Statut manche (session)',
    'session.round_delete'                => 'Suppression manche (session)',
    'session.scores_saved'                => 'Scores enregistrés (session)',
];

// ── Labels d'entité ──
$entityLabels = [
    'user'                 => 'Utilisateur',
    'space'                => 'Espace',
    'game'                 => 'Partie',
    'game_type'            => 'Type de jeu',
    'round'                => 'Manche',
    'player'               => 'Joueur',
    'competition'          => 'Compétition',
    'competition_session'  => 'Session',
    'space_invitation'     => 'Invitation',
    'space_member'         => 'Membre',
    'space_invite'         => 'Invitation',
    'user_ban'             => 'Bannissement',
    'ip_ban'               => 'Bannissement IP',
    'ip'                   => 'IP',
    'comment'              => 'Commentaire',
    'api'                  => 'API',
];

// ── Labels de clés de détail ──
$detailKeyLabels = [
    'username'                   => 'Utilisateur',
    'email'                      => 'Email',
    'role'                       => 'Rôle',
    'new_role'                   => 'Nouveau rôle',
    'old_role'                   => 'Ancien rôle',
    'reason'                     => 'Raison',
    'target_username'            => 'Cible',
    'target_id'                  => 'ID cible',
    'admin_username'             => 'Administrateur',
    'status'                     => 'Statut',
    'old_status'                 => 'Ancien statut',
    'new_status'                 => 'Nouveau statut',
    'name'                       => 'Nom',
    'space_name'                 => 'Espace',
    'player_name'                => 'Joueur',
    'game_type'                  => 'Type de jeu',
    'local_name'                 => 'Nom local',
    'global_name'                => 'Nom global',
    'category'                   => 'Catégorie',
    'subject'                    => 'Sujet',
    'ip'                         => 'IP',
    'duration'                   => 'Durée',
    'duration_minutes'           => 'Durée (min)',
    'expires_at'                 => 'Expire le',
    'banned_until'               => 'Banni jusqu\'à',
    'password_mode'              => 'Mode MDP',
    'permanent'                  => 'Permanent',
    'restrictions'               => 'Restrictions',
    'requested_by'               => 'Demandé par',
    'request_note'               => 'Note',
    'filename'                   => 'Fichier',
    'source_exported_at'         => 'Exporté le',
    'executed_at'                => 'Exécuté le',
    'effective_at'               => 'Effectif le',
    'reference'                  => 'Référence',
    'old_reference'              => 'Anc. référence',
    'new_reference'              => 'Nouv. référence',
    'referee'                    => 'Arbitre',
    'referee_name'               => 'Nom arbitre',
    'via'                        => 'Via',
    'signature_valid'            => 'Signature valide',
    'players'                    => 'Joueurs',
    'sessions'                   => 'Sessions',
    'games_moved'                => 'Parties déplacées',
    'round_number'               => 'Manche n°',
    'session_number'             => 'Session n°',
    'game_id'                    => 'Partie',
    'space_id'                   => 'Espace',
    'recent_checksum_failures'   => 'Échecs checksum',
    'threshold'                  => 'Seuil',
    'window_minutes'             => 'Fenêtre (min)',
    'by'                         => 'Par',
];
?>

<div class="page-header">
    <h1>📋 Journal d'activité</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/logs" class="card mb-3">
    <div class="card-body">
        <div class="d-flex gap-1 flex-wrap align-items-end">
            <div>
                <label class="form-label">Portée</label>
                <select name="scope" class="form-control" style="min-width:140px;">
                    <option value="">Tous</option>
                    <option value="space" <?= ($filters['scope'] ?? '') === 'space' ? 'selected' : '' ?>>🎮 Espace</option>
                    <option value="competition" <?= ($filters['scope'] ?? '') === 'competition' ? 'selected' : '' ?>>⚽ Compétition</option>
                    <option value="admin" <?= ($filters['scope'] ?? '') === 'admin' ? 'selected' : '' ?>>🔧 Administration</option>
                    <option value="auth" <?= ($filters['scope'] ?? '') === 'auth' ? 'selected' : '' ?>>🔐 Authentification</option>
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
                            <th>Portée</th>
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
                                    <?php $badgeClass = $scopeBadges[$log['scope']] ?? 'badge-secondary'; ?>
                                    <span class="badge <?= $badgeClass ?>" style="font-size:0.7em;">
                                        <?= $scopeLabels[$log['scope']] ?? e($log['scope']) ?>
                                        <?php if ($log['scope_id']): ?>
                                            #<?= (int) $log['scope_id'] ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $label = $actionLabels[$log['action']] ?? null; ?>
                                    <?php if ($label): ?>
                                        <span title="<?= e($log['action']) ?>"><?= $label ?></span>
                                    <?php else: ?>
                                        <code style="font-size:0.85em;"><?= e($log['action']) ?></code>
                                    <?php endif; ?>
                                </td>
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
                                        <?= $entityLabels[$log['entity_type']] ?? e($log['entity_type']) ?>
                                        <?php if ($log['entity_id']): ?>
                                            <span class="text-muted">#<?= (int) $log['entity_id'] ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-small"><?= e($log['ip_address'] ?? '—') ?></td>
                                <td class="text-small" style="max-width:300px;">
                                    <?php if ($log['details']): ?>
                                        <?php
                                            $details = json_decode($log['details'], true);
                                            if (is_array($details)):
                                        ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:3px;" title="<?= e($log['details']) ?>">
                                                <?php foreach ($details as $k => $v): ?>
                                                    <?php
                                                        if (is_bool($v)) {
                                                            $value = $v ? 'oui' : 'non';
                                                        } elseif (is_array($v)) {
                                                            $value = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                        } elseif ($v === null) {
                                                            $value = '—';
                                                        } else {
                                                            $value = (string) $v;
                                                        }
                                                        $keyLabel = $detailKeyLabels[$k] ?? $k;
                                                    ?>
                                                    <span class="badge badge-secondary" style="font-size:0.7em;font-weight:normal;white-space:nowrap;">
                                                        <strong><?= e($keyLabel) ?></strong> <?= e($value) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
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
