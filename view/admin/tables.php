<?php /** @var string BASE_URL */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Yönetim • Masalar</title>
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
        .row-item{display:grid;grid-template-columns:1fr 100px 80px auto;gap:.5rem;align-items:center;border-bottom:1px dashed #e5e7eb;padding:.6rem .25rem}
        @media(max-width:576px){.row-item{grid-template-columns:1fr 100px auto}}
        .badge-soft{border:1px solid #e5e7eb;border-radius:999px;background:#f8fafc;padding:.2rem .5rem;font-weight:600}
        .offcanvas-start{width:300px}
        .menu-list .item{display:flex;gap:.8rem;align-items:center;padding:12px;border-bottom:1px solid #eef2f7;text-decoration:none;color:#111827}
        .menu-list .item:last-child{border-bottom:0}
        .menu-list i{font-size:1.1rem}

        /* iOS switch */
        .ios-switch{ --w:48px; --h:28px; --p:2px; position:relative; width:var(--w); height:var(--h); display:inline-block }
        .ios-switch input{ position:absolute; inset:0; opacity:0 }
        .ios-switch .track{
            position:absolute; inset:0; border-radius:999px; background:#e5e7eb; transition:all .2s ease; box-shadow:inset 0 0 0 1px #d1d5db;
        }
        .ios-switch .thumb{
            position:absolute; top:var(--p); left:var(--p);
            width:calc(var(--h) - 2*var(--p)); height:calc(var(--h) - 2*var(--p));
            border-radius:999px; background:#fff; transition:transform .2s ease, box-shadow .2s;
            box-shadow:0 2px 6px rgba(0,0,0,.15);
        }
        .ios-switch input:checked + .track{ background:#34c759; box-shadow:inset 0 0 0 1px #2bb24d }
        .ios-switch input:checked + .track .thumb{ transform:translateX(calc(var(--w) - var(--h))) }
        .ios-switch:active .thumb{ box-shadow:0 2px 10px rgba(0,0,0,.25) }
    </style>
</head>
<body class="no-x">
<div class="app">
    <div class="app-header d-flex align-items-center px-2">
        <button class="btn btn-light border me-2" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-label="Menüyü Aç"><i class="bi bi-list"></i></button>
        <div><h1 class="h1-sm">Masalar</h1><div class="text-muted-2 small">Liste • Aktif • Düzenle</div></div>
        <button id="btnNew" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg"></i> Yeni</button>
    </div>

    <div class="app-body">
        <div class="scroll-y container-np">
            <div id="empty" class="alert alert-light border my-3 d-none">Henüz masa yok.</div>
            <div id="list" class="card p-2 my-3"></div>
        </div>
    </div>
</div>

<!-- Menü -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuTitle">
    <div class="offcanvas-header"><h6 class="offcanvas-title" id="menuTitle">Menü</h6><button class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button></div>
    <div class="offcanvas-body p-0">
        <div class="menu-list">
            <a class="item" href="<?= BASE_URL ?>/admin/main"><i class="bi bi-speedometer2"></i><span>Panel</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/categories"><i class="bi bi-folder2-open"></i><span>Kategoriler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/products"><i class="bi bi-box-seam"></i><span>Ürünler</span></a>
            <a class="item" href="<?= BASE_URL ?>/admin/reports"><i class="bi bi-bar-chart"></i><span>Raporlar</span></a>
            <a class="item" href="<?= BASE_URL ?>/cashier/main"><i class="bi bi-grid"></i><span>Kasiyer</span></a>
            <a class="item" href="<?= BASE_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i><span>Çıkış</span></a>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="tblModal" tabindex="-1" aria-hidden="true" aria-labelledby="tblTitle">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="tblTitle">Yeni Masa</h6><button class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label" for="tblName">Ad *</label><input id="tblName" type="text" class="form-control" autocomplete="off"></div>
                <div class="mb-2"><label class="form-label" for="tblSort">Sıra</label><input id="tblSort" type="number" class="form-control" inputmode="numeric"></div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="tblActive" checked>
                    <label class="form-check-label" for="tblActive">Aktif</label>
                </div>
                <div id="tblMsg" class="text-danger small" style="min-height:18px"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button><button id="tblSave" class="btn btn-primary">Kaydet</button></div>
        </div>
    </div>
</div>

<script>
    (function(){
        const $list=$('#list'),$empty=$('#empty'),$modal=$('#tblModal'),bsModal=new bootstrap.Modal($modal[0]);
        const $msg=$('#tblMsg'),$save=$('#tblSave'); const csrf=$('meta[name="csrf-token"]').attr('content')||'';

        const e=s=>String(s??'').replace(/[&<>"'`=\/]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'}[ch]||ch));

        function load(){
            $.getJSON('<?= BASE_URL ?>/admin/api/tables')
                .done(j=>render(j?.data?.tables||[]))
                .fail(()=>{ $list.empty(); $empty.removeClass('d-none').text('Bağlantı hatası'); });
        }

        function render(rows){
            $list.empty();
            if(!rows.length){ $empty.removeClass('d-none'); return; }
            $empty.addClass('d-none');

            rows.sort((a,b)=>(+a.sort_order||0)-(+b.sort_order||0)||(a.id-b.id));
            const frag=document.createDocumentFragment();
            rows.forEach(r=>{
                const active=(r.is_active==null)?1:(+r.is_active?1:0);
                const row=document.createElement('div'); row.className='row-item';
                row.innerHTML=`
                <div>
                    <div class="fw-semibold">${e(r.name||'-')}</div>
                    <div class="text-muted small">Sıra: ${e(r.sort_order??'')}</div>
                </div>
                <div class="text-center">
                    <label class="ios-switch" title="${active?'Pasife al':'Aktif et'}">
                        <input type="checkbox" class="js-toggle" data-id="${e(r.id)}" ${active?'checked':''}>
                        <span class="track"><span class="thumb"></span></span>
                    </label>
                </div>
                <div class="text-end">
                    <button class="btn btn-outline-secondary btn-sm me-1 btn-edit" data-id="${e(r.id)}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger btn-sm btn-del" data-id="${e(r.id)}"><i class="bi bi-trash"></i></button>
                </div>`;
                frag.appendChild(row);
            });
            $list[0].appendChild(frag);
        }

        $('#btnNew').on('click',()=>{
            $modal.removeData('edit-id');
            $('#tblTitle').text('Yeni Masa');
            $('#tblName,#tblSort').val(''); $('#tblActive').prop('checked',true); $msg.text('');
            bsModal.show(); $('#tblName').trigger('focus');
        });

        function doSave(){
            const id=$modal.data('edit-id')||null;
            const name=($('#tblName').val()||'').trim();
            const sort=$('#tblSort').val();
            const act=$('#tblActive').is(':checked')?1:0;
            if(!name){$msg.text('Ad zorunlu');return;}
            const payload={csrf:csrf,name,is_active:act};
            if(sort!=='')payload.sort_order=sort;
            if(id)payload.id=id;
            $msg.text('Kaydediliyor...');$save.prop('disabled',true);
            $.post(id?'<?= BASE_URL ?>/admin/api/table-update':'<?= BASE_URL ?>/admin/api/table',payload)
                .done(j=>{
                    if(!j?.status){$msg.text(j?.message||'Kaydedilemedi');$save.prop('disabled',false);return;}
                    bsModal.hide();$save.prop('disabled',false);load();
                })
                .fail(()=>{$msg.text('Bağlantı hatası');$save.prop('disabled',false);});
        }
        $save.on('click',doSave);
        $modal.on('keydown',ev=>{if(ev.key==='Enter'&&!ev.shiftKey){ev.preventDefault();doSave();}});

        $list.on('click','.btn-edit',function(){
            const id=$(this).data('id');
            $msg.text('Yükleniyor...');
            $modal.data('edit-id',id);
            $('#tblTitle').text('Masa Düzenle'); bsModal.show();
            $.getJSON('<?= BASE_URL ?>/admin/api/table-get',{id})
                .done(j=>{
                    const r=j?.data?.table||{};
                    $('#tblName').val(r.name||''); $('#tblSort').val(r.sort_order??'');
                    $('#tblActive').prop('checked',(r.is_active==null)?true:(+r.is_active?true:false));
                    $msg.text(''); $('#tblName').trigger('focus');
                })
                .fail(()=> $msg.text('Bağlantı hatası'));
        });

        $list.on('click','.btn-del',function(){
            const id=$(this).data('id');
            Swal.fire({title:'Emin misiniz?',text:'Masa silinecek',icon:'warning',showCancelButton:true,confirmButtonText:'Sil',cancelButtonText:'Vazgeç'})
                .then(r=>{
                    if(!r.isConfirmed)return;
                    $.post('<?= BASE_URL ?>/admin/api/table-delete',{csrf:csrf,id})
                        .done(j=>{if(!j?.status){Swal.fire('Hata',j?.message||'Silinemedi','error');return;}load();})
                        .fail(()=>Swal.fire('Hata','Bağlantı hatası','error'));
                });
        });

        $list.on('change','.js-toggle',function(){
            const id=$(this).data('id'),active=this.checked?1:0,self=this;
            $.post('<?= BASE_URL ?>/admin/api/table-toggle',{csrf:csrf,id,is_active:active})
                .done(j=>{if(!j?.status){Swal.fire('Hata',j?.message||'Güncellenemedi','error');self.checked=!active;}})
                .fail(()=>{Swal.fire('Hata','Bağlantı hatası','error');self.checked=!active;});
        });

        load();
    })();
</script>
</body>
</html>
