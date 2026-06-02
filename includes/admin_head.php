<?php
// $pageTitle must be set before including this file
$pageTitle = $pageTitle ?? 'Admin — Harvy Mance Films';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg:    #1a1a2e;
            --sidebar-hover: #16213e;
            --gold:          #c9a84c;
            --gold-hover:    #b8963e;
        }

        body { background: #f4f6f9; }

        /* ── Sidebar ── */
        #sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: width .25s ease;
        }
        #sidebar .brand {
            color: var(--gold);
            font-weight: 700;
            font-size: 1rem;
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #2a2e3e;
            letter-spacing: .4px;
            white-space: nowrap;
            overflow: hidden;
        }
        #sidebar .brand small { display: block; color: #6c757d; font-size: .7rem; font-weight: 400; }
        #sidebar .nav-link {
            color: #adb5bd;
            padding: .6rem 1rem;
            border-radius: 6px;
            margin: 1px 8px;
            font-size: .875rem;
            display: flex;
            align-items: center;
            gap: .55rem;
            transition: background .2s, color .2s;
            white-space: nowrap;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: var(--sidebar-hover);
            color: var(--gold);
        }
        #sidebar .nav-link i { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
        #sidebar .logout-link { color: #dc3545 !important; }
        #sidebar .logout-link:hover { background: rgba(220,53,69,.15) !important; }

        /* ── Main wrapper ── */
        #main-wrapper {
            margin-left: 240px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Topbar ── */
        #topbar {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: .75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 900;
        }
        #topbar .welcome { font-size: .9rem; color: #495057; }
        #topbar .welcome span { color: var(--gold); font-weight: 600; }
        #topbar .search-input {
            width: 260px;
            font-size: .85rem;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            padding: .35rem .9rem;
        }
        #topbar .search-input:focus { outline: none; border-color: var(--gold); box-shadow: none; }

        /* ── Stat cards ── */
        .stat-card {
            border: none;
            border-radius: 10px;
            padding: 1.2rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        .stat-card .icon-box {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .stat-card .count { font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .stat-card .label { font-size: .78rem; color: #6c757d; margin-top: 2px; }

        /* ── Calendar ── */
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .cal-grid .day-header {
            text-align: center;
            font-size: .72rem;
            font-weight: 600;
            color: #6c757d;
            padding: 4px 0;
        }
        .cal-grid .cal-day {
            text-align: center;
            padding: 6px 4px;
            border-radius: 6px;
            font-size: .82rem;
            cursor: default;
            min-height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cal-day.booked { background: var(--gold); color: #fff; font-weight: 600; }
        .cal-day.today  { border: 2px solid var(--gold); font-weight: 700; }

        /* ── Progress ── */
        .progress { border-radius: 20px; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { width: 0; overflow: hidden; }
            #main-wrapper { margin-left: 0; }
        }
    </style>
