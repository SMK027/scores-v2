<div class="page-header">
    <h1>Nouvelle compétition</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/competitions" class="btn btn-outline btn-sm">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/create" id="competitionForm">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nom de la compétition *</label>
                <input type="text" id="name" name="name" class="form-control" required autofocus maxlength="200">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000"></textarea>
            </div>

            <div class="d-flex gap-2">
                <div class="form-group" style="flex:1;">
                    <label for="starts_at" class="form-label">Date de début *</label>
                    <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label for="ends_at" class="form-label">Date de fin *</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Types de jeu autorisés *</label>
                <div class="d-flex gap-1 flex-wrap">
                    <?php foreach (($gameTypes ?? []) as $gt): ?>
                        <label style="display:flex;align-items:center;gap:0.45rem;cursor:pointer;border:1px solid var(--gray-light);padding:0.35rem 0.55rem;border-radius:8px;">
                            <input type="checkbox" name="allowed_game_type_ids[]" value="<?= (int) $gt['id'] ?>">
                            <span>
                                <strong><?= e($gt['name']) ?></strong>
                                <span class="text-muted text-small">(<?= win_condition_label($gt['win_condition']) ?>)</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="form-hint">Seuls ces types seront proposés aux arbitres pendant la compétition.</span>
            </div>

            <hr>
            <h3>Sessions (tables / arbitres)</h3>
            <p class="text-muted text-small">
                Chaque session correspond à une table gérée par un arbitre.
                Un mot de passe sera généré automatiquement pour chaque session.
            </p>

            <div id="sessionsContainer">
                <div class="d-flex gap-1 mb-2 session-row">
                    <input type="text" name="referee_names[]" class="form-control" placeholder="Nom de l'arbitre" required style="flex:1;">
                    <input type="email" name="referee_emails[]" class="form-control" placeholder="Email de l'arbitre" style="flex:1;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.session-row').remove()" title="Supprimer">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline mb-3" onclick="addSessionRow()">
                <i class="bi bi-plus-circle"></i> Ajouter une session
            </button>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Créer la compétition</button>
            </div>
        </form>
    </div>
</div>

<script>
function addSessionRow() {
    const container = document.getElementById('sessionsContainer');
    const div = document.createElement('div');
    div.className = 'd-flex gap-1 mb-2 session-row';
    div.innerHTML = '<input type="text" name="referee_names[]" class="form-control" placeholder="Nom de l\'arbitre" required style="flex:1;">' +
        '<input type="email" name="referee_emails[]" class="form-control" placeholder="Email de l\'arbitre" style="flex:1;">' +
        '<button type="button" class="btn btn-sm btn-outline" onclick="this.closest(\'.session-row\').remove()" title="Supprimer"><i class="bi bi-trash"></i></button>';
    container.appendChild(div);
    div.querySelector('input').focus();
}
</script>
