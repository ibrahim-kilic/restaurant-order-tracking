// Admin • Panel
(function () {
    const $t = $('#sTables'), $split = $('#sSplit'), $cats = $('#sCats'), $prods = $('#sProds');

    function updateStat(el, val) { $(el).text(val ?? '-'); }

    function loadStats() {
        $.getJSON(`${BASE_URL}/cashier/api/tables`, { all: 1 })
            .done(j => {
                const rows = j?.data?.tables || [];
                const total = rows.length;
                const empty = rows.filter(x => x.status === 'empty' || x.status === 'free').length;
                const open  = rows.filter(x => x.status === 'open').length;
                const pay   = rows.filter(x => x.status === 'payment').length;
                updateStat($t, total);
                updateStat($split, `${empty} / ${open} / ${pay}`);
            })
            .fail(() => { updateStat($t, '?'); updateStat($split, '?'); });

        $.getJSON(`${BASE_URL}/admin/api/categories`)
            .done(j => updateStat($cats, (j?.data?.categories || []).length))
            .fail(() => updateStat($cats, '?'));

        $.getJSON(`${BASE_URL}/admin/api/products`)
            .done(j => updateStat($prods, (j?.data?.products || []).length))
            .fail(() => updateStat($prods, '?'));
    }

    loadStats();
})();
