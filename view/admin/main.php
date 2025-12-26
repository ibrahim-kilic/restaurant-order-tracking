<?php /** @var string BASE_URL */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Yönetim • Panel</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root{--header-h:56px}
        html,body{height:100%;overflow:hidden}
        body{background:#f6f7fb;color:#111827;font-family:system-ui,-apple-system,Segoe UI,Roboto}
        .app{min-height:100dvh;display:flex;flex-direction:column}
        .app-header{position:sticky;top:0;z-index:20;height:var(--header-h);background:#fff;border-bottom:1px solid #e5e7eb}
        .app-body{flex:1;min-height:0}
        .container-np{padding-left:12px;padding-right:12px}
        .h1-sm{font-size:1.15rem;margin:0;font-weight:700}
        .text-muted-2{color:#64748b}
        .scroll-y{height:calc(100dvh - var(--header-h));overflow:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
        .no-x{overflow-x:hidden}
        .section-title{font-weight:700;margin:14px 2px 6px}
        .card-link{display:flex;align-items:center;gap:.8rem;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;text-decoration:none;color:#111827;transition:background .15s}
        .card-link:hover{background:#f3f4f6}
        .card-link i{font-size:1.2rem}
        .stat{display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
        .badge-soft{border:1px solid #e5e7eb;border-radius:999px;background:#f8fafc;padding:.2rem .6rem;font-weight:600}
        .offcanvas-start{width:300px}
        .menu-list .item{display:flex;gap:.8rem;align-items:center;padding:12px;border-bottom:1px solid #eef2f7;text-decoration:none;color:#111827}
        .menu-list .item:last-child{border-bottom:0}
        .menu-list i{font-size:1.1rem}
    </style>
</head>
<body class="no-x">
<div class="app">
    <div class="app-header d-flex align-items-center px-2">
        <button class="btn btn-light border me-2" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-label="Menüyü Aç"><i class="bi bi-list"></i></button>
        <div>
            <h1 class="h1-sm">Panel</h1>
            <div class="text-muted-2 small">Genel Bakış</div>
        </div>
    </div>

    <div class="app-body">
        <div class="scroll-y container-np">
            <div class="section-title">Kısayollar</div>
            <div class="d-grid gap-2">
                <a class="card-link" href="<?= BASE_URL ?>/admin/tables"><i class="bi bi-layout-text-window-reverse"></i><span>Masalar</span></a>
                <a class="card-link" href="<?= BASE_URL ?>/admin/categories"><i class="bi bi-folder2-open"></i><span>Kategoriler</span></a>
                <a class="card-link" href="<?= BASE_URL ?>/admin/products"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
                <a class="card-link" href="<?= BASE_URL ?>/admin/reports"><i class="bi bi-graph-up"></i><span>Raporlar</span></a>
                <a class="card-link" href="<?= BASE_URL ?>/cashier/main"><i class="bi bi-grid"></i><span>Kasiyer’e Geç</span></a>
                <a class="card-link" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></a>
            </div>

            <div class="section-title">Durum</div>
            <div class="d-grid gap-2" id="stats">
                <div class="stat"><span>Toplam Masa</span><span class="badge-soft" id="sTables">-</span></div>
                <div class="stat"><span>Boş / Açık / Ödeme</span><span class="badge-soft" id="sSplit">-</span></div>
                <div class="stat"><span>Kategori</span><span class="badge-soft" id="sCats">-</span></div>
                <div class="stat"><span>Ürün</span><span class="badge-soft" id="sProds">-</span></div>
            </div>
            <div style="height:16px"></div>
        </div>
    </div>
</div>

<!-- Menü -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuTitle">
    <div class="offcanvas-header">
        <h6 class="offcanvas-title" id="menuTitle">Menü</h6>
        <button class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="menu-list">
            <a class="item" href="<?= BASE_URL ?>/admin/main"><i class="bi bi-speedometer2"></i><span>Panel</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/tables"><i class="bi bi-layout-text-window-reverse"></i><span>Masalar</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/categories"><i class="bi bi-folder2-open"></i><span>Kategoriler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/products"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/reports"><i class="bi bi-bar-chart"></i><span>Raporlar</span></a>
            <a class="item" href="<?= BASE_URL ?>/cashier/main"><i class="bi bi-grid"></i><span>Kasiyer</span></a>
            <a class="item" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></a>
        </div>
    </div>
</div>

<script>
    (function(){
        const $t = $('#sTables'), $split = $('#sSplit'), $cats = $('#sCats'), $prods = $('#sProds');

        function updateStat(el, val){
            $(el).text(val ?? '-');
        }

        function loadStats(){
            $.getJSON('<?= BASE_URL ?>/cashier/api/tables', {all:1})
                .done(j=>{
                    const rows = j?.data?.tables || [];
                    const total = rows.length;
                    const empty = rows.filter(x=>x.status==='empty' || x.status==='free').length;
                    const open  = rows.filter(x=>x.status==='open').length;
                    const pay   = rows.filter(x=>x.status==='payment').length;
                    updateStat($t, total);
                    updateStat($split, `${empty} / ${open} / ${pay}`);
                })
                .fail(()=>{ updateStat($t,'?'); updateStat($split,'?'); });

            $.getJSON('<?= BASE_URL ?>/admin/api/categories')
                .done(j=> updateStat($cats, (j?.data?.categories || []).length))
                .fail(()=> updateStat($cats,'?'));

            $.getJSON('<?= BASE_URL ?>/admin/api/products')
                .done(j=> updateStat($prods, (j?.data?.products || []).length))
                .fail(()=> updateStat($prods,'?'));
        }

        loadStats();
    })();
</script>
</body>
</html>
