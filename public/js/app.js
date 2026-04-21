/**
 * Scores - JavaScript principal
 * Gère les interactions dynamiques de l'application
 */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // Navigation mobile (hamburger menu)
    // =========================================================
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarMenu = document.getElementById('navbarMenu');

    if (navbarToggle && navbarMenu) {
        navbarToggle.addEventListener('click', function () {
            navbarMenu.classList.toggle('open');
            // Animation du bouton hamburger
            navbarToggle.classList.toggle('active');
        });

        // Fermer le menu en cliquant en dehors
        document.addEventListener('click', function (e) {
            if (!navbarToggle.contains(e.target) && !navbarMenu.contains(e.target)) {
                navbarMenu.classList.remove('open');
                navbarToggle.classList.remove('active');
            }
        });
    }

    // =========================================================
    // Sidebar mobile toggle
    // =========================================================
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        // Créer le bouton de toggle pour la sidebar sur mobile
        const sidebarToggle = document.createElement('button');
        sidebarToggle.className = 'sidebar-toggle-btn';
        sidebarToggle.innerHTML = '☰ Menu';
        sidebarToggle.style.cssText = `
            display: none;
            position: fixed;
            bottom: 1rem;
            left: 1rem;
            z-index: 1400;
            padding: 0.625rem 1.25rem;
            background: var(--primary, #4361ee);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.4);
        `;
        document.body.appendChild(sidebarToggle);

        // Afficher le bouton uniquement sur mobile
        function checkSidebarToggle() {
            if (window.innerWidth <= 992) {
                sidebarToggle.style.display = 'block';
            } else {
                sidebarToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }
        checkSidebarToggle();
        window.addEventListener('resize', checkSidebarToggle);

        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        // Fermer sidebar en cliquant en dehors
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 992 &&
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // =========================================================
    // Auto-fermeture des alertes flash
    // =========================================================
    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // =========================================================
    // Confirmations de suppression
    // =========================================================
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir effectuer cette action ?';

            // Créer le modal de confirmation
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay active';
            overlay.innerHTML = `
                <div class="modal">
                    <h3>Confirmation</h3>
                    <p>${message}</p>
                    <div class="btn-group">
                        <button class="btn btn-outline modal-cancel">Annuler</button>
                        <button class="btn btn-danger modal-confirm">Confirmer</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            // Si c'est un lien ou un bouton dans un formulaire
            e.preventDefault();

            const form = this.closest('form');

            overlay.querySelector('.modal-cancel').addEventListener('click', function () {
                overlay.remove();
            });

            overlay.querySelector('.modal-confirm').addEventListener('click', function () {
                overlay.remove();
                if (form) {
                    form.submit();
                } else if (el.tagName === 'A') {
                    window.location.href = el.href;
                }
            });

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    overlay.remove();
                }
            });
        });
    });

    // =========================================================
    // Formulaire de scores dynamiques (AJAX)
    // =========================================================
    document.querySelectorAll('.score-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const url = this.action;
            const formData = new FormData(this);

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    showToast(data.message || 'Scores mis à jour', 'success');
                    // Mettre à jour les totaux si fournis
                    if (data.totals) {
                        Object.keys(data.totals).forEach(function (playerId) {
                            const totalEl = document.getElementById('total-' + playerId);
                            if (totalEl) {
                                totalEl.textContent = data.totals[playerId];
                            }
                        });
                    }
                    // Recharger la page après 1 seconde pour afficher les changements
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Erreur lors de la mise à jour', 'danger');
                }
            })
            .catch(function (error) {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'danger');
            });
        });
    });

    // =========================================================
    // Champs de joueurs dynamiques (ajout de parties)
    // =========================================================
    const addPlayerBtn = document.getElementById('addPlayerField');
    const playerFieldsContainer = document.getElementById('playerFields');

    if (addPlayerBtn && playerFieldsContainer) {
        addPlayerBtn.addEventListener('click', function () {
            const template = playerFieldsContainer.querySelector('.player-field');
            if (template) {
                const clone = template.cloneNode(true);
                // Réinitialiser les valeurs
                clone.querySelectorAll('select, input').forEach(function (input) {
                    input.value = '';
                });
                playerFieldsContainer.appendChild(clone);
                updateRemoveButtons();
            }
        });

        // Délégation d'événement pour les boutons de suppression
        playerFieldsContainer.addEventListener('click', function (e) {
            if (e.target.closest('.remove-player')) {
                const fields = playerFieldsContainer.querySelectorAll('.player-field');
                if (fields.length > 1) {
                    e.target.closest('.player-field').remove();
                    updateRemoveButtons();
                }
            }
        });

        function updateRemoveButtons() {
            const fields = playerFieldsContainer.querySelectorAll('.player-field');
            fields.forEach(function (field) {
                const removeBtn = field.querySelector('.remove-player');
                if (removeBtn) {
                    removeBtn.style.display = fields.length > 1 ? 'inline-flex' : 'none';
                }
            });
        }
        updateRemoveButtons();
    }

    // =========================================================
    // Copier le lien d'invitation
    // =========================================================
    const copyInviteBtn = document.getElementById('copyInviteLink');
    if (copyInviteBtn) {
        copyInviteBtn.addEventListener('click', function () {
            const input = document.getElementById('inviteLink');
            if (input) {
                input.select();
                navigator.clipboard.writeText(input.value).then(function () {
                    showToast('Lien copié !', 'success');
                }).catch(function () {
                    // Fallback
                    document.execCommand('copy');
                    showToast('Lien copié !', 'success');
                });
            }
        });
    }

    // =========================================================
    // Recherche dynamique avec debounce
    // =========================================================
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value;
            debounceTimer = setTimeout(function () {
                if (query.length >= 2) {
                    // Soumettre le formulaire de recherche
                    searchInput.closest('form').submit();
                }
            }, 500);
        });
    }

    // =========================================================
    // Toast notification helper
    // =========================================================
    window.showToast = function (message, type) {
        type = type || 'info';
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(function () {
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 3000);
    };

    // =========================================================
    // Prévisualisation d'avatar
    // =========================================================
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    showToast('L\'image ne doit pas dépasser 5 Mo', 'danger');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreview.src = e.target.result;
                    avatarPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

// --- Password toggle visibility ---
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-toggle-password');
        if (!btn) return;

        var targetId = btn.getAttribute('data-target');
        var input = document.getElementById(targetId);
        if (!input) return;

        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            btn.classList.add('active');
            btn.title = 'Masquer le mot de passe';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
            btn.classList.remove('active');
            btn.title = 'Afficher le mot de passe';
        }
    });
})();

// =========================================================
// Notifications in-app — polling + dropdown
// =========================================================
(function () {
    var bell = document.getElementById('notifBell');
    if (!bell) return; // Non connecté

    var bellBtn   = document.getElementById('notifBellBtn');
    var badge     = document.getElementById('notifBadge');
    var dropdown  = document.getElementById('notifDropdown');
    var list      = document.getElementById('notifList');
    var markAllBtn = document.getElementById('notifMarkAll');

    var isOpen    = false;
    var lastMaxId = 0;
    var csrfMeta  = document.querySelector('meta[name="csrf-token"]');

    function getCsrf() {
        return csrfMeta ? csrfMeta.content : '';
    }

    // --- Ouvrir / fermer le dropdown ---
    bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        isOpen = !isOpen;
        dropdown.hidden = !isOpen;
        bellBtn.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
            loadDropdown();
        }
    });

    document.addEventListener('click', function (e) {
        if (!bell.contains(e.target) && isOpen) {
            isOpen = false;
            dropdown.hidden = true;
            bellBtn.setAttribute('aria-expanded', 'false');
        }
    });

    // --- Tout marquer comme lu ---
    markAllBtn.addEventListener('click', function () {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(getCsrf()),
        }).then(function () {
            list.querySelectorAll('.notif-item.unread').forEach(function (el) {
                el.classList.remove('unread');
            });
            updateBadge(0);
        }).catch(function () {});
    });

    // --- Charger le dropdown ---
    function loadDropdown() {
        fetch('/notifications/poll')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateBadge(data.unread_count || 0);
                renderList(data.notifications || []);
                if (data.max_id > lastMaxId) { lastMaxId = data.max_id; }
            })
            .catch(function () {
                list.innerHTML = '<p class="notif-empty">Impossible de charger les notifications.</p>';
            });
    }

    // --- Polling en arrière-plan (badge + toasts) ---
    function pollBackground() {
        var url = '/notifications/poll' + (lastMaxId > 0 ? '?since=' + lastMaxId : '');
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateBadge(data.unread_count || 0);
                // Toasts pour les nouvelles notifications
                if (data.new_items && data.new_items.length > 0) {
                    data.new_items.forEach(function (n) {
                        showToast(n.title + ' — ' + n.message, 'info');
                    });
                }
                if (data.max_id > lastMaxId) { lastMaxId = data.max_id; }
                // Rafraîchir le dropdown s'il est ouvert
                if (isOpen && data.new_items && data.new_items.length > 0) {
                    loadDropdown();
                }
            })
            .catch(function () {});
    }

    // --- Mise à jour du badge ---
    function updateBadge(count) {
        var n = parseInt(count, 10) || 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.hidden = (n === 0);
    }

    // --- Rendu de la liste ---
    function renderList(notifications) {
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<p class="notif-empty">Aucune notification.</p>';
            return;
        }
        var html = '';
        notifications.forEach(function (n) {
            var unread = parseInt(n.is_read, 10) === 0;
            var cls    = 'notif-item' + (unread ? ' unread' : '');
            var icon   = notifIcon(n.type);
            var time   = timeAgo(n.created_at);
            var tag    = n.url ? 'a' : 'div';
            var href   = n.url ? ' href="' + escHtml(n.url) + '"' : '';
            html += '<' + tag + ' class="' + cls + '" data-id="' + n.id + '"' + href + '>';
            html += '<span class="notif-icon">' + icon + '</span>';
            html += '<div class="notif-content">';
            html += '<div class="notif-title">' + escHtml(n.title) + '</div>';
            html += '<div class="notif-msg">' + escHtml(n.message) + '</div>';
            html += '<div class="notif-time">' + time + '</div>';
            html += '</div>';
            html += '</' + tag + '>';
        });
        list.innerHTML = html;

        // Marquer comme lu au clic
        list.querySelectorAll('.notif-item[data-id]').forEach(function (el) {
            el.addEventListener('click', function () {
                var id = this.dataset.id;
                if (this.classList.contains('unread')) {
                    this.classList.remove('unread');
                    fetch('/notifications/' + id + '/read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'csrf_token=' + encodeURIComponent(getCsrf()),
                    }).then(function () {
                        var unreadLeft = list.querySelectorAll('.notif-item.unread').length;
                        updateBadge(unreadLeft);
                    }).catch(function () {});
                }
            });
        });
    }

    // --- Icône selon le type ---
    function notifIcon(type) {
        var icons = {
            'lobby.join':            '🎮',
            'lobby.invite':          '📩',
            'lobby.invite_accepted': '✅',
            'lobby.launch':          '🚀',
            'space.invite':          '🏠',
            'space.invite_accepted': '✅',
            'space.invite_declined': '❌',
        };
        return icons[type] || '🔔';
    }

    // --- Utilitaires ---
    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function timeAgo(dateStr) {
        if (!dateStr) { return ''; }
        var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)    { return "à l'instant"; }
        if (diff < 3600)  { return 'il y a ' + Math.floor(diff / 60) + ' min'; }
        if (diff < 86400) { return 'il y a ' + Math.floor(diff / 3600) + ' h'; }
        return 'il y a ' + Math.floor(diff / 86400) + ' j';
    }

    // --- Démarrage ---
    setTimeout(pollBackground, 1200);
    setInterval(pollBackground, 15000);
})();