(function () {
  'use strict';

  const toggle = document.getElementById('nav-toggle');
  const menu = document.getElementById('nav-menu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      const isOpen = menu.classList.toggle('active');
      toggle.classList.toggle('active');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  const userBtn = document.getElementById('nav-user-btn');
  const userMenu = document.getElementById('nav-user-menu');

  if (userBtn && userMenu) {
    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = userMenu.classList.toggle('active');
      userBtn.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', function (e) {
      const dropdown = document.getElementById('nav-user-dropdown');
      if (dropdown && !dropdown.contains(e.target)) {
        userMenu.classList.remove('active');
        userBtn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        userMenu.classList.remove('active');
        userBtn.setAttribute('aria-expanded', 'false');
        userBtn.focus();
      }
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
