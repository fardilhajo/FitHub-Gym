/* ==========================================================================
   admin-views.js — render functions for each view + shared UI partials
   All functions return HTML strings. Data comes from `state` (admin-data.js).
   ========================================================================== */

/* ----- Partials -------------------------------------------------------- */

function tmplAvatar(name) {
  return '<span class="table-avatar" aria-hidden="true">' + escapeHtml(initials(name)) + '</span>';
}

function tmplPill(kind, label) {
  var cls = kind ? "pill pill--" + kind : "pill";
  return '<span class="' + cls + '">' + label + '</span>';
}

function tmplStatCard(label, value, iconKey, trend, trendKind) {
  var iconSvg = iconKey ? '<span class="stat-card-icon">' + Icons[iconKey] + '</span>' : "";
  var trendCls = trendKind === "down" ? " is-down" : trendKind === "flat" ? " is-flat" : "";
  var trendSvg = trendKind === "down" ? Icons.TrendDown : Icons.Trend;
  var trendHtml = trend ? '<span class="stat-card-trend' + trendCls + '">' + trendSvg + escapeHtml(trend) + '</span>' : "";
  return ''
    + '<article class="stat-card">'
    +   '<div class="stat-card-label">' + iconSvg + escapeHtml(label) + '</div>'
    +   '<div class="stat-card-value">' + escapeHtml(String(value)) + '</div>'
    +   trendHtml
    + '</article>';
}

function tmplMiniMeter(pct) {
  var clamped = Math.min(100, Math.max(0, pct));
  var cls = pct >= 100 ? " class=\"is-full\"" : "";
  return ''
    + '<div class="mini-meter"><span' + cls + ' style="width:' + clamped + '%"></span></div>';
}

function tmplEmptyState(iconKey, title, body) {
  return ''
    + '<div class="empty-state">'
    +   '<div class="empty-state-icon">' + Icons[iconKey] + '</div>'
    +   '<h3>' + escapeHtml(title) + '</h3>'
    +   '<p>' + escapeHtml(body) + '</p>'
    + '</div>';
}

/* =====================================================================
   DASHBOARD
   ===================================================================== */
function viewDashboard() {
  var users = state.users, classes = state.classes, equipment = state.equipment;
  var memberCount  = users.filter(function (u) { return u.Role === "Member"  && u.IsActive; }).length;
  var trainerCount = users.filter(function (u) { return u.Role === "Trainer" && u.IsActive; }).length;
  var activeClasses = classes.filter(function (c) { return c.IsActive; }).length;
  var equipUp = equipment.filter(function (e) { return e.Status === "Available"; }).length;
  var equipDown = equipment.filter(function (e) { return e.Status === "Maintenance"; }).length;

  var todaysClasses = classes
    .filter(function (c) { return c.IsActive && c.Day === "Monday"; })
    .sort(function (a, b) { return a.StartTime.localeCompare(b.StartTime); })
    .slice(0, 4);

  var tableRows = todaysClasses.map(function (c) {
    var trainer = findById(users, "UserID", c.TrainerID);
    var pct = Math.round((c.Enrolled / c.Capacity) * 100);
    return ''
      + '<tr>'
      +   '<td>'
      +     '<div style="display:flex;flex-direction:column;line-height:1.25">'
      +       '<strong style="color:var(--text-dark);font-weight:700;font-size:0.94rem">' + escapeHtml(c.Name) + '</strong>'
      +       '<span style="color:var(--text-soft);font-size:0.8rem">' + escapeHtml(c.Type) + ' · ' + escapeHtml(c.Room) + '</span>'
      +     '</div>'
      +   '</td>'
      +   '<td>'
      +     '<div class="table-user">'
      +       tmplAvatar(trainer ? trainer.Name : "—")
      +       '<div class="table-user-meta">'
      +         '<strong>' + escapeHtml(trainer ? trainer.Name.split(" ")[0] : "Unassigned") + '</strong>'
      +         '<span>' + escapeHtml(trainer ? "@" + trainer.Username : "no trainer") + '</span>'
      +       '</div>'
      +     '</div>'
      +   '</td>'
      +   '<td><span style="color:var(--text-soft);font-size:0.85rem;font-weight:600">' + escapeHtml(c.StartTime) + '–' + escapeHtml(c.EndTime) + '</span></td>'
      +   '<td>'
      +     '<div class="capacity-cell">' + tmplMiniMeter(pct) + '<span class="capacity-cell-text">' + c.Enrolled + '/' + c.Capacity + '</span></div>'
      +   '</td>'
      + '</tr>';
  }).join("");

  var activityHtml = state.activity.map(function (item) {
    var iconKey = item.icon === "user-plus" ? "Users"
                : item.icon === "edit"      ? "Edit"
                : item.icon === "crown"     ? "Crown"
                : item.icon === "wrench"    ? "Wrench"
                : item.icon === "pause"     ? "Power"
                : "Check";
    var toneCls = item.tone === "green" ? "is-green" : item.tone === "red" ? "is-red" : item.tone === "blue" ? "is-blue" : "";
    return ''
      + '<div class="activity-item">'
      +   '<div class="activity-icon ' + toneCls + '">' + Icons[iconKey] + '</div>'
      +   '<div>'
      +     '<div class="activity-text">' + item.text + '</div>'
      +     '<div class="activity-time">' + escapeHtml(item.time) + '</div>'
      +   '</div>'
      + '</div>';
  }).join("");

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Platform overview</h1>'
    +     '<p>What\'s working, what\'s broken, and where Ana&nbsp;P. needs a backup spin instructor by Thursday.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-secondary" data-action="refresh">' + Icons.Refresh + ' Refresh</button>'
    +     '<button class="btn-primary" data-action="jump" data-view="classes">' + Icons.Plus + ' New class</button>'
    +   '</div>'
    + '</header>'

    + '<div class="admin-stats">'
    +   tmplStatCard("Active members", memberCount,    "Users",    "+12 this month", "up")
    +   tmplStatCard("Trainers",       trainerCount,   "Whistle",  "1 on leave",     "flat")
    +   tmplStatCard("Live classes",   activeClasses,  "Calendar", "2 near full",    "up")
    +   tmplStatCard("Equipment up",   equipUp + " / " + equipment.length, "Dumbbell", equipDown + " down", equipDown ? "down" : "flat")
    + '</div>'

    + '<div class="dashboard-grid">'
    +   '<section class="panel">'
    +     '<div class="panel-header">'
    +       '<div><p class="panel-kicker">Monday Schedule</p><h2>Today\'s classes</h2></div>'
    +       '<button class="panel-link" data-action="jump" data-view="classes">Manage all →</button>'
    +     '</div>'
    +     '<div class="table-wrap">'
    +       '<table class="admin-table">'
    +         '<thead><tr><th>Class</th><th>Trainer</th><th>Time</th><th>Fill</th></tr></thead>'
    +         '<tbody>' + tableRows + '</tbody>'
    +       '</table>'
    +     '</div>'
    +   '</section>'

    +   '<section class="panel">'
    +     '<div class="panel-header">'
    +       '<div><p class="panel-kicker">Activity</p><h2>Recent events</h2></div>'
    +     '</div>'
    +     '<div class="activity-list">' + activityHtml + '</div>'
    +   '</section>'
    + '</div>';
}

