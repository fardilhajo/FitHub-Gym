/**
 * Navbar — Hamburger toggle + Ajax search
 */
(function () {
  'use strict';

  // ══════════════════════════════════════════════════════════════════════
  //  HAMBURGER TOGGLE
  // ══════════════════════════════════════════════════════════════════════

  const toggle = document.getElementById('nav-toggle');
  const menu   = document.getElementById('nav-menu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      const isOpen = menu.classList.toggle('active');
      toggle.classList.toggle('active');
      toggle.setAttribute('aria-expanded', isOpen);
    });
  }

  // ══════════════════════════════════════════════════════════════════════
  //  AJAX SEARCH (Fetch API)
  // ══════════════════════════════════════════════════════════════════════

  const searchInput   = document.getElementById('navbar-search');
  const searchResults = document.getElementById('search-results');

  if (!searchInput || !searchResults) return;

  let debounceTimer = null;

  searchInput.addEventListener('input', function () {
    const query = searchInput.value.trim();

    clearTimeout(debounceTimer);

    if (query.length < 2) {
      hideResults();
      return;
    }

    debounceTimer = setTimeout(function () {
      fetchResults(query);
    }, 300);
  });

  async function fetchResults(query) {
    try {
      const response = await fetch(
        'api/search.php?q=' + encodeURIComponent(query)
      );

      if (!response.ok) {
        hideResults();
        return;
      }

      const data = await response.json();
      renderResults(data);
    } catch (err) {
      console.error('Search error:', err);
      hideResults();
    }
  }

  function renderResults(items) {
    searchResults.innerHTML = '';

    if (!items || items.length === 0) {
      searchResults.innerHTML =
        '<p class="search-no-results">No classes found</p>';
      searchResults.classList.add('active');
      return;
    }

    items.forEach(function (item) {
      var link = document.createElement('a');
      link.href = 'book-class.php';
      link.className = 'search-result-item';
      link.innerHTML =
        '<span class="search-result-name">' + escapeHtml(item.Name) + '</span>' +
        '<span class="search-result-meta">' +
          escapeHtml(item.Type) + ' · ' +
          escapeHtml(item.Day || '') + ' · ' +
          escapeHtml(item.Time || '') +
          (item.TrainerName ? ' · ' + escapeHtml(item.TrainerName) : '') +
        '</span>';
      searchResults.appendChild(link);
    });

    searchResults.classList.add('active');
  }

  function hideResults() {
    searchResults.classList.remove('active');
    searchResults.innerHTML = '';
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function (e) {
    var wrapper = document.getElementById('nav-search-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      hideResults();
    }
  });

  // Helper: escape HTML to avoid XSS
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
  }
})();
