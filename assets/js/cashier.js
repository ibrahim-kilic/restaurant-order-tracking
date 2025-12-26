(function(){
    const $grid = $('#tablesGrid');
    const modalEl = document.getElementById('tableModal');
    const bsModal = new bootstrap.Modal(modalEl,{backdrop:'static',keyboard:false});
    const closeBtn = modalEl.querySelector('.btn-modal-close');

    let NEED_TABLES_REFRESH=false;
    closeBtn?.addEventListener('click',()=>{NEED_TABLES_REFRESH=true;});
    modalEl.addEventListener('hidden.bs.modal',()=>{ if(NEED_TABLES_REFRESH){NEED_TABLES_REFRESH=false; loadTables();}});

    function updateHeaderH(){
        const h=document.getElementById('cashierHeader')?.offsetHeight||68;
        document.documentElement.style.setProperty('--header-h',h+'px');
    }
    updateHeaderH(); window.addEventListener('resize',updateHeaderH);

    // Formatlayıcılar
    const nf0 = new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',minimumFractionDigits:0,maximumFractionDigits:0});
    const nf2 = new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',minimumFractionDigits:2,maximumFractionDigits:2});
    const fmtTL = (n) => {
        const v = Number(n) || 0;
        const hasCents = Math.round(v*100) % 100 !== 0;
        return (hasCents ? nf2 : nf0).format(v);
    };
    const fmtQty = (q) => {
        const v = Number(q) || 0;
        return Number.isInteger(v) ? String(v) : String(v).replace(/(\.\d*[1-9])0+$|\.0+$/, '$1');
    };

    // State
    let ALL_PRODUCTS=[], ALL_CATEGORIES=[], ALL_TABLES_CACHE=[];
    let CURR={ table_id:null, ticket_id:null, totals:{sum:0,paid:0,due:0} };
    let CUR_CAT_ID='__fav';
    let PAY_METHOD='cash';

    $('#btnRefresh').on('click',loadTables);
    $('#btnLogout').on('click',()=> swalConfirm('Çıkış yapmak istediğinize emin misiniz?','Onay','Evet','Vazgeç').then(r=>{if(r.isConfirmed) location.href=BASE_URL+'/auth/logout'}));

    /* GRID */
    function renderTables(list){
        const rows=(list||[]).slice().sort((a,b)=>(a.id||0)-(b.id||0));
        $grid.empty();
        const frag=document.createDocumentFragment();
        rows.forEach(r=>{
            const card=document.createElement('button');
            card.type='button'; card.className='tbl-card w-100 text-start';
            card.dataset.id=r.id; card.setAttribute('data-st', r.status);
            card.innerHTML=`
          <div class="d-flex justify-content-between align-items-center">
            <div class="tbl-name">${r.name}</div>
            <div class="tbl-status">
              <div class="st-label">${r.status_label||''}</div>
              ${r.status==='empty' ? '' : `
                <div class="st-min js-open-min" data-id="${r.id}">
                  ${r.open_min!=null ? (r.open_min+' dk') : ''}
                </div>`}
            </div>
          </div>
          <div class="tbl-meta">${r.status==='empty'?'Hazır':(fmtTL(r.total||0))}</div>`;
            card.addEventListener('click',()=> openTableModal(r.id,r.name));
            frag.appendChild(card);
        });
        $grid[0].appendChild(frag);
    }

    function loadTables(){
        api.get('/cashier/api/tables')
            .done(j=>{
                if(j?.status){ renderTables(j.data.tables||[]); refreshAges(); }
                else{ swalError(j.message||'Masalar getirilemedi'); }
            })
            .fail(x=>showError(x,'Bağlantı hatası'));
    }

    function refreshAges(){
        api.get('/cashier/api/tables-ages').done(j=>{
            if(!(j && j.status))return;
            (j.data.ages||[]).forEach(a=>{
                const $el=$('.tbl-card[data-id="'+a.id+'"] .js-open-min');
                if($el.length)$el.text((a.open_min==null)?'':(a.open_min+' dk'));
            });
        });
    }

    /* Görünürlük bazlı poll (gereksiz istekleri azaltır) */
    function pollAges(){
        if(document.visibilityState === 'visible') refreshAges();
        setTimeout(pollAges, 60000);
    }
    pollAges();

    /* MODAL */
    function openTableModal(tableId, tableName){
        CURR={ table_id:tableId, ticket_id:null, totals:{sum:0,paid:0,due:0} };
        PAY_METHOD='cash'; updatePayUI();
        $('#tableBadge').text('['+(tableName||'Masa')+']');

        api.get('/cashier/api/table-detail',{table_id:tableId}).done(j=>{
            if(!j?.status){ swalError(j.message||'Detay alınamadı'); return; }
            const d=j.data;
            CURR.ticket_id=d.ticket?.id||null;
            ALL_PRODUCTS=d.products||[]; ALL_CATEGORIES=d.categories||[]; ALL_TABLES_CACHE=d.tables||[];

            buildCategoryChips();
            rebuildProductGrid();
            renderItems(d.items||[]);
            renderTotals(d.totals||{sum:0,paid:0,due:0});

            bsModal.show();
        }).fail(x=>showError(x,'Bağlantı hatası'));
    }

    /* Kategoriler */
    function buildCategoryChips(){
        const host=document.getElementById('catChips'); host.innerHTML='';
        const mk=(id,name)=>{
            const b=document.createElement('button');
            b.type='button'; b.className='btn'; b.dataset.id=id; b.textContent=name;
            if(String(CUR_CAT_ID)===String(id)) b.classList.add('active');
            b.addEventListener('click',()=>{
                CUR_CAT_ID=id;
                host.querySelectorAll('.btn').forEach(x=>x.classList.remove('active'));
                b.classList.add('active');
                rebuildProductGrid();
            });
            return b;
        };
        host.appendChild(mk('__fav','Sık Kullanılanlar'));
        (ALL_CATEGORIES||[]).forEach(c=> host.appendChild(mk(c.id,c.name)));
    }

    /* Ürün grid: event delegation */
    const $g = $('#prodGrid');
    $g.on('click', '.prod-card', function(){
        const pid = this.dataset.id;
        api.post('/cashier/api/item-add',{
            csrf:$('meta[name="csrf-token"]').attr('content'),
            table_id:CURR.table_id, product_id:pid, qty:1
        }).done(()=>reloadItemsAndTotals())
            .fail(x=>showError(x,'Eklenemedi'));
    });

    function rebuildProductGrid(){
        $g.empty();
        let list=ALL_PRODUCTS.slice();
        if(CUR_CAT_ID==='__fav') list=list.filter(p=>(+p.fav||0)===1);
        else if(CUR_CAT_ID) list=list.filter(p=>String(p.category_id)===String(CUR_CAT_ID));
        if(!list.length){ $g.append('<div class="text-muted small">Sonuç yok</div>'); return; }

        const frag=document.createDocumentFragment();
        list.forEach(p=>{
            const el=document.createElement('button');
            el.type='button'; el.className='prod-card text-start'; el.dataset.id=p.id;
            el.innerHTML=`<div class="prod-title">${p.name}</div><div class="prod-price">${fmtTL(p.price)}</div>`;
            frag.appendChild(el);
        });
        $g[0].appendChild(frag);
    }

    /* Adisyon */
    function renderItems(items){
        const host=$('#itemsHost').empty()[0];
        if(!items.length){ host.innerHTML='<div class="text-muted small">Henüz ürün eklenmemiş.</div>'; return; }
        const frag=document.createDocumentFragment();
        items.forEach(it=>{
            const row=document.createElement('div'); row.className='items-row';
            const c1=document.createElement('div'); c1.className='fw-semibold'; c1.textContent=it.product_name;
            const c2=document.createElement('div'); c2.className='qty-wrap';
            c2.innerHTML=`<button class="qty-btn js-minus" data-id="${it.id}">−</button>
          <span class="qty-val" data-id="${it.id}">${fmtQty(it.qty)}</span>
          <button class="qty-btn js-plus" data-id="${it.id}">+</button>`;
            const c3=document.createElement('div'); c3.textContent=fmtTL(it.price);
            const c4=document.createElement('div'); c4.className='text-end fw-bold'; c4.textContent=fmtTL(it.line_total);
            row.appendChild(c1); row.appendChild(c2); row.appendChild(c3); row.appendChild(c4);
            frag.appendChild(row);
        });
        host.appendChild(frag);
    }

    function renderTotals(t){
        CURR.totals=t||{sum:0,paid:0,due:0};
        $('#sumTL').text(fmtTL(CURR.totals.sum));
        $('#paidTL').text(fmtTL(CURR.totals.paid));
        $('#dueTL').text(fmtTL(CURR.totals.due));
    }

    function reloadItemsAndTotals(){
        api.get('/cashier/api/table-detail',{table_id:CURR.table_id}).done(j=>{
            if(!j?.status){ swalError(j.message||'Detay alınamadı'); return; }
            CURR.ticket_id=j.data.ticket?.id||null;
            renderItems(j.data.items||[]);
            renderTotals(j.data.totals||{sum:0,paid:0,due:0});
        }).fail(x=>showError(x,'Bağlantı hatası'));
    }

    $('#itemsHost').on('click','.js-plus',function(){
        const id=this.getAttribute('data-id'); const $v=$('.qty-val[data-id="'+id+'"]');
        const next=(parseInt($v.text()||'0',10)||0)+1;
        api.post('/cashier/api/item-update',{csrf:$('meta[name="csrf-token"]').attr('content'), item_id:id, qty:next})
            .done(()=>reloadItemsAndTotals()).fail(x=>showError(x,'Güncellenemedi'));
    });
    $('#itemsHost').on('click','.js-minus',function(){
        const id=this.getAttribute('data-id'); const $v=$('.qty-val[data-id="'+id+'"]');
        const curr=parseInt($v.text()||'0',10)||0; const next=curr-1;
        if(next<=0){
            swalConfirm('Kalem silinsin mi?').then(res=>{
                if(!res.isConfirmed) return;
                api.post('/cashier/api/item-update',{csrf:$('meta[name="csrf-token"]').attr('content'), item_id:id, delete:1})
                    .done(()=>reloadItemsAndTotals()).fail(x=>showError(x,'Silinemedi'));
            });
            return;
        }
        api.post('/cashier/api/item-update',{csrf:$('meta[name="csrf-token"]').attr('content'), item_id:id, qty:next})
            .done(()=>reloadItemsAndTotals()).fail(x=>showError(x,'Güncellenemedi'));
    });

    /* Ödeme yöntemi */
    function updatePayUI(){
        const $btns=$('#payButtons .btn');
        $btns.removeClass('active');
        $btns.filter('[data-method="'+PAY_METHOD+'"]').addClass('active');
    }
    $('#payButtons').on('click','.btn',function(){
        PAY_METHOD=$(this).data('method');
        updatePayUI();
    });

    /* AL */
    $('#btnPayOk').on('click', function(){
        if(!CURR.ticket_id){ swalWarn('Önce ürün ekleyin'); return; }
        const due = Number(CURR.totals?.due||0);
        if(!(due>0)){ swalWarn('Tahsil edilecek tutar yok'); return; }
        const amount = Math.round(due*100)/100; // kuruş koru

        api.post('/cashier/api/payment-add',{
            csrf:$('meta[name="csrf-token"]').attr('content'),
            ticket_id:CURR.ticket_id,
            amount,
            method:PAY_METHOD
        }).done(j=>{
            if(j?.status){
                if(j.data?.closed){ NEED_TABLES_REFRESH=true; bsModal.hide(); return; }
                reloadItemsAndTotals();
            }else{ swalError(j.message||'Ödeme alınamadı'); }
        }).fail(x=>showError(x,'Ödeme alınamadı'));
    });

    /* Taşı / Birleştir */
    function openChooseTableModal(title,tables,excludeId){
        return new Promise(resolve=>{
            const $m=$('#chooseTableModal'); $('#chooseTitle').text(title||'Masa Seç');
            const $sel=$('#selChooseTable').empty(); $('<option>').val('').text('Masa seçin').appendTo($sel);
            (tables||[]).forEach(t=>{ if(String(t.id)!==String(excludeId)) $('<option>').val(t.id).text(t.name).appendTo($sel); });
            $('#btnChooseOk').off('click').on('click',()=>{ const v=$sel.val(); if(!v){ $sel.focus(); return; } $m.modal('hide'); resolve(v); });
            $m.off('hidden.bs.modal').on('hidden.bs.modal',()=>resolve(null));
            new bootstrap.Modal($m[0],{backdrop:true,keyboard:true}).show();
        });
    }
    $('#btnTransfer').on('click',function(){
        if(!CURR.ticket_id){ swalWarn('Taşınacak açık adisyon yok'); return; }
        openChooseTableModal('Hedef masayı seçin', ALL_TABLES_CACHE, CURR.table_id).then(targetId=>{
            if(!targetId) return;
            api.post('/cashier/api/transfer',{csrf:$('meta[name="csrf-token"]').attr('content'), ticket_id:CURR.ticket_id, to_table_id:targetId})
                .done(()=>{ NEED_TABLES_REFRESH=true; bsModal.hide(); })
                .fail(x=>showError(x,'Taşınamadı'));
        });
    });
    $('#btnMerge').on('click',function(){
        if(!CURR.ticket_id){ swalWarn('Birleştirilecek açık adisyon yok'); return; }
        openChooseTableModal('Birleştirilecek masayı seçin', ALL_TABLES_CACHE, CURR.table_id).then(targetId=>{
            if(!targetId) return;
            api.get('/cashier/api/table-detail',{table_id:targetId}).done(j=>{
                const t=j?.data; const targetTicket=t?.ticket?.id||null; const targetQty=(t?.items||[]).length||t?.totals?.qty||0;
                if(!targetTicket||targetQty<=0){ swalWarn('Hedef masada en az 1 ürün olmalı'); return; }
                swalConfirm('Adisyonlar birleştirilsin mi?').then(r=>{ if(!r.isConfirmed) return;
                    api.post('/cashier/api/merge',{csrf:$('meta[name="csrf-token"]').attr('content'), from_ticket_id:CURR.ticket_id, to_ticket_id:targetTicket})
                        .done(()=>{ NEED_TABLES_REFRESH=true; bsModal.hide(); })
                        .fail(x=>showError(x,'Birleştirilemedi'));
                });
            });
        });
    });

    /* Init */
    function loadAndStart(){ loadTables(); }
    loadAndStart();

})();