/* =====================================================================
   MEMBERS
   ===================================================================== */
function viewMembers() {
  var f = state.members;
  var q = f.search.trim().toLowerCase();
  var filtered = state.users.filter(function (u) {
    if (f.role !== "All" && u.Role !== f.role) return false;
    if (f.status === "Active"   && !u.IsActive) return false;
    if (f.status === "Inactive" &&  u.IsActive) return false;
    if (!q) return true;
    return u.Name.toLowerCase().indexOf(q) >= 0 || u.Username.toLowerCase().indexOf(q) >= 0;
  });

  var rows = filtered.map(function (u) {
    var roleKind = "role-" + u.Role.toLowerCase();
    var actions = ''
      + '<button class="btn-icon" title="Edit" data-action="user-edit" data-id="' + u.UserID + '">' + Icons.Edit + '</button>';
    if (u.Role !== "Admin") {
      actions += '<button class="btn-icon" title="Promote to Admin" data-action="user-promote" data-id="' + u.UserID + '">' + Icons.Crown + '</button>';
    }
    if (u.IsActive) {
      actions += '<button class="btn-icon is-danger" title="Deactivate" data-action="user-deactivate" data-id="' + u.UserID + '">' + Icons.Power + '</button>';
    } else {
      actions += '<button class="btn-icon" title="Reactivate" data-action="user-activate" data-id="' + u.UserID + '">' + Icons.Check + '</button>';
    }
    return ''
      + '<tr>'
      +   '<td data-action="user-row-click" data-id="' + u.UserID + '" style="cursor:pointer"><div class="table-user">' + tmplAvatar(u.Name) + '<div class="table-user-meta"><strong>' + escapeHtml(u.Name) + '</strong><span>@' + escapeHtml(u.Username) + '</span></div></div></td>'
      +   '<td>' + tmplPill(roleKind, '<span class="pill-dot"></span>' + escapeHtml(u.Role)) + '</td>'
      +   '<td>' + tmplPill(u.IsActive ? "active" : "inactive", u.IsActive ? "Active" : "Inactive") + '</td>'
      +   '<td><span style="color:var(--text-soft);font-size:0.85rem;font-weight:600">' + escapeHtml(u.Joined) + '</span></td>'
      +   '<td><span style="color:var(--text-soft);font-size:0.85rem;font-weight:600">' + escapeHtml(u.LastLogin) + '</span></td>'
      +   '<td class="col-actions"><div class="actions-row">' + actions + '</div></td>'
      + '</tr>';
  }).join("");

  var tableHtml = filtered.length === 0
    ? tmplEmptyState("Users", "No users match those filters", "Try a different search term or clear the role/status filter.")
    : '<div class="table-wrap"><table class="admin-table">'
      + '<thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Last login</th><th class="col-actions">Actions</th></tr></thead>'
      + '<tbody>' + rows + '</tbody>'
      + '</table></div>';

  var roleOpts = ["All", "Member", "Trainer", "Admin"].map(function (r) {
    return '<option' + (f.role === r ? " selected" : "") + '>' + r + '</option>';
  }).join("");
  var statusOpts = ["All", "Active", "Inactive"].map(function (s) {
    return '<option' + (f.status === s ? " selected" : "") + '>' + s + '</option>';
  }).join("");

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Members &amp; trainers</h1>'
    +     '<p>Edit accounts, switch roles, promote to admin, deactivate the ones who only show up in January.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-secondary" data-action="export-csv">' + Icons.Inbox + ' Export CSV</button>'
    +     '<button class="btn-primary" data-action="user-new">' + Icons.Plus + ' New user</button>'
    +   '</div>'
    + '</header>'

    + '<section class="panel">'
    +   '<div class="toolbar">'
    +     '<div class="toolbar-search">' + Icons.Search
    +       '<input type="search" placeholder="Search by name or username..." data-bind="members.search" value="' + escapeHtml(f.search) + '">'
    +     '</div>'
    +     '<select data-bind="members.role">' + roleOpts + '</select>'
    +     '<select data-bind="members.status">' + statusOpts + '</select>'
    +     '<div class="toolbar-spacer"></div>'
    +     '<div class="toolbar-count"><strong>' + filtered.length + '</strong> of ' + state.users.length + ' users</div>'
    +   '</div>'
    +   tableHtml
    + '</section>';
}

/* =====================================================================
   TRAINERS
   ===================================================================== */
