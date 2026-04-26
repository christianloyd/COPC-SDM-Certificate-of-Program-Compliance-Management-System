<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid or expired security token. Please try again.', 'Request Rejected');
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        $pdo = getDBConnection();

        if ($action === 'create_user' || $action === 'update_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($username === '') {
                throw new RuntimeException('Username is required.');
            }

            if (!preg_match('/^[a-zA-Z0-9._-]{4,100}$/', $username)) {
                throw new RuntimeException('Username must be 4 to 100 characters and use only letters, numbers, dots, underscores, or dashes.');
            }

            $passwordIsRequired = $action === 'create_user';
            if ($passwordIsRequired && $password === '') {
                throw new RuntimeException('Password is required.');
            }

            if ($password !== '' || $confirmPassword !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException('Password must be at least 8 characters long.');
                }

                if (!hash_equals($password, $confirmPassword)) {
                    throw new RuntimeException('Password confirmation does not match.');
                }
            }

            if ($action === 'create_user') {
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ?');
                $checkStmt->execute([$username]);
                if ((int) $checkStmt->fetchColumn() > 0) {
                    throw new RuntimeException('That username is already in use.');
                }

                $insertStmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, full_name) VALUES (?, ?, ?)');
                $insertStmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName !== '' ? $fullName : null,
                ]);

                setFlashMessage('success', 'New user account created successfully.', 'User Created');
            } else {
                if ($userId <= 0) {
                    throw new RuntimeException('Invalid user selection.');
                }

                $currentUserStmt = $pdo->prepare('SELECT id FROM admin_users WHERE id = ?');
                $currentUserStmt->execute([$userId]);
                if (!$currentUserStmt->fetch()) {
                    throw new RuntimeException('User account not found.');
                }

                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ? AND id <> ?');
                $checkStmt->execute([$username, $userId]);
                if ((int) $checkStmt->fetchColumn() > 0) {
                    throw new RuntimeException('That username is already in use by another account.');
                }

                if ($password !== '') {
                    $updateStmt = $pdo->prepare('UPDATE admin_users SET username = ?, full_name = ?, password_hash = ? WHERE id = ?');
                    $updateStmt->execute([
                        $username,
                        $fullName !== '' ? $fullName : null,
                        password_hash($password, PASSWORD_DEFAULT),
                        $userId,
                    ]);
                } else {
                    $updateStmt = $pdo->prepare('UPDATE admin_users SET username = ?, full_name = ? WHERE id = ?');
                    $updateStmt->execute([
                        $username,
                        $fullName !== '' ? $fullName : null,
                        $userId,
                    ]);
                }

                if ((int) ($_SESSION['admin_id'] ?? 0) === $userId) {
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $fullName;
                }

                setFlashMessage('success', 'User account updated successfully.', 'User Updated');
            }
        } elseif ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user selection.');
            }

            if ((int) ($_SESSION['admin_id'] ?? 0) === $userId) {
                throw new RuntimeException('You cannot delete the account that is currently signed in.');
            }

            $deleteStmt = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
            $deleteStmt->execute([$userId]);

            if ($deleteStmt->rowCount() === 0) {
                throw new RuntimeException('User account not found.');
            }

            setFlashMessage('success', 'User account deleted successfully.', 'User Deleted');
        } else {
            throw new RuntimeException('Unsupported action.');
        }
    } catch (\Throwable $e) {
        setFlashMessage('error', $e->getMessage(), 'User Management Error');
    }

    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

