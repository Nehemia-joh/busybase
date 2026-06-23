<?php requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'OneSystem BMS') ?> &mdash; OneSystem</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
[x-cloak]{display:none!important}

/* Sidebar */
.sidebar { background: linear-gradient(180deg,#0f172a 0%,#1a1040 60%,#0f172a 100%); }
.nav-group-label { font-size:.65rem; font-weight:700; letter-spacing:.1em; color:#475569; text-transform:uppercase; padding:.5rem .75rem .25rem; display:block; }
.nav-item {
  display:flex; align-items:center; gap:.75rem;
  padding:.6rem .75rem; border-radius:.75rem;
  font-size:.875rem; font-weight:500; color:#94a3b8;
  transition:all .15s ease; cursor:pointer; text-decoration:none;
}
.nav-item:hover { background:rgba(255,255,255,.07); color:#e2e8f0; }
.nav-item.active { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; box-shadow:0 4px 15px rgba(79,70,229,.4); }
.nav-item.active .icon-badge { background:rgba(255,255,255,.2)!important; color:#fff!important; }
.icon-badge {
  width:2rem; height:2rem; border-radius:.5rem; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:.75rem; transition:all .15s;
}

/* Cards */
.stat-card { border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; }
.stat-card .card-orb-1 { position:absolute; width:6rem; height:6rem; border-radius:50%; right:-1.25rem; bottom:-1.25rem; background:rgba(255,255,255,.12); }
.stat-card .card-orb-2 { position:absolute; width:9rem; height:9rem; border-radius:50%; right:-2.5rem; bottom:-2.5rem; background:rgba(255,255,255,.06); }

/* Form helpers */
.form-input { width:100%; border:1.5px solid #e2e8f0; border-radius:.625rem; padding:.5rem .75rem; font-size:.875rem; outline:none; transition:border-color .15s,box-shadow .15s; background:#fff; }
.form-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.15); }
.form-label { display:block; font-size:.8125rem; font-weight:600; color:#374151; margin-bottom:.35rem; }
.table-th { padding:.75rem 1rem; text-align:left; font-size:.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; background:#f9fafb; }
.table-td { padding:.75rem 1rem; font-size:.875rem; color:#374151; }
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:9999px; font-size:.7rem; font-weight:600; }

/* Buttons */
.btn-primary { display:inline-flex; align-items:center; gap:.4rem; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; padding:.5rem 1rem; border-radius:.625rem; font-size:.875rem; font-weight:600; border:none; cursor:pointer; transition:all .15s; box-shadow:0 2px 8px rgba(79,70,229,.3); }
.btn-primary:hover { filter:brightness(1.1); box-shadow:0 4px 12px rgba(79,70,229,.4); transform:translateY(-1px); }
.btn-secondary { display:inline-flex; align-items:center; gap:.4rem; background:#fff; color:#374151; border:1.5px solid #e2e8f0; padding:.5rem 1rem; border-radius:.625rem; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .15s; }
.btn-secondary:hover { background:#f9fafb; border-color:#d1d5db; transform:translateY(-1px); }
.btn-danger { display:inline-flex; align-items:center; gap:.4rem; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; padding:.5rem 1rem; border-radius:.625rem; font-size:.875rem; font-weight:600; border:none; cursor:pointer; transition:all .15s; box-shadow:0 2px 8px rgba(239,68,68,.25); }
.btn-danger:hover { filter:brightness(1.1); transform:translateY(-1px); }
.btn-success { display:inline-flex; align-items:center; gap:.4rem; background:linear-gradient(135deg,#10b981,#059669); color:#fff; padding:.5rem 1rem; border-radius:.625rem; font-size:.875rem; font-weight:600; border:none; cursor:pointer; transition:all .15s; box-shadow:0 2px 8px rgba(16,185,129,.25); }
.btn-success:hover { filter:brightness(1.1); transform:translateY(-1px); }

/* Page card */
.page-card { background:#fff; border-radius:1rem; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid #f1f5f9; }

/* Scrollbar */
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:#334155; border-radius:9999px; }
</style>
</head>
<body class="bg-slate-100 font-sans antialiased" x-data="{ sidebarOpen: true }">

<?php $u = currentUser(); $cp = basename($_SERVER['PHP_SELF']); ?>

<div class="flex h-screen overflow-hidden">

<!-- ══════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<aside class="sidebar flex flex-col flex-shrink-0 transition-all duration-300 z-40"
       :class="sidebarOpen ? 'w-64' : 'w-[4.5rem]'">

  <!-- Logo -->
  <div class="flex items-center gap-3 px-4 py-5 border-b border-white/[.07]">
    <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"
         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <i class="fas fa-layer-group text-white text-sm"></i>
    </div>
    <div x-show="sidebarOpen" x-cloak>
      <div class="font-bold text-white text-base leading-tight">OneSystem</div>
      <div class="text-[.65rem] text-indigo-400 font-medium">Business Management</div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-0.5">

    <!-- OVERVIEW -->
    <span class="nav-group-label" x-show="sidebarOpen" x-cloak>Overview</span>
    <a href="/dashboard.php" class="nav-item <?= $cp==='dashboard.php'?'active':'' ?>">
      <span class="icon-badge bg-amber-400/15 text-amber-400"><i class="fas fa-th-large"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Dashboard</span>
    </a>

    <!-- MANAGEMENT -->
    <?php if (isSuperAdmin() || canManageBranches()): ?>
    <div class="pt-1" x-show="sidebarOpen" x-cloak></div>
    <span class="nav-group-label" x-show="sidebarOpen" x-cloak>Management</span>
    <?php endif; ?>

    <?php if (canManageUsers()): ?>
    <a href="/users.php" class="nav-item <?= $cp==='users.php'?'active':'' ?>">
      <span class="icon-badge bg-violet-400/15 text-violet-400"><i class="fas fa-users"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Users</span>
    </a>
    <?php endif; ?>

    <?php if (isSuperAdmin()): ?>
    <a href="/zones.php" class="nav-item <?= $cp==='zones.php'?'active':'' ?>">
      <span class="icon-badge bg-teal-400/15 text-teal-400"><i class="fas fa-globe-africa"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Zones</span>
    </a>
    <?php endif; ?>

    <?php if (canManageBranches()): ?>
    <a href="/branches.php" class="nav-item <?= $cp==='branches.php'?'active':'' ?>">
      <span class="icon-badge bg-sky-400/15 text-sky-400"><i class="fas fa-store"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Branches</span>
    </a>
    <?php endif; ?>

    <!-- INVENTORY & SALES -->
    <div class="pt-1" x-show="sidebarOpen" x-cloak></div>
    <span class="nav-group-label" x-show="sidebarOpen" x-cloak>Inventory &amp; Sales</span>

    <?php if (canManageStock()): ?>
    <a href="/stock.php" class="nav-item <?= $cp==='stock.php'?'active':'' ?>">
      <span class="icon-badge bg-emerald-400/15 text-emerald-400"><i class="fas fa-boxes"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Stock</span>
    </a>
    <?php endif; ?>

    <a href="/sales.php" class="nav-item <?= $cp==='sales.php'?'active':'' ?>">
      <span class="icon-badge bg-orange-400/15 text-orange-400"><i class="fas fa-cash-register"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Sales</span>
    </a>

    <!-- PROCUREMENT -->
    <?php if (hasRole('super_admin','zone_manager','branch_manager','stock_controller')): ?>
    <div class="pt-1" x-show="sidebarOpen" x-cloak></div>
    <span class="nav-group-label" x-show="sidebarOpen" x-cloak>Procurement</span>

    <a href="/suppliers.php" class="nav-item <?= $cp==='suppliers.php'?'active':'' ?>">
      <span class="icon-badge bg-cyan-400/15 text-cyan-400"><i class="fas fa-truck"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Suppliers</span>
    </a>

    <a href="/purchase_orders.php" class="nav-item <?= $cp==='purchase_orders.php'?'active':'' ?>">
      <span class="icon-badge bg-purple-400/15 text-purple-400"><i class="fas fa-file-invoice"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Purchase Orders</span>
    </a>
    <?php endif; ?>

    <!-- SETTINGS -->
    <?php if (isSuperAdmin()): ?>
    <div class="pt-1" x-show="sidebarOpen" x-cloak></div>
    <span class="nav-group-label" x-show="sidebarOpen" x-cloak>Settings</span>
    <a href="/currency_settings.php" class="nav-item <?= $cp==='currency_settings.php'?'active':'' ?>">
      <span class="icon-badge bg-yellow-400/15 text-yellow-400"><i class="fas fa-coins"></i></span>
      <span x-show="sidebarOpen" x-cloak class="truncate">Currency</span>
    </a>
    <?php endif; ?>

  </nav>

  <!-- User profile footer -->
  <div class="px-2.5 py-3 border-t border-white/[.07]">
    <div class="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-white/5 transition-colors">
      <div class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm text-white"
           style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <?= strtoupper(substr($u['name'],0,2)) ?>
      </div>
      <div x-show="sidebarOpen" x-cloak class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-white truncate"><?= e($u['name']) ?></p>
        <p class="text-[.65rem] text-slate-400 capitalize truncate"><?= e(str_replace('_',' ',$u['role'])) ?></p>
      </div>
      <a href="/logout.php" x-show="sidebarOpen" x-cloak
         class="flex-shrink-0 p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
         title="Sign out" onclick="return confirm('Sign out?')">
        <i class="fas fa-sign-out-alt text-xs"></i>
      </a>
    </div>

    <!-- Collapse toggle -->
    <button @click="sidebarOpen = !sidebarOpen"
      class="mt-2 w-full flex items-center justify-center gap-2 py-2 rounded-xl text-slate-500 hover:text-slate-300 hover:bg-white/5 text-xs transition-colors">
      <i class="fas transition-transform duration-300" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
      <span x-show="sidebarOpen" x-cloak>Collapse</span>
    </button>
  </div>
</aside>

<!-- ══════════════════════════════════════
     MAIN AREA
════════════════════════════════════════ -->
<div class="flex-1 flex flex-col overflow-hidden">

  <!-- Top bar -->
  <header class="bg-white border-b border-slate-100 px-6 py-3 flex items-center justify-between flex-shrink-0 z-30"
          style="box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div class="flex items-center gap-4">
      <!-- Breadcrumb / title -->
      <div class="flex items-center gap-2 text-sm">
        <span class="text-slate-400">OneSystem</span>
        <i class="fas fa-chevron-right text-slate-300 text-xs"></i>
        <span class="font-semibold text-slate-700"><?= e($pageTitle ?? 'Dashboard') ?></span>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <!-- Date chip -->
      <div class="hidden md:flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-full px-3 py-1.5 text-xs text-slate-500">
        <i class="fas fa-calendar-alt text-indigo-400"></i>
        <?= date('D, d M Y') ?>
      </div>

      <!-- Notification bell -->
      <button class="relative p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
        <i class="fas fa-bell text-sm"></i>
      </button>

      <!-- Divider -->
      <div class="h-7 w-px bg-slate-200 mx-1"></div>

      <!-- User menu -->
      <div class="relative" x-data="{ open:false }">
        <button @click="open=!open"
          class="flex items-center gap-2.5 pl-1 pr-3 py-1.5 rounded-xl hover:bg-slate-100 transition-colors text-sm">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-white text-xs"
               style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
            <?= strtoupper(substr($u['name'],0,2)) ?>
          </div>
          <div class="hidden sm:block text-left">
            <p class="font-semibold text-slate-700 leading-tight text-xs"><?= e($u['name']) ?></p>
            <p class="text-[.65rem] text-slate-400 capitalize leading-tight"><?= e(str_replace('_',' ',$u['role'])) ?></p>
          </div>
          <i class="fas fa-chevron-down text-[.6rem] text-slate-400"></i>
        </button>

        <div x-show="open" @click.away="open=false" x-cloak x-transition
          class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50"
          style="box-shadow:0 8px 30px rgba(0,0,0,.12)">
          <div class="px-4 py-2.5 border-b border-slate-100">
            <p class="text-sm font-semibold text-slate-800"><?= e($u['name']) ?></p>
            <p class="text-xs text-slate-400"><?= e($u['username']) ?></p>
          </div>
          <div class="py-1.5 px-2">
            <a href="/logout.php" onclick="return confirm('Sign out?')"
               class="flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-colors">
              <i class="fas fa-sign-out-alt text-xs"></i> Sign Out
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Page content -->
  <main class="flex-1 overflow-auto p-6">
    <?= renderFlash() ?>
