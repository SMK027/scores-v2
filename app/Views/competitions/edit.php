<div class="page-header">
    <h1>Modifier la compétition</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>" class="btn btn-outline btn-sm">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/edit">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nom de la compétition *</label>
                <input type="text" id="name" name="name" class="form-control" required autofocus maxlength="200"
                       value="<?= e($competition['name']) ?>">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000"><?= e($competition['description'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label for="starts_at" class="form-label">Date de début *</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" required
                           value="<?= date('Y-m-d\TH:i', strtotime($competition['starts_at'])) ?>">
                </div>
                <div class="form-group" style="flex:1;">
                    <label for="ends_at" class="form-label">Date de fin *</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" required
                           value="<?= date('Y-m-d\TH:i', strtotime($competition['ends_at'])) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Types de jeu autorisés *</label>
                <div class="d-flex gap-1 flex-wrap">
                    <?php $selected = array_map('intval', $selectedGameTypeIds ?? []); ?>
                    <?php foreach (($gameTypes ?? []) as $gt): ?>
                        <?php $checked = in_array((int) $gt['id'], $selected, true); ?>
                        <label style="display:flex;align-items:center;gap:0.45rem;cursor:pointer;border:1px solid var(--gray-light);padding:0.35rem 0.55rem;border-radius:8px;">
                            <input type="checkbox" name="allowed_game_type_ids[]" value="<?= (int) $gt['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                            <span>
                                <strong><?= e($gt['name']) ?></strong>
                                <span class="text-muted text-small">(<?= win_condition_label($gt['win_condition']) ?>)</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>
