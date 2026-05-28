/* ==========================================================================
   admin-app.js — root app: sidebar, view router, event delegation, toast
   ========================================================================== */

var nav = [
  { id: "dashboard", label: "Dashboard", iconKey: "Dashboard" },
  { id: "members",   label: "Members",   iconKey: "Users" },
  { id: "trainers",  label: "Trainers",  iconKey: "Whistle" },
  { id: "classes",   label: "Classes",   iconKey: "Calendar" },
  { id: "equipment", label: "Equipment", iconKey: "Dumbbell" },
  { id: "reports",   label: "Reports",   iconKey: "Flag" },
  { id: "promotions", label: "Promotions", iconKey: "Star" }
];

/* ----- Render --------------------------------------------------------- */

function render() {
  var root = document.getElementById("admin-root");
  if (!root) return;

  // Sidebar
  var sidebarItems = nav.map(function (item) {
    var badge = "";
    if (item.id === "members")   badge = state.users.length;
    if (item.id === "trainers")  badge = state.users.filter(function (u) { return u.Role === "Trainer"; }).length;
    if (item.id === "classes")   badge = state.classes.length;
    if (item.id === "equipment") badge = state.equipment.length;
    if (item.id === "reports")   badge = state.reports.filter(function (r) { return r.Status === "Open" || r.Status === "In Progress"; }).length;
    if (item.id === "promotions") badge = state.promotions.filter(function (p) { return p.IsActive; }).length;
    var badgeHtml = badge !== "" ? '<span class="admin-nav-badge">' + badge + '</span>' : "";
    return ''
      + '<button class="admin-nav-item' + (state.view === item.id ? " is-active" : "") + '" data-action="jump" data-view="' + item.id + '">'
      +   '<span class="admin-nav-icon">' + Icons[item.iconKey] + '</span>'
      +   '<span>' + item.label + '</span>'
      +   badgeHtml
      + '</button>';
  }).join("");

  // Main view
  var mainHtml = state.view === "dashboard" ? viewDashboard()
              : state.view === "members"   ? viewMembers()
              : state.view === "trainers"  ? viewTrainers()
              : state.view === "classes"   ? viewClasses()
              : state.view === "equipment" ? viewEquipment()
              : state.view === "reports"   ? viewReports()
              : state.view === "promotions" ? viewPromotions()
              : "";

  root.innerHTML = ''
    + '<div class="admin-shell">'
    +   '<aside class="admin-sidebar">'
    +     '<div class="admin-sidebar-header">'
    +       '<p class="admin-sidebar-kicker">Admin</p>'
    +       '<p class="admin-sidebar-title">Control Room</p>'
    +     '</div>'
    +     sidebarItems
    +     '<div class="admin-sidebar-footer">'
    +       '<strong>Signed in as admin</strong>'
    +       'Full platform access. Be gentle.'
    +     '</div>'
    +   '</aside>'
    +   '<main class="admin-main">' + mainHtml + '</main>'
    + '</div>'
    + renderModal()
    + renderConfirm();

  // Lock body scroll while a modal is open
  document.body.style.overflow = (state.modal || state.confirm) ? "hidden" : "";

  // Focus first focusable input in modal
  if (state.modal) {
    var first = root.querySelector(".modal-body input, .modal-body select, .modal-body textarea");
    if (first) first.focus();
  }
}

/* ----- Toast ---------------------------------------------------------- */

function toast(text, kind) {
  var stack = document.getElementById("toast-stack");
  if (!stack) return;
  var el = document.createElement("div");
  el.className = "toast" + (kind === "error" ? " is-error" : "");
  el.innerHTML = Icons.Check + '<span>' + escapeHtml(text) + '</span>';
  stack.appendChild(el);
  setTimeout(function () {
    el.style.transition = "opacity 0.2s, transform 0.2s";
    el.style.opacity = "0";
    el.style.transform = "translateY(8px)";
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 250);
  }, 3000);
}

/* ----- API helpers ---------------------------------------------------- */

function apiCall(method, url, data, done) {
  fetch(url, {
    method: method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(data)
  })
    .then(function (r) { return r.json(); })
    .then(done)
    .catch(function (err) {
      console.error('API error:', err);
      done({ ok: false, error: 'Network error' });
    });
}

function replaceInArray(arr, key, obj) {
  for (var i = 0; i < arr.length; i++) {
    if (arr[i][key] === obj[key]) { arr[i] = obj; return; }
  }
}

/* ----- Data load ------------------------------------------------------ */

