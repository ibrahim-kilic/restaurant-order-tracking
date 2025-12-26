// Global AJAX ayarları
const CSRF = $('meta[name="csrf-token"]').attr('content') || '';

$.ajaxSetup({
    cache: false,
    dataType: 'json',
    beforeSend: function (xhr, settings) {
        if (settings.type && settings.type.toUpperCase() === 'POST') {
            if (typeof settings.data === 'string') {
                settings.data += (settings.data ? '&' : '') + $.param({ csrf: CSRF });
            } else if ($.isPlainObject(settings.data)) {
                settings.data.csrf = CSRF;
            }
        }
    }
});

// Basit loading overlay (istersen aynı kalsın)
$(document).on('ajaxStart', function () { $('#ajaxLoading').show(); });
$(document).on('ajaxStop',  function () { $('#ajaxLoading').hide(); });

// Tek noktadan GET/POST yardımcıları
window.api = {
    get:  (path, data) => $.ajax({ url: BASE_URL + path, method: 'GET',  data: data || {} }),
    post: (path, data) => $.ajax({ url: BASE_URL + path, method: 'POST', data: data || {} })
};

// ---------- SweetAlert2 yardımcıları ----------

// Ortak toast (sağ üst)
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true
});

// Başarı/Hata/Uyarı
window.swalSuccess = (text, title='Başarılı') =>
    Swal.fire({ icon: 'success', title, text });

window.swalError = (text='İşlem başarısız', title='Hata') =>
    Swal.fire({ icon: 'error', title, text });

window.swalWarn = (text, title='Uyarı') =>
    Swal.fire({ icon: 'warning', title, text });

// Toast kısayolları
window.toastOk   = (msg='Kayıt başarılı') => Toast.fire({ icon:'success', title: msg });
window.toastInfo = (msg='Bilgi')          => Toast.fire({ icon:'info',    title: msg });
window.toastErr  = (msg='Hata')           => Toast.fire({ icon:'error',   title: msg });

// Onay penceresi (Promise döner)
window.swalConfirm = (text='Bu işlemi onaylıyor musunuz?', title='Onay', confirmText='Evet', cancelText='Vazgeç') =>
    Swal.fire({
        icon: 'question',
        title, text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true
    });

// Ortak hata yakalayıcı (XHR)
window.showError = function (xhr, fallback) {
    let msg = fallback || 'İşlem başarısız';
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
    }
    swalError(msg);

    document.addEventListener('click', function(e){
        const btn = e.target.closest('.js-fav-toggle');
        if(!btn) return;

        const id   = btn.dataset.id;
        const icon = btn.querySelector('.fav-icon');
        const cur  = icon.getAttribute('data-state') === '1' ? 1 : 0;
        const next = cur ? 0 : 1;

        fetch(BASE_URL + '/admin/api/product-favorite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': window.CSRF || ''
            },
            body: new URLSearchParams({ product_id: id, state: next })
        })
            .then(r => r.json())
            .then(res => {
                if(res?.ok){
                    icon.setAttribute('data-state', String(next));
                    icon.textContent = next ? '★' : '☆';
                }else{
                    alert(res?.error || 'Hata');
                }
            })
            .catch(()=> alert('Ağ hatası'));
    });

};