function viewTrainers() {
  var trainers = state.users.filter(function (u) { return u.Role === "Trainer"; });

  var rows = trainers.map(function (t) {
    var p = state.trainerProfiles[t.UserID] || { Specializations: "" };
    var myClasses = state.classes.filter(function (c) { return c.TrainerID === t.UserID; });
    var active = myClasses.filter(function (c) { return c.IsActive; }).length;
    var totalSeats = myClasses.reduce(function (s, c) { return s + c.Capacity; }, 0);
    var filledSeats = myClasses.reduce(function (s, c) { return s + c.Enrolled; }, 0);
    var fill = totalSeats ? Math.round((filledSeats / totalSeats) * 100) : 0;

    var specPills = (p.Specializations || "").split(",")
      .map(function (s) { return s.trim(); })
      .filter(Boolean)
      .map(function (spec) { return tmplPill("", escapeHtml(spec)); })
      .join("");

    return ''
      + '<tr>'
      +   '<td><div class="table-user">' + tmplAvatar(t.Name) + '<div class="table-user-meta"><strong>' + escapeHtml(t.Name) + '</strong><span>@' + escapeHtml(t.Username) + '</span></div></div></td>'
      +   '<td><div style="display:flex;flex-wrap:wrap;gap:0.3rem">' + (specPills || '<span style="color:var(--text-soft);font-size:0.85rem">—</span>') + '</div></td>'
      +   '<td><span style="color:var(--text-dark);font-weight:700">' + active + '</span></td>'
      +   '<td><div class="capacity-cell">' + tmplMiniMeter(fill) + '<span class="capacity-cell-text">' + fill + '%</span></div></td>'
      +   '<td>' + tmplPill(t.IsActive ? "active" : "inactive", t.IsActive ? "Active" : "On leave") + '</td>'
      +   '<td class="col-actions"><div class="actions-row"><button class="btn-icon" title="Edit profile" data-action="trainer-edit" data-id="' + t.UserID + '">' + Icons.Edit + '</button></div></td>'
      + '</tr>';
  }).join("");

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Trainer roster</h1>'
    +     '<p>Bios, specializations, class load. Quick edits without leaving the table.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-primary" data-action="user-new" data-role="Trainer">' + Icons.Plus + ' New trainer</button>'
    +   '</div>'
    + '</header>'

    + '<section class="panel">'
    +   '<div class="toolbar"><div class="toolbar-count"><strong>' + trainers.length + '</strong> trainers on staff</div></div>'
    +   '<div class="table-wrap"><table class="admin-table">'
    +     '<thead><tr><th>Trainer</th><th>Specializations</th><th>Active classes</th><th>Fill rate</th><th>Status</th><th class="col-actions">Actions</th></tr></thead>'
    +     '<tbody>' + rows + '</tbody>'
    +   '</table></div>'
    + '</section>';
}

/* =====================================================================
   CLASSES
   ===================================================================== */
function viewClasses() {
  var f = state.classesFilter;
  var q = f.search.trim().toLowerCase();
  var trainers = state.users.filter(function (u) { return u.Role === "Trainer"; });
  var types = [];
  state.classes.forEach(function (c) { if (types.indexOf(c.Type) < 0) types.push(c.Type); });

  var filtered = state.classes.filter(function (c) {
    if (f.type !== "All" && c.Type !== f.type) return false;
    if (f.trainer !== "All" && String(c.TrainerID) !== String(f.trainer)) return false;
    if (f.active === "Active" && !c.IsActive) return false;
    if (f.active === "Paused" &&  c.IsActive) return false;
    if (!q) return true;
    return c.Name.toLowerCase().indexOf(q) >= 0 || c.Type.toLowerCase().indexOf(q) >= 0;
  });

  var cards = filtered.map(function (c) {
    var trainer = findById(state.users, "UserID", c.TrainerID);
    var pct = Math.round((c.Enrolled / c.Capacity) * 100);
    var trainerHtml = trainer
      ? '<div class="class-admin-trainer">'
      +   '<div class="class-admin-trainer-avatar">' + escapeHtml(initials(trainer.Name)) + '</div>'
      +   '<div><small>Trainer</small><strong>' + escapeHtml(trainer.Name) + '</strong></div>'
      +   '<span style="margin-left:auto">' + tmplPill(c.IsActive ? "active" : "inactive", c.IsActive ? "Live" : "Paused") + '</span>'
      + '</div>'
      : '<div class="class-admin-trainer"><em style="color:var(--text-soft);font-size:0.85rem">No trainer assigned</em></div>';

    return ''
      + '<article class="class-admin-card' + (c.IsActive ? "" : " is-inactive") + '">'
      +   '<div class="class-admin-card-top">'
      +     '<div><h3>' + escapeHtml(c.Name) + '</h3><p>' + escapeHtml(c.Description) + '</p></div>'
      +     tmplPill("", escapeHtml(c.Type))
      +   '</div>'
      +   '<div class="class-admin-meta">'
      +     '<span>' + Icons.Calendar + escapeHtml(c.Day) + '</span>'
      +     '<span>' + Icons.Clock + escapeHtml(c.StartTime) + '–' + escapeHtml(c.EndTime) + '</span>'
      +     '<span>' + Icons.Pin + escapeHtml(c.Room) + '</span>'
      +     '<span>' + Icons.Cap + c.Enrolled + '/' + c.Capacity + '</span>'
      +   '</div>'
      +   '<div class="capacity-cell" style="width:100%">'
      +     '<div class="mini-meter" style="flex:1;width:auto"><span' + (pct >= 100 ? ' class="is-full"' : '') + ' style="width:' + Math.min(100, pct) + '%"></span></div>'
      +     '<span class="capacity-cell-text">' + pct + '%</span>'
      +   '</div>'
      +   trainerHtml
      +   '<div class="class-admin-actions">'
      +     '<button class="btn-secondary" data-action="class-edit" data-id="' + c.ClassID + '">' + Icons.Edit + ' Edit</button>'
      +     '<button class="btn-icon" title="' + (c.IsActive ? "Pause" : "Reactivate") + '" data-action="class-toggle" data-id="' + c.ClassID + '">' + (c.IsActive ? Icons.Power : Icons.Check) + '</button>'
      +     '<button class="btn-icon is-danger" title="Delete" data-action="class-delete" data-id="' + c.ClassID + '">' + Icons.Trash + '</button>'
      +   '</div>'
      + '</article>';
  }).join("");

  var typeOpts = '<option>All</option>' + types.map(function (t) {
    return '<option' + (f.type === t ? " selected" : "") + '>' + escapeHtml(t) + '</option>';
  }).join("");
  var trainerOpts = '<option value="All">All trainers</option>' + trainers.map(function (t) {
    return '<option value="' + t.UserID + '"' + (String(f.trainer) === String(t.UserID) ? " selected" : "") + '>' + escapeHtml(t.Name) + '</option>';
  }).join("");
  var activeOpts = ["All", "Active", "Paused"].map(function (s) {
    return '<option' + (f.active === s ? " selected" : "") + '>' + s + '</option>';
  }).join("");

  var bodyHtml = filtered.length === 0
    ? tmplEmptyState("Calendar", "No classes match", "Adjust filters or create a new class.")
    : '<div class="class-admin-grid">' + cards + '</div>';

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Class catalog</h1>'
    +     '<p>Schedule, assign a trainer, pause classes that aren\'t pulling their weight. Members see changes immediately.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-primary" data-action="class-new">' + Icons.Plus + ' New class</button>'
    +   '</div>'
    + '</header>'

    + '<section class="panel">'
    +   '<div class="toolbar">'
    +     '<div class="toolbar-search">' + Icons.Search
    +       '<input type="search" placeholder="Search class name or type..." data-bind="classesFilter.search" value="' + escapeHtml(f.search) + '">'
    +     '</div>'
    +     '<select data-bind="classesFilter.type">' + typeOpts + '</select>'
    +     '<select data-bind="classesFilter.trainer">' + trainerOpts + '</select>'
    +     '<select data-bind="classesFilter.active">' + activeOpts + '</select>'
    +     '<div class="toolbar-spacer"></div>'
    +     '<div class="toolbar-count"><strong>' + filtered.length + '</strong> classes</div>'
    +   '</div>'
    +   bodyHtml
    + '</section>';
}

