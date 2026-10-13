<?php

declare(strict_types=1);

const STATUS_TONES = [
    'ACTIVE' => 'bg-[#e8f7ef] text-[#0f6b45]',
    'RECORDED' => 'bg-[#e8f7ef] text-[#0f6b45]',
    'INACTIVE' => 'bg-[#f1efeb] text-[#5c574f]',
    'REVERSED' => 'bg-[#fdebec] text-[#9f2f2d]',
    'DORMANT' => 'bg-[#fff1e0] text-[#9a5b12]',
    'AT_RISK' => 'bg-[#fff1e0] text-[#9a5b12]',
    'WEEKLY' => 'bg-[#e8f1fe] text-[#1d4f91]',
    'BIWEEKLY' => 'bg-[#efe8ff] text-[#5b3d9a]',
    'MONTHLY' => 'bg-[#e7f6f2] text-[#0f6b5c]',
    'MULTIPLE_PER_WEEK' => 'bg-[#efe8ff] text-[#5b3d9a]',
    'IRREGULAR' => 'bg-[#f1efeb] text-[#5c574f]',
    'INSUFFICIENT_DATA' => 'bg-[#f1efeb] text-[#5c574f]',
    'CLIENT_PURCHASE' => 'bg-[#e8f7ef] text-[#0f6b45]',
    'SUPPLIER_SUPPLY' => 'bg-[#e8f1fe] text-[#1d4f91]',
    'FINISHED_PRODUCT' => 'bg-[#e7f6f2] text-[#0f6b5c]',
    'RAW_MATERIAL' => 'bg-[#fff1e0] text-[#9a5b12]',
    'OTHER' => 'bg-[#f1efeb] text-[#5c574f]',
    'ADMIN' => 'bg-[#efe8ff] text-[#5b3d9a]',
    'MANAGER' => 'bg-[#e8f1fe] text-[#1d4f91]',
    'STAFF' => 'bg-[#f1efeb] text-[#5c574f]',
    'LOGIN' => 'bg-[#e8f1fe] text-[#1d4f91]',
    'LOGOUT' => 'bg-[#f1efeb] text-[#5c574f]',
    'CREATE' => 'bg-[#e8f7ef] text-[#0f6b45]',
    'UPDATE' => 'bg-[#e8f1fe] text-[#1d4f91]',
    'DEACTIVATE' => 'bg-[#fdebec] text-[#9f2f2d]',
    'REACTIVATE' => 'bg-[#e8f7ef] text-[#0f6b45]',
    'ROLE_CHANGE' => 'bg-[#efe8ff] text-[#5b3d9a]',
    'PASSWORD_CHANGE' => 'bg-[#fff1e0] text-[#9a5b12]',
    'REPORT_EXPORT' => 'bg-[#e7f6f2] text-[#0f6b5c]',
];

function status_text(string $value): string
{
    $tone = STATUS_TONES[$value] ?? 'bg-[#e8f1fe] text-[#1d4f91]';
    $label = strtolower(str_replace('_', ' ', $value));
    return '<span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium capitalize ' . $tone . '">' . e($label) . '</span>';
}

function entity_cell(string $name, ?string $detail = null): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    if ($initials === '') {
        $initials = '?';
    }
    $tones = [
        'bg-[#dbeafe] text-[#1d4f91]',
        'bg-[#dcfce7] text-[#0f6b45]',
        'bg-[#fae8ff] text-[#5b3d9a]',
        'bg-[#ffedd5] text-[#9a5b12]',
        'bg-[#e0e7ff] text-[#3730a3]',
        'bg-[#ccfbf1] text-[#0f766e]',
    ];
    $sum = 0;
    $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($chars as $char) {
        $sum += mb_ord($char);
    }
    $tone = $tones[abs($sum) % count($tones)];
    $detailHtml = $detail ? '<span class="block truncate text-xs text-slate-500">' . e($detail) . '</span>' : '';
    return '<span class="inline-flex min-w-0 items-center gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xs font-semibold ' . $tone . '">' . e($initials) . '</span><span class="min-w-0"><span class="block truncate font-medium text-slate-800">' . e($name) . '</span>' . $detailHtml . '</span></span>';
}

