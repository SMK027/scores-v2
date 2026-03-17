<?php
/* Ressources FullCalendar chargées depuis jsDelivr */
$fcVersion = '6.1.15';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@<?= $fcVersion ?>/index.global.min.css">
<style>
.fc-ev-win    { --fc-event-bg-color:#22c55e; --fc-event-border-color:#16a34a; --fc-event-text-color:#fff; }
.fc-ev-loss   { --fc-event-bg-color:#ef4444; --fc-event-border-color:#b91c1c; --fc-event-text-color:#fff; }
.fc-ev-ongoing{ --fc-event-bg-color:#3b82f6; --fc-event-border-color:#1d4ed8; --fc-event-text-color:#fff; }
.fc-ev-paused { --fc-event-bg-color:#f59e0b; --fc-event-border-color:#b45309; --fc-event-text-color:#fff; }
.fc-ev-pending{ --fc-event-bg-color:#94a3b8; --fc-event-border-color:#64748b; --fc-event-text-color:#fff; }
.fc-ev-comp-player  { --fc-event-bg-color:#0ea5e9; --fc-event-border-color:#0369a1; --fc-event-text-color:#fff; }
.fc-ev-comp-referee{ --fc-event-bg-color:#10b981; --fc-event-border-color:#047857; --fc-event-text-color:#fff; }
.fc-ev-comp-both   { --fc-event-bg-color:#f97316; --fc-event-border-color:#c2410c; --fc-event-text-color:#fff; }
#fc-calendar  { min-height: 320px; }
.collapsible-toggle {
    margin-left: auto;
    white-space: nowrap;
}
.collapsible-content.is-collapsed {
    display: none;
}
@media (max-width:600px){
    .fc .fc-toolbar { flex-direction:column; gap:.4rem; }
    .fc .fc-toolbar-title { font-size:1rem; }
}
</style>

<div class="page-header">
    <div>
        <h1>Calendrier des parties</h1>
        <p class="page-description">Historique de vos parties avec filtres par espace, période, statut et type de jeu.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/profile/calendar" class="calendar-filters" id="filter-form">
            <div class="form-group">
                <label for="space_id" class="form-label">Espace</label>
                <select id="space_id" name="space_id" class="form-control">
                    <option value="">Tous les espaces</option>
                    <?php foreach ($spaces as $space): ?>
                        <option value="<?= (int) $space['id'] ?>" <?= ((int) ($filters['space_id'] ?? 0) === (int) $space['id']) ? 'selected' : '' ?>>
                            <?= e($space['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="period" class="form-label">Période (liste)</label>
                <select id="period" name="period" class="form-control">
                    <option value="7d"    <?= ($filters['period'] ?? '') === '7d'     ? 'selected' : '' ?>>7 derniers jours</option>
                    <option value="30d"   <?= ($filters['period'] ?? '') === '30d'    ? 'selected' : '' ?>>30 derniers jours</option>
                    <option value="90d"   <?= ($filters['period'] ?? '') === '90d'    ? 'selected' : '' ?>>90 derniers jours</option>
                    <option value="365d"  <?= ($filters['period'] ?? '') === '365d'   ? 'selected' : '' ?>>12 derniers mois</option>
                    <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : '' ?>>Plage personnalisée</option>
                    <option value="all"   <?= ($filters['period'] ?? '') === 'all'    ? 'selected' : '' ?>>Tout l'historique</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Statut</label>
                <select id="status" name="status" class="form-control">
                    <option value="">Tous</option>
                    <option value="pending"     <?= ($filters['status'] ?? '') === 'pending'     ? 'selected' : '' ?>>En attente</option>
                    <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                    <option value="paused"      <?= ($filters['status'] ?? '') === 'paused'      ? 'selected' : '' ?>>En pause</option>
                    <option value="completed"   <?= ($filters['status'] ?? '') === 'completed'   ? 'selected' : '' ?>>Terminée</option>
                </select>
            </div>

            <div class="form-group">
                <label for="game_type_id" class="form-label">Type de jeu</label>
                <select id="game_type_id" name="game_type_id" class="form-control">
                    <option value="">Tous les types</option>
                    <?php foreach ($gameTypes as $gt): ?>
                        <option value="<?= (int) $gt['id'] ?>" <?= ((int) ($filters['game_type_id'] ?? 0) === (int) $gt['id']) ? 'selected' : '' ?>>
                            <?= e($gt['name']) ?><?= empty($filters['space_id']) ? ' – ' . e($gt['space_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="from" class="form-label">Du</label>
                <input id="from" type="date" name="from" class="form-control" value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="to" class="form-label">Au</label>
                <input id="to" type="date" name="to" class="form-control" value="<?= e($filters['to'] ?? '') ?>">
            </div>

            <div class="calendar-filter-actions">
                <button type="submit" class="btn btn-primary">Appliquer</button>
                <a href="/profile/calendar" class="btn btn-outline">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Vue calendrier</h3>
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;flex-wrap:wrap;">
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="width:10px;height:10px;border-radius:2px;background:#22c55e;display:inline-block;"></span>Victoire</span>
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="width:10px;height:10px;border-radius:2px;background:#ef4444;display:inline-block;"></span>Défaite</span>
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="width:10px;height:10px;border-radius:2px;background:#3b82f6;display:inline-block;"></span>En cours</span>
            <span style="display:inline-flex;align-items:center;gap:.3rem;"><span style="width:10px;height:10px;border-radius:2px;background:#f59e0b;display:inline-block;"></span>En pause</span>
        </div>
        <button
            type="button"
            class="btn btn-sm btn-outline collapsible-toggle"
            data-target="calendar-section-content"
            data-label-expand="Afficher"
            data-label-collapse="Replier"
            aria-expanded="true"
        >Replier</button>
    </div>
    <div class="card-body collapsible-content" id="calendar-section-content" style="padding:.75rem;">
        <div id="fc-calendar"></div>
    </div>
</div>

<?php
$queryBase = [
    'space_id'     => $filters['space_id'] ?? '',
    'status'       => $filters['status'] ?? '',
    'game_type_id' => $filters['game_type_id'] ?? '',
    'period'       => $filters['period'] ?? '30d',
    'from'         => $filters['from'] ?? '',
    'to'           => $filters['to'] ?? '',
];
?>
<div class="card">
    <div class="card-header">
        <h3>Historique des parties</h3>
        <span class="badge badge-primary"><?= (int) $history['total'] ?> résultat<?= ((int) $history['total'] > 1) ? 's' : '' ?></span>
        <button
            type="button"
            class="btn btn-sm btn-outline collapsible-toggle"
            data-target="history-section-content"
            data-label-expand="Afficher"
            data-label-collapse="Replier"
            aria-expanded="true"
        >Replier</button>
    </div>
    <div class="card-body collapsible-content" id="history-section-content">
        <?php if (empty($history['data'])): ?>
            <p class="text-muted">Aucune partie ne correspond aux filtres actuels.</p>
        <?php else: ?>
            <div class="table-responsive calendar-history-table">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Espace</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Participants</th>
                            <th>Mon résultat</th>
                            <th>Gagnant(s)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history['data'] as $game): ?>
                            <tr>
                                <td>
                                    <div class="font-bold"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'd/m/Y')) ?></div>
                                    <div class="text-small text-muted"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'H:i')) ?></div>
                                </td>
                                <td><?= e($game['space_name']) ?></td>
                                <td><?= e($game['game_type_name']) ?></td>
                                <td><span class="badge <?= e(game_status_class($game['status'])) ?>"><?= e(game_status_label($game['status'])) ?></span></td>
                                <td><?= (int) $game['player_count'] ?></td>
                                <td>
                                    <?php if ($game['my_rank'] !== null): ?>
                                        <span class="font-bold">#<?= (int) $game['my_rank'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <span class="text-small text-muted">(<?= e($game['my_player_name']) ?>)</span>
                                    <?php if ((int) $game['my_is_winner'] === 1): ?>
                                        <span class="badge badge-success">Victoire</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($game['winner_names'] ?? '-') ?></td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline" href="/spaces/<?= (int) $game['space_id'] ?>/games/<?= (int) $game['id'] ?>">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="calendar-history-cards">
                <?php foreach ($history['data'] as $game): ?>
                    <article class="calendar-history-card">
                        <div class="calendar-history-card-head">
                            <div>
                                <div class="font-bold"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'd/m/Y H:i')) ?></div>
                                <div class="text-small text-muted"><?= e($game['space_name']) ?> – <?= e($game['game_type_name']) ?></div>
                            </div>
                            <span class="badge <?= e(game_status_class($game['status'])) ?>"><?= e(game_status_label($game['status'])) ?></span>
                        </div>
                        <div class="calendar-history-card-body">
                            <p><strong>Participants :</strong> <?= (int) $game['player_count'] ?></p>
                            <p>
                                <strong>Mon résultat :</strong>
                                <?php if ($game['my_rank'] !== null): ?>
                                    #<?= (int) $game['my_rank'] ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                                <?php if ((int) $game['my_is_winner'] === 1): ?>
                                    <span class="badge badge-success">Victoire</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Gagnant(s) :</strong> <?= e($game['winner_names'] ?? '-') ?></p>
                        </div>
                        <a class="btn btn-sm btn-outline" href="/spaces/<?= (int) $game['space_id'] ?>/games/<?= (int) $game['id'] ?>">Ouvrir la partie</a>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($history['lastPage'] ?? 1) > 1): ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= (int) $history['lastPage']; $p++): ?>
                        <?php $qs = http_build_query(array_merge($queryBase, ['page' => $p])); ?>
                        <?php if ($p === (int) $history['page']): ?>
                            <span class="active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="/profile/calendar?<?= e($qs) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@<?= $fcVersion ?>/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@<?= $fcVersion ?>/locales/fr.global.js"></script>
<script>
(function () {
    var calendarEl = document.getElementById('fc-calendar');
    var calendar = null;

    function bindCollapsibles() {
        var toggles = document.querySelectorAll('.collapsible-toggle');
        toggles.forEach(function (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var targetId = toggleBtn.getAttribute('data-target');
                var target = targetId ? document.getElementById(targetId) : null;
                if (!target) return;

                var isCollapsed = target.classList.toggle('is-collapsed');
                toggleBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                var expandLabel = toggleBtn.getAttribute('data-label-expand') || 'Afficher';
                var collapseLabel = toggleBtn.getAttribute('data-label-collapse') || 'Replier';
                toggleBtn.textContent = isCollapsed ? expandLabel : collapseLabel;

                if (!isCollapsed && targetId === 'calendar-section-content' && calendar) {
                    calendar.updateSize();
                }
            });
        });
    }

    bindCollapsibles();

    if (!calendarEl) return;

    var filterParams = new URLSearchParams();
    <?php if (!empty($filters['space_id'])): ?>
    filterParams.set('space_id', '<?= (int) $filters['space_id'] ?>');
    <?php endif; ?>
    <?php if (!empty($filters['status'])): ?>
    filterParams.set('status', '<?= e($filters['status']) ?>');
    <?php endif; ?>
    <?php if (!empty($filters['game_type_id'])): ?>
    filterParams.set('game_type_id', '<?= (int) $filters['game_type_id'] ?>');
    <?php endif; ?>

    var isMobile = window.innerWidth < 700;

    function getToolbar(mobile) {
        return mobile
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };
    }

    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'fr',
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        headerToolbar: getToolbar(isMobile),
        buttonText: { today: "Aujourd'hui", month: 'Mois', week: 'Semaine', list: 'Liste' },
        views: {
            timeGridWeek: {
                allDaySlot: true,
                slotMinTime: '06:00:00',
                slotMaxTime: '23:00:00',
                expandRows: true
            }
        },
        eventOrder: 'start,-duration,allDay,title',
        displayEventEnd: true,
        height: 'auto',
        events: {
            url: '/profile/calendar/events?' + filterParams.toString(),
            failure: function () {
                calendarEl.insertAdjacentHTML(
                    'beforebegin',
                    '<p class="text-center" style="color:red;">Erreur lors du chargement des événements.</p>'
                );
            }
        },
        eventClick: function (info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function (info) {
            var p = info.event.extendedProps;
            var parts = [p.space, p.status];
            if (p.entry_type === 'competition') {
                parts.push('Rôle: ' + p.role);
            } else {
                if (p.rank) parts.push('#' + p.rank);
                parts.push(p.player_count + ' joueur(s)');
            }
            info.el.title = parts.join(' · ');
        },
        windowResize: function () {
            var mobile = window.innerWidth < 700;
            calendar.changeView(mobile ? 'listMonth' : 'dayGridMonth');
            calendar.setOption('headerToolbar', getToolbar(mobile));
        },
        noEventsContent: 'Aucune partie sur cette période.'
    });

    calendar.render();
})();
</script>
