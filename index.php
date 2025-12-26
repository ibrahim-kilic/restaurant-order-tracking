<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CashierController.php';
require_once __DIR__ . '/controllers/AdminController.php';

secure_session_start();
enforce_session_fingerprint();

$path   = request_path();                      // örn: /cashier/main
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$auth    = new AuthController();
$cashier = new CashierController();
$admin   = new AdminController();

/** root yönlendirme */
if ($path === '' || $path === '/') {
    if (is_logged_in()) {
        redirect(role_home($_SESSION['role'] ?? 'waiter'));
    } else {
        redirect('/auth/login');
    }
    exit;
}

switch ($path) {

    /* ---------- AUTH ---------- */
    case '/auth/login':   $auth->login();  break;
    case '/auth/logout':  $auth->logout(); break;

    /* ---------- CASHIER VIEW ---------- */
    case '/cashier/main':
        require_role(['cashier','admin']);
        view('cashier/main.php', ['title' => 'Kasiyer']);
        break;

    /* ---------- CASHIER API ---------- */
    case '/cashier/api/tables':        $cashier->apiTables();        break;
    case '/cashier/api/table-detail':  $cashier->apiTableDetail();   break;
    case '/cashier/api/item-add':      $cashier->apiAddItem();       break;
    case '/cashier/api/item-update':   $cashier->apiUpdateItem();    break;
    case '/cashier/api/payment-add':   $cashier->apiAddPayment();    break;
    case '/cashier/api/transfer':      $cashier->apiTransferTable(); break;
    case '/cashier/api/merge':         $cashier->apiMergeTable();    break;
    case '/cashier/api/tables-ages':   $cashier->apiTablesAges();    break;
    case '/cashier/api/favorites':     $cashier->apiFavorites();     break;

    /* ---------- WAITER VIEW ---------- */
    case '/waiter/main':
        require_role(['waiter','admin']);
        view('waiter/main.php', ['title' => 'Garson']);
        break;

    /* ---------- ADMIN VIEWS ---------- */
    case '/admin/main':
        require_role(['admin']);
        view('admin/main.php', ['title' => 'Yönetim']);
        break;

    case '/admin/categories':
        require_role(['admin']);
        view('admin/categories.php', ['title' => 'Kategoriler']);
        break;

    case '/admin/products':
        require_role(['admin']);
        view('admin/products.php', ['title' => 'Ürünler']);
        break;

    /* EKLENDİ: Masalar sayfası */
    case '/admin/tables':
        require_role(['admin']);
        view('admin/tables.php', ['title' => 'Masalar']);
        break;

    /* ---------- ADMIN API: MASA ---------- */
    /* Eksik olan listeleme eklendi */
    case '/admin/api/tables':            $admin->apiTables();         break;

    /* Mevcut ayrıntılı yollar */
    case '/admin/api/table-create':      $admin->apiTableCreate();    break;
    case '/admin/api/table-update':      $admin->apiTableUpdate();    break;
    case '/admin/api/table-delete':      $admin->apiTableDelete();    break;
    case '/admin/api/table-get':         $admin->apiTableGet();       break;
    case '/admin/api/table-set-active':  $admin->apiTableSetActive(); break;

    /* Frontend kolaylığı için alias’lar (tables.php eski çağrıları) */
    case '/admin/api/table':             $admin->apiTableCreate();    break; // POST create
    case '/admin/api/table-toggle':      $admin->apiTableSetActive(); break; // POST aktif/pasif

    /* ---------- ADMIN API: KATEGORİ ---------- */
    case '/admin/api/categories':        $admin->apiCategories();     break; // GET list
    case '/admin/api/category':          $admin->apiCategoryCreate(); break; // POST create
    case '/admin/api/category-update':   $admin->apiCategoryUpdate(); break; // POST update
    case '/admin/api/category-delete':   $admin->apiCategoryDelete(); break; // POST delete
    case '/admin/api/category-get':      $admin->apiCategoryGet();    break; // GET single (frontend kullanıyor)

    /* ---------- ADMIN API: ÜRÜN ---------- */
    case '/admin/api/products':          $admin->apiProducts();       break; // GET list/single
    case '/admin/api/product':           $admin->apiProductCreate();  break; // POST create
    case '/admin/api/product-update':    $admin->apiProductUpdate();  break; // POST update
    case '/admin/api/product-delete':    $admin->apiProductDelete();  break; // POST delete
    case '/admin/api/product-toggle':    $admin->apiProductToggle();  break; // POST aktif/pasif
    case '/admin/api/product-favorite':  $admin->apiProductFavorite();break; // POST fav toggle
    case '/admin/api/product-get':       $admin->apiProductGet();     break; // GET single


    /* ---------- ADMIN API: RAPORLAR ---------- */
    case '/admin/reports':
        require_role(['admin']);
        view('admin/reports.php', ['title' => 'Raporlar']);
        break;

    case '/admin/api/reports/sales':        $admin->apiReportsSales();      break;
    case '/admin/api/reports/by-category':  $admin->apiReportsByCategory(); break;
    case '/admin/api/reports/by-product':   $admin->apiReportsByProduct();  break;
    case '/admin/api/reports/payments':     $admin->apiReportsPayments();   break;
    case '/admin/api/reports/hours':        $admin->apiReportsHours();      break;
    case '/admin/api/reports/tables':       $admin->apiReportsTables();     break;
    case '/admin/api/reports/staff':        $admin->apiReportsStaff();      break;


    /* ---------- 404 ---------- */
    default:
        http_response_code(404);
        echo '<h3 style="font-family:system-ui;margin:40px">404 - Sayfa bulunamadı</h3>';
}