function loadData(done) {
  var loaded = { main: false, reports: false };
  function check() {
    if (loaded.main && loaded.reports) done();
  }

  fetch('api/admin/data.php', { headers: { Accept: 'application/json' } })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      state.users          = data.users          || [];
      state.classes        = data.classes        || [];
      state.equipment      = data.equipment      || [];
      state.trainerProfiles = data.trainerProfiles || {};
      state.promotions     = data.promotions     || [];
      loaded.main = true;
      check();
    })
    .catch(function (err) {
      console.error('Failed to load admin data:', err);
      loaded.main = true;
      check();
    });

  fetch('api/admin/reports.php', { headers: { Accept: 'application/json' } })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      state.reports = data.reports || [];
      loaded.reports = true;
      check();
    })
    .catch(function (err) {
      console.error('Failed to load reports:', err);
      loaded.reports = true;
      check();
    });
}

/* ----- Form helpers --------------------------------------------------- */

function collectForm(form) {
  var data = {};
  var els = form.querySelectorAll("input, select, textarea");
  for (var i = 0; i < els.length; i++) {
    var el = els[i];
    if (!el.name) continue;
    if (el.type === "checkbox") data[el.name] = el.checked ? 1 : 0;
    else if (el.type === "number") data[el.name] = el.value === "" ? "" : Number(el.value);
    else data[el.name] = el.value;
  }
  return data;
}

/* ----- Action handlers ------------------------------------------------ */

function submitUserForm(form) {
  var d = collectForm(form);
  var errs = {};
  if (!d.Name || !String(d.Name).trim()) errs.Name = "Name is required";
  if (!d.Username || !String(d.Username).trim()) errs.Username = "Username is required";
  else if (String(d.Username).length < 3) errs.Username = "At least 3 characters";

  if (Object.keys(errs).length) {
    state.modal.errors = errs;
    state.modal.data = d;
    render();
    return;
  }

  var isNew = state.modal.isNew;
  if (isNew) {
    apiCall('POST', 'api/admin/users.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error creating user', 'error'); return; }
      state.users.push(res.user);
      toast('Created ' + res.user.Name);
      state.modal = null;
      render();
    });
  } else {
    d.UserID = state.modal.id;
    apiCall('PATCH', 'api/admin/users.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error saving user', 'error'); return; }
      replaceInArray(state.users, 'UserID', res.user);
      toast('Updated ' + res.user.Name);
      state.modal = null;
      render();
    });
  }
}

function submitTrainerForm(form) {
  var d = collectForm(form);
  var id = state.modal.trainer.UserID;
  apiCall('PATCH', 'api/admin/trainers.php', {
    TrainerID:       id,
    Name:            d.Name,
    Bio:             d.Bio             || '',
    Specializations: d.Specializations || '',
    Certifications:  d.Certifications  || 0
  }, function (res) {
    if (!res.ok) { toast(res.error || 'Error saving profile', 'error'); return; }
    var trainer = findById(state.users, 'UserID', id);
    if (trainer) trainer.Name = d.Name;
    state.trainerProfiles[id] = {
      Bio:             d.Bio             || '',
      Specializations: d.Specializations || '',
      Certifications:  Number(d.Certifications) || 0
    };
    toast('Profile saved');
    state.modal = null;
    render();
  });
}

function submitClassForm(form) {
  var d = collectForm(form);
  var errs = {};
  if (!d.Name || !String(d.Name).trim()) errs.Name = "Name is required";
  if (!d.TrainerID) errs.TrainerID = "Pick a trainer";
  if (!d.Capacity || d.Capacity < 1) errs.Capacity = "At least 1";

  if (Object.keys(errs).length) {
    state.modal.errors = errs;
    state.modal.data = d;
    render();
    return;
  }

  d.TrainerID = Number(d.TrainerID);
  d.IsActive  = Number(d.IsActive);
  d.Capacity  = Number(d.Capacity);

  var isNew = state.modal.isNew;
  if (isNew) {
    apiCall('POST', 'api/admin/classes.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error creating class', 'error'); return; }
      state.classes.push(res.class);
      toast('Created "' + res.class.Name + '"');
      state.modal = null;
      render();
    });
  } else {
    d.ClassID = state.modal.id;
    apiCall('PATCH', 'api/admin/classes.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error saving class', 'error'); return; }
      replaceInArray(state.classes, 'ClassID', res.class);
      toast('Updated "' + res.class.Name + '"');
      state.modal = null;
      render();
    });
  }
}