/* =====================================================================
   EQUIPMENT
   ===================================================================== */
function viewEquipment() {
  var f = state.equipmentFilter;
  var q = f.search.trim().toLowerCase();
  var categories = [];
  state.equipment.forEach(function (e) { if (categories.indexOf(e.Category) < 0) categories.push(e.Category); });

  var filtered = state.equipment.filter(function (e) {
    if (f.category !== "All" && e.Category !== f.category) return false;
    if (f.status   !== "All" && e.Status   !== f.status)   return false;
    if (!q) return true;
    return e.Name.toLowerCase().indexOf(q) >= 0 || e.Location.toLowerCase().indexOf(q) >= 0;
  });

  var statCards = EQUIPMENT_STATUSES.map(function (s) {
    var n = state.equipment.filter(function (e) { return e.Status === s; }).length;
    var icon = s === "Available" ? "Check" : s === "Maintenance" ? "Wrench" : s === "Reserved" ? "Clock" : "Dumbbell";
    return tmplStatCard(s, n, icon);
  }).join("");

  var rows = filtered.map(function (item) {
    var statusStyle = item.Status === "Available"   ? "background:#e1f5ee;color:#085041"
                    : item.Status === "In use"      ? "background:#fcebeb;color:#791f1f"
                    : item.Status === "Reserved"    ? "background:#faeeda;color:#633806"
                    :                                 "background:#ececec;color:#444";
    var statusOptions = EQUIPMENT_STATUSES.map(function (s) {
      return '<option' + (s === item.Status ? " selected" : "") + '>' + s + '</option>';
    }).join("");
    return ''
      + '<div class="equipment-admin-row">'
      +   '<div><h3>' + escapeHtml(item.Name) + '</h3><p>' + escapeHtml(item.Category) + ' · ' + escapeHtml(item.Location) + '</p></div>'
      +   '<div style="color:var(--text-soft);font-size:0.82rem;font-weight:600">Updated ' + escapeHtml(item.UpdatedAt) + '</div>'
      +   '<select data-action="equipment-status" data-id="' + item.EquipmentID + '" '
      +     'style="height:2.4rem;border-radius:12px;padding:0 2rem 0 0.75rem;font-weight:700;border:1px solid var(--border);font-family:inherit;appearance:none;cursor:pointer;'
      +     'background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'8\' viewBox=\'0 0 12 8\' fill=\'none\'%3E%3Cpath d=\'M1 1.5L6 6.5L11 1.5\' stroke=\'%237d675b\' stroke-width=\'1.8\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E&quot;);background-repeat:no-repeat;background-position:right 0.65rem center;'
      +     statusStyle + '">' + statusOptions + '</select>'
      +   '<button class="btn-icon" title="Edit" data-action="equipment-edit" data-id="' + item.EquipmentID + '">' + Icons.Edit + '</button>'
      + '</div>';
  }).join("");

  var catOpts = '<option>All</option>' + categories.map(function (c) {
    return '<option' + (f.category === c ? " selected" : "") + '>' + escapeHtml(c) + '</option>';
  }).join("");
  var statusOpts = '<option>All</option>' + EQUIPMENT_STATUSES.map(function (s) {
    return '<option' + (f.status === s ? " selected" : "") + '>' + s + '</option>';
  }).join("");

  var bodyHtml = filtered.length === 0
    ? tmplEmptyState("Dumbbell", "No equipment matches", "Try clearing filters.")
    : '<div class="table-wrap"><div class="equipment-admin-list">' + rows + '</div></div>';

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Equipment availability</h1>'
    +     '<p>Set status manually when something breaks, gets booked, or comes back from service.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-primary" data-action="equipment-new">' + Icons.Plus + ' Add equipment</button>'
    +   '</div>'
    + '</header>'

    + '<div class="admin-stats">' + statCards + '</div>'

    + '<section class="panel">'
    +   '<div class="toolbar">'
    +     '<div class="toolbar-search">' + Icons.Search
    +       '<input type="search" placeholder="Search by name or location..." data-bind="equipmentFilter.search" value="' + escapeHtml(f.search) + '">'
    +     '</div>'
    +     '<select data-bind="equipmentFilter.category">' + catOpts + '</select>'
    +     '<select data-bind="equipmentFilter.status">' + statusOpts + '</select>'
    +     '<div class="toolbar-spacer"></div>'
    +     '<div class="toolbar-count"><strong>' + filtered.length + '</strong> of ' + state.equipment.length + ' items</div>'
    +   '</div>'
    +   bodyHtml
    + '</section>';
}

