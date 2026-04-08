<?php
// Charger la DB dans le scope de cette vue pour les updates de compte.
require('../data/backdb.php');
require_once('../data/middleware.php');
initMiddleware($dtb);

$app_base = defined('APP_BASE') ? APP_BASE : '';
$user = currentUser();

if (!$user || empty($user['id'])) {
	header('Location: ' . $app_base . '/login', true, 302);
	exit;
}

$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
	$currentPassword = trim($_POST['current_password'] ?? '');
	$newPassword = trim($_POST['new_password'] ?? '');
	$confirmPassword = trim($_POST['confirm_password'] ?? '');
	$csrf = $_POST['csrf_token'] ?? '';

	if (!verify_csrf($csrf)) {
		header('Location: ' . $app_base . '/my-account?error=csrf', true, 302);
		exit;
	}

	if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
		header('Location: ' . $app_base . '/my-account?error=empty', true, 302);
		exit;
	}

	if (strlen($newPassword) < 8) {
		header('Location: ' . $app_base . '/my-account?error=length', true, 302);
		exit;
	}

	if ($newPassword !== $confirmPassword) {
		header('Location: ' . $app_base . '/my-account?error=mismatch', true, 302);
		exit;
	}

	$salt = 'fixing_password';
	$currentHash = hash('sha256', $currentPassword . $salt);

	if (!hash_equals((string)$user['password'], $currentHash)) {
		header('Location: ' . $app_base . '/my-account?error=current', true, 302);
		exit;
	}

	$newHash = hash('sha256', $newPassword . $salt);

	try {
		$stmt = $dtb->prepare('UPDATE compt_utilisateur SET password = :password WHERE id = :id LIMIT 1');
		$updated = $stmt->execute([
			'password' => $newHash,
			'id' => (int)$user['id']
		]);

		if ($updated) {
			$_SESSION['infinit_password'] = $newHash;
			$_SESSION['password_updated_at'] = time();
			header('Location: ' . $app_base . '/my-account?status=password-updated', true, 302);
			exit;
		}

		header('Location: ' . $app_base . '/my-account?error=update', true, 302);
		exit;
	} catch (Throwable $e) {
		header('Location: ' . $app_base . '/my-account?error=server', true, 302);
		exit;
	}
}

$roleLabel = getRoleLabel((int)($user['level'] ?? 0));
$displayName = trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''));
$displayName = $displayName !== '' ? $displayName : ($user['pseudo'] ?? 'Utilisateur');
$lastLogin = !empty($_SESSION['login_time']) ? date('d/m/Y H:i', (int)$_SESSION['login_time']) : 'N/A';
$passwordUpdatedAt = !empty($_SESSION['password_updated_at']) ? date('d/m/Y H:i', (int)$_SESSION['password_updated_at']) : 'Jamais';

$messageMap = [
	'password-updated' => ['success', 'Mot de passe mis a jour avec succes.'],
];

$errorMap = [
	'csrf' => 'Session invalide. Veuillez reessayer.',
	'empty' => 'Tous les champs mot de passe sont obligatoires.',
	'length' => 'Le nouveau mot de passe doit contenir au moins 8 caracteres.',
	'mismatch' => 'La confirmation du mot de passe ne correspond pas.',
	'current' => 'Le mot de passe actuel est incorrect.',
	'update' => 'Impossible de mettre a jour le mot de passe.',
	'server' => 'Erreur serveur lors de la mise a jour.',
];

