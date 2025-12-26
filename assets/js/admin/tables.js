// Admin • Masalar
(function(){
    const $list=$('#list'),$empty=$('#empty'),$modal=$('#tblModal'),bsModal=new bootstrap.Modal($modal[0]);
    const $msg=$('#tblMsg'),$save=$('#tblSave'); const csrf=$('meta[name="csrf-token"]').attr('content')||'';

    const e=s=>String(s??'').replace(/[&<>"'`=\/]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;','`':'&#96;','=':'&#61;'}[ch]||ch));

    function load(){
        $.getJSON(`${BASE_URL}/admin/api/tables`)
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
        $.post(id?`${BASE_URL}/admin/api/table-update`:`${BASE_URL}/admin/api/table`,payload)
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
        $.getJSON(`${BASE_URL}/admin/api/table-get`,{id})
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
                $.post(`${BASE_URL}/admin/api/table-delete`,{csrf:csrf,id})
                    .done(j=>{if(!j?.status){Swal.fire('Hata',j?.message||'Silinemedi','error');return;}load();})
                    .fail(()=>Swal.fire('Hata','Bağlantı hatası','error'));
            });
    });

    $list.on('change','.js-toggle',function(){
        const id=$(this).data('id'),active=this.checked?1:0,self=this;
        $.post(`${BASE_URL}/admin/api/table-toggle`,{csrf:csrf,id,is_active:active})
            .done(j=>{if(!j?.status){Swal.fire('Hata',j?.message||'Güncellenemedi','error');self.checked=!active;}})
            .fail(()=>{Swal.fire('Hata','Bağlantı hatası','error');self.checked=!active;});
    });

    load();
})();
