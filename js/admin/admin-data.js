/* ==========================================================================
   admin-data.js — seed data, icons, helpers, app state
   No frameworks. Loaded as a plain <script>.
   ========================================================================== */

/* ----- Icons (SVG strings) -------------------------------------------- */
var Icons = {
  Dashboard:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
  Users:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  Whistle:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="13" r="6"/><path d="M15 13h7l-3-3v-4l-4 4"/></svg>',
  Calendar:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  Dumbbell:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5l11 11"/><path d="M21 21l-1 -1"/><path d="M3 3l1 1"/><path d="M18 22l4 -4"/><path d="M2 6l4 -4"/><path d="M3 10l7 -7"/><path d="M14 21l7 -7"/></svg>',
  Search:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
  Plus:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  Edit:        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  Trash:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
  X:           '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  Check:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  Crown:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18l2 -10l5 5l2 -8l2 8l5 -5l2 10z"/><path d="M3 21h18"/></svg>',
  Warning:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  Trend:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/></svg>',
  TrendDown:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 7 9 13 13 9 21 17"/><polyline points="15 17 21 17 21 11"/></svg>',
  Clock:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  Pin:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
  Cap:         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/></svg>',
  Inbox:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
  Power:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>',
  Refresh:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
  Wrench:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>'
};

/* ----- Constants ------------------------------------------------------- */
var SPECIALIZATIONS = ["HIIT", "Yoga", "Pilates", "Strength", "Mobility", "Spinning", "Boxing", "CrossFit", "Cardio", "Calisthenics", "Stretching"];
var ROOMS = ["Studio A", "Studio B", "Studio C", "Spin Room", "Main Floor", "Combat Room"];
var DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
var EQUIPMENT_STATUSES = ["Available", "In use", "Reserved", "Maintenance"];

/* ----- Seed data (matches your DB schema) ------------------------------ */
var seedUsers = [
  { UserID: 1,  Name: "Admin User",     Username: "admin",   Role: "Admin",   IsActive: 1, Joined: "2024-08-12", LastLogin: "Today, 09:14" },
  { UserID: 2,  Name: "Marta Costa",    Username: "member",  Role: "Member",  IsActive: 1, Joined: "2025-02-04", LastLogin: "Today, 07:52" },
  { UserID: 3,  Name: "Ana Pereira",    Username: "ana",     Role: "Trainer", IsActive: 1, Joined: "2024-09-21", LastLogin: "Yesterday, 18:30" },
  { UserID: 4,  Name: "Ricardo Faria",  Username: "ricardo", Role: "Trainer", IsActive: 1, Joined: "2024-11-03", LastLogin: "Today, 06:10" },
  { UserID: 5,  Name: "Sofia Almeida",  Username: "sofia",   Role: "Trainer", IsActive: 1, Joined: "2025-01-14", LastLogin: "2 days ago" },
  { UserID: 6,  Name: "David Marques",  Username: "david",   Role: "Trainer", IsActive: 0, Joined: "2024-07-08", LastLogin: "3 weeks ago" },
  { UserID: 7,  Name: "Inês Silva",     Username: "ines",    Role: "Member",  IsActive: 1, Joined: "2025-03-22", LastLogin: "Today, 12:01" },
  { UserID: 8,  Name: "João Ribeiro",   Username: "joao_r",  Role: "Member",  IsActive: 1, Joined: "2024-12-30", LastLogin: "Today, 11:44" },
  { UserID: 9,  Name: "Carla Mendes",   Username: "carla",   Role: "Member",  IsActive: 1, Joined: "2025-04-01", LastLogin: "Yesterday, 19:05" },
  { UserID: 10, Name: "Pedro Santos",   Username: "pedro_s", Role: "Member",  IsActive: 1, Joined: "2025-04-18", LastLogin: "Yesterday, 08:22" },
  { UserID: 11, Name: "Beatriz Lopes",  Username: "bea",     Role: "Member",  IsActive: 1, Joined: "2025-05-02", LastLogin: "4 days ago" },
  { UserID: 12, Name: "Miguel Sousa",   Username: "miguel",  Role: "Member",  IsActive: 0, Joined: "2024-10-11", LastLogin: "2 months ago" },
  { UserID: 13, Name: "Helena Cruz",    Username: "helena",  Role: "Member",  IsActive: 1, Joined: "2025-01-29", LastLogin: "Today, 10:33" },
  { UserID: 14, Name: "Tiago Nunes",    Username: "tiago",   Role: "Member",  IsActive: 1, Joined: "2025-03-14", LastLogin: "Yesterday, 21:09" },
  { UserID: 15, Name: "Rita Carvalho",  Username: "rita",    Role: "Member",  IsActive: 1, Joined: "2025-02-19", LastLogin: "Today, 08:47" }
];

