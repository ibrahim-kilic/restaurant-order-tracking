// Admin • Ürünler
(function(){
    const $list=$('#list'), $empty=$('#empty'), $modal=$('#prdModal'), bsModal=new bootstrap.Modal($modal[0]), $chips=$('#catChips');
    const $msg=$('#prdMsg'), $save=$('#prdSave');
    const csrf=$('meta[name="csrf-token"]').attr('content')||'';
    let CATS=[], FILTER='';

    const esc = s => String(s ?? '').replace(/[&<>"'`=\/]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'}[ch]||ch));
    function fmt(n){ try{ return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY'}).format(+n||0) }catch{ return ((+n||0).toFixed(2)+' ₺') } }
    function normalizePrice(raw){
        const s=String(raw??'').trim().replace(',','.');
        if(!/^\d+(?:\.\d{1,2})?$/.test(s)) return null;
        return s;
    }

    function loadCats(){
        return $.getJSON(`${BASE_URL}/admin/api/categories`).then(j=>{
            CATS=(j?.data?.categories||[]).slice().sort((a,b)=>(+a.sort_order||0)-(+b.sort_order||0)||(a.id-b.id));
            renderChips();
        });
    }
    function renderChips(){
        $chips.empty().removeClass('d-none');
        const all=$('<span class="pill">Tümü</span>').toggleClass('active',FILTER==='').on('click',()=>{FILTER=''; renderChips(); load();});
        $chips.append(all);
        CATS.forEach(c=>{
            const p=$('<span class="pill"></span>').text(c.name).toggleClass('active',String(FILTER)===String(c.id)).on('click',()=>{FILTER=String(c.id); renderChips(); load();});
            $chips.append(p);
        });
    }

    function load(){
        const qs = FILTER ? {category_id: FILTER} : {};
        $.getJSON(`${BASE_URL}/admin/api/products`, qs)
            .done(j=> render(j?.data?.products||[]))
            .fail(()=>{ $list.empty(); $empty.removeClass('d-none').text('Bağlantı hatası'); });
    }

    function render(rows){
        $list.empty();
        if(!rows.length){ $empty.removeClass('d-none'); return; }
        $empty.addClass('d-none');

        rows.sort((a,b)=>(+a.sort_order||0)-(+b.sort_order||0)||(a.id-b.id));
        const frag=document.createDocumentFragment();
        rows.forEach(r=>{
            const active = (r.is_active==null)?1:(+r.is_active?1:0);
            const catName = (CATS.find(c=>String(c.id)===String(r.category_id))||{}).name || '-';
            const row=document.createElement('div'); row.className='row-item';
            row.innerHTML=`
        <div>
          <div class="fw-semibold">${esc(r.name||'-')}</div>
          <div class="text-muted small">${esc(catName)}</div>
        </div>
        <div class="text-end">${fmt(r.price)}</div>
        <div class="text-center">
          <label class="ios-switch" title="${active?'Pasife al':'Aktif et'}">
            <input type="checkbox" class="js-toggle" data-id="${esc(r.id)}" ${active?'checked':''}>
            <span class="track"><span class="thumb"></span></span>
          </label>
        </div>
        <div class="text-end">
          <button class="btn btn-outline-secondary btn-sm me-1 btn-edit" data-id="${esc(r.id)}" aria-label="Düzenle"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-outline-danger btn-sm btn-del" data-id="${esc(r.id)}" aria-label="Sil"><i class="bi bi-trash"></i></button>
        </div>`;
            frag.appendChild(row);
        });
        $list[0].appendChild(frag);
    }

    function fillCats(){
        const $sel=$('#prdCat').empty();
        $sel.append('<option value="">Seçin</option>');
        CATS.forEach(c=>$sel.append(`<option value="${esc(c.id)}">${esc(c.name)}</option>`));
    }

    // Yeni
    $('#btnNew').on('click',()=>{
        $modal.removeData('edit-id');
        $('#prdTitle').text('Yeni Ürün');
        $('#prdName,#prdPrice,#prdSort').val('');
        $('#prdActive').prop('checked',true);
        fillCats();
        $('#prdCat').val(FILTER);
        $msg.text('');
        bsModal.show();
        $('#prdName').trigger('focus');
    });

    // Kaydet
    function doSave(){
        const id=$modal.data('edit-id')||null;
        const name=($('#prdName').val()||'').trim();
        const cat=$('#prdCat').val();
        const priceRaw=$('#prdPrice').val();
        const price=normalizePrice(priceRaw);
        const sort=$('#prdSort').val();
        const act=$('#prdActive').is(':checked')?1:0;

        if(!name){ $msg.text('Ad zorunlu'); return; }
        if(!cat){ $msg.text('Kategori zorunlu'); return; }
        if(price===null){ $msg.text('Fiyat geçersiz. Örn: 12,50'); return; }

        const payload={ csrf:csrf, name, category_id:cat, price:String(price), is_active:act };
        if(sort!=='') payload.sort_order=sort;
        if(id) payload.id=id;

        $msg.text('Kaydediliyor...'); $save.prop('disabled',true);
        $.post(id ? `${BASE_URL}/admin/api/product-update` : `${BASE_URL}/admin/api/product`, payload)
            .done(j=>{
                if(!j?.status){ $msg.text(j?.message||'Kaydedilemedi'); $save.prop('disabled',false); return; }
                bsModal.hide(); $save.prop('disabled',false); load();
            })
            .fail(()=>{ $msg.text('Bağlantı hatası'); $save.prop('disabled',false); });
    }
    $save.on('click', doSave);
    $modal.on('keydown', function(ev){
        if(ev.key==='Enter' && !ev.shiftKey){ ev.preventDefault(); doSave(); }
    });

    // Düzenle
    $list.on('click','.btn-edit',function(){
        const id=$(this).data('id');
        $msg.text('Yükleniyor...');
        $modal.data('edit-id',id);
        $('#prdTitle').text('Ürün Düzenle');
        fillCats(); bsModal.show();
        $.getJSON(`${BASE_URL}/admin/api/products`, {id})
            .done(j=>{
                const r=j?.data?.product||{};
                $('#prdName').val(r.name||'');
                $('#prdCat').val(r.category_id||'');
                $('#prdPrice').val(r.price??'');
                $('#prdSort').val(r.sort_order??'');
                $('#prdActive').prop('checked',(r.is_active==null)?true:(+r.is_active?true:false));
                $msg.text('');
                $('#prdName').trigger('focus');
            })
            .fail(()=> $msg.text('Bağlantı hatası'));
    });

    // Sil
    $list.on('click','.btn-del',function(){
        const id=$(this).data('id');
        Swal.fire({title:'Emin misiniz?',text:'Ürün silinecek',icon:'warning',showCancelButton:true,confirmButtonText:'Sil',cancelButtonText:'Vazgeç'})
            .then(r=>{
                if(!r.isConfirmed) return;
                $.post(`${BASE_URL}/admin/api/product-delete`,{csrf:csrf,id})
                    .done(j=>{ if(!j?.status){ Swal.fire('Hata',j?.message||'Silinemedi','error'); return; } load(); })
                    .fail(()=> Swal.fire('Hata','Bağlantı hatası','error'));
            });
    });

    // Aktif toggle
    $list.on('change','.js-toggle',function(){
        const id=$(this).data('id'), active=this.checked?1:0, self=this;
        $.post(`${BASE_URL}/admin/api/product-toggle`,{csrf:csrf,id,is_active:active})
            .done(j=>{ if(!j?.status){ Swal.fire('Hata',j?.message||'Güncellenemedi','error'); self.checked=!active; } })
            .fail(()=>{ Swal.fire('Hata','Bağlantı hatası','error'); self.checked=!active; });
    });

    loadCats().then(load);
})();
