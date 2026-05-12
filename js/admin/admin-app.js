/* ==========================================================================
   admin-app.js — root app: sidebar, view router, event delegation, toast
   ========================================================================== */

var nav = [
  { id: "dashboard", label: "Dashboard", iconKey: "Dashboard" },
  { id: "members",   label: "Members",   iconKey: "Users" },
  { id: "trainers",  label: "Trainers",  iconKey: "Whistle" },
  { id: "classes",   label: "Classes",   iconKey: "Calendar" },
  { id: "equipment", label: "Equipment", iconKey: "Dumbbell" }
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
    if (item.id === "equipment") {
      var down = state.equipment.filter(function (e) { return e.Status === "Maintenance"; }).length;
      badge = down ? down + "!" : "";
    }
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

  if (state.modal.isNew) {
    var id = nextId(state.users, "UserID");
    state.users.push({
      UserID: id,
      Name: d.Name,
      Username: d.Username,
      Role: d.Role || "Member",
      IsActive: 1,
      Joined: new Date().toISOString().slice(0, 10),
      LastLogin: "Never"
    });
    toast("Created " + d.Name);
  } else {
    var u = findById(state.users, "UserID", state.modal.id);
    if (u) {
      u.Name = d.Name;
      u.Username = d.Username;
      u.Role = d.Role;
      u.IsActive = d.IsActive ? 1 : 0;
      toast("Updated " + u.Name);
    }
  }
  state.modal = null;
  render();
}

function submitTrainerForm(form) {
  var d = collectForm(form);
  var id = state.modal.trainer.UserID;
  var trainer = findById(state.users, "UserID", id);
  if (trainer) trainer.Name = d.Name;
  state.trainerProfiles[id] = {
    Bio: d.Bio || "",
    Specializations: d.Specializations || "",
    Certifications: Number(d.Certifications) || 0
  };
  toast("Profile saved");
  state.modal = null;
  render();
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
  d.IsActive = Number(d.IsActive);
  d.Capacity = Number(d.Capacity);

  if (state.modal.isNew) {
    d.ClassID = nextId(state.classes, "ClassID");
    d.Enrolled = 0;
    state.classes.push(d);
    toast('Created "' + d.Name + '"');
  } else {
    var c = findById(state.classes, "ClassID", state.modal.id);
    if (c) {
      for (var k in d) if (d.hasOwnProperty(k)) c[k] = d[k];
      toast('Updated "' + c.Name + '"');
    }
  }
  state.modal = null;
  render();
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

  if (state.modal.isNew) {
    d.EquipmentID = nextId(state.equipment, "EquipmentID");
    d.UpdatedAt = "Just now";
    state.equipment.push(d);
    toast("Added " + d.Name);
  } else {
    var e = findById(state.equipment, "EquipmentID", state.modal.id);
    if (e) {
      for (var k in d) if (d.hasOwnProperty(k)) e[k] = d[k];
      e.UpdatedAt = "Just now";
      toast("Updated " + e.Name);
    }
  }
  state.modal = null;
  render();
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
      // Reset modals + scroll to top on view change
      state.modal = null; state.confirm = null;
      render();
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    if (action === "modal-close" || action === "modal-close-bg") {
      // Backdrop close: only if the backdrop itself, not the inner modal
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
      if (fn) fn();
      render();
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
      return;
    }

    /* ----- Users ----- */
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
        onConfirm: function () { up.Role = "Admin"; toast(up.Name + " is now an Admin"); render(); }
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
        onConfirm: function () { ud.IsActive = 0; toast(ud.Name + " deactivated"); render(); }
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
        onConfirm: function () { ua.IsActive = 1; toast(ua.Name + " reactivated"); render(); }
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
      var form = root.querySelector('form[data-form="trainer"]');
      if (!form) return;
      // Persist form values into modal state before re-render
      var current = collectForm(form);
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
      if (ct) {
        ct.IsActive = ct.IsActive ? 0 : 1;
        toast('"' + ct.Name + '"' + (ct.IsActive ? " reactivated" : " paused"));
        render();
      }
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
          state.classes = state.classes.filter(function (x) { return x.ClassID !== cd.ClassID; });
          toast('Deleted "' + cd.Name + '"');
          render();
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
      var e = findById(state.equipment, "EquipmentID", Number(id));
      if (e) {
        state.modal = { kind: "equipment", isNew: false, id: e.EquipmentID, data: Object.assign({}, e) };
        render();
      }
      return;
    }

    /* ----- Misc ----- */
    if (action === "refresh") {
      toast("Refreshed");
      return;
    }
    if (action === "export-csv") {
      toast("CSV export queued");
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
      // Re-render without losing focus on this input
      var name = el.name;
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

    if (action === "equipment-status") {
      var id = Number(el.getAttribute("data-id"));
      var item = findById(state.equipment, "EquipmentID", id);
      if (item) {
        item.Status = el.value;
        item.UpdatedAt = "Just now";
        toast(item.Name + " → " + el.value);
        render();
      }
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
  setupEvents();
  render();
});
