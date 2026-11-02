<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#351014">
    <title><?php echo e($label); ?> · Al Asra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset('panel-assets/admin-ui.css')); ?>">
</head>
<body data-page="<?php echo e($page); ?>" data-id="<?php echo e($id ?? ''); ?>">
    <?php
        $icons = [
            'dashboard' => '◫', 'orders' => '▤', 'products' => '◇',
            'categories' => '▦', 'coupons' => '%', 'banners' => '▱',
            'faqs' => '?', 'reviews' => '☆', 'customers' => '♙',
            'staff' => '♧', 'settings' => '⚙', 'audit' => '◷',
        ];
    ?>

    <div class="app-shell">
        <aside class="sidebar" id="admin-sidebar">
            <div class="brand">
                <span class="mark">A</span>
                <span><b>AL ASRA</b><small>STORE MANAGEMENT</small></span>
                <button class="mobileClose" type="button" aria-label="Close navigation"
                    onclick="document.getElementById('admin-sidebar').classList.remove('open')">×</button>
            </div>

            <nav aria-label="Admin navigation">
                <span class="eyebrow navSection">WORKSPACE</span>
                <?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e($slug === 'dashboard' ? url('/admin/dashboard') : url('/admin/'.$slug)); ?>"
                       class="<?php echo e($page === $slug ? 'active' : ''); ?>">
                        <span class="navIcon" aria-hidden="true"><?php echo e($icons[$slug] ?? '•'); ?></span>
                        <span><?php echo e($name); ?></span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>

            <div class="storeCard">
                <span class="liveDot"></span> Store administration
                <small>Al Asra · Pakistan</small>
            </div>
        </aside>

        <div class="mainArea">
            <header class="topbar">
                <div class="topbarLeft">
                    <button class="menu" type="button" aria-label="Open navigation"
                        onclick="document.getElementById('admin-sidebar').classList.add('open')">☰</button>
                    <div class="crumb">Al Asra <span>/</span> <b><?php echo e($label); ?></b></div>
                </div>
                <div class="topActions">
                    <span class="avatar" aria-label="Administrator">AA</span>
                    <button id="lo" class="logoutBtn" type="button">Log out</button>
                </div>
            </header>

            <section class="contentArea">
                <div class="hero">
                    <div>
                        <p class="eyebrow">AL ASRA ADMINISTRATION</p>
                        <h1><?php echo e($label); ?></h1>
                        <p>Manage your store, orders and customers.</p>
                    </div>
                </div>
                <main id="main" aria-live="polite"></main>
            </section>
        </div>
    </div>

    <div id="msg" class="toast" role="status" aria-live="polite"></div>
    <script src="<?php echo e(asset('panel-assets/panel.js')); ?>" defer></script>
</body>
</html>
<?php /**PATH E:\laragon\www\al_asra_bckend\resources\views/panel/page.blade.php ENDPATH**/ ?>