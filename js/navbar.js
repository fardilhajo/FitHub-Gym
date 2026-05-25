(function () {
  'use strict';

  // ── Notification bell ────────────────────────────────────────────────────

  const bellBtn      = document.getElementById('nav-bell-btn');
  const bellBadge    = document.getElementById('bell-badge');
  const bellWrapper  = document.getElementById('nav-bell-wrapper');
  const dropdown     = document.getElementById('notif-dropdown');
  const notifList    = document.getElementById('notif-list');
  const readAllBtn   = document.getElementById('notif-read-all');

  if (bellBtn && dropdown) {
    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.toggle('notif-dropdown--open');
      dropdown.setAttribute('aria-hidden', String(!isOpen));
      if (isOpen) loadNotifications();
    });

    document.addEventListener('click', function (e) {
      if (bellWrapper && !bellWrapper.contains(e.target)) {
        dropdown.classList.remove('notif-dropdown--open');
        dropdown.setAttribute('aria-hidden', 'true');
      }
    });

    if (readAllBtn) {
      readAllBtn.addEventListener('click', async function () {
        await fetch('api/notifications.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'action=read_all'
        });
        hideBadge();
        notifList.querySelectorAll('.notif-item--unread').forEach(el => el.classList.remove('notif-item--unread'));
        notifList.querySelectorAll('.notif-title').forEach(el => el.style.fontWeight = '');
      });
    }
  }

  async function loadNotifications() {
    try {
      const res = await fetch('api/notifications.php', { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const data = await res.json();
      renderNotifications(data.items || []);
      updateBadge(data.unread || 0);
    } catch (err) {
      console.error('Notifications error:', err);
    }
  }

  function renderNotifications(items) {
    if (!notifList) return;
    notifList.innerHTML = '';

    if (!items.length) {
      notifList.innerHTML = '<li class="notif-empty">No notifications yet.</li>';
      return;
    }

    items.forEach(function (n) {
      const li = document.createElement('li');
      li.className = 'notif-item' + (n.IsRead === '0' || n.IsRead === 0 ? ' notif-item--unread' : '');
      li.dataset.id = n.NotificationID;

      const title = document.createElement('p');
      title.className = 'notif-title';
      title.textContent = n.Title;

      const msg = document.createElement('p');
      msg.className = 'notif-msg';
      msg.textContent = n.Message;

      const time = document.createElement('p');
      time.className = 'notif-time';
      time.textContent = formatDate(n.CreatedAt);

      li.append(title, msg, time);

      li.addEventListener('click', async function () {
        if (li.classList.contains('notif-item--unread')) {
          li.classList.remove('notif-item--unread');
          await fetch('api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=read&id=' + encodeURIComponent(n.NotificationID)
          });
          const remaining = notifList.querySelectorAll('.notif-item--unread').length;
          updateBadge(remaining);
        }
      });

      notifList.appendChild(li);
    });
  }

  function updateBadge(count) {
    if (!bellBadge) return;
    if (count > 0) {
      bellBadge.textContent = count > 99 ? '99+' : String(count);
      bellBadge.classList.remove('bell-badge--hidden');
    } else {
      hideBadge();
    }
  }

  function hideBadge() {
    if (bellBadge) {
      bellBadge.textContent = '';
      bellBadge.classList.add('bell-badge--hidden');
    }
  }

  function formatDate(str) {
    if (!str) return '';
    const d = new Date(str.replace(' ', 'T') + 'Z');
    if (isNaN(d)) return str;
    return d.toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  // ── Mobile nav toggle ─────────────────────────────────────────────────────

  const toggle = document.getElementById('nav-toggle');
  const menu = document.getElementById('nav-menu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      const isOpen = menu.classList.toggle('active');
      toggle.classList.toggle('active');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  const searchInput = document.getElementById('navbar-search');
  const searchResults = document.getElementById('search-results');
  let debounceTimer = null;

  if (searchInput && searchResults) {
    searchInput.addEventListener('input', function () {
      const query = searchInput.value.trim();
      clearTimeout(debounceTimer);

      if (query.length < 2) {
        hideResults();
        return;
      }

      debounceTimer = setTimeout(function () {
        fetchResults(query);
      }, 250);
    });

    document.addEventListener('click', function (event) {
      const wrapper = document.getElementById('nav-search-wrapper');
      if (wrapper && !wrapper.contains(event.target)) {
        hideResults();
      }
    });
  }

  async function fetchResults(query) {
    try {
      const response = await fetch('api/search.php?q=' + encodeURIComponent(query), {
        headers: { Accept: 'application/json' }
      });

      if (!response.ok) {
        hideResults();
        return;
      }

      renderResults(await response.json());
    } catch (err) {
      console.error('Search error:', err);
      hideResults();
    }
  }

  function renderResults(items) {
    searchResults.innerHTML = '';

    if (!items || items.length === 0) {
      searchResults.innerHTML = '<p class="search-no-results">No classes found</p>';
      searchResults.classList.add('active');
      return;
    }

    items.forEach(function (item) {
      const link = document.createElement('a');
      link.href = item.Url || 'book-class.php?q=' + encodeURIComponent(item.Name || '');
      link.className = 'search-result-item';

      const name = document.createElement('span');
      name.className = 'search-result-name';
      name.textContent = item.Name || '';

      const meta = document.createElement('span');
      meta.className = 'search-result-meta';
      meta.textContent = [item.Type, item.Day, item.StartTime, item.TrainerName].filter(Boolean).join(' · ');

      link.append(name, meta);
      searchResults.appendChild(link);
    });

    searchResults.classList.add('active');
  }

  function hideResults() {
    if (!searchResults) return;
    searchResults.classList.remove('active');
    searchResults.innerHTML = '';
  }

  const equipmentList = document.getElementById('equipment-list');
  const equipmentStats = document.getElementById('equipment-stats');

  if (equipmentList && equipmentStats) {
    refreshEquipment();
    setInterval(refreshEquipment, 30000);
  }

  async function refreshEquipment() {
    try {
      const response = await fetch('api/equipment.php', { headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const data = await response.json();

      Object.entries(data.stats || {}).forEach(function ([key, value]) {
        const target = equipmentStats.querySelector('[data-stat="' + key + '"]');
        if (target) target.textContent = value;
      });

      if (Array.isArray(data.items)) {
        equipmentList.innerHTML = '';
        data.items.forEach(function (item) {
          const article = document.createElement('article');
          article.className = 'equipment-item';

          const info = document.createElement('div');
          const title = document.createElement('h3');
          title.textContent = item.Name;
          const meta = document.createElement('p');
          meta.textContent = item.Category + ' · ' + item.Location;
          info.append(title, meta);

          const pill = document.createElement('span');
          pill.className = 'equipment-pill equipment-pill--' + String(item.Status).toLowerCase().replaceAll(' ', '-');
          pill.textContent = item.Status;

          article.append(info, pill);
          equipmentList.appendChild(article);
        });
      }
    } catch (err) {
      console.error('Equipment refresh error:', err);
    }
  }
})();
