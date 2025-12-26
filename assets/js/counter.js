(function(){
    const tbody = document.getElementById('c_body');
    let since = null;

    async function load(){
        const qs = since ? '?since='+encodeURIComponent(since) : '';
        const res = await fetch('/counter/api/feed' + qs, {credentials:'include'});
        let data = {}; try { data = await res.json(); } catch(e){}
        if(!data.ok) return;

        tbody.innerHTML = '';
        data.rows.forEach(r=>{
            const tr = document.createElement('tr');
            const t = (r.served_at||'').split(' ')[1]?.slice(0,5) || '';
            tr.innerHTML = `<td>${t}</td><td>${r.table_name||r.table_id}</td><td>${r.product_name||('#'+r.product_id)}</td><td>${r.qty}</td><td>${r.served_by_username||''}</td>`;
            tbody.appendChild(tr);
        });

        since = new Date().toISOString().slice(0,19).replace('T',' ');
    }

    load();
    setInterval(load, 5000);
})();
