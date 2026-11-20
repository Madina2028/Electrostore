<?php
require_once __DIR__ . '/auth/security.php';
secureSessionStart();
require_once __DIR__ . '/database_conn.php';

$db = new Database();
$conn = $db->connect();

$search = $_GET['search'] ?? '';
$search = is_string($search) ? trim(substr($search, 0, 100)) : '';
$sortOptions = ['newest', 'name-asc', 'price-asc', 'price-desc'];
$sort = $_GET['sort'] ?? 'newest';
$sort = is_string($sort) && in_array($sort, $sortOptions, true) ? $sort : 'newest';
$category = $_GET['category'] ?? '';
$category = is_string($category) ? $category : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'], $_POST['product_id'])) {
    rejectInvalidCsrf();

    if (!isset($_SESSION['user_id'])) {
        header('Location: auth/login.php?message=login_required');
        exit();
    }

    $productId = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1) {
        http_response_code(400);
        exit('Invalid product.');
    }

    $stmt = $conn->prepare('SELECT id, name, price FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        exit('Product not found.');
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $found = false;
    foreach ($_SESSION['cart'] as &$cartItem) {
        if ((int) $cartItem['id'] === (int) $product['id']) {
            $cartItem['quantity'] = min(100, (int) $cartItem['quantity'] + 1);
            $found = true;
            break;
        }
    }
    unset($cartItem);

    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => 1,
        ];
    }

    $redirectParams = ['added' => 1];
    if ($search !== '') {
        $redirectParams['search'] = $search;
    }
    if ($category !== '') {
        $redirectParams['category'] = $category;
    }
    if ($sort !== 'newest') {
        $redirectParams['sort'] = $sort;
    }
    header('Location: shop.php?' . http_build_query($redirectParams, '', '&', PHP_QUERY_RFC3986));
    exit();
}

$stmt = $conn->query('SELECT id, name, description, price, image_path, created_at FROM products');
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [
    'laptops' => 'Laptops',
    'desktops' => 'Desktops',
    'tablets' => 'Tablets',
    'printers' => 'Printers',
    'other' => 'Other',
];
$getCategory = static function (array $product): string {
    $searchable = strtolower($product['name'] . ' ' . ($product['description'] ?? ''));
    if (strpos($searchable, 'laptop') !== false || strpos($searchable, 'notebook') !== false) {
        return 'laptops';
    }
    if (strpos($searchable, 'desktop') !== false || strpos($searchable, 'gaming pc') !== false) {
        return 'desktops';
    }
    if (strpos($searchable, 'tablet') !== false || strpos($searchable, 'elite x2') !== false) {
        return 'tablets';
    }
    if (
        strpos($searchable, 'printer') !== false
        || strpos($searchable, 'laserjet') !== false
        || strpos($searchable, 'officejet') !== false
    ) {
        return 'printers';
    }
    return 'other';
};

$availableCategories = [];
foreach ($products as $product) {
    $productCategory = $getCategory($product);
    $availableCategories[$productCategory] = $categoryLabels[$productCategory];
}
if (!array_key_exists($category, $availableCategories)) {
    $category = '';
}

$products = array_values(array_filter($products, static function (array $product) use ($search, $category, $getCategory): bool {
    $matchesSearch = $search === ''
        || stripos($product['name'] . ' ' . ($product['description'] ?? ''), $search) !== false;
    $matchesCategory = $category === '' || $getCategory($product) === $category;
    return $matchesSearch && $matchesCategory;
}));