var seedTrainerProfiles = {
  3: { Bio: "Strength & conditioning coach. Yes, those are her squats on Instagram.", Specializations: "Strength, HIIT, Mobility", Certifications: 4 },
  4: { Bio: "Ex-cyclist turned spin instructor. Pain is a feature, not a bug.",       Specializations: "Spinning, Cardio, HIIT",    Certifications: 3 },
  5: { Bio: "Yoga and breathwork. Promises not to chant. Much.",                       Specializations: "Yoga, Pilates, Mobility",   Certifications: 5 },
  6: { Bio: "Combat sports background. On sabbatical.",                                Specializations: "Boxing, CrossFit",          Certifications: 2 }
};

var seedClasses = [
  { ClassID: 101, Name: "Sunrise HIIT",        Type: "HIIT",     TrainerID: 3, Day: "Monday",    StartTime: "07:00", EndTime: "07:45", Room: "Studio A",   Capacity: 16, Enrolled: 14, IsActive: 1, Description: "Wake up, burn out, repeat." },
  { ClassID: 102, Name: "Power Hour Strength", Type: "Strength", TrainerID: 3, Day: "Wednesday", StartTime: "18:00", EndTime: "19:00", Room: "Main Floor", Capacity: 12, Enrolled: 12, IsActive: 1, Description: "Compound lifts, big weights, bigger ego (just kidding)." },
  { ClassID: 103, Name: "Spin & Sweat",        Type: "Spinning", TrainerID: 4, Day: "Tuesday",   StartTime: "19:30", EndTime: "20:15", Room: "Spin Room",  Capacity: 20, Enrolled: 18, IsActive: 1, Description: "Beat-matched climbs. You will hate the playlist by minute 30." },
  { ClassID: 104, Name: "Threshold Ride",      Type: "Spinning", TrainerID: 4, Day: "Thursday",  StartTime: "06:30", EndTime: "07:15", Room: "Spin Room",  Capacity: 20, Enrolled: 9,  IsActive: 1, Description: "FTP test territory. Bring a towel and lower expectations." },
  { ClassID: 105, Name: "Morning Flow Yoga",   Type: "Yoga",     TrainerID: 5, Day: "Monday",    StartTime: "08:30", EndTime: "09:30", Room: "Studio C",   Capacity: 18, Enrolled: 17, IsActive: 1, Description: "Slow ramp, deep breath, exit calm." },
  { ClassID: 106, Name: "Reformer Pilates",    Type: "Pilates",  TrainerID: 5, Day: "Friday",    StartTime: "12:00", EndTime: "12:50", Room: "Studio B",   Capacity: 8,  Enrolled: 8,  IsActive: 1, Description: "Core focus, controlled movement, surprising soreness." },
  { ClassID: 107, Name: "Mobility Lab",        Type: "Mobility", TrainerID: 3, Day: "Saturday",  StartTime: "10:00", EndTime: "10:45", Room: "Studio C",   Capacity: 14, Enrolled: 6,  IsActive: 1, Description: "Hip openers. Bring patience, leave with range of motion." },
  { ClassID: 108, Name: "Boxing Fundamentals", Type: "Boxing",   TrainerID: 6, Day: "Wednesday", StartTime: "20:00", EndTime: "21:00", Room: "Combat Room",Capacity: 10, Enrolled: 0,  IsActive: 0, Description: "On pause while David is on sabbatical." },
  { ClassID: 109, Name: "Lunchtime Pilates",   Type: "Pilates",  TrainerID: 5, Day: "Tuesday",   StartTime: "12:30", EndTime: "13:15", Room: "Studio B",   Capacity: 12, Enrolled: 11, IsActive: 1, Description: "45 minutes. In and out before your meeting." },
  { ClassID: 110, Name: "Weekend Warrior HIIT",Type: "HIIT",     TrainerID: 3, Day: "Saturday",  StartTime: "09:00", EndTime: "09:45", Room: "Main Floor", Capacity: 18, Enrolled: 13, IsActive: 1, Description: "Saturday morning's least forgiving alarm." }
];

