<div class="page-header">
    <h1>🚫 Bannir un compte</h1>
    <a href="/admin/bans/users" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/bans/users/create" id="banForm">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="user_id" value="">

            <div class="form-group" style="position:relative;">
                <label for="user_search" class="form-label">Utilisateur à bannir</label>
                <input type="text" id="user_search" class="form-control" autocomplete="off"
                       placeholder="Rechercher par nom ou email…" required>
                <div id="autocompleteResults" style="position:absolute;z-index:100;width:100%;max-height:260px;overflow-y:auto;background:var(--bg-card,#16213e);border:1px solid var(--border,#2a3a5c);border-radius:0 0 8px 8px;display:none;"></div>
                <span class="form-hint" id="selectedHint" style="display:none;color:var(--success,#4ecca3);"></span>
            </div>

            <div class="form-group">
                <label for="reason" class="form-label">Raison du bannissement</label>
                <textarea id="reason" name="reason" class="form-control" rows="3"
                          placeholder="Expliquez la raison du bannissement..." required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Type de bannissement</label>
                <div class="d-flex gap-2 flex-wrap">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="radio" name="duration_type" value="temporary" checked
                               onchange="document.getElementById('durationFields').style.display='flex'">
                        Temporaire
                    </label>
                    <?php if (in_array(current_global_role(), ['admin', 'superadmin'])): ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                            <input type="radio" name="duration_type" value="permanent"
                                   onchange="document.getElementById('durationFields').style.display='none'">
                            Permanent
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div id="durationFields" class="d-flex gap-1 flex-wrap align-center" style="display:flex;">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="duration_value" class="form-label">Durée</label>
                    <input type="number" id="duration_value" name="duration_value" class="form-control"
                           min="1" value="24" style="width:100px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="duration_unit" class="form-label">Unité</label>
                    <select id="duration_unit" name="duration_unit" class="form-control" style="width:auto;">
                        <option value="minutes">Minute(s)</option>
                        <option value="hours" selected>Heure(s)</option>
                        <option value="days">Jour(s)</option>
                        <option value="weeks">Semaine(s)</option>
                        <option value="months">Mois</option>
                    </select>
                </div>
            </div>

            <div class="form-group mt-2">
                <button type="submit" class="btn btn-danger" data-confirm="Confirmer le bannissement ?">🚫 Bannir le compte</button>
            </div>
        </form>
    </div>
</div>

<style>
    .ac-item{padding:.6rem .8rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border,#2a3a5c);transition:background .15s;}
    .ac-item:hover,.ac-item.active{background:var(--bg-hover,rgba(78,204,163,.12));}
    .ac-item:last-child{border-bottom:none;}
    .ac-name{font-weight:600;}
    .ac-email{font-size:.85em;opacity:.7;}
    .ac-role{font-size:.75em;padding:2px 8px;border-radius:4px;background:var(--bg-body,#0f3460);color:var(--text-muted,#aaa);}
    .ac-role.admin{background:#e94560;color:#fff;}
    .ac-role.moderator{background:#e98b45;color:#fff;}
    .ac-empty{padding:.8rem;text-align:center;opacity:.6;}
</style>

<script>
(function(){
    const input = document.getElementById('user_search');
    const hidden = document.getElementById('user_id');
    const results = document.getElementById('autocompleteResults');
    const hint = document.getElementById('selectedHint');
    const form = document.getElementById('banForm');
    let debounce = null;
    let activeIdx = -1;

    const roleBadge = (role) => {
        if(role === 'admin') return '<span class="ac-role admin">Admin</span>';
        if(role === 'moderator') return '<span class="ac-role moderator">Modérateur</span>';
        return '<span class="ac-role">Utilisateur</span>';
    };

    input.addEventListener('input', function(){
        clearTimeout(debounce);
        hidden.value = '';
        hint.style.display = 'none';
        activeIdx = -1;

        const q = this.value.trim();
        if(q.length < 2){ results.style.display='none'; return; }

        debounce = setTimeout(()=>{
            fetch('/admin/bans/users/search?q='+encodeURIComponent(q))
                .then(r=>r.json())
                .then(data=>{
                    results.innerHTML='';
                    if(!data.results || data.results.length===0){
                        results.innerHTML='<div class="ac-empty">Aucun utilisateur trouvé</div>';
                        results.style.display='block';
                        return;
                    }
                    data.results.forEach((u,i)=>{
                        const div=document.createElement('div');
                        div.className='ac-item';
                        div.dataset.index=i;
                        div.innerHTML=`<div><span class="ac-name">${esc(u.username)}</span> <span class="ac-email">${esc(u.email)}</span></div>${roleBadge(u.global_role)}`;
                        div.addEventListener('click',()=>selectUser(u));
                        results.appendChild(div);
                    });
                    results.style.display='block';
                });
        }, 250);
    });

    input.addEventListener('keydown', function(e){
        const items = results.querySelectorAll('.ac-item');
        if(!items.length) return;
        if(e.key==='ArrowDown'){ e.preventDefault(); activeIdx=Math.min(activeIdx+1,items.length-1); highlight(items); }
        else if(e.key==='ArrowUp'){ e.preventDefault(); activeIdx=Math.max(activeIdx-1,0); highlight(items); }
        else if(e.key==='Enter' && activeIdx>=0){ e.preventDefault(); items[activeIdx].click(); }
        else if(e.key==='Escape'){ results.style.display='none'; }
    });

    function highlight(items){
        items.forEach((el,i)=>el.classList.toggle('active',i===activeIdx));
        if(items[activeIdx]) items[activeIdx].scrollIntoView({block:'nearest'});
    }

    function selectUser(u){
        hidden.value=u.id;
        input.value=u.username+' ('+u.email+')';
        hint.textContent='✓ '+u.username+' sélectionné (ID: '+u.id+')';
        hint.style.display='block';
        results.style.display='none';
    }

    document.addEventListener('click',function(e){
        if(!input.contains(e.target)&&!results.contains(e.target)) results.style.display='none';
    });

    form.addEventListener('submit',function(e){
        if(!hidden.value){
            e.preventDefault();
            input.focus();
            input.style.borderColor='var(--danger,#e94560)';
            setTimeout(()=>input.style.borderColor='',2000);
        }
    });

    function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
})();
</script>
