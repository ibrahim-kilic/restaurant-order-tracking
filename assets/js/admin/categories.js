// Admin • Kategoriler
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

    const e = s => String(s ?? '').replace(/[&<>"'`=\/]/g, ch => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'
    }[ch] || ch));
    const disable = (el, on)=>$(el).prop('disabled', !!on);

    function load(){
        $.getJSON(`${BASE_URL}/admin/api/categories`)
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
        $name.val(''); $sort.val(''); $msg.text('');
        bsModal.show(); $name.trigger('focus');
    });

    // Düzenle
    $list.on('click','.btn-edit', function(){
        const id = $(this).data('id');
        $('#catTitle').text('Kategori Düzenle');
        $msg.text('Yükleniyor...');
        $modal.data('edit-id', id);
        bsModal.show();

        $.getJSON(`${BASE_URL}/admin/api/category-get`, { id })
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

        $.post(id ? `${BASE_URL}/admin/api/category-update` : `${BASE_URL}/admin/api/category`, payload)
            .done(j=>{
                if(!j?.status){ $msg.text(j?.message || 'Kaydedilemedi'); disable($save,false); return; }
                bsModal.hide(); disable($save,false); load();
            })
            .fail(()=>{ $msg.text('Bağlantı hatası'); disable($save,false); });
    }
    $save.on('click', doSave);
    $modal.on('keydown', function(ev){
        if(ev.key === 'Enter' && !ev.shiftKey){
            ev.preventDefault(); doSave();
        }
    });

    // Sil
    $list.on('click','.btn-del', function(){
        const id = $(this).data('id');
        Swal.fire({ title:'Emin misiniz?', text:'Kategori silinecek', icon:'warning',
            showCancelButton:true, confirmButtonText:'Sil', cancelButtonText:'Vazgeç' })
            .then(r=>{
                if(!r.isConfirmed) return;
                $.post(`${BASE_URL}/admin/api/category-delete`, { csrf: csrf, id })
                    .done(j=>{
                        if(!j?.status){ Swal.fire('Hata', j?.message || 'Silinemedi', 'error'); return; }
                        load();
                    })
                    .fail(()=> Swal.fire('Hata','Bağlantı hatası','error'));
            });
    });

    load();
})();