$toastType = '';
$toastMessage = '';
if (isset($messageMap[$status])) {
	$toastType = 'success';
	$toastMessage = $messageMap[$status][1];
} elseif (isset($errorMap[$error])) {
	$toastType = 'error';
	$toastMessage = $errorMap[$error];
}
?>
<!DOCTYPE html>
<html>
<head>
	<!-- REQUEST HEAD --><?php require('../init/head.php');?>
	<title>Mon compte</title>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
	<div class="h-screen w-full <?=$bg_three_color?>">

		<!-- TOP BAR --><?php require('../init/topbar.php');?>
		<!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
		<!-- BIG NOTIFICATION MANAGER --><?php require('../src/main/bigNotif.php');?>

		<div class="w-full flex flex-col lg:flex-row">

			<!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

			<div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">

				<div class="back flex-1 overflow-y-auto p-4 lg:p-6">
					<div class="max-w-4xl mx-auto space-y-4">

						<div class="<?=$bg_one_color?> border border-slate-700 rounded-xl p-5">
							<h1 class="text-xl text-white font-semibold mb-1">Mon compte</h1>
							<p class="text-slate-400">Consultez vos informations et securisez votre acces.</p>
						</div>

						<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
							<div class="lg:col-span-2 <?=$bg_one_color?> border border-slate-700 rounded-xl p-5">
								<h2 class="text-white text-lg font-semibold mb-4">Informations du profil</h2>
								<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
									<div>
										<div class="text-slate-400">Nom complet</div>
										<div class="text-white font-medium"><?=e($displayName)?></div>
									</div>
									<div>
										<div class="text-slate-400">Pseudo</div>
										<div class="text-white font-medium"><?=e($user['pseudo'] ?? 'N/A')?></div>
									</div>
									<div>
										<div class="text-slate-400">Role</div>
										<div class="text-white font-medium"><?=e($roleLabel)?></div>
									</div>
									<div>
										<div class="text-slate-400">Privilege</div>
										<div class="text-white font-medium"><?=e($user['privilege'] ?? 'N/A')?></div>
									</div>
									<div>
										<div class="text-slate-400">Derniere connexion</div>
										<div class="text-white font-medium"><?=e($lastLogin)?></div>
									</div>
									<div>
										<div class="text-slate-400">Mot de passe change le</div>
										<div class="text-white font-medium"><?=e($passwordUpdatedAt)?></div>
									</div>
								</div>
							</div>

							<div class="<?=$bg_one_color?> border border-slate-700 rounded-xl p-5">
								<h2 class="text-white text-lg font-semibold mb-3">Securite</h2>
								<div class="text-slate-400 text-sm space-y-2">
									<p>Utilisez un mot de passe long et unique.</p>
									<p>Ne partagez jamais vos identifiants.</p>
									<p>Fermez votre session sur les postes partages.</p>
								</div>
							</div>
						</div>

						<div class="<?=$bg_one_color?> border border-slate-700 rounded-xl p-5">
							<h2 class="text-white text-lg font-semibold mb-4">Changer le mot de passe</h2>

							<form method="post" action="<?=$app_base?>/my-account" class="grid grid-cols-1 md:grid-cols-3 gap-4">
								<input type="hidden" name="action" value="change_password">
								<input type="hidden" name="csrf_token" value="<?=csrf_token()?>">

								<div>
									<label class="block text-slate-300 text-sm mb-1">Mot de passe actuel</label>
									<input type="password" name="current_password" required class="w-full rounded-lg border border-slate-600 <?=$bg_two_color?> text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
								</div>

								<div>
									<label class="block text-slate-300 text-sm mb-1">Nouveau mot de passe</label>
									<input type="password" name="new_password" minlength="8" required class="w-full rounded-lg border border-slate-600 <?=$bg_two_color?> text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
								</div>

								<div>
									<label class="block text-slate-300 text-sm mb-1">Confirmer le mot de passe</label>
									<input type="password" name="confirm_password" minlength="8" required class="w-full rounded-lg border border-slate-600 <?=$bg_two_color?> text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500" />
								</div>

								<div class="md:col-span-3 flex justify-end">
									<button type="submit" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-medium transition-colors">
										Mettre a jour
									</button>
								</div>
							</form>
						</div>

					</div>
				</div>

				<?php require('../init/footer.php'); ?>
			</div>
		</div>
	</div>

	<script type="text/javascript">
	(function () {
		var toastType = <?=json_encode($toastType)?>;
		var toastMessage = <?=json_encode($toastMessage, JSON_UNESCAPED_UNICODE)?>;

		if (!toastType || !toastMessage) {
			return;
		}

		if (typeof Toast === 'undefined') {
			return;
		}

		if (toastType === 'success' && typeof Toast.success === 'function') {
			Toast.success(toastMessage);
			return;
		}

		if (toastType === 'error' && typeof Toast.error === 'function') {
			Toast.error(toastMessage);
		}
	})();
	</script>
</body>
</html>
