$(function () {
    var $f = $('#loginForm');
    var $btn = $('#btnLogin');

    $f.on('submit', function (e) {
        e.preventDefault();
        $btn.prop('disabled', true);

        api.post('/auth/login', $f.serialize())
            .done(function (j) {
                if (j && j.status) {
                    toastOk('Giriş başarılı');
                    setTimeout(function () {
                        window.location.href = BASE_URL + j.data.redirect;
                    }, 400);
                } else {
                    swalError(j.message || 'Giriş başarısız');
                }
            })
            .fail(function (xhr) {
                showError(xhr, 'Bağlantı hatası');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });
});