/* =====================================================================
   REPORTS
   ===================================================================== */
function viewReports() {
  var f = state.reportsFilter;
  var q = f.search.trim().toLowerCase();

  var filtered = state.reports.filter(function (r) {
    if (f.status   !== "All" && r.Status   !== f.status)   return false;
    if (f.category !== "All" && r.Category !== f.category) return false;
    if (f.priority !== "All" && r.Priority !== f.priority) return false;
    if (!q) return true;
    return r.Subject.toLowerCase().indexOf(q) >= 0
        || r.MemberName.toLowerCase().indexOf(q) >= 0
        || r.Description.toLowerCase().indexOf(q) >= 0;
  });

  var openCount       = state.reports.filter(function (r) { return r.Status === "Open"; }).length;
  var inProgressCount = state.reports.filter(function (r) { return r.Status === "In Progress"; }).length;
  var resolvedCount   = state.reports.filter(function (r) { return r.Status === "Resolved"; }).length;
  var closedCount     = state.reports.filter(function (r) { return r.Status === "Closed"; }).length;

  var statCards = ''
    + tmplStatCard("Open",        openCount,       "Flag",    "", openCount > 0 ? "up" : "flat")
    + tmplStatCard("In Progress", inProgressCount, "Clock",   "", "flat")
    + tmplStatCard("Resolved",    resolvedCount,   "Check",   "", "up")
    + tmplStatCard("Closed",      closedCount,     "Power",   "", "flat");

  function priorityPill(p) {
    var kind = p === "High" ? "pill--priority-high" : p === "Medium" ? "pill--priority-medium" : "pill--priority-low";
    return '<span class="pill ' + kind + '">' + escapeHtml(p) + '</span>';
  }
  function statusPill(s) {
    var kind = s === "Open" ? "pill--report-open"
             : s === "In Progress" ? "pill--report-progress"
             : s === "Resolved" ? "pill--report-resolved"
             : "pill--report-closed";
    return '<span class="pill ' + kind + '">' + escapeHtml(s) + '</span>';
  }
  function categoryPill(c) {
    return '<span class="pill pill--report-category">' + escapeHtml(c) + '</span>';
  }

  var rows = filtered.map(function (r) {
    var isExpanded = state.reportDetail === r.ReportID;
    var respCount = (r.Responses || []).length;

    var row = ''
      + '<div class="report-admin-row' + (isExpanded ? ' is-expanded' : '') + '">'
      +   '<div class="report-admin-row-main" data-action="report-toggle" data-id="' + r.ReportID + '">'
      +     '<div class="report-admin-user">'
      +       tmplAvatar(r.MemberName)
      +       '<div class="table-user-meta">'
      +         '<strong>' + escapeHtml(r.MemberName) + '</strong>'
      +         '<span>@' + escapeHtml(r.MemberUsername) + '</span>'
      +       '</div>'
      +     '</div>'
      +     '<div class="report-admin-subject">'
      +       '<strong>' + escapeHtml(r.Subject) + '</strong>'
      +       '<span>' + escapeHtml(r.Category) + (r.EquipmentName ? ' · ' + escapeHtml(r.EquipmentName) : '') + (r.ClassName ? ' · ' + escapeHtml(r.ClassName) : '') + '</span>'
      +     '</div>'
      +     '<div class="report-admin-badges">'
      +       statusPill(r.Status)
      +       priorityPill(r.Priority)
      +     '</div>'
      +     '<div class="report-admin-meta">'
      +       '<span>' + Icons.Clock + escapeHtml(r.CreatedAt ? r.CreatedAt.substring(0, 10) : '') + '</span>'
      +       (respCount > 0 ? '<span>' + Icons.Message + respCount + '</span>' : '')
      +     '</div>'
      +     '<span class="report-admin-chevron">'
      +       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>'
      +     '</span>'
      +   '</div>';

    if (isExpanded) {
      var statusBtns = REPORT_STATUSES.map(function (s) {
        var cls = r.Status === s ? "btn-primary" : "btn-secondary";
        return '<button class="' + cls + '" style="min-height:2rem;padding:0.35rem 0.9rem;font-size:0.8rem" data-action="report-status" data-id="' + r.ReportID + '" data-status="' + s + '"'
          + (r.Status === s ? ' disabled' : '')
          + '>' + escapeHtml(s) + '</button>';
      }).join('');

      var threadHtml = (r.Responses || []).map(function (resp) {
        var isAdmin = resp.AuthorRole === 'Admin';
        return ''
          + '<div class="report-thread-msg' + (isAdmin ? ' is-admin' : ' is-member') + '">'
          +   '<div class="report-thread-msg-head">'
          +     '<strong>' + escapeHtml(resp.AuthorName) + '</strong>'
          +     '<span class="report-thread-role">' + escapeHtml(resp.AuthorRole) + '</span>'
          +     '<time>' + escapeHtml(resp.CreatedAt || '') + '</time>'
          +   '</div>'
          +   '<p>' + escapeHtml(resp.Message) + '</p>'
          + '</div>';
      }).join('');

      row += ''
        + '<div class="report-admin-detail" id="report-detail-' + r.ReportID + '">'
        +   '<div class="report-detail-desc">'
        +     '<h4>Description</h4>'
        +     '<p>' + escapeHtml(r.Description) + '</p>'
        +   '</div>'
        +   '<div class="report-detail-info">'
        +     categoryPill(r.Category)
        +     priorityPill(r.Priority)
        +     (r.EquipmentName ? '<span class="report-detail-ref">' + Icons.Wrench + escapeHtml(r.EquipmentName) + '</span>' : '')
        +     (r.ClassName ? '<span class="report-detail-ref">' + Icons.Calendar + escapeHtml(r.ClassName) + '</span>' : '')
        +   '</div>'
        +   '<div class="report-detail-status">'
        +     '<h4>Change Status</h4>'
        +     '<div class="report-status-btns">' + statusBtns + '</div>'
        +   '</div>'
        +   (threadHtml ? '<div class="report-detail-thread"><h4>Conversation (' + (r.Responses || []).length + ')</h4>' + threadHtml + '</div>' : '')
        +   '<div class="report-detail-reply">'
        +     '<h4>Reply</h4>'
        +     '<form data-form="report-respond-' + r.ReportID + '" class="report-reply-form">'
        +       '<textarea name="Message" rows="2" placeholder="Type your response…"></textarea>'
        +       '<button type="button" class="btn-primary" data-action="report-respond" data-id="' + r.ReportID + '" style="min-height:2.2rem;padding:0.4rem 1.1rem;font-size:0.85rem;gap:0.3rem">'
        +         Icons.Send + ' Send'
        +       '</button>'
        +     '</form>'
        +   '</div>'
        + '</div>';
    }

    row += '</div>';
    return row;
  }).join('');

  var statusOpts = '<option>All</option>' + REPORT_STATUSES.map(function (s) {
    return '<option' + (f.status === s ? " selected" : "") + '>' + escapeHtml(s) + '</option>';
  }).join('');
  var catOpts = '<option>All</option>' + REPORT_CATEGORIES.map(function (c) {
    return '<option' + (f.category === c ? " selected" : "") + '>' + escapeHtml(c) + '</option>';
  }).join('');
  var prioOpts = '<option>All</option>' + REPORT_PRIORITIES.map(function (p) {
    return '<option' + (f.priority === p ? " selected" : "") + '>' + escapeHtml(p) + '</option>';
  }).join('');

  var bodyHtml = filtered.length === 0
    ? tmplEmptyState("Flag", "No reports match", "Try adjusting your filters or wait for members to submit reports.")
    : '<div class="report-admin-list">' + rows + '</div>';

  return ''
    + '<header class="admin-header">'
    +   '<div class="admin-header-copy">'
    +     '<h1>Reports &amp; Feedback</h1>'
    +     '<p>Review member reports, respond to issues, and update resolution status.</p>'
    +   '</div>'
    +   '<div class="admin-header-actions">'
    +     '<button class="btn-secondary" data-action="refresh">' + Icons.Refresh + ' Refresh</button>'
    +   '</div>'
    + '</header>'

    + '<div class="admin-stats">' + statCards + '</div>'

    + '<section class="panel">'
    +   '<div class="toolbar">'
    +     '<div class="toolbar-search">' + Icons.Search
    +       '<input type="search" placeholder="Search by subject, member…" data-bind="reportsFilter.search" value="' + escapeHtml(f.search) + '">'
    +     '</div>'
    +     '<select data-bind="reportsFilter.status">' + statusOpts + '</select>'
    +     '<select data-bind="reportsFilter.category">' + catOpts + '</select>'
    +     '<select data-bind="reportsFilter.priority">' + prioOpts + '</select>'
    +     '<div class="toolbar-spacer"></div>'
    +     '<div class="toolbar-count"><strong>' + filtered.length + '</strong> of ' + state.reports.length + ' reports</div>'
    +   '</div>'
    +   bodyHtml
    + '</section>';
}

