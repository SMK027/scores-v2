<div style="max-width:500px;margin:3rem auto;">
    <div class="card">
        <div class="card-header text-center">
            <h2>🏆 Connexion session</h2>
            <p class="text-muted text-small">Connectez-vous avec vos identifiants de session pour saisir les scores.</p>
        </div>
        <div class="card-body">
            <form method="POST" action="/competition/login">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="competition_id" class="form-label">ID de la compétition</label>
                    <input type="number" id="competition_id" name="competition_id" class="form-control"
                           required min="1" autofocus placeholder="ex: 1">
                </div>

                <div class="form-group">
                    <label for="session_number" class="form-label">Numéro de session</label>
                    <input type="number" id="session_number" name="session_number" class="form-control"
                           required min="1" placeholder="ex: 3">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" class="form-control"
                               required placeholder="Mot de passe" autocomplete="off" style="padding-right:3rem;">
                        <button type="button" onclick="var p=document.getElementById('password');if(p.type==='password'){p.type='text';this.textContent='🙈';}else{p.type='password';this.textContent='👁';}" style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;padding:0;">👁</button>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Se connecter</button>
                </div>
            </form>
        </div>
    </div>

    <p class="text-center text-muted text-small mt-2">
        <a href="/login">← Connexion utilisateur classique</a>
    </p>
</div>
