<div class="page-header">
    <div>
        <h1>🏆 <?= e($session['competition_name']) ?> — Session #<?= (int) $session['session_number'] ?></h1>
        <p class="text-muted text-small">Arbitre : <?= e($session['referee_name']) ?></p>
    </div>
</div>

<!-- Créer une partie -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Nouvelle partie</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/competition/games/create">
            <?= csrf_field() ?>

            <div class="d-flex gap-2 flex-wrap">
                <div class="form-group" style="flex:1;min-width:200px;">
                    <label for="game_type_id" class="form-label">Type de jeu *</label>
                    <select id="game_type_id" name="game_type_id" class="form-control" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($gameTypes as $gt): ?>
                            <option value="<?= $gt['id'] ?>"
                                data-min="<?= (int) ($gt['min_players'] ?? 1) ?>"
                                data-max="<?= (int) ($gt['max_players'] ?? 0) ?>">
                                <?= e($gt['name']) ?>
                                (<?= (int) ($gt['min_players'] ?? 1) ?>-<?= $gt['max_players'] ? (int) $gt['max_players'] : '∞' ?> joueurs)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:2;min-width:300px;">
                    <label class="form-label">Joueurs *</label>
                    <div class="d-flex gap-1 flex-wrap" id="playerCheckboxes">
                        <?php foreach ($players as $p): ?>
                            <label class="d-flex align-center gap-05" style="cursor:pointer;padding:0.25rem 0.5rem;background:var(--bg-secondary);border-radius:6px;">
                                <input type="checkbox" name="player_ids[]" value="<?= $p['id'] ?>">
                                <?= e($p['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notes (optionnel)</label>
                <input type="text" id="notes" name="notes" class="form-control" maxlength="500" placeholder="Notes sur la partie...">
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Créer la partie</button>
        </form>
    </div>
</div>

<!-- Liste des parties -->
<div class="card">
    <div class="card-header">
        <h3>Parties de cette session (<?= count($games) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-muted text-center">Aucune partie pour le moment. Créez-en une ci-dessus.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type de jeu</th>
                            <th>Joueurs</th>
                            <th>Manches</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><strong><?= e($g['game_type_name']) ?></strong></td>
                            <td><?= (int) $g['player_count'] ?></td>
                            <td><?= (int) $g['round_count'] ?></td>
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
                            <td>
                                <a href="/competition/games/<?= $g['id'] ?>" class="btn btn-sm btn-outline" title="Gérer">
                                    <i class="bi bi-pencil-square"></i>
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