/* =====================================================================
   MODAL TEMPLATES
   ===================================================================== */

function modalUser(m) {
  var isNew = m.isNew;
  var u = m.data || { Name: "", Username: "", Role: m.role || "Member", IsActive: 1 };
  var errs = m.errors || {};
  var title = isNew ? "New user" : "Edit " + (u.Name || "");
  var subtitle = isNew ? "Create an account. They'll receive a welcome email with a one-time setup link." : "@" + (u.Username || "");

  var activeChecked = u.IsActive ? "checked" : "";
  var roles = ["Member", "Trainer", "Admin"].map(function (r) {
    return '<option' + (u.Role === r ? " selected" : "") + '>' + r + '</option>';
  }).join("");

  return ''
    + '<form class="form-grid" data-form="user">'
    +   '<div class="field full"><label>Full name</label>'
    +     '<input name="Name" class="' + (errs.Name ? "has-error" : "") + '" value="' + escapeHtml(u.Name) + '" placeholder="e.g. Ana Pereira">'
    +     (errs.Name ? '<span class="field-error">' + errs.Name + '</span>' : '')
    +   '</div>'
    +   '<div class="field"><label>Username</label>'
    +     '<input name="Username" class="' + (errs.Username ? "has-error" : "") + '" value="' + escapeHtml(u.Username) + '" placeholder="ana_p">'
    +     (errs.Username ? '<span class="field-error">' + errs.Username + '</span>' : '')
    +   '</div>'
    +   '<div class="field"><label>Role</label><select name="Role">' + roles + '</select></div>'
    +   (isNew
        ? '<div class="field full"><label>Temporary password</label><input name="Password" type="text" value="Welcome2026!"><span class="field-hint">The user will be asked to change this on first login.</span></div>'
        : '<div class="field full"><div class="checkbox-row"><input id="f-active" name="IsActive" type="checkbox" ' + activeChecked + '><label for="f-active">Account is active</label></div></div>')
    + '</form>'
    + tmplModalFooter(isNew ? "Create account" : "Save changes", "user");
}