function submitEquipmentForm(form) {
  var d = collectForm(form);
  var errs = {};
  if (!d.Name || !String(d.Name).trim()) errs.Name = "Name is required";

  if (Object.keys(errs).length) {
    state.modal.errors = errs;
    state.modal.data = d;
    render();
    return;
  }

  var isNew = state.modal.isNew;
  if (isNew) {
    apiCall('POST', 'api/admin/equipment.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error adding equipment', 'error'); return; }
      state.equipment.push(res.equipment);
      toast('Added ' + res.equipment.Name);
      state.modal = null;
      render();
    });
  } else {
    d.EquipmentID = state.modal.id;
    apiCall('PATCH', 'api/admin/equipment.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error saving equipment', 'error'); return; }
      replaceInArray(state.equipment, 'EquipmentID', res.equipment);
      toast('Updated ' + res.equipment.Name);
      state.modal = null;
      render();
    });
  }
}

function submitPromotionForm(form) {
  var d = collectForm(form);
  var errs = {};
  if (!d.PromoTitle || !String(d.PromoTitle).trim()) errs.PromoTitle = "Promo Title is required";
  if (d.Type === "Class" && !d.ClassID) errs.ClassID = "Please select a class";
  if (d.Type === "Trainer" && !d.TrainerID) errs.TrainerID = "Please select a trainer";

  if (Object.keys(errs).length) {
    state.modal.errors = errs;
    state.modal.data = d;
    render();
    return;
  }

  d.IsActive = Number(d.IsActive);
  if (d.Type === "Class") {
    d.ClassID = Number(d.ClassID);
    d.TrainerID = null;
  } else {
    d.TrainerID = Number(d.TrainerID);
    d.ClassID = null;
  }

  var isNew = state.modal.isNew;
  if (isNew) {
    apiCall('POST', 'api/admin/promotions.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error creating promotion', 'error'); return; }
      state.promotions.push(res.promotion);
      toast('Created promotion "' + res.promotion.PromoTitle + '"');
      state.modal = null;
      render();
    });
  } else {
    d.PromotionID = state.modal.id;
    apiCall('PATCH', 'api/admin/promotions.php', d, function (res) {
      if (!res.ok) { toast(res.error || 'Error saving promotion', 'error'); return; }
      replaceInArray(state.promotions, 'PromotionID', res.promotion);
      toast('Updated promotion "' + res.promotion.PromoTitle + '"');
      state.modal = null;
      render();
    });
  }
}

/* ----- Event delegation ----------------------------------------------- */