function field(string $label, string $control): string
{
    return '<div class="text-sm"><label class="font-medium text-slate-700">' . e($label) . '</label><div class="mt-1">' . $control . '</div></div>';
}

function input_class(): string
{
    return 'w-full rounded-md border border-slate-300 bg-[#fffcf8] px-3 py-2';
}

function page_header(string $title, string $action = ''): string
{
    return '<div class="mb-6 flex flex-wrap items-end justify-between gap-3"><h1 class="text-4xl font-medium tracking-tight">' . e($title) . '</h1>' . $action . '</div>';
}

function primary_link(string $href, string $label): string
{
    return '<a class="rounded-md bg-emerald-600 px-3.5 py-2 text-sm text-white hover:bg-emerald-700" href="' . e($href) . '">' . e($label) . '</a>';
}

function data_panel(string $inner, ?string $title = null): string
{
    $heading = $title ? '<div class="data-panel__title">' . e($title) . '</div>' : '';
    return '<div class="data-panel">' . $heading . '<div class="overflow-x-auto">' . $inner . '</div></div>';
}

function data_table(array $headers, string $body): string
{
    $head = '';
    foreach ($headers as $header) {
        $head .= '<th>' . e($header) . '</th>';
    }
    return '<table class="data-table"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>';
}

function notice(string $html): string
{
    return '<p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm" role="status">' . $html . '</p>';
}

function error_text(string $message): string
{
    if ($message === '') {
        return '';
    }
    return '<p class="text-sm text-red-700" role="alert">' . e($message) . '</p>';
}

function modal(string $title, string $body, string $closeHref): string
{
    return '<div class="fixed inset-0 z-40 flex items-end justify-center p-4 sm:items-center" role="presentation">'
        . '<a class="absolute inset-0 bg-[#2c2825]/45 backdrop-blur-[2px]" aria-label="Close dialog" href="' . e($closeHref) . '"></a>'
        . '<div role="dialog" aria-modal="true" aria-label="' . e($title) . '" class="relative z-10 max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-[#fffcf8] p-5 shadow-xl sm:p-6">'
        . '<div class="mb-5 flex items-start justify-between gap-3"><h2 class="text-xl font-medium tracking-tight text-slate-900">' . e($title) . '</h2>'
        . '<a class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Close" href="' . e($closeHref) . '"><span aria-hidden="true" class="text-lg leading-none">×</span></a></div>'
        . $body . '</div></div>';
}

function icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h2"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'package' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/>',
        'swap' => '<path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>',
        'report' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 18v-2"/><path d="M12 18v-6"/><path d="M16 18v-4"/>',
        'users-cog' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="19" cy="11" r="2"/><path d="M19 8v1"/><path d="M19 13v1"/><path d="m21.6 9.5-.87.5"/><path d="m17.27 12-.87.5"/><path d="m21.6 12.5-.87-.5"/><path d="m17.27 10-.87-.5"/>',
        'scroll' => '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'menu' => '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
        'check' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m9 12 2 2 4-4"/>',
        'up' => '<path d="m18 9-6-6-6 6"/><path d="M12 3v14"/><path d="M5 21h14"/>',
        'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8"/><path d="M16 12H8"/><path d="M13 16H8"/>',
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function qty_badge($kg, ?string $unit = null, $input = null): string
{
    if ($unit && !in_array($unit, ['KG', 'TONNE'], true)) {
        $text = rtrim(rtrim(number_format((float) $input, 3, '.', ''), '0'), '.') . ' ' . $unit;
    } else {
        $text = format_kg($kg);
    }
    return '<span class="inline-flex rounded-md bg-[#e8f7ef] px-2.5 py-1 text-xs font-medium text-[#0f6b45]">' . e($text) . '</span>';
}
