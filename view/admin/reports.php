<?php /** @var string BASE_URL */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Yönetim • Raporlar</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
        .card-soft{border:1px solid #e5e7eb;border-radius:12px;background:#fff}
        .kpi{padding:12px;text-align:center}
        .kpi .v{font-weight:700;font-size:1.15rem}
        .kpi .l{color:#64748b;font-size:.85rem}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
        @media(min-width:992px){.grid-2{grid-template-columns:1fr 1fr}}
        .table-sm td,.table-sm th{padding:.35rem .5rem}
        .offcanvas-start{width:300px}
        .menu-list .item{display:flex;gap:.8rem;align-items:center;padding:12px;border-bottom:1px solid #eef2f7;color:#111827;text-decoration:none}
        .menu-list .item:last-child{border-bottom:0}
        .menu-list i{font-size:1.1rem}
        .section-title{font-weight:700;margin:14px 2px 6px}
        .chip{border:1px solid #d1d5db;background:#fff;border-radius:999px;padding:.25rem .6rem;cursor:pointer}
        .chip.active{background:#111827;color:#fff;border-color:#111827}
    </style>
</head>
<body class="no-x">
<div class="app">
    <div class="app-header d-flex align-items-center px-2">
        <button class="btn btn-light border me-2" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-label="Menüyü Aç"><i class="bi bi-list"></i></button>
        <div><h1 class="h1-sm">Raporlar</h1><div class="text-muted-2 small">owyeah</div></div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <input id="dateFrom" type="date" class="form-control form-control-sm" style="width:140px">
            <input id="dateTo" type="date" class="form-control form-control-sm" style="width:140px">
            <div class="d-none d-md-flex gap-1">
                <span class="chip" data-preset="today">Bugün</span>
                <span class="chip" data-preset="week">Bu Hafta</span>
                <span class="chip" data-preset="month">Bu Ay</span>
            </div>
            <button id="btnRun" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Uygula</button>
        </div>
    </div>

    <div class="app-body">
        <div class="scroll-y container-np">
            <!-- KPIs -->
            <div class="grid-2 my-2">
                <div class="card-soft kpi"><div class="v" id="kpiTotal">-</div><div class="l">Toplam Ciro</div></div>
                <div class="card-soft kpi"><div class="v" id="kpiTickets">-</div><div class="l">Adisyon Sayısı</div></div>
            </div>
            <div class="grid-2 my-2">
                <div class="card-soft kpi"><div class="v" id="kpiAvg">-</div><div class="l">Ortalama Adisyon</div></div>
                <div class="card-soft kpi"><div class="v" id="kpiQty">-</div><div class="l">Toplam Ürün Adedi</div></div>
            </div>

            <!-- Trend + Ödeme -->
            <div class="section-title">Trend</div>
            <div class="card-soft p-2 mb-2"><canvas id="chDaily" height="120"></canvas></div>

            <div class="section-title">Ödeme Türleri</div>
            <div class="card-soft p-2 mb-2"><canvas id="chPayments" height="120"></canvas></div>

            <!-- Kategori + Ürün -->
            <div class="section-title">Kategori Bazında Ciro</div>
            <div class="card-soft p-2 mb-2"><canvas id="chCategory" height="140"></canvas></div>

            <div class="section-title d-flex justify-content-between">
                <span>En Çok Satan Ürünler</span>
                <div>
                    <select id="topLimit" class="form-select form-select-sm" style="width:auto;display:inline-block">
                        <option value="10">Top 10</option>
                        <option value="20" selected>Top 20</option>
                        <option value="50">Top 50</option>
                    </select>
                </div>
            </div>
            <div class="card-soft p-2 mb-2">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Ürün</th><th class="text-end">Adet</th><th class="text-end">Ciro</th></tr></thead>
                        <tbody id="tbTopProducts"><tr><td colspan="4" class="text-muted">Veri yok</td></tr></tbody>
                    </table>
                </div>
            </div>

            <!-- Saatlik + Masa + Personel -->
            <div class="section-title">Saatlik Yoğunluk</div>
            <div class="card-soft p-2 mb-2"><canvas id="chHours" height="120"></canvas></div>

            <div class="section-title">Masa Bazında</div>
            <div class="card-soft p-2 mb-2">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Masa</th><th class="text-end">Adisyon</th><th class="text-end">Ciro</th></tr></thead>
                        <tbody id="tbTables"><tr><td colspan="4" class="text-muted">Veri yok</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="section-title">Personel Bazında</div>
            <div class="card-soft p-2 mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Kullanıcı ID</th><th class="text-end">Adisyon</th><th class="text-end">Ciro</th></tr></thead>
                        <tbody id="tbStaff"><tr><td colspan="4" class="text-muted">Veri yok</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div style="height:16px"></div>
        </div>
    </div>
</div>

<!-- Menü -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuTitle">
    <div class="offcanvas-header"><h6 class="offcanvas-title" id="menuTitle">Menü</h6><button class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button></div>
    <div class="offcanvas-body p-0">
        <div class="menu-list">
            <a class="item" href="<?= BASE_URL ?>/admin/main"><i class="bi bi-speedometer2"></i><span>Panel</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/tables"><i class="bi bi-layout-text-window-reverse"></i><span>Masalar</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/categories"><i class="bi bi-folder2-open"></i><span>Kategoriler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/products"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/reports"><i class="bi bi-graph-up"></i><span>Raporlar</span></a>
            <a class="item" href="<?= BASE_URL ?>/cashier/main"><i class="bi bi-grid"></i><span>Kasiyer</span></a>
            <a class="item" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></a>
        </div>
    </div>
</div>

<script>
    (function(){
        const qsBase = '<?= BASE_URL ?>';
        const $from = $('#dateFrom'), $to = $('#dateTo');
        const todayStr = ()=> new Date().toISOString().slice(0,10);
        function startOfWeek(d){ const dt=new Date(d); const day=(dt.getDay()+6)%7; dt.setDate(dt.getDate()-day); return dt.toISOString().slice(0,10); }
        function startOfMonth(d){ const dt=new Date(d); dt.setDate(1); return dt.toISOString().slice(0,10); }
        function fmtTRY(n){ try{ return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY'}).format(+n||0); } catch { return ((+n||0).toFixed(2)+' ₺'); } }
        function setPreset(p){
            const t=todayStr();
            if(p==='today'){ $from.val(t); $to.val(t); }
            if(p==='week'){  $from.val(startOfWeek(t)); $to.val(t); }
            if(p==='month'){ $from.val(startOfMonth(t)); $to.val(t); }
            runAll();
            $('.chip').removeClass('active'); $(`.chip[data-preset="${p}"]`).addClass('active');
        }
        $('.chip').on('click', function(){ setPreset($(this).data('preset')); });

        let chDaily=null, chPayments=null, chCategory=null, chHours=null;
        function destroy(c){ if(c){ c.destroy(); } }

        async function fetchJSON(url, params){
            const u = new URL(url, location.origin);
            if(params) Object.entries(params).forEach(([k,v])=>{ if(v) u.searchParams.set(k,v); });
            try{
                const r = await fetch(u, {headers:{'X-Requested-With':'fetch'}});
                return await r.json();
            }catch(_){ return {status:false}; }
        }

        async function runAll(){
            const date_from=$from.val(), date_to=$to.val();
            // Sales
            const sales = await fetchJSON(qsBase+'/admin/api/reports/sales',{date_from,date_to});
            const bycat = await fetchJSON(qsBase+'/admin/api/reports/by-category',{date_from,date_to});
            const byprd = await fetchJSON(qsBase+'/admin/api/reports/by-product',{date_from,date_to,limit:$('#topLimit').val()});
            const pays  = await fetchJSON(qsBase+'/admin/api/reports/payments',{date_from,date_to});
            const hours = await fetchJSON(qsBase+'/admin/api/reports/hours',{date_from,date_to});
            const tbls  = await fetchJSON(qsBase+'/admin/api/reports/tables',{date_from,date_to});
            const staff = await fetchJSON(qsBase+'/admin/api/reports/staff',{date_from,date_to});

            // KPIs
            const total = +((sales?.data?.total_sales)||0);
            const days  = (sales?.data?.sales||[]);
            const ticketCount = days.reduce((a,x)=> a + (+x.tickets||0), 0);
            const avg   = ticketCount ? (total / ticketCount) : 0;

// ürün adedi: kategorilerden topla (Top N’den bağımsız)
            const totalQty = (bycat?.data||[]).reduce((a,x)=> a + (+x.qty||0), 0);

            $('#kpiTotal').text(fmtTRY(total));
            $('#kpiTickets').text(String(ticketCount));
            $('#kpiAvg').text(fmtTRY(avg));
            $('#kpiQty').text(String(totalQty));


            // Günlük trend
            destroy(chDaily);
            const dl = days.map(x=>x.date);
            const dv = days.map(x=>+x.amount||0);
            chDaily = new Chart(document.getElementById('chDaily'),{
                type:'line',
                data:{labels:dl,datasets:[{label:'Ciro',data:dv,borderWidth:2}]},
                options:{responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, scales:{y:{beginAtZero:true}}}
            });

            // Ödemeler
            destroy(chPayments);
            const pl=(pays?.data||[]).map(x=>x.method||'Bilinmiyor');
            const pv=(pays?.data||[]).map(x=>+x.total||0);
            chPayments = new Chart(document.getElementById('chPayments'),{
                type:'pie',
                data:{labels:pl,datasets:[{data:pv}]},
                options:{responsive:true, maintainAspectRatio:false}
            });

            // Kategori
            destroy(chCategory);
            const cl=(bycat?.data||[]).map(x=>x.category||'-');
            const cv=(bycat?.data||[]).map(x=>+x.total||0);
            chCategory = new Chart(document.getElementById('chCategory'),{
                type:'bar',
                data:{labels:cl,datasets:[{label:'Ciro',data:cv}]},
                options:{responsive:true, maintainAspectRatio:false, scales:{y:{beginAtZero:true}}}
            });

            // Saatlik
            destroy(chHours);
            const hl=(hours?.data||[]).map(x=>String(x.h).padStart(2,'0')+':00');
            const hv=(hours?.data||[]).map(x=>+x.v||0);
            chHours = new Chart(document.getElementById('chHours'),{
                type:'bar',
                data:{labels:hl,datasets:[{label:'Yoğunluk',data:hv}]},
                options:{responsive:true, maintainAspectRatio:false, scales:{y:{beginAtZero:true}}}
            });

            // Top ürünler tablo
            const $tp = $('#tbTopProducts').empty();
            const rowsP = (byprd?.data||[]);
            if(!rowsP.length){ $tp.html('<tr><td colspan="4" class="text-muted">Veri yok</td></tr>'); }
            rowsP.forEach((r,i)=>{
                $tp.append(`<tr>
                <td>${i+1}</td>
                <td>${escapeHtml(r.name||'-')}</td>
                <td class="text-end">${(+r.qty||0)}</td>
                <td class="text-end">${fmtTRY(r.total||0)}</td>
            </tr>`);
            });

            // Masalar tablo
            const $tt = $('#tbTables').empty();
            const rowsT=(tbls?.data||[]);
            if(!rowsT.length){ $tt.html('<tr><td colspan="4" class="text-muted">Veri yok</td></tr>'); }
            rowsT.forEach((r,i)=>{
                $tt.append(`<tr>
                <td>${i+1}</td>
                <td>${escapeHtml(r.name||('- #'+(r.id||'')))}</td>
                <td class="text-end">${(+r.tickets||0)}</td>
                <td class="text-end">${fmtTRY(r.total||0)}</td>
            </tr>`);
            });

            // Personel tablo
            const $ts = $('#tbStaff').empty();
            const rowsS=(staff?.data||[]);
            if(!rowsS.length){ $ts.html('<tr><td colspan="4" class="text-muted">Veri yok</td></tr>'); }
            rowsS.forEach((r,i)=>{
                $ts.append(`<tr>
                <td>${i+1}</td>
                <td>${escapeHtml(String(r.user_id||'-'))}</td>
                <td class="text-end">${(+r.tickets||0)}</td>
                <td class="text-end">${fmtTRY(r.total||0)}</td>
            </tr>`);
            });
        }

        function escapeHtml(s){ return String(s??'').replace(/[&<>"'`=\/]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'}[ch]||ch)); }

        $('#btnRun').on('click', runAll);
        $('#topLimit').on('change', runAll);

        // Varsayılan: Bu ay
        setPreset('month');
    })();
</script>
</body>
</html>