function setupEvents() {
  var root = document.getElementById("admin-root");

  // Clicks
  root.addEventListener("click", function (ev) {
    var target = ev.target.closest("[data-action]");
    if (!target) return;
    var action = target.getAttribute("data-action");
    var id = target.getAttribute("data-id");

    if (action === "jump") {
      state.view = target.getAttribute("data-view");
      state.modal = null; state.confirm = null;
      render();
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    if (action === "modal-close" || action === "modal-close-bg") {
      if (action === "modal-close-bg" && ev.target !== target) return;
      state.modal = null;
      render();
      return;
    }

    if (action === "confirm-close" || action === "confirm-close-bg") {
      if (action === "confirm-close-bg" && ev.target !== target) return;
      state.confirm = null;
      render();
      return;
    }

    if (action === "confirm-yes") {
      var fn = state.confirm && state.confirm.onConfirm;
      state.confirm = null;
      render();
      if (fn) fn();
      return;
    }

    if (action === "modal-submit") {
      var formName = target.getAttribute("data-form");
      var form = root.querySelector('form[data-form="' + formName + '"]');
      if (!form) return;
      if (formName === "user")      submitUserForm(form);
      if (formName === "trainer")   submitTrainerForm(form);
      if (formName === "class")     submitClassForm(form);
      if (formName === "equipment") submitEquipmentForm(form);
      if (formName === "promotion") submitPromotionForm(form);
      return;
    }

    /* ----- Users ----- */
    if (action === "user-row-click") {
      if (window.innerWidth <= 820) {
        var u = findById(state.users, "UserID", Number(id));
        if (u) {
          state.modal = { kind: "user-actions", user: u };
          render();
        }
      }
      return;
    }
    if (action === "user-edit-inside-actions") {
      state.modal = null;
      render();
      var u = findById(state.users, "UserID", Number(id));
      if (u) {
        state.modal = { kind: "user", isNew: false, id: u.UserID, data: Object.assign({}, u) };
        render();
      }
      return;
    }
    if (action === "user-promote-inside-actions") {
      state.modal = null;
      render();
      setTimeout(function() {
        var btn = document.querySelector('[data-action="user-promote"][data-id="' + id + '"]');
        if (btn) btn.click();
      }, 50);
      return;
    }
    if (action === "user-deactivate-inside-actions") {
      state.modal = null;
      render();
      setTimeout(function() {
        var btn = document.querySelector('[data-action="user-deactivate"][data-id="' + id + '"]');
        if (btn) btn.click();
      }, 50);
      return;
    }
    if (action === "user-activate-inside-actions") {
      state.modal = null;
      render();
      setTimeout(function() {
        var btn = document.querySelector('[data-action="user-activate"][data-id="' + id + '"]');
        if (btn) btn.click();
      }, 50);
      return;
    }

    if (action === "user-new") {
      state.modal = { kind: "user", isNew: true, role: target.getAttribute("data-role") || "Member" };
      render();
      return;
    }
    if (action === "user-edit") {
      var u = findById(state.users, "UserID", Number(id));
      if (u) {
        state.modal = { kind: "user", isNew: false, id: u.UserID, data: Object.assign({}, u) };
        render();
      }
      return;
    }
    if (action === "user-promote") {
      var up = findById(state.users, "UserID", Number(id));
      if (!up) return;
      state.confirm = {
        title: "Promote to Admin?",
        body: up.Name + " will get full admin access to FitHub. They'll be able to manage every member, trainer, class and piece of equipment.",
        kind: "warning",
        confirmLabel: "Yes, promote",
        onConfirm: function () {
          apiCall('PATCH', 'api/admin/users.php', { UserID: up.UserID, Name: up.Name, Username: up.Username, Role: 'Admin', IsActive: up.IsActive }, function (res) {
            if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
            replaceInArray(state.users, 'UserID', res.user);
            state.activity.unshift({ id: Date.now(), icon: 'crown', tone: 'default', text: '<strong>' + escapeHtml(res.user.Name) + '</strong> was promoted to Admin.', time: 'Just now' });
            toast(res.user.Name + ' is now an Admin');
            render();
          });
        }
      };
      render();
      return;
    }
    if (action === "user-deactivate") {
      var ud = findById(state.users, "UserID", Number(id));
      if (!ud) return;
      state.confirm = {
        title: "Deactivate user?",
        body: ud.Name + " will lose access to FitHub. Their bookings and enrollments stay on record — you can reactivate them anytime.",
        kind: "danger",
        confirmLabel: "Deactivate",
        onConfirm: function () {
          apiCall('PATCH', 'api/admin/users.php', { UserID: ud.UserID, Name: ud.Name, Username: ud.Username, Role: ud.Role, IsActive: 0 }, function (res) {
            if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
            replaceInArray(state.users, 'UserID', res.user);
            toast(res.user.Name + ' deactivated');
            render();
          });
        }
      };
      render();
      return;
    }
    if (action === "user-activate") {
      var ua = findById(state.users, "UserID", Number(id));
      if (!ua) return;
      state.confirm = {
        title: "Reactivate user?",
        body: ua.Name + " will regain access to FitHub immediately.",
        kind: "info",
        confirmLabel: "Reactivate",
        onConfirm: function () {
          apiCall('PATCH', 'api/admin/users.php', { UserID: ua.UserID, Name: ua.Name, Username: ua.Username, Role: ua.Role, IsActive: 1 }, function (res) {
            if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
            replaceInArray(state.users, 'UserID', res.user);
            toast(res.user.Name + ' reactivated');
            render();
          });
        }
      };
      render();
      return;
    }

    /* ----- Trainers ----- */
    if (action === "trainer-edit") {
      var t = findById(state.users, "UserID", Number(id));
      if (t) {
        state.modal = { kind: "trainer", trainer: t, profile: state.trainerProfiles[t.UserID] || { Bio: "", Specializations: "", Certifications: 0 } };
        render();
      }
      return;
    }
    if (action === "trainer-spec-toggle") {
      var spec = target.getAttribute("data-spec");
      var trainerForm = root.querySelector('form[data-form="trainer"]');
      if (!trainerForm) return;
      var current = collectForm(trainerForm);
      var specs = (current.Specializations || "").split(",").map(function (s) { return s.trim(); }).filter(Boolean);
      var idx = specs.indexOf(spec);
      if (idx >= 0) specs.splice(idx, 1); else specs.push(spec);
      current.Specializations = specs.join(", ");
      state.modal.data = current;
      render();
      return;
    }

    /* ----- Classes ----- */
    if (action === "class-new") {
      state.modal = { kind: "class", isNew: true };
      render();
      return;
    }
    if (action === "class-edit") {
      var c = findById(state.classes, "ClassID", Number(id));
      if (c) {
        state.modal = { kind: "class", isNew: false, id: c.ClassID, data: Object.assign({}, c) };
        render();
      }
      return;
    }
    if (action === "class-toggle") {
      var ct = findById(state.classes, "ClassID", Number(id));
      if (!ct) return;
      var payload = Object.assign({}, ct, { IsActive: ct.IsActive ? 0 : 1 });
      apiCall('PATCH', 'api/admin/classes.php', payload, function (res) {
        if (!res.ok) { toast(res.error || 'Error', 'error'); return; }
        replaceInArray(state.classes, 'ClassID', res.class);
        var label = res.class.IsActive ? 'reactivated' : 'paused';
        toast('"' + res.class.Name + '" ' + label);
        state.activity.unshift({
          id:   Date.now(),
          icon: 'edit',
          tone: res.class.IsActive ? 'green' : 'red',
          text: '<strong>' + escapeHtml(res.class.Name) + '</strong> was ' + label + '.',
          time: 'Just now'
        });
        render();
      });
      return;
    }
    if (action === "class-delete") {
      var cd = findById(state.classes, "ClassID", Number(id));
      if (!cd) return;
      state.confirm = {
        title: "Delete class?",
        body: '"' + cd.Name + '" will be removed permanently. Any current enrollments will be cancelled and members notified.',
        kind: "danger",
        confirmLabel: "Delete class",
        onConfirm: function () {
          apiCall('DELETE', 'api/admin/classes.php', { ClassID: cd.ClassID }, function (res) {
            if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
            state.classes = state.classes.filter(function (x) { return x.ClassID !== cd.ClassID; });
            toast('Deleted "' + cd.Name + '"');
            render();
          });
        }
      };
      render();
      return;
    }

    /* ----- Equipment ----- */
    if (action === "equipment-new") {
      state.modal = { kind: "equipment", isNew: true };
      render();
      return;
    }
    if (action === "equipment-edit") {
      var eq = findById(state.equipment, "EquipmentID", Number(id));
      if (eq) {
        state.modal = { kind: "equipment", isNew: false, id: eq.EquipmentID, data: Object.assign({}, eq) };
        render();
      }
      return;
    }

    /* ----- Promotions ----- */
    if (action === "promotion-new") {
      state.modal = { kind: "promotion", isNew: true };
      render();
      return;
    }
    if (action === "promotion-edit") {
      var p = findById(state.promotions, "PromotionID", Number(id));
      if (p) {
        state.modal = { kind: "promotion", isNew: false, id: p.PromotionID, data: Object.assign({}, p) };
        render();
      }
      return;
    }
    if (action === "promotion-toggle") {
      var pt = findById(state.promotions, "PromotionID", Number(id));
      if (!pt) return;
      var payload = Object.assign({}, pt, { IsActive: pt.IsActive ? 0 : 1 });
      apiCall('PATCH', 'api/admin/promotions.php', payload, function (res) {
        if (!res.ok) { toast(res.error || 'Error', 'error'); return; }
        replaceInArray(state.promotions, 'PromotionID', res.promotion);
        var label = res.promotion.IsActive ? 'activated' : 'paused';
        toast('Promotion "' + res.promotion.PromoTitle + '" ' + label);
        render();
      });
      return;
    }
    if (action === "promotion-delete") {
      var pd = findById(state.promotions, "PromotionID", Number(id));
      if (!pd) return;
      state.confirm = {
        title: "Delete promotion?",
        body: 'Promotion "' + pd.PromoTitle + '" will be permanently deleted from the database.',
        kind: "danger",
        confirmLabel: "Delete promotion",
        onConfirm: function () {
          apiCall('DELETE', 'api/admin/promotions.php', { PromotionID: pd.PromotionID }, function (res) {
            if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
            state.promotions = state.promotions.filter(function (x) { return x.PromotionID !== pd.PromotionID; });
            toast('Deleted promotion "' + pd.PromoTitle + '"');
            render();
          });
        }
      };
      render();
      return;
    }

    /* ----- Misc ----- */
    if (action === "refresh") {
      loadData(function () { toast("Refreshed"); render(); });
      return;
    }
    if (action === "export-csv") {
      toast("CSV export queued");
      return;
    }

    /* ----- Reports ----- */
    if (action === "report-toggle") {
      var reportId = Number(id);
      state.reportDetail = (state.reportDetail === reportId) ? null : reportId;
      render();
      if (state.reportDetail) {
        setTimeout(function () {
          var el = document.getElementById('report-detail-' + reportId);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
      }
      return;
    }
    if (action === "report-respond") {
      var respForm = root.querySelector('form[data-form="report-respond-' + id + '"]');
      if (!respForm) return;
      var fd = collectForm(respForm);
      var msg = (fd.Message || '').trim();
      if (!msg) { toast('Please enter a message', 'error'); return; }
      apiCall('POST', 'api/admin/reports.php', {
        action: 'respond',
        ReportID: Number(id),
        Message: msg
      }, function (res) {
        if (!res.ok) { toast(res.error || 'Error', 'error'); return; }
        var rpt = findById(state.reports, 'ReportID', Number(id));
        if (rpt) {
          rpt.Responses = rpt.Responses || [];
          rpt.Responses.push(res.response);
          rpt.UpdatedAt = res.response.CreatedAt;
        }
        toast('Response sent');
        render();
      });
      return;
    }
    if (action === "report-status") {
      var newSt = target.getAttribute('data-status');
      apiCall('POST', 'api/admin/reports.php', {
        action: 'update_status',
        ReportID: Number(id),
        Status: newSt
      }, function (res) {
        if (!res.ok) { toast(res.error || 'Error', 'error'); return; }
        var rpt = findById(state.reports, 'ReportID', Number(id));
        if (rpt) {
          rpt.Status = res.Status;
        }
        toast('Status updated to ' + res.Status);
        render();
      });
      return;
    }
  });

  // Input changes (filters)
  root.addEventListener("input", function (ev) {
    var el = ev.target;
    var bind = el.getAttribute && el.getAttribute("data-bind");
    if (!bind) return;
    var parts = bind.split(".");
    var bucket = state[parts[0]];
    if (bucket) {
      bucket[parts[1]] = el.value;
      var bindAttr = bind;
      var caret = el.selectionStart;
      render();
      var nextEl = document.querySelector('[data-bind="' + bindAttr + '"]');
      if (nextEl) {
        nextEl.focus();
        try { if (caret != null) nextEl.setSelectionRange(caret, caret); } catch (e) {}
      }
    }
  });

  // Change events (selects, equipment status)
  root.addEventListener("change", function (ev) {
    var el = ev.target;
    var action = el.getAttribute && el.getAttribute("data-action");
    var bind = el.getAttribute && el.getAttribute("data-bind");

    if (el.name === "Type" && el.closest('form[data-form="promotion"]')) {
      var form = el.closest('form');
      var type = el.value;
      var classField = form.querySelector('.field-class-select');
      var trainerField = form.querySelector('.field-trainer-select');
      if (type === "Class") {
        classField.style.display = "block";
        trainerField.style.display = "none";
      } else {
        classField.style.display = "none";
        trainerField.style.display = "block";
      }
      return;
    }

    if (action === "equipment-status") {
      var eqId = Number(el.getAttribute("data-id"));
      var item = findById(state.equipment, "EquipmentID", eqId);
      if (!item) return;
      var newStatus = el.value;
      apiCall('PATCH', 'api/admin/equipment.php', Object.assign({}, item, { Status: newStatus }), function (res) {
        if (!res.ok) { toast(res.error || 'Error', 'error'); render(); return; }
        replaceInArray(state.equipment, 'EquipmentID', res.equipment);
        toast(res.equipment.Name + ' → ' + res.equipment.Status);
        render();
      });
      return;
    }

    if (bind) {
      var parts = bind.split(".");
      var bucket = state[parts[0]];
      if (bucket) {
        bucket[parts[1]] = el.value;
        render();
      }
    }
  });

  // Escape to close modals
  document.addEventListener("keydown", function (ev) {
    if (ev.key !== "Escape") return;
    if (state.confirm) { state.confirm = null; render(); }
    else if (state.modal) { state.modal = null; render(); }
  });
}

/* ----- Boot ----------------------------------------------------------- */

document.addEventListener("DOMContentLoaded", function () {
  var root = document.getElementById("admin-root");
  if (root) root.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:60vh;color:var(--text-soft);font-size:0.95rem;gap:0.6rem">' + Icons.Refresh + ' Loading…</div>';
  setupEvents();
  loadData(function () { render(); });
});