function modalTrainer(m) {
  var t = m.trainer;
  var p = m.profile || { Bio: "", Specializations: "", Certifications: 0 };
  var form = m.data || { Name: t.Name, Bio: p.Bio, Specializations: p.Specializations, Certifications: p.Certifications };
  var activeSpecs = (form.Specializations || "").split(",").map(function (s) { return s.trim(); }).filter(Boolean);

  var specBtns = SPECIALIZATIONS.map(function (s) {
    var on = activeSpecs.indexOf(s) >= 0;
    return '<button type="button" data-action="trainer-spec-toggle" data-spec="' + s + '" class="' + (on ? "btn-primary" : "btn-secondary") + '" style="min-height:2.1rem;padding:0.4rem 0.85rem;font-size:0.82rem">'
      + (on ? Icons.Check : "") + s + '</button>';
  }).join("");

  return ''
    + '<form class="form-grid" data-form="trainer">'
    +   '<div class="field full"><label>Display name</label><input name="Name" value="' + escapeHtml(form.Name) + '"></div>'
    +   '<div class="field full"><label>Bio</label><textarea name="Bio" rows="3" placeholder="A short bio that shows on the public trainer page.">' + escapeHtml(form.Bio) + '</textarea></div>'
    +   '<div class="field full"><label>Specializations</label>'
    +     '<div style="display:flex;flex-wrap:wrap;gap:0.4rem">' + specBtns + '</div>'
    +     '<input type="hidden" name="Specializations" value="' + escapeHtml(form.Specializations) + '">'
    +     '<span class="field-hint">Click to toggle. These appear on the trainer\'s public profile.</span>'
    +   '</div>'
    +   '<div class="field"><label>Certifications on file</label><input name="Certifications" type="number" min="0" max="20" value="' + (form.Certifications || 0) + '"></div>'
    + '</form>'
    + tmplModalFooter("Save profile", "trainer");
}

function modalClass(m) {
  var isNew = m.isNew;
  var trainers = state.users.filter(function (u) { return u.Role === "Trainer"; });
  var c = m.data || { Name: "", Type: "HIIT", TrainerID: trainers[0] ? trainers[0].UserID : "", Day: "Monday", StartTime: "07:00", EndTime: "07:45", Room: ROOMS[0], Capacity: 12, Description: "", IsActive: 1 };
  var errs = m.errors || {};

  var typeOpts = SPECIALIZATIONS.map(function (s) {
    return '<option' + (c.Type === s ? " selected" : "") + '>' + s + '</option>';
  }).join("");
  var trainerOpts = trainers.map(function (t) {
    return '<option value="' + t.UserID + '"' + (String(c.TrainerID) === String(t.UserID) ? " selected" : "") + '>' + escapeHtml(t.Name) + '</option>';
  }).join("");
  var dayOpts = DAYS.map(function (d) {
    return '<option' + (c.Day === d ? " selected" : "") + '>' + d + '</option>';
  }).join("");
  var roomOpts = ROOMS.map(function (r) {
    return '<option' + (c.Room === r ? " selected" : "") + '>' + r + '</option>';
  }).join("");

  return ''
    + '<form class="form-grid" data-form="class">'
    +   '<div class="field full"><label>Class name</label><input name="Name" class="' + (errs.Name ? "has-error" : "") + '" value="' + escapeHtml(c.Name) + '" placeholder="e.g. Threshold Ride">'
    +     (errs.Name ? '<span class="field-error">' + errs.Name + '</span>' : '') + '</div>'
    +   '<div class="field"><label>Type</label><select name="Type">' + typeOpts + '</select></div>'
    +   '<div class="field"><label>Trainer</label><select name="TrainerID" class="' + (errs.TrainerID ? "has-error" : "") + '">' + trainerOpts + '</select>'
    +     (errs.TrainerID ? '<span class="field-error">' + errs.TrainerID + '</span>' : '') + '</div>'
    +   '<div class="field"><label>Day</label><select name="Day">' + dayOpts + '</select></div>'
    +   '<div class="field"><label>Room</label><select name="Room">' + roomOpts + '</select></div>'
    +   '<div class="field"><label>Start time</label><input name="StartTime" type="time" value="' + escapeHtml(c.StartTime) + '"></div>'
    +   '<div class="field"><label>End time</label><input name="EndTime" type="time" value="' + escapeHtml(c.EndTime) + '"></div>'
    +   '<div class="field"><label>Capacity</label><input name="Capacity" type="number" min="1" max="100" class="' + (errs.Capacity ? "has-error" : "") + '" value="' + c.Capacity + '">'
    +     (errs.Capacity ? '<span class="field-error">' + errs.Capacity + '</span>' : '') + '</div>'
    +   '<div class="field"><label>Status</label><select name="IsActive">'
    +     '<option value="1"' + (c.IsActive ? " selected" : "") + '>Active</option>'
    +     '<option value="0"' + (!c.IsActive ? " selected" : "") + '>Paused</option>'
    +   '</select></div>'
    +   '<div class="field full"><label>Description</label><textarea name="Description" rows="2" placeholder="A short blurb shown on the class card.">' + escapeHtml(c.Description) + '</textarea></div>'
    + '</form>'
    + tmplModalFooter(isNew ? "Create class" : "Save changes", "class");
}

function modalEquipment(m) {
  var isNew = m.isNew;
  var e = m.data || { Name: "", Category: "Cardio", Status: "Available", Location: "Main Floor" };
  var errs = m.errors || {};
  var categories = [];
  state.equipment.forEach(function (it) { if (categories.indexOf(it.Category) < 0) categories.push(it.Category); });

  var catOpts = categories.map(function (c) {
    return '<option' + (e.Category === c ? " selected" : "") + '>' + escapeHtml(c) + '</option>';
  }).join("");
  var statusOpts = EQUIPMENT_STATUSES.map(function (s) {
    return '<option' + (e.Status === s ? " selected" : "") + '>' + s + '</option>';
  }).join("");

  return ''
    + '<form class="form-grid" data-form="equipment">'
    +   '<div class="field full"><label>Name</label><input name="Name" class="' + (errs.Name ? "has-error" : "") + '" value="' + escapeHtml(e.Name) + '" placeholder="e.g. Treadmill #4">'
    +     (errs.Name ? '<span class="field-error">' + errs.Name + '</span>' : '') + '</div>'
    +   '<div class="field"><label>Category</label><select name="Category">' + catOpts + '</select></div>'
    +   '<div class="field"><label>Status</label><select name="Status">' + statusOpts + '</select></div>'
    +   '<div class="field full"><label>Location</label><input name="Location" value="' + escapeHtml(e.Location) + '" placeholder="e.g. Cardio Zone"></div>'
    + '</form>'
    + tmplModalFooter(isNew ? "Add to inventory" : "Save", "equipment");
}