try {
    $pdo = getDBConnection();
    $userStmt = $pdo->query('SELECT id, username, full_name, created_at, last_login_at FROM admin_users ORDER BY created_at DESC');
    $users = $userStmt->fetchAll();
} catch (\Throwable $e) {
    die('Unable to load user accounts: ' . $e->getMessage());
}

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mx-auto max-w-7xl">
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-prcnavy">User Management</h1>
            <p class="mt-2 text-sm text-gray-500">Manage admin portal accounts. New users are created only by an admin from this page.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs font-bold uppercase tracking-widest text-blue-700">
                <?php echo number_format(count($users)); ?> total account<?php echo count($users) === 1 ? '' : 's'; ?>
            </div>
            <button type="button" id="openAddUserModalBtn" class="inline-flex items-center rounded-2xl bg-prcnavy px-5 py-3 text-sm font-bold uppercase tracking-widest text-white transition hover:bg-prcaccent">
                <i class="fa-solid fa-user-plus mr-2 text-prcgold"></i> Add User
            </button>
        </div>
    </div>

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft">
        <div class="border-b border-gray-100 px-8 py-6">
            <h2 class="text-xl font-bold text-prcnavy">Existing Accounts</h2>
            <p class="mt-1 text-sm text-gray-500">Click edit to update name, username, or password. Delete removes the account permanently.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-left">
                <thead class="bg-prclight text-xs font-bold uppercase tracking-widest text-prcnavy">
                    <tr>
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Username</th>
                        <th class="px-6 py-4">Created</th>
                        <th class="px-6 py-4">Last Login</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center italic text-gray-400">No user accounts found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php $isCurrentUser = (int) $user['id'] === (int) ($_SESSION['admin_id'] ?? 0); ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 align-top">
                                    <div class="font-bold text-prcnavy"><?php echo h($user['full_name'] ?: 'No full name set'); ?></div>
                                    <?php if ($isCurrentUser): ?>
                                        <span class="mt-2 inline-flex rounded-full border border-green-200 bg-green-50 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-green-700">Current Session</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-gray-600"><?php echo h($user['username']); ?></td>
                                <td class="px-6 py-4 align-top text-gray-500"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></td>
                                <td class="px-6 py-4 align-top text-gray-500">
                                    <?php echo $user['last_login_at'] ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'Never logged in'; ?>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="edit-user-btn inline-flex items-center rounded-xl bg-amber-50 px-4 py-2 text-xs font-bold uppercase tracking-widest text-amber-600 transition hover:bg-amber-500 hover:text-white"
                                            data-user-id="<?php echo (int) $user['id']; ?>"
                                            data-full-name="<?php echo h($user['full_name'] ?? ''); ?>"
                                            data-username="<?php echo h($user['username']); ?>"
                                        >
                                            <i class="fa-solid fa-pen-to-square mr-2"></i> Edit
                                        </button>

                                        <?php if ($isCurrentUser): ?>
                                            <span class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-bold uppercase tracking-widest text-gray-400">Protected</span>
                                        <?php else: ?>
                                            <form method="POST" action="" class="delete-user-form inline-flex">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                <input type="hidden" name="username_label" value="<?php echo h($user['username']); ?>">
                                                <button type="submit" class="inline-flex items-center rounded-xl bg-red-50 px-4 py-2 text-xs font-bold uppercase tracking-widest text-red-500 transition hover:bg-red-500 hover:text-white">
                                                    <i class="fa-solid fa-user-xmark mr-2"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="userModalOverlay" class="fixed inset-0 z-[118] hidden bg-prcnavy/70 p-4">
    <div class="flex min-h-screen items-center justify-center">
        <div class="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-soft-lg">
            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
                <div class="flex items-center">
                    <div class="mr-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-prcgold/10 text-prcgold">
                        <i class="fa-solid fa-users-gear text-xl"></i>
                    </div>
                    <div>
                        <h3 id="userModalTitle" class="text-lg font-bold text-prcnavy">Add User</h3>
                        <p id="userModalSubtitle" class="text-xs uppercase tracking-widest text-gray-400">Create a new admin account</p>
                    </div>
                </div>
                <button id="closeUserModalBtn" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-900">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="userModalForm" method="POST" action="" class="space-y-5 px-6 py-6">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" id="userModalAction" value="create_user">
                <input type="hidden" name="user_id" id="userModalUserId" value="">

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-bold uppercase tracking-widest text-gray-500">Full Name</label>
                        <input type="text" id="userModalFullName" name="full_name" class="w-full rounded-2xl border-none bg-gray-50 px-4 py-3 text-prcnavy focus:outline-none focus:ring-2 focus:ring-prcgold" placeholder="Optional display name">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-bold uppercase tracking-widest text-gray-500">Username *</label>
                        <input type="text" id="userModalUsername" name="username" required class="w-full rounded-2xl border-none bg-gray-50 px-4 py-3 text-prcnavy focus:outline-none focus:ring-2 focus:ring-prcgold" placeholder="e.g. staff.audit">
                        <p class="mt-1 text-[11px] text-gray-400">Use 4 to 100 characters with letters, numbers, dot, underscore, or dash.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-widest text-gray-500">Password <span id="userModalPasswordRequired">*</span></label>
                        <input type="password" id="userModalPassword" name="password" class="w-full rounded-2xl border-none bg-gray-50 px-4 py-3 text-prcnavy focus:outline-none focus:ring-2 focus:ring-prcgold" placeholder="Minimum 8 characters">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-widest text-gray-500">Confirm Password <span id="userModalConfirmRequired">*</span></label>
                        <input type="password" id="userModalConfirmPassword" name="confirm_password" class="w-full rounded-2xl border-none bg-gray-50 px-4 py-3 text-prcnavy focus:outline-none focus:ring-2 focus:ring-prcgold" placeholder="Re-enter password">
                    </div>
                </div>

                <p id="userModalPasswordHelp" class="text-xs text-gray-400">Set a password for this account.</p>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelUserModalBtn" class="inline-flex items-center justify-center rounded-2xl border border-gray-200 px-5 py-3 text-sm font-bold uppercase tracking-widest text-gray-600 transition hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" id="saveUserModalBtn" class="inline-flex items-center justify-center rounded-2xl bg-prcnavy px-5 py-3 text-sm font-bold uppercase tracking-widest text-white transition hover:bg-prcaccent">
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalOverlay = document.getElementById('userModalOverlay');
    const openAddUserModalBtn = document.getElementById('openAddUserModalBtn');
    const closeUserModalBtn = document.getElementById('closeUserModalBtn');
    const cancelUserModalBtn = document.getElementById('cancelUserModalBtn');
    const userModalTitle = document.getElementById('userModalTitle');
    const userModalSubtitle = document.getElementById('userModalSubtitle');
    const userModalForm = document.getElementById('userModalForm');
    const userModalAction = document.getElementById('userModalAction');
    const userModalUserId = document.getElementById('userModalUserId');
    const userModalFullName = document.getElementById('userModalFullName');
    const userModalUsername = document.getElementById('userModalUsername');
    const userModalPassword = document.getElementById('userModalPassword');
    const userModalConfirmPassword = document.getElementById('userModalConfirmPassword');
    const userModalPasswordRequired = document.getElementById('userModalPasswordRequired');
    const userModalConfirmRequired = document.getElementById('userModalConfirmRequired');
    const userModalPasswordHelp = document.getElementById('userModalPasswordHelp');
    const saveUserModalBtn = document.getElementById('saveUserModalBtn');
    const editButtons = document.querySelectorAll('.edit-user-btn');
    const deleteForms = document.querySelectorAll('.delete-user-form');

    const openModal = () => {
        modalOverlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modalOverlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    const setCreateMode = () => {
        userModalTitle.textContent = 'Add User';
        userModalSubtitle.textContent = 'Create a new admin account';
        userModalAction.value = 'create_user';
        userModalUserId.value = '';
        userModalForm.reset();
        userModalPassword.required = true;
        userModalConfirmPassword.required = true;
        userModalPasswordRequired.textContent = '*';
        userModalConfirmRequired.textContent = '*';
        userModalPasswordHelp.textContent = 'Set a password for this account.';
        saveUserModalBtn.textContent = 'Create User';
    };

    const setEditMode = (button) => {
        userModalTitle.textContent = 'Edit User';
        userModalSubtitle.textContent = 'Update account details or reset password';
        userModalAction.value = 'update_user';
        userModalUserId.value = button.dataset.userId || '';
        userModalFullName.value = button.dataset.fullName || '';
        userModalUsername.value = button.dataset.username || '';
        userModalPassword.value = '';
        userModalConfirmPassword.value = '';
        userModalPassword.required = false;
        userModalConfirmPassword.required = false;
        userModalPasswordRequired.textContent = '';
        userModalConfirmRequired.textContent = '';
        userModalPasswordHelp.textContent = 'Leave password fields blank if you do not want to change the current password.';
        saveUserModalBtn.textContent = 'Save Changes';
    };

    if (openAddUserModalBtn) {
        openAddUserModalBtn.addEventListener('click', () => {
            setCreateMode();
            openModal();
        });
    }

    editButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setEditMode(button);
            openModal();
        });
    });

    [closeUserModalBtn, cancelUserModalBtn].forEach((button) => {
        if (button) {
            button.addEventListener('click', closeModal);
        }
    });

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (event) => {
            if (event.target === modalOverlay) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalOverlay && !modalOverlay.classList.contains('hidden')) {
            closeModal();
        }
    });

    deleteForms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const username = form.querySelector('input[name="username_label"]')?.value || 'this user';
            let confirmed = true;

            if (typeof window.showAppConfirm === 'function') {
                confirmed = await window.showAppConfirm({
                    title: 'Delete User Account',
                    subtitle: 'Permanent account removal',
                    message: `This will permanently remove the account "${username}". Continue?`,
                    confirmText: 'Delete User',
                    cancelText: 'Cancel',
                    type: 'danger'
                });
            } else {
                confirmed = window.confirm(`Delete user account "${username}"?`);
            }

            if (confirmed) {
                form.submit();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
