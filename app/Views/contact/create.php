<div class="page-header">
    <h1>📬 Nouveau ticket</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/contact/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="category" class="form-label">Catégorie</label>
                <select name="category" id="category" class="form-control" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject" class="form-label">Sujet</label>
                <input type="text" name="subject" id="subject" class="form-control" maxlength="255" required>
            </div>

            <div class="form-group">
                <label for="body" class="form-label">Message</label>
                <textarea name="body" id="body" class="form-control" rows="6" required></textarea>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">Envoyer</button>
                <a href="/spaces/<?= $currentSpace['id'] ?>/contact" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div>
</div>
