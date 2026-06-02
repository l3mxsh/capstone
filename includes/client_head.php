<?php
$pageTitle = $pageTitle ?? 'Client Portal — Harvy Mance Films';
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
            --gold:        #c9a84c;
            --gold-hover:  #b8963e;
            --sidebar-bg:  #1a1a2e;
            --sidebar-hover: #16213e;
        }

        body { background: #f4f6f9; }

        /* ── Client Sidebar ── */
        #client-sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        #client-sidebar .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1.1rem 1rem;
            border-bottom: 1px solid #2a2e3e;
        }
        #client-sidebar .brand-logo {
            width: 36px; height: 36px;
            background: var(--gold);
            color: #1a1a2e;
            font-weight: 800;
            font-size: .8rem;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        #client-sidebar .brand > div {
            color: var(--gold);
            font-weight: 700;
            font-size: .875rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
        }
        #client-sidebar .brand small { display: block; color: #6c757d; font-size: .68rem; font-weight: 400; }
        #client-sidebar .nav-link {
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
        #client-sidebar .nav-link:hover,
        #client-sidebar .nav-link.active {
            background: var(--sidebar-hover);
            color: var(--gold);
        }
        #client-sidebar .nav-link i { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
        #client-sidebar .logout-link { color: #dc3545 !important; }
        #client-sidebar .logout-link:hover { background: rgba(220,53,69,.15) !important; }

        /* ── Main wrapper ── */
        #client-main {
            margin-left: 240px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Topbar ── */
        #client-topbar {
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
        #client-topbar .page-label { font-weight: 600; font-size: .95rem; color: #212529; }
        #client-topbar .user-pill {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            color: #495057;
        }
        #client-topbar .avatar {
            width: 32px; height: 32px;
            background: var(--gold);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: .8rem;
        }

        /* ── Cards & Utilities ── */
        .portal-card {
            background: #fff;
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
        }
        .section-title {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6c757d;
            margin-bottom: .6rem;
        }
        .status-badge-lg {
            font-size: .78rem;
            padding: .3rem .65rem;
            border-radius: 20px;
        }
        .progress { border-radius: 20px; }
        .progress-lg { height: 10px; }

        /* ── Quick link buttons ── */
        .quick-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: 1.1rem .5rem;
            border-radius: 12px;
            border: 1.5px solid #e0e0e0;
            background: #fff;
            color: #495057;
            text-decoration: none;
            font-size: .8rem;
            font-weight: 600;
            transition: border-color .2s, color .2s, box-shadow .2s;
        }
        .quick-btn i { font-size: 1.5rem; color: var(--gold); }
        .quick-btn:hover {
            border-color: var(--gold);
            color: var(--gold);
            box-shadow: 0 2px 8px rgba(201,168,76,.15);
        }

        /* ── Welcome banner ── */
        .welcome-banner {
            background: linear-gradient(135deg, #1a1a2e 60%, #2a2250);
            border-radius: 14px;
            padding: 1.6rem 2rem;
            color: #fff;
        }
        .welcome-banner .gold { color: var(--gold); }

        @media (max-width: 768px) {
            #client-sidebar { width: 0; overflow: hidden; }
            #client-main    { margin-left: 0; }
        }
    </style>
