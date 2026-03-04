<div class="page-header">
    <h1>🔍 Recherche</h1>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/spaces/<?= $currentSpace['id'] ?>/search" class="d-flex gap-1">
            <input type="text" name="q" class="form-control" placeholder="Rechercher joueurs, jeux, parties, commentaires..."
                   value="<?= e($query) ?>" autofocus minlength="2" required style="flex:1;">
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </form>
    </div>
</div>

<?php if ($query !== ''): ?>
    <?php if (empty($results)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔎</div>
            <p>Aucun résultat pour « <strong><?= e($query) ?></strong> »</p>
        </div>
    <?php else: ?>
        <p class="text-muted mb-2"><?= count($results) ?> résultat(s) pour « <strong><?= e($query) ?></strong> »</p>

        <div class="card">
            <div class="card-body">
                <?php
                    $icons = [
                        'player'    => '👤',
                        'game_type' => '🎯',
                        'game'      => '🎮',
                        'comment'   => '💬',
                    ];
                    $labels = [
                        'player'    => 'Joueur',
                        'game_type' => 'Type de jeu',
                        'game'      => 'Partie',
                        'comment'   => 'Commentaire',
                    ];
                ?>
                <?php foreach ($results as $result): ?>
                    <?php
                        $icon = $icons[$result['type']] ?? '📌';
                        $label = $labels[$result['type']] ?? $result['type'];

                        // Construire le lien selon le type
                        switch ($result['type']) {
                            case 'player':
                                $link = "/spaces/{$currentSpace['id']}/players";
                                break;
                            case 'game_type':
                                $link = "/spaces/{$currentSpace['id']}/game-types";
                                break;
                            case 'game':
                                $link = "/spaces/{$currentSpace['id']}/games/{$result['id']}";
                                break;
                            case 'comment':
                                $link = "/spaces/{$currentSpace['id']}/games/{$result['id']}";
                                break;
                            default:
                                $link = "#";
                        }
                    ?>
                    <a href="<?= $link ?>" class="search-result" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-bottom:1px solid var(--gray-light);text-decoration:none;color:inherit;transition:background 0.2s;" onmouseover="this.style.background='var(--gray-light)'" onmouseout="this.style.background=''">
                        <span style="font-size:1.5em;"><?= $icon ?></span>
                        <div style="flex:1;">
                            <div class="font-bold"><?= e($result['name']) ?></div>
                            <div class="d-flex gap-1 align-center">
                                <span class="badge badge-info" style="font-size:0.7em;"><?= $label ?></span>
                                <?php if (!empty($result['extra'])): ?>
                                    <span class="text-muted text-small"><?= e($result['extra']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-muted">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="text-muted text-center mt-3">
        <p>Entrez au moins 2 caractères pour lancer la recherche.</p>
    </div>
<?php endif; ?>
