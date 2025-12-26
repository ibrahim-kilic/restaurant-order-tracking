<?php /** @var string BASE_URL */ ?>

<!-- Cashier CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/cashier.css">

<div class="cashier-wrap container-fluid">
    <div id="cashierHeader" class="cashier-header d-flex align-items-center justify-content-between">
        <div class="py-1">
            <h5 class="mb-0">(<?= htmlspecialchars($_SESSION['name'] ?? '') ?>)</h5>
        </div>
        <div class="top-actions py-1">
            <button id="btnRefresh" class="btn btn-outline-secondary btn-sm" title="Yenile">
                <i class="bi bi-arrow-repeat"></i>
            </button>
            <a id="btnLogout" class="btn btn-outline-danger btn-sm" href="javascript:void(0)" title="Çıkış">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="cashier-grid-wrap">
        <div class="grid-viewport"><div id="tablesGrid"></div></div>
    </div>
</div>

<!-- POS MODAL -->
<div class="modal fade modal-95vh" id="tableModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <button type="button" class="btn-close btn-modal-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            <div class="modal-body">

                <div class="pos-body">
                    <!-- SOL -->
                    <div class="panel">
                        <div class="panel-bd">
                            <div class="prod-wrap">
                                <div class="vert-label">ÜRÜNLER</div>
                                <div class="min-h-0 d-flex flex-column">
                                    <div class="cat-chips" id="catChips"></div>
                                    <div class="prod-list mt-1">
                                        <div class="prod-grid" id="prodGrid"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SAĞ -->
                    <div class="panel">
                        <div class="panel-hd">
                            Adisyon <small id="tableBadge" class="ms-1">[Masa]</small>
                        </div>
                        <div class="panel-bd">
                            <div class="items-head">
                                <div>Ürün</div>
                                <div>Adet</div>
                                <div>Birim</div>
                                <div class="text-end">Toplam</div>
                            </div>
                            <div class="items-scroll" id="itemsHost"></div>
                        </div>
                    </div>
                </div>

                <!-- ALT -->
                <div class="pos-footer">
                    <div class="pay-block">
                        <div class="left-pay">
                            <div class="totals-row">
                                <span class="chip">Toplam: <strong id="sumTL">₺0,00</strong></span>
                                <span class="chip">Ödenen: <span id="paidTL">₺0,00</span></span>
                                <span class="chip text-danger">Kalan: <strong id="dueTL">₺0,00</strong></span>

                                <div class="right-actions">
                                    <button type="button" id="btnTransfer" class="btn btn-outline-primary">Masa Taşı</button>
                                    <button type="button" id="btnMerge" class="btn btn-outline-secondary">Birleştir</button>
                                </div>
                            </div>

                            <div class="pay-row">
                                <div class="pay-group" id="payButtons">
                                    <button type="button" class="btn btn-outline-secondary" data-method="cash">Nakit</button>
                                    <button type="button" class="btn btn-outline-secondary" data-method="card">Kart</button>
                                    <button type="button" class="btn btn-outline-secondary" data-method="discount">İndirim</button>
                                </div>
                                <button type="button" id="btnPayOk" class="btn btn-success">AL</button>
                            </div>

                        </div>
                    </div>
                </div>

            </div><!--/modal-body-->
        </div>
    </div>
</div>

<!-- Masa seç -->
<div class="modal fade" id="chooseTableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="chooseTitle">Masa Seç</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <label class="form-label mb-2">Hedef Masa</label>
                <select id="selChooseTable" class="form-select" style="width:100%"></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" id="btnChooseOk" class="btn btn-primary">Onayla</button>
            </div>
        </div>
    </div>
</div>

<!-- Cashier JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/cashier.js"></script>