usort($products, static function (array $first, array $second) use ($sort): int {
    if ($sort === 'price-asc') {
        return (float) $first['price'] <=> (float) $second['price'];
    }
    if ($sort === 'price-desc') {
        return (float) $second['price'] <=> (float) $first['price'];
    }
    if ($sort === 'name-asc') {
        return strcasecmp($first['name'], $second['name']);
    }
    return strcmp($second['created_at'] ?? '', $first['created_at'] ?? '')
        ?: ((int) $second['id'] <=> (int) $first['id']);
});

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $cartItem) {
    $cartCount += max(0, (int) ($cartItem['quantity'] ?? 0));
}
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Electronics - Electrostore</title>
    <meta name="description" content="Browse laptops, desktops, tablets, and printers at Electrostore.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/shop.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row align-items-center py-3 px-xl-5">
            <div class="col-lg-3 d-none d-lg-block">
                <a href="shop.php" class="text-decoration-none">
                    <h1 class="m-0 display-6 font-weight-semi-bold">
                        <span class="text-primary font-weight-bold border px-3 mr-1">E</span>lectrostore
                    </h1>
                </a>
            </div>
            <div class="col-lg-1">
                <a href="cartpg.php" class="btn border" aria-label="Shopping cart, <?= $cartCount ?> items">
                    <i class="fas fa-shopping-cart text-primary" aria-hidden="true"></i>
                    <span class="badge"><?= $cartCount ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="row align-items-center justify-content-center">
            <div class="col-lg-11">
                <nav class="navbar navbar-expand-lg bg-white navbar-light py-3 py-lg-0 px-0">
                    <a href="shop.php" class="text-decoration-none d-block d-lg-none">
                        <h1 class="m-0 display-5 font-weight-semi-bold"><span class="text-primary font-weight-bold border px-3 mr-1">E</span>lectrostore</h1>
                    </a>
                    <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse justify-content-between" id="navbarCollapse">
                        <div class="navbar-nav mr-auto py-0">
                            <a href="index.php" class="nav-item nav-link">Home</a>
                            <a href="shop.php" class="nav-item nav-link active" aria-current="page">Shop</a>
                            <a href="cartpg.php" class="nav-item nav-link">Shopping Cart</a>
                            <a href="contact.php" class="nav-item nav-link">Contact</a>
                        </div>
                        <div class="navbar-nav ml-auto py-0">
                            <?php if ($isLoggedIn): ?>
                                <a href="profile.php" class="nav-item nav-link">Profile</a>
                                <a href="auth/logout.php" class="nav-item nav-link">Logout</a>
                            <?php else: ?>
                                <a href="auth/login.php" class="nav-item nav-link">Login</a>
                                <a href="auth/signup.php" class="nav-item nav-link">Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    <main>
        <section class="products-section" id="products">
            <div class="products-heading">
                <div>
                    <p class="shop-eyebrow">Our collection</p>
                    <h2>Shop all products</h2>
                </div>
                <span><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></span>
            </div>

            <?php if (isset($_GET['added'])): ?>
                <div class="shop-alert" role="status">Product added to your cart.</div>
            <?php endif; ?>

            <form class="shop-filters" method="get" action="shop.php">
                <label class="shop-search">
                    <span class="sr-only">Search products</span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search products...">
                </label>
                <label>
                    <span class="sr-only">Category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($availableCategories as $categoryValue => $categoryLabel): ?>
                            <option value="<?= htmlspecialchars($categoryValue, ENT_QUOTES, 'UTF-8') ?>" <?= $category === $categoryValue ? 'selected' : '' ?>><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="sr-only">Sort products</span>
                    <select name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="name-asc" <?= $sort === 'name-asc' ? 'selected' : '' ?>>Name: A to Z</option>
                        <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>>Price: low to high</option>
                        <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: high to low</option>
                    </select>
                </label>
                <button type="submit" class="filter-submit">Apply filters</button>
                <?php if ($search !== '' || $category !== '' || $sort !== 'newest'): ?>
                    <a class="filter-reset" href="shop.php">Clear</a>
                <?php endif; ?>
            </form>

            <?php if ($products): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="shop-product-card">
                            <a class="product-image" href="productpg.php?id=<?= (int) $product['id'] ?>">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="product-no-image">Image coming soon</span>
                                <?php endif; ?>
                            </a>
                            <div class="product-info">
                                <span class="product-category"><?= htmlspecialchars($categoryLabels[$getCategory($product)], ENT_QUOTES, 'UTF-8') ?></span>
                                <h3><a href="productpg.php?id=<?= (int) $product['id'] ?>"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                                <p><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="product-card-footer">
                                    <strong>$<?= number_format((float) $product['price'], 2) ?></strong>
                                    <form method="post">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" name="add_to_cart" aria-label="Add <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> to cart">
                                            <i class="fas fa-plus" aria-hidden="true"></i> Add to cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($search !== '' || $category !== ''): ?>
                <div class="empty-products">
                    <p>No products match those filters.</p>
                    <a href="shop.php">Clear filters and view all products</a>
                </div>
            <?php else: ?>
                <p class="empty-products">There are no products to display right now. Please check back soon.</p>
            <?php endif; ?>
        </section>
    </main>

    <div class="container-fluid bg-secondary text-dark mt-5 pt-5">
        <div class="row px-xl-5 pt-5">
            <div class="col-lg-4 col-md-12 mb-5 pr-3 pr-xl-5">
                <a href="index.php" class="text-decoration-none">
                    <h1 class="mb-4 display-5 font-weight-semi-bold"><span class="text-primary font-weight-bold border border-white px-3 mr-1">E</span>lectrostore</h1>
                </a>
                <p class="mb-2"><i class="fa fa-map-marker-alt text-primary mr-3"></i>123 ElectroStore</p>
                <p class="mb-2"><i class="fa fa-envelope text-primary mr-3"></i>info@ElectroStore.com</p>
                <p class="mb-0"><i class="fa fa-phone-alt text-primary mr-3"></i>+012 345 67890</p>
            </div>
            <div class="col-lg-8 col-md-12">
                <div class="row">
                    <div class="col-md-4 mb-5">
                        <h5 class="font-weight-bold text-dark mb-4">Quick Links</h5>
                        <div class="d-flex flex-column justify-content-start">
                            <a class="text-dark mb-2" href="index.php"><i class="fa fa-angle-right mr-2"></i>Home</a>
                            <a class="text-dark mb-2" href="shop.php"><i class="fa fa-angle-right mr-2"></i>Shop</a>
                            <a class="text-dark mb-2" href="cartpg.php"><i class="fa fa-angle-right mr-2"></i>Shopping Cart</a>
                            <a class="text-dark" href="contact.php"><i class="fa fa-angle-right mr-2"></i>Contact Us</a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-5">
                        <h5 class="font-weight-bold text-dark mb-4">Newsletter</h5>
                        <form action="">
                            <div class="form-group">
                                <input type="text" class="form-control border-0 py-4" placeholder="Your Name" required="required" />
                            </div>
                            <div class="form-group">
                                <input type="email" class="form-control border-0 py-4" placeholder="Your Email"
                                    required="required" />
                            </div>
                            <div>
                                <button class="btn btn-primary btn-block border-0 py-3" type="submit">Subscribe Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-dark text-white text-center py-3">&copy; 2025 ElectroStore. All rights reserved.</footer>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
</body>
</html>
