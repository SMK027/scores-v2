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
                <div id="selected-game-types" class="d-flex gap-1 flex-wrap" style="margin-bottom:0.5rem;"></div>
                <div class="autocomplete-wrapper" style="position:relative;max-width:540px;">
                    <input type="text" id="game_type_search" class="form-control" placeholder="Rechercher un type de jeu..." autocomplete="off">
                    <div id="game_type_options" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:240px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
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
                    <div class="autocomplete-wrapper" style="position:relative;flex:1;min-width:260px;">
                        <input type="text" class="form-control referee-member-search" placeholder="Membre de l'espace (optionnel)" autocomplete="off">
                        <input type="hidden" name="referee_user_ids[]" class="referee-user-id">
                        <div class="autocomplete-list referee-member-options" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                    <input type="text" name="referee_names[]" class="form-control" placeholder="Nom de l'arbitre (si pas de membre)" style="flex:1;">
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
const refereeMembersData = <?= json_encode(array_map(fn($m) => [
    'id' => (int) $m['id'],
    'username' => (string) ($m['username'] ?? ''),
    'email' => (string) ($m['email'] ?? ''),
], $spaceMembers ?? []), JSON_UNESCAPED_UNICODE) ?>;

function attachRefereeAutocomplete(row) {
    const input = row.querySelector('.referee-member-search');
    const hidden = row.querySelector('.referee-user-id');
    const options = row.querySelector('.referee-member-options');
    if (!input || !hidden || !options) {
        return;
    }

    function hideOptions() {
        options.style.display = 'none';
    }

    function renderOptions(items) {
        if (items.length === 0) {
            options.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucun membre correspondant.</div>';
            options.style.display = 'block';
            return;
        }

        options.innerHTML = items.map((m) => (
            '<div class="referee-member-option" data-id="' + m.id + '" data-label="' + (m.username + ' (' + m.email + ')').replace(/"/g, '&quot;') + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:pointer;display:flex;justify-content:space-between;gap:0.5rem;">'
            + '<span>' + m.username + '</span>'
            + '<span class="text-muted text-small">' + m.email + '</span>'
            + '</div>'
        )).join('');

        options.querySelectorAll('.referee-member-option').forEach((el) => {
            el.addEventListener('click', () => {
                hidden.value = el.dataset.id;
                input.value = el.dataset.label;
                hideOptions();
            });
        });

        options.style.display = 'block';
    }

    input.addEventListener('focus', () => renderOptions(refereeMembersData));
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        hidden.value = '';
        const filtered = query === ''
            ? refereeMembersData
            : refereeMembersData.filter((m) =>
                m.username.toLowerCase().includes(query)
                || m.email.toLowerCase().includes(query)
            );
        renderOptions(filtered);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            hideOptions();
        }
    });
}

function addSessionRow() {
    const container = document.getElementById('sessionsContainer');
    const div = document.createElement('div');
    div.className = 'd-flex gap-1 mb-2 session-row';
    div.innerHTML = '<div class="autocomplete-wrapper" style="position:relative;flex:1;min-width:260px;">' +
        '<input type="text" class="form-control referee-member-search" placeholder="Membre de l\'espace (optionnel)" autocomplete="off">' +
        '<input type="hidden" name="referee_user_ids[]" class="referee-user-id">' +
        '<div class="autocomplete-list referee-member-options" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>' +
        '</div>' +
        '<input type="text" name="referee_names[]" class="form-control" placeholder="Nom de l\'arbitre (si pas de membre)" style="flex:1;">' +
        '<input type="email" name="referee_emails[]" class="form-control" placeholder="Email de l\'arbitre" style="flex:1;">' +
        '<button type="button" class="btn btn-sm btn-outline" onclick="this.closest(\'.session-row\').remove()" title="Supprimer"><i class="bi bi-trash"></i></button>';
    container.appendChild(div);
    attachRefereeAutocomplete(div);
    div.querySelector('input').focus();
}

document.querySelectorAll('#sessionsContainer .session-row').forEach(attachRefereeAutocomplete);

(function() {
    const availableGameTypes = <?= json_encode(array_map(fn($gt) => [
        'id' => (int) $gt['id'],
        'name' => $gt['name'],
        'win_condition' => win_condition_label($gt['win_condition']),
    ], $gameTypes ?? []), JSON_UNESCAPED_UNICODE) ?>;

    const selectedContainer = document.getElementById('selected-game-types');
    const searchInput = document.getElementById('game_type_search');
    const optionsContainer = document.getElementById('game_type_options');
    const form = document.getElementById('competitionForm');
    const selectedIds = new Set();

    function hideOptions() {
        optionsContainer.style.display = 'none';
    }

    function renderSelected() {
        selectedContainer.innerHTML = '';

        selectedIds.forEach((id) => {
            const gt = availableGameTypes.find((item) => item.id === id);
            if (!gt) return;

            const tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.55rem;border-radius:20px;background:var(--primary);color:#fff;font-size:0.85rem;';
            tag.innerHTML = '<span>' + gt.name + '</span>'
                + '<input type="hidden" name="allowed_game_type_ids[]" value="' + gt.id + '">'
                + '<button type="button" class="gt-remove" data-id="' + gt.id + '" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem;line-height:1;">&times;</button>';
            selectedContainer.appendChild(tag);
        });
    }

    function renderOptions(items) {
        const filtered = items.filter((item) => !selectedIds.has(item.id));
        if (filtered.length === 0) {
            optionsContainer.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucun type disponible.</div>';
            optionsContainer.style.display = 'block';
            return;
        }

        optionsContainer.innerHTML = filtered.map((gt) => (
            '<div class="gt-option" data-id="' + gt.id + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:pointer;">'
            + '<strong>' + gt.name + '</strong> <span class="text-muted text-small">(' + gt.win_condition + ')</span>'
            + '</div>'
        )).join('');

        optionsContainer.querySelectorAll('.gt-option').forEach((el) => {
            el.addEventListener('click', () => {
                selectedIds.add(parseInt(el.dataset.id, 10));
                renderSelected();
                searchInput.value = '';
                renderOptions(availableGameTypes);
                searchInput.focus();
            });
        });

        optionsContainer.style.display = 'block';
    }

    selectedContainer.addEventListener('click', (event) => {
        const btn = event.target.closest('.gt-remove');
        if (!btn) return;
        selectedIds.delete(parseInt(btn.dataset.id, 10));
        renderSelected();
        renderOptions(availableGameTypes);
    });

    searchInput.addEventListener('focus', () => renderOptions(availableGameTypes));
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        const filtered = q === ''
            ? availableGameTypes
            : availableGameTypes.filter((gt) => gt.name.toLowerCase().includes(q));
        renderOptions(filtered);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-wrapper')) {
            hideOptions();
        }
    });

    form.addEventListener('submit', (event) => {
        if (selectedIds.size === 0) {
            event.preventDefault();
            alert('Veuillez sélectionner au moins un type de jeu autorisé.');
        }
    });
})();
</script>