function modalUserActions(m) {
  var u = m.user;
  var roleKind = "role-" + u.Role.toLowerCase();

  var editBtn = '<button class="action-menu-btn action-menu-btn--primary" data-action="user-edit-inside-actions" data-id="' + u.UserID + '">' + Icons.Edit + ' <span>Edit User Info</span></button>';

  var promoteBtn = "";
  if (u.Role !== "Admin") {
    promoteBtn = '<button class="action-menu-btn action-menu-btn--secondary" data-action="user-promote-inside-actions" data-id="' + u.UserID + '">' + Icons.Crown + ' <span>Promote to Admin</span></button>';
  }

  var activeBtn = "";
  if (u.IsActive) {
    activeBtn = '<button class="action-menu-btn action-menu-btn--danger" data-action="user-deactivate-inside-actions" data-id="' + u.UserID + '">' + Icons.Power + ' <span>Deactivate Account</span></button>';
  } else {
    activeBtn = '<button class="action-menu-btn action-menu-btn--success" data-action="user-activate-inside-actions" data-id="' + u.UserID + '">' + Icons.Check + ' <span>Reactivate Account</span></button>';
  }

  return ''
    + '<div class="action-sheet">'
    +   '<div class="action-sheet-profile">'
    +     '<div class="action-sheet-avatar-container">' + tmplAvatar(u.Name) + '</div>'
    +     '<div class="action-sheet-meta">'
    +       '<h3 class="action-sheet-name">' + escapeHtml(u.Name) + '</h3>'
    +       '<span class="action-sheet-username">@' + escapeHtml(u.Username) + '</span>'
    +       '<div class="action-sheet-badges">'
    +         tmplPill(roleKind, escapeHtml(u.Role))
    +         tmplPill(u.IsActive ? "active" : "inactive", u.IsActive ? "Active" : "Inactive")
    +       '</div>'
    +     '</div>'
    +   '</div>'
    +   '<div class="action-sheet-menu">'
    +     editBtn
    +     promoteBtn
    +     activeBtn
    +   '</div>'
    +   '<div class="action-sheet-footer">'
    +     '<button class="btn-secondary" style="width:100%" data-action="modal-close">Close</button>'
    +   '</div>'
    + '</div>';
}

function tmplModalFooter(submitLabel, formName) {
  return ''
    + '<footer class="modal-footer">'
    +   '<button type="button" class="btn-secondary" data-action="modal-close">Cancel</button>'
    +   '<button type="button" class="btn-primary" data-action="modal-submit" data-form="' + formName + '">' + escapeHtml(submitLabel) + '</button>'
    + '</footer>';
}

/* =====================================================================
   MODAL SHELL + CONFIRM DIALOG
   ===================================================================== */
function renderModal() {
  if (!state.modal) return "";
  var m = state.modal;
  var title, subtitle, body, width;

  if (m.kind === "user")      { title = m.isNew ? "New user" : "Edit " + (m.data ? m.data.Name : ""); subtitle = m.isNew ? "Create an account. They'll receive a welcome email with a one-time setup link." : "@" + (m.data ? m.data.Username : ""); body = modalUser(m); width = 560; }
  else if (m.kind === "user-actions") { title = "Manage User"; subtitle = ""; body = modalUserActions(m); width = 420; }
  else if (m.kind === "trainer")   { title = "Edit " + m.trainer.Name; subtitle = "@" + m.trainer.Username + " · Trainer profile"; body = modalTrainer(m); width = 620; }
  else if (m.kind === "class")     { title = m.isNew ? "New class" : "Edit " + (m.data ? m.data.Name : ""); subtitle = m.isNew ? "Members will see this in the schedule as soon as you save." : ""; body = modalClass(m); width = 640; }
  else if (m.kind === "equipment") { title = m.isNew ? "Add equipment" : "Edit " + (m.data ? m.data.Name : ""); subtitle = ""; body = modalEquipment(m); width = 520; }
  else return "";

  return ''
    + '<div class="modal-backdrop" data-action="modal-close-bg">'
    +   '<div class="modal" style="width:100%;max-width:' + width + 'px" data-modal-inner>'
    +     '<header class="modal-header">'
    +       '<div>'
    +         '<h2>' + escapeHtml(title) + '</h2>'
    +         (subtitle ? '<p>' + escapeHtml(subtitle) + '</p>' : '')
    +       '</div>'
    +       '<button class="modal-close" data-action="modal-close" aria-label="Close">' + Icons.X + '</button>'
    +     '</header>'
    +     '<div class="modal-body">' + body + '</div>'
    +   '</div>'
    + '</div>';
}

function renderConfirm() {
  if (!state.confirm) return "";
  var c = state.confirm;
  var iconCls = c.kind === "warning" ? "is-warning" : c.kind === "info" ? "is-info" : "";
  var btnCls = c.kind === "danger" ? "btn-danger" : "btn-primary";

  return ''
    + '<div class="modal-backdrop" data-action="confirm-close-bg">'
    +   '<div class="modal" style="width:100%;max-width:460px">'
    +     '<header class="modal-header">'
    +       '<div><h2>' + escapeHtml(c.title) + '</h2></div>'
    +       '<button class="modal-close" data-action="confirm-close" aria-label="Close">' + Icons.X + '</button>'
    +     '</header>'
    +     '<div class="modal-body">'
    +       '<div class="confirm-icon ' + iconCls + '">' + (c.kind === "info" ? Icons.Check : Icons.Warning) + '</div>'
    +       '<p style="color:var(--text-body);font-size:0.95rem;line-height:1.5">' + escapeHtml(c.body) + '</p>'
    +     '</div>'
    +     '<footer class="modal-footer">'
    +       '<button class="btn-secondary" data-action="confirm-close">Cancel</button>'
    +       '<button class="' + btnCls + '" data-action="confirm-yes">' + escapeHtml(c.confirmLabel || "Confirm") + '</button>'
    +     '</footer>'
    +   '</div>'
    + '</div>';
}
