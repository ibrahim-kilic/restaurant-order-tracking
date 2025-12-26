// Admin • Raporlar (Chart.js gerekli)
(function(){
    const qsBase = BASE_URL;
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
        if(params) Object.entries(params).forEach(([k,v])=>{ if(v !== undefined && v !== null) u.searchParams.set(k,v); });
        try{
            const r = await fetch(u, {headers:{'X-Requested-With':'fetch'}});
            return await r.json();
        }catch(_){ return {status:false}; }
    }

    async function runAll(){
        const date_from=$from.val(), date_to=$to.val();
        // API'ler
        const sales = await fetchJSON(`${qsBase}/admin/api/reports/sales`,{date_from,date_to});
        const bycat = await fetchJSON(`${qsBase}/admin/api/reports/by-category`,{date_from,date_to});
        const byprd = await fetchJSON(`${qsBase}/admin/api/reports/by-product`,{date_from,date_to,limit:$('#topLimit').val()});
        const pays  = await fetchJSON(`${qsBase}/admin/api/reports/payments`,{date_from,date_to});
        const hours = await fetchJSON(`${qsBase}/admin/api/reports/hours`,{date_from,date_to});
        const tbls  = await fetchJSON(`${qsBase}/admin/api/reports/tables`,{date_from,date_to});
        const staff = await fetchJSON(`${qsBase}/admin/api/reports/staff`,{date_from,date_to});

        // KPIs
        const total = +((sales?.data?.total_sales)||0);
        const days  = (sales?.data?.sales||[]);
        const ticketCount = days.reduce((a,x)=> a + (+x.tickets||0), 0);
        const avg   = ticketCount ? (total / ticketCount) : 0;
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

        // Top ürünler
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

        // Masalar
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

        // Personel
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

    function setPreset(p){
        const t=todayStr();
        if(p==='today'){ $from.val(t); $to.val(t); }
        if(p==='week'){  $from.val(startOfWeek(t)); $to.val(t); }
        if(p==='month'){ $from.val(startOfMonth(t)); $to.val(t); }
        runAll();
        $('.chip').removeClass('active'); $(`.chip[data-preset="${p}"]`).addClass('active');
    }
})();
