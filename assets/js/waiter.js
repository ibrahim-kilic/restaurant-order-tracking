(function(){
    const cart = {}; // {product_id: qty}
    const elCart = document.getElementById('w_cart');
    const elMsg  = document.getElementById('w_msg');

    function renderCart(){
        elCart.innerHTML = '';
        Object.keys(cart).forEach(pid=>{
            const li = document.createElement('li');
            li.textContent = `#${pid} x ${cart[pid]}`;
            elCart.appendChild(li);
        });
    }

    document.querySelectorAll('.w_prod').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const pid = btn.dataset.id;
            cart[pid] = (cart[pid]||0) + 1;
            renderCart();
        });
    });

    document.getElementById('w_save').addEventListener('click', async ()=>{
        const tableId = document.getElementById('w_table').value;
        const items = Object.keys(cart).map(pid=>({product_id: parseInt(pid), qty: cart[pid]}));
        if(items.length===0){ elMsg.textContent='Sepet boş'; return; }

        const fd = new FormData();
        fd.append('table_id', tableId);
        items.forEach((it,i)=>{
            fd.append(`items[${i}][product_id]`, it.product_id);
            fd.append(`items[${i}][qty]`, it.qty);
        });

        const res  = await fetch('/waiter/api/add-items', {method:'POST', body:fd, credentials:'include'});
        let data = {}; try { data = await res.json(); } catch(e){}
        if(data.ok){
            elMsg.textContent = `Kaydedildi (ticket #${data.ticket_id})`;
            Object.keys(cart).forEach(k=>delete cart[k]); renderCart();
        }else{
            elMsg.textContent = 'Hata: ' + (data.msg||'');
        }
    });
})();
