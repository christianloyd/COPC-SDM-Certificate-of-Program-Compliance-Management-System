<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

// Generate CSRF token for login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Session expired or invalid token. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$username || !$password) {
            $error = "Please enter both username and password.";
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare('SELECT id, password_hash, full_name FROM admin_users WHERE username = ?');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Success
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['last_activity'] = time();

                    // Update last login
                    $updateStmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
                    $updateStmt->execute([$user['id']]);

                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    exit;
                } else {
                    $error = "Invalid username or password.";
                }
            } catch (\Exception $e) {
                $error = "A database error occurred. Please try again.";
            }
        }
    }
}

// Check for timeout error from auth.php
if (isset($_GET['error']) && $_GET['error'] === 'timeout') {
    $error = "Your session has expired. Please log in again.";
}

$pageTitle = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<section class="relative mx-auto max-w-xl overflow-hidden rounded-[2rem] border border-gray-100 bg-gradient-to-br from-white via-prclight to-blue-50/70 shadow-soft-lg">
    <div class="absolute -left-16 top-0 h-40 w-40 rounded-full bg-prcgold/10 blur-3xl"></div>
    <div class="absolute right-0 top-0 h-48 w-48 rounded-full bg-prcnavy/10 blur-3xl"></div>

    <div class="relative px-6 py-10 sm:px-8 sm:py-12">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-10 text-center">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-gradient-to-br from-prcnavy to-blue-900 shadow-xl shadow-blue-900/20 mb-6 group transition-transform hover:scale-105">
                    <i class="fa-solid fa-shield-halved text-3xl text-prcgold group-hover:animate-pulse"></i>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Welcome Back</h1>
                <p class="mt-2 text-sm font-medium tracking-wide text-gray-500 uppercase">PRC COPC System Administration</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 shadow-sm animate-fade-in flex mt-2">
                    <i class="fa-solid fa-triangle-exclamation mr-3 mt-0.5 text-red-500 text-lg"></i>
                    <span class="font-medium leading-relaxed"><?php echo h($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                <div class="group">
                    <label for="username" class="mb-2.5 block text-[11px] font-black uppercase tracking-[0.15em] text-gray-500 group-focus-within:text-prcnavy transition-colors">Username</label>
                    <div class="relative">
                        <input type="text" id="username" name="username" class="w-full rounded-2xl border-2 border-gray-100 bg-gray-50 py-4 px-6 text-gray-900 font-medium shadow-sm transition-all focus:border-prcgold focus:bg-white focus:outline-none focus:ring-4 focus:ring-prcgold/10 placeholder-gray-400 hover:border-gray-200" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="group">
                    <label for="password" class="mb-2.5 block text-[11px] font-black uppercase tracking-[0.15em] text-gray-500 group-focus-within:text-prcnavy transition-colors">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="w-full rounded-2xl border-2 border-gray-100 bg-gray-50 py-4 px-6 text-gray-900 font-medium shadow-sm transition-all focus:border-prcgold focus:bg-white focus:outline-none focus:ring-4 focus:ring-prcgold/10 placeholder-gray-400 hover:border-gray-200" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="group relative mt-2 flex w-full items-center justify-center overflow-hidden rounded-2xl bg-prcnavy px-6 py-4 text-sm font-black uppercase tracking-[0.15em] text-white shadow-lg shadow-prcnavy/20 transition-all hover:-translate-y-1 hover:shadow-xl hover:bg-prcaccent focus:outline-none focus:ring-4 focus:ring-prcgold/30">
                    <span class="relative z-10 flex items-center gap-2">
                        <span>Sign In Securely</span>
                        <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <a href="<?php echo BASE_URL; ?>/index.php" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-gray-400 transition hover:text-prcnavy py-2 px-4 rounded-xl hover:bg-gray-50">
                    <i class="fa-solid fa-arrow-left"></i> Return to Document Search
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
