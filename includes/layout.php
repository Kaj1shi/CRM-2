<?php

declare(strict_types=1);

function render(string $page, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/pages/' . $page . '.php';
}

function layout_start(string $active): void
{
    $user = current_user();
    $links = [
        ['to' => '', 'label' => 'Dashboard', 'permission' => 'dashboard.view', 'icon' => 'dashboard'],
        ['to' => 'clients', 'label' => 'Clients', 'permission' => 'clients.view', 'icon' => 'users'],
        ['to' => 'suppliers', 'label' => 'Suppliers', 'permission' => 'suppliers.view', 'icon' => 'truck'],
        ['to' => 'products', 'label' => 'Products', 'permission' => 'products.view', 'icon' => 'package'],
        ['to' => 'transactions', 'label' => 'Transactions', 'permission' => 'transactions.view', 'icon' => 'swap'],
        ['to' => 'reports', 'label' => 'Reports', 'permission' => 'reports.operational', 'icon' => 'report'],
        ['to' => 'users', 'label' => 'Users', 'permission' => 'users.manage', 'icon' => 'users-cog'],
        ['to' => 'audit', 'label' => 'Audit logs', 'permission' => 'audit.view', 'icon' => 'scroll'],
        ['to' => 'settings', 'label' => 'Settings', 'permission' => 'settings.manage', 'icon' => 'settings'],
    ];
    $crumb = $active === '' ? 'dashboard' : str_replace('-', ' ', $active);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Factory CRM</title><meta name="theme-color" content="#059669">';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="stylesheet" href="' . e(url('assets/app.css')) . '">';
    echo '<script>tailwind.config={theme:{extend:{fontFamily:{sans:["Geist Variable","Geist","ui-sans-serif","system-ui","sans-serif"]},colors:{blue:{700:"#047857",800:"#065f46"},slate:{900:"#2c2825",800:"#3f3832",700:"#44403c",600:"#57534e",500:"#78716c",400:"#a8a29e",300:"#e4ddd2",200:"#ebe4da",100:"#f3efe8",50:"#f7f3ec"}}}}}</script>';
    echo '</head><body data-base="' . e(base_path()) . '"><div class="shell min-h-dvh" id="shell">';
    echo '<a class="skip-link" href="#content">Skip to content</a>';
    echo '<aside id="sidebar" class="sidebar hidden md:flex fixed inset-y-0 left-0 z-20 h-dvh w-60 flex-col overflow-hidden bg-emerald-700 text-white sidebar-width">';
    echo '<div class="flex h-full w-60 shrink-0 flex-col py-5"><div class="mb-6 px-3"><div class="flex h-8 items-center pr-14"><p class="sidebar-label truncate text-2xl font-medium leading-none text-white">Factory CRM</p></div>';
    echo '<div class="sidebar-fold"><p class="pt-1 text-xs tracking-[0.14em] text-emerald-100 uppercase">Kampala</p></div></div>';
    echo '<nav class="space-y-1 px-3" aria-label="Primary">';
    foreach ($links as $link) {
        if (!can($link['permission'])) {
            continue;
        }
        $isActive = $active === $link['to'] || ($link['to'] !== '' && str_starts_with($active, $link['to']));
        $class = $isActive ? 'bg-white/20 font-medium' : 'hover:bg-white/10';
        echo '<a class="flex items-center overflow-hidden rounded-md text-sm text-white ' . $class . '" href="' . e(url($link['to'])) . '" title="' . e($link['label']) . '">';
        echo '<span class="grid h-10 w-12 shrink-0 place-items-center">' . icon($link['icon']) . '</span>';
        echo '<span class="sidebar-label whitespace-nowrap">' . e($link['label']) . '</span></a>';
    }
    echo '</nav></div>';
    echo '<div class="absolute top-5 right-5 z-10"><button type="button" id="nav-toggle" class="nav-toggle grid h-8 w-8 place-items-center rounded-md text-white hover:bg-white/10" aria-label="Collapse sidebar">';
    echo '<span class="relative block h-[18px] w-[18px]" aria-hidden="true"><span class="nav-toggle-bar absolute left-0 top-1/2 h-[1.5px] w-full rounded-full bg-current" data-bar="1"></span><span class="nav-toggle-bar absolute left-0 top-1/2 h-[1.5px] w-full rounded-full bg-current" data-bar="2"></span><span class="nav-toggle-bar absolute left-0 top-1/2 h-[1.5px] w-full rounded-full bg-current" data-bar="3"></span></span>';
    echo '</button></div></aside>';
    echo '<div id="offset" class="sidebar-offset min-w-0 md:pl-60">';
    echo '<header class="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-200/80 bg-[#f6f4ef]/90 px-4 py-3 backdrop-blur-md md:px-8">';
    echo '<button type="button" id="menu-open" class="rounded-md border border-slate-300 px-2 py-2 md:hidden" aria-label="Open menu">' . icon('menu') . '</button>';
    echo '<div class="relative w-full max-w-xl"><span class="absolute top-2.5 left-3 text-slate-400">' . icon('search') . '</span>';
    echo '<input id="global-search" aria-label="Search" class="w-full rounded-md border border-slate-300 bg-white/80 py-2 pr-3 pl-9" placeholder="Search code, name, or reference" autocomplete="off">';
    echo '<div id="search-results" class="absolute z-20 mt-1 hidden w-full rounded-md border border-slate-200 bg-[#fffcf8] p-2 text-sm"></div></div>';
    echo '<div class="ml-auto flex items-center gap-3"><span class="hidden text-sm text-slate-600 sm:inline">' . e($user['full_name'] ?? '') . '</span>';
    echo '<form method="post" action="' . e(url('logout')) . '">' . csrf_field();
    echo '<button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">' . icon('logout') . ' Log out</button></form></div></header>';
    echo '<main id="content" class="mx-auto max-w-[1200px] px-4 py-6 md:px-8 md:py-8">';
    echo '<p class="mb-4 text-sm text-slate-500">' . e($crumb) . '</p>';
}

function layout_end(): void
{
    echo '</main></div></div><script src="' . e(url('assets/app.js')) . '"></script></body></html>';
}

function guest_start(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title><script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="stylesheet" href="' . e(url('assets/app.css')) . '">';
    echo '<script>tailwind.config={theme:{extend:{fontFamily:{sans:["Geist Variable","Geist","ui-sans-serif","system-ui","sans-serif"]},colors:{slate:{600:"#57534e",500:"#78716c",300:"#e4ddd2"},red:{700:"#9f2f2d"}}}}}</script>';
    echo '</head><body>';
}

function guest_end(): void
{
    echo '<script src="' . e(url('assets/app.js')) . '"></script></body></html>';
}
