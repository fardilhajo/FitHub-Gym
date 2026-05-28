(function () {
  'use strict';

  var enhancedPages = ['book-class.php', 'equipment.php'];
  var pageName = (window.location.pathname.split('/').pop() || 'index.php');

  if (enhancedPages.indexOf(pageName) === -1) {
    return;
  }

  function isSameOrigin(url) {
    return url.origin === window.location.origin;
  }

  function isEnhancedPage(url) {
    var targetPage = url.pathname.split('/').pop() || 'index.php';
    return enhancedPages.indexOf(targetPage) !== -1;
  }

  function shouldEnhanceForm(form) {
    if (!form || form.hasAttribute('data-no-ajax')) return false;
    if (!form.closest('main.content')) return false;

    var method = (form.getAttribute('method') || 'get').toLowerCase();
    var action = new URL(form.getAttribute('action') || window.location.href, window.location.href);

    if (!isSameOrigin(action) || !isEnhancedPage(action)) return false;

    return method === 'post' || form.classList.contains('filter-controls');
  }

  function shouldEnhanceLink(link) {
    if (!link || !link.matches('[data-ajax-link]')) return false;
    var url = new URL(link.getAttribute('href'), window.location.href);
    return isSameOrigin(url) && isEnhancedPage(url) && link.closest('main.content');
  }

  function buildGetUrl(form) {
    var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    var data = new FormData(form);
    url.search = '';

    data.forEach(function (value, key) {
      if (typeof value === 'string' && value.trim() === '') return;
      if (key === 'return_to' || key === 'csrf_token') return;
      url.searchParams.append(key, value);
    });

    return url;
  }

  function addReturnTo(data) {
    if (!data.has('return_to')) {
      data.append('return_to', pageName + window.location.search);
    }
    return data;
  }

  function setSubmitting(form, submitter, busy) {
    form.classList.toggle('is-submitting', busy);

    var button = submitter && submitter.matches('button, input[type="submit"]')
      ? submitter
      : form.querySelector('button[type="submit"], input[type="submit"]');

    if (!button) return;

    if (busy) {
      button.dataset.originalLabel = button.tagName === 'INPUT' ? button.value : button.textContent;
      button.disabled = true;
      if (button.tagName === 'INPUT') button.value = 'Working...';
      else button.textContent = 'Working...';
    } else {
      button.disabled = false;
      if (button.dataset.originalLabel) {
        if (button.tagName === 'INPUT') button.value = button.dataset.originalLabel;
        else button.textContent = button.dataset.originalLabel;
        delete button.dataset.originalLabel;
      }
    }
  }

  function injectMessage(message, kind) {
    var main = document.querySelector('main.content');
    if (!main) return;

    var existing = main.querySelector('.page-message');
    if (!existing) {
      existing = document.createElement('p');
      existing.className = 'page-message';
      existing.setAttribute('role', kind === 'error' ? 'alert' : 'status');

      var header = main.querySelector('.page-header');
      if (header && header.nextSibling) {
        main.insertBefore(existing, header.nextSibling);
      } else if (header) {
        main.appendChild(existing);
      } else {
        main.insertBefore(existing, main.firstChild);
      }
    }

    existing.textContent = message;
    existing.dataset.kind = kind || 'info';
  }

  function replaceMainFromHtml(html, opts) {
    opts = opts || {};
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var nextMain = doc.querySelector('main.content');
    var currentMain = document.querySelector('main.content');

    if (!nextMain || !currentMain) {
      injectMessage('The request finished, but the response could not be merged into the page.', 'error');
      return false;
    }

    var scrollY = typeof opts.scrollY === 'number' ? opts.scrollY : window.scrollY;
    currentMain.replaceWith(nextMain);

    var nextTitle = doc.querySelector('title');
    if (nextTitle) document.title = nextTitle.textContent;

    var nextBadge = doc.querySelector('#bell-badge');
    var currentBadge = document.querySelector('#bell-badge');
    if (nextBadge && currentBadge) {
      currentBadge.textContent = nextBadge.textContent;
      currentBadge.className = nextBadge.className;
    }

    window.dispatchEvent(new CustomEvent('fithub:content-updated', { detail: { source: 'ajax-form' } }));

    requestAnimationFrame(function () {
      window.scrollTo({ top: scrollY, behavior: 'auto' });
      var msg = document.querySelector('main.content .page-message');
      if (msg) {
        msg.setAttribute('tabindex', '-1');
        msg.setAttribute('role', msg.dataset.kind === 'error' ? 'alert' : 'status');
      }
    });

    return true;
  }

  async function loadHtml(url, options) {
    var response = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
    }, options || {}));

    if (response.redirected) {
      var redirectedUrl = new URL(response.url);
      if (!isEnhancedPage(redirectedUrl)) {
        window.location.href = response.url;
        return null;
      }
    }

    var text = await response.text();
    if (!response.ok) {
      throw new Error(text || ('Request failed with HTTP ' + response.status));
    }

    return text;
  }

  async function handlePostForm(ev, form, submitter) {
    ev.preventDefault();

    if (form.classList.contains('is-submitting')) return;

    var action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    var data = addReturnTo(new FormData(form));
    var scrollY = window.scrollY;

    setSubmitting(form, submitter, true);

    try {
      var html = await loadHtml(action, { method: 'POST', body: data });
      if (html !== null) replaceMainFromHtml(html, { scrollY: scrollY });
    } catch (err) {
      console.error('Interactive form error:', err);
      injectMessage('Could not complete that action without refreshing. Try again.', 'error');
      setSubmitting(form, submitter, false);
    }
  }

  async function handleGetForm(ev, form) {
    ev.preventDefault();

    var url = buildGetUrl(form);
    var scrollY = window.scrollY;

    try {
      var html = await loadHtml(url);
      if (html !== null && replaceMainFromHtml(html, { scrollY: scrollY })) {
        window.history.pushState({ ajaxPage: true }, '', url);
      }
    } catch (err) {
      console.error('Filter form error:', err);
      window.location.href = url.href;
    }
  }

  async function handleAjaxLink(ev, link) {
    ev.preventDefault();

    var url = new URL(link.getAttribute('href'), window.location.href);

    try {
      var html = await loadHtml(url);
      if (html !== null && replaceMainFromHtml(html, { scrollY: window.scrollY })) {
        window.history.pushState({ ajaxPage: true }, '', url);
      }
    } catch (err) {
      console.error('Ajax link error:', err);
      window.location.href = url.href;
    }
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!shouldEnhanceForm(form)) return;

    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'post') {
      handlePostForm(ev, form, ev.submitter || document.activeElement);
    } else {
      handleGetForm(ev, form);
    }
  });

  document.addEventListener('click', function (ev) {
    var link = ev.target.closest && ev.target.closest('a');
    if (!shouldEnhanceLink(link)) return;
    handleAjaxLink(ev, link);
  });

  window.addEventListener('popstate', async function () {
    var url = new URL(window.location.href);
    if (!isEnhancedPage(url)) return;

    try {
      var html = await loadHtml(url);
      if (html !== null) replaceMainFromHtml(html, { scrollY: 0 });
    } catch (err) {
      window.location.reload();
    }
  });
}());