var seedEquipment = [
  { EquipmentID: 201, Name: "Treadmill #1",        Category: "Cardio",       Status: "Available",   Location: "Cardio Zone", UpdatedAt: "10 min ago" },
  { EquipmentID: 202, Name: "Treadmill #2",        Category: "Cardio",       Status: "In use",      Location: "Cardio Zone", UpdatedAt: "2 min ago" },
  { EquipmentID: 203, Name: "Treadmill #3",        Category: "Cardio",       Status: "Maintenance", Location: "Cardio Zone", UpdatedAt: "Yesterday" },
  { EquipmentID: 204, Name: "Rowing Machine A",    Category: "Cardio",       Status: "Available",   Location: "Cardio Zone", UpdatedAt: "5 min ago" },
  { EquipmentID: 205, Name: "Rowing Machine B",    Category: "Cardio",       Status: "Available",   Location: "Cardio Zone", UpdatedAt: "1 hr ago" },
  { EquipmentID: 206, Name: "Stationary Bike",     Category: "Cardio",       Status: "Reserved",    Location: "Cardio Zone", UpdatedAt: "20 min ago" },
  { EquipmentID: 207, Name: "Squat Rack #1",       Category: "Strength",     Status: "In use",      Location: "Main Floor",  UpdatedAt: "Just now" },
  { EquipmentID: 208, Name: "Squat Rack #2",       Category: "Strength",     Status: "Available",   Location: "Main Floor",  UpdatedAt: "8 min ago" },
  { EquipmentID: 209, Name: "Bench Press",         Category: "Strength",     Status: "In use",      Location: "Main Floor",  UpdatedAt: "Just now" },
  { EquipmentID: 210, Name: "Cable Machine",       Category: "Strength",     Status: "Available",   Location: "Main Floor",  UpdatedAt: "15 min ago" },
  { EquipmentID: 211, Name: "Dumbbell Set 5-30kg", Category: "Free Weights", Status: "Available",   Location: "Main Floor",  UpdatedAt: "1 hr ago" },
  { EquipmentID: 212, Name: "Kettlebell Set",      Category: "Free Weights", Status: "Available",   Location: "Studio A",    UpdatedAt: "2 hr ago" },
  { EquipmentID: 213, Name: "Olympic Barbells",    Category: "Free Weights", Status: "Maintenance", Location: "Main Floor",  UpdatedAt: "Yesterday" },
  { EquipmentID: 214, Name: "Reformer #1",         Category: "Pilates",      Status: "Reserved",    Location: "Studio B",    UpdatedAt: "30 min ago" },
  { EquipmentID: 215, Name: "Reformer #2",         Category: "Pilates",      Status: "Available",   Location: "Studio B",    UpdatedAt: "1 hr ago" },
  { EquipmentID: 216, Name: "Yoga Mat Bundle",     Category: "Studio",       Status: "Available",   Location: "Studio C",    UpdatedAt: "Earlier today" }
];

var seedActivity = [
  { id: 1, icon: "user-plus", tone: "green",   text: "<strong>Rita Carvalho</strong> joined as a Member.",            time: "10 minutes ago" },
  { id: 2, icon: "edit",      tone: "default", text: "<strong>Sunrise HIIT</strong> capacity raised from 14 to 16.",  time: "32 minutes ago" },
  { id: 3, icon: "crown",     tone: "default", text: "<strong>Inês Silva</strong> was promoted to Admin.",            time: "1 hour ago" },
  { id: 4, icon: "wrench",    tone: "red",     text: "<strong>Treadmill #3</strong> marked as Maintenance by Ana P.", time: "Yesterday" },
  { id: 5, icon: "pause",     tone: "default", text: "<strong>Boxing Fundamentals</strong> deactivated.",             time: "2 days ago" },
  { id: 6, icon: "user-plus", tone: "green",   text: "<strong>Beatriz Lopes</strong> joined as a Member.",            time: "3 days ago" }
];

/* ----- Global app state ------------------------------------------------ */
var state = {
  view: "dashboard",
  users: seedUsers.slice(),
  classes: seedClasses.slice(),
  equipment: seedEquipment.slice(),
  trainerProfiles: Object.assign({}, seedTrainerProfiles),
  activity: seedActivity.slice(),

  // Per-view filters
  members: { search: "", role: "All", status: "All" },
  classesFilter: { search: "", type: "All", trainer: "All", active: "All" },
  equipmentFilter: { search: "", category: "All", status: "All" },

  // Modal state: { kind, ... } | null
  modal: null,
  // Confirm state: { title, body, kind, confirmLabel, onConfirm } | null
  confirm: null
};

/* ----- Helpers --------------------------------------------------------- */
function escapeHtml(s) {
  if (s == null) return "";
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function initials(name) {
  var parts = (name || "").split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map(function (s) { return s.charAt(0).toUpperCase(); }).join("");
}

function classify(s) { return (s || "").toLowerCase().replace(/\s+/g, "-"); }

function findById(arr, key, id) {
  for (var i = 0; i < arr.length; i++) if (arr[i][key] === id) return arr[i];
  return null;
}

function nextId(arr, key) {
  var max = 0;
  for (var i = 0; i < arr.length; i++) if (arr[i][key] > max) max = arr[i][key];
  return max + 1;
}
