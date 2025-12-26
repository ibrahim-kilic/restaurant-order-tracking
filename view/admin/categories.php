<?php /** @var string BASE_URL */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Yönetim • Kategoriler</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root{--header-h:56px}
        html,body{height:100%;overflow:hidden}
        body{background:#f6f7fb}
        .app{min-height:100dvh;display:flex;flex-direction:column}
        .app-header{position:sticky;top:0;z-index:20;height:var(--header-h);background:#fff;border-bottom:1px solid #e5e7eb}
        .app-body{flex:1;min-height:0}
        .container-np{padding-left:12px;padding-right:12px}
        .h1-sm{font-size:1.15rem;margin:0;font-weight:700}
        .text-muted-2{color:#64748b}
        .scroll-y{height:calc(100dvh - var(--header-h));overflow:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
        .no-x{overflow-x:hidden}
        .row-item{display:grid;grid-template-columns:1fr auto;gap:.5rem;align-items:center;border-bottom:1px dashed #e5e7eb;padding:.6rem .25rem}
        .badge-soft{border:1px solid #e5e7eb;border-radius:999px;background:#f8fafc;padding:.2rem .5rem;font-weight:600}
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
            <h1 class="h1-sm">Kategoriler</h1>
            <div class="text-muted-2 small">Liste • Ekle • Düzenle</div>
        </div>
        <button id="btnNew" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg"></i> Yeni</button>
    </div>

    <div class="app-body">
        <div class="scroll-y container-np">
            <div id="empty" class="alert alert-light border my-3 d-none">Henüz kategori yok.</div>
            <div id="list" class="card p-2 my-3" role="list"></div>
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
            <a class="item" href="<?= BASE_URL ?>/admin/categories"><i class="bi bi-folder2-open"></i><span>Kategoriler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/products"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
            <a class="item" href="<?= BASE_URL ?>/cashier/main"><i class="bi bi-grid"></i><span>Kasiyer</span></a>
            <a class="item" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></a>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true" aria-labelledby="catTitle">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="catTitle">Yeni Kategori</h6>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label" for="catName">Ad *</label>
                    <input id="catName" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="catSort">Sıra</label>
                    <input id="catSort" type="number" class="form-control" inputmode="numeric">
                </div>
                <div id="catMsg" class="text-danger small" style="min-height:18px"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button id="catSave" class="btn btn-primary">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){
        const $list = $('#list');
        const $empty = $('#empty');
        const $modal = $('#catModal');
        const bsModal = new bootstrap.Modal($modal[0]);
        const $name = $('#catName');
        const $sort = $('#catSort');
        const $msg  = $('#catMsg');
        const $save = $('#catSave');
        const csrf  = $('meta[name="csrf-token"]').attr('content') || '';

        // küçük yardımcılar
        const e = s => String(s ?? '').replace(/[&<>"'`=\/]/g, ch => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'
        }[ch] || ch));
        const disable = (el, on)=>$(el).prop('disabled', !!on);

        function load(){
            $.getJSON('<?= BASE_URL ?>/admin/api/categories')
                .done(j => render(j?.status ? (j.data?.categories || []) : []))
                .fail(()=>{ $list.empty(); $empty.removeClass('d-none').text('Bağlantı hatası'); });
        }

        function render(rows){
            $list.empty();
            if(!rows.length){ $empty.removeClass('d-none'); return; }
            $empty.addClass('d-none');

            rows.sort((a,b)=>(+a.sort_order||0)-(+b.sort_order||0)||(a.id-b.id));

            const frag = document.createDocumentFragment();
            rows.forEach(r=>{
                const row = document.createElement('div');
                row.className = 'row-item';
                row.setAttribute('role','listitem');
                row.innerHTML = `
                <div>
                    <div class="fw-semibold">${e(r.name || '-')}</div>
                    <div class="text-muted small">Sıra: ${e(r.sort_order ?? '')}</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm btn-edit" data-id="${e(r.id)}" aria-label="Düzenle">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm btn-del" data-id="${e(r.id)}" aria-label="Sil">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
                frag.appendChild(row);
            });
            $list[0].appendChild(frag);
        }

        // Yeni
        $('#btnNew').on('click', ()=>{
            $modal.removeData('edit-id');
            $('#catTitle').text('Yeni Kategori');
            $name.val('');
            $sort.val('');
            $msg.text('');
            bsModal.show();
            $name.trigger('focus');
        });

        // Düzenle
        $list.on('click','.btn-edit', function(){
            const id = $(this).data('id');
            $('#catTitle').text('Kategori Düzenle');
            $msg.text('Yükleniyor...');
            $modal.data('edit-id', id);
            bsModal.show();

            $.getJSON('<?= BASE_URL ?>/admin/api/category-get', { id })
                .done(j=>{
                    const r = j?.data?.category || {};
                    $name.val(r.name || '');
                    $sort.val(r.sort_order ?? '');
                    $msg.text('');
                    $name.trigger('focus');
                })
                .fail(()=> $msg.text('Bağlantı hatası'));
        });

        // Kaydet
        function doSave(){
            const id   = $modal.data('edit-id') || null;
            const name = ($name.val() || '').trim();
            const sort = $sort.val();

            if(!name){ $msg.text('Ad zorunlu'); $name.trigger('focus'); return; }

            const payload = { csrf: csrf, name };
            if(sort !== '') payload.sort_order = sort;
            if(id) payload.id = id;

            disable($save, true);
            $msg.text('Kaydediliyor...');

            $.post(id ? '<?= BASE_URL ?>/admin/api/category-update' : '<?= BASE_URL ?>/admin/api/category', payload)
                .done(j=>{
                    if(!j?.status){ $msg.text(j?.message || 'Kaydedilemedi'); disable($save,false); return; }
                    bsModal.hide();
                    disable($save,false);
                    load();
                })
                .fail(()=>{ $msg.text('Bağlantı hatası'); disable($save,false); });
        }
        $save.on('click', doSave);
        // Enter ile kaydet
        $modal.on('keydown', function(ev){
            if(ev.key === 'Enter' && !ev.shiftKey){
                ev.preventDefault();
                doSave();
            }
        });

        // Sil
        $list.on('click','.btn-del', function(){
            const id = $(this).data('id');
            Swal.fire({
                title:'Emin misiniz?',
                text:'Kategori silinecek',
                icon:'warning',
                showCancelButton:true,
                confirmButtonText:'Sil',
                cancelButtonText:'Vazgeç'
            }).then(r=>{
                if(!r.isConfirmed) return;
                $.post('<?= BASE_URL ?>/admin/api/category-delete', { csrf: csrf, id })
                    .done(j=>{
                        if(!j?.status){ Swal.fire('Hata', j?.message || 'Silinemedi', 'error'); return; }
                        load();
                    })
                    .fail(()=> Swal.fire('Hata','Bağlantı hatası','error'));
            });
        });

        load();
    })();
</script>
</body>
</html>
