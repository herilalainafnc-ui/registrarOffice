<?php
require_once('../data/backdb.php');
require_once('../data/middleware.php');
require_once('../data/newsletter.php');

$dtb = $dtb ?? ($GLOBALS['dtb'] ?? null);
if (!($dtb instanceof PDO) && function_exists('getDbConnection')) {
    $dtb = getDbConnection();
    if (class_exists('DB')) {
        DB::init($dtb);
    }
}
if (!($dtb instanceof PDO)) {
    throw new RuntimeException('Connexion base de donnees indisponible pour la newsletter.');
}

initMiddleware($dtb);

$flash = $newsletterFlash ?? ($_SESSION['newsletter_flash'] ?? '');
unset($_SESSION['newsletter_flash']);

ensureNewsletterTables($dtb);
$subscribers = newsletterListSubscribers($dtb, 500);
$campaigns = newsletterListCampaigns($dtb, 30);
$subscriberTotals = ['active' => 0, 'unsubscribed' => 0];
foreach ($subscribers as $subscriber) {
    $status = (string)($subscriber['status'] ?? '');
    if (isset($subscriberTotals[$status])) {
        $subscriberTotals[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require('../init/head.php');?>
    <title>Newsletter</title>
    <style>
        .newsletter-shell { width: 100%; margin: 0 auto; padding: 16px; }
        .newsletter-hero { background: linear-gradient(135deg, rgb(var(--c-surface)) 0%, rgb(var(--c-surface-2)) 100%); border-radius: 16px; border: 1px solid rgb(var(--c-line)); color: rgb(var(--c-text)); padding: 28px; }
        .newsletter-card { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-line-strong)); border-radius: 14px; box-shadow: var(--shadow-1); }
        .newsletter-input, .newsletter-textarea, .newsletter-select { width: 100%; border: 1px solid rgb(var(--c-line-strong)); border-radius: 14px; padding: 12px 14px; font-size: 14px; background: rgb(var(--c-inset)); color: rgb(var(--c-text)); }
        .newsletter-input:focus, .newsletter-textarea:focus, .newsletter-select:focus { outline: none; border-color: rgb(var(--c-accent)); box-shadow: var(--ring); }
        .newsletter-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 999px; padding: 12px 18px; font-weight: 700; }
        .newsletter-btn-primary { background: rgb(var(--c-accent-strong)); color: #fff; }
        .newsletter-btn-primary:hover { background: rgb(var(--c-accent)); }
        .newsletter-btn-dark { background: rgb(var(--c-raised)); color: rgb(var(--c-text)); }
        .newsletter-btn-dark:hover { background: rgb(var(--c-line-strong)); }
        .newsletter-table { width: 100%; border-collapse: collapse; }
        .newsletter-table th, .newsletter-table td { padding: 12px 10px; border-bottom: 1px solid rgb(var(--c-line)); text-align: left; vertical-align: top; }
        .newsletter-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
    </style>
</head>
</head>
<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
    <div class="h-screen w-full <?=$bg_three_color?>">

        <!-- TOP BAR --><?php require('../init/topbar.php');?>
        <!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>
        <!-- NOTIFICATION MANAGER --><?php require_once(__DIR__ . '/main/bigNotif.php');?>

        <div class="w-full flex flex-col lg:flex-row">

            <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

            <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
                <div class="back flex-1 overflow-y-auto p-0">
                    <div class="newsletter-shell">
                        <section class="newsletter-hero mb-6">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Communication</div>
                            <h1 class="mt-2 text-3xl font-bold">Newsletter</h1>
                            <p class="mt-3 max-w-3xl text-slate-300">Gere les abonnés, les campagnes et les envois planifiés. Chaque email inclut un lien de desabonnement individuel.</p>
                            <?php if (defined('MAIL_ENABLED') && MAIL_ENABLED): ?>
                                <div class="mt-4 inline-flex items-center rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-medium text-emerald-400">Envoi email actif</div>
                            <?php else: ?>
                                <div class="mt-4 inline-flex items-center rounded-full bg-amber-500/15 px-4 py-2 text-sm font-medium text-amber-400">Envoi email desactive en configuration</div>
                            <?php endif; ?>
                        </section>

                        <?php if (!empty($flash)): ?>
                            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800"><?=e($flash)?></div>
                        <?php endif; ?>

                        <div class="grid gap-6 lg:grid-cols-12">
                            <div class="lg:col-span-4 space-y-6">
                                <section class="newsletter-card p-6" data-tuto="nl-stats">
                                    <h2 class="text-xl font-semibold text-slate-100">Statistiques</h2>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="rounded-2xl bg-slate-900 p-4">
                                            <div class="text-sm text-slate-500">Actifs</div>
                                            <div class="text-3xl font-bold text-slate-100"><?= (int)($subscriberTotals['active'] ?? 0) ?></div>
                                        </div>
                                        <div class="rounded-2xl bg-slate-900 p-4">
                                            <div class="text-sm text-slate-500">Desabonnes</div>
                                            <div class="text-3xl font-bold text-slate-100"><?= (int)($subscriberTotals['unsubscribed'] ?? 0) ?></div>
                                        </div>
                                    </div>
                                </section>

                                <section class="newsletter-card p-6" data-tuto="nl-ajout">
                                    <h2 class="text-xl font-semibold text-slate-100">Ajouter un abonne</h2>
                                    <form method="post" class="mt-4 grid gap-4">
                                        <input type="hidden" name="newsletter_action" value="subscriber_add" />
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-300">Nom complet</label>
                                            <input type="text" name="full_name" class="newsletter-input" placeholder="Nom de l'abonne" />
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-300">Adresse email</label>
                                            <input type="email" name="email" class="newsletter-input" placeholder="adresse@domaine.com" required />
                                        </div>
                                        <button type="submit" class="newsletter-btn newsletter-btn-primary">Enregistrer</button>
                                    </form>
                                </section>

                                <section class="newsletter-card p-6" data-tuto="nl-abonnes">
                                    <h2 class="text-xl font-semibold text-slate-100">Abonnés</h2>
                                    <div class="mt-4 max-h-[520px] overflow-auto">
                                        <table class="newsletter-table">
                                            <thead>
                                            <tr>
                                                <th>Contact</th>
                                                <th>Etat</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($subscribers as $subscriber): ?>
                                                <tr>
                                                    <td>
                                                        <div class="font-semibold text-slate-100"><?=e((string)($subscriber['full_name'] ?: 'Sans nom'))?></div>
                                                        <div class="text-sm text-slate-500"><?=e((string)$subscriber['email'])?></div>
                                                    </td>
                                                    <td>
                                                        <span class="newsletter-pill <?=($subscriber['status'] ?? '') === 'active' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'?>"><?=e((string)($subscriber['status'] ?? ''))?></span>
                                                    </td>
                                                    <td>
                                                        <form method="post" onsubmit="return window.Toast ? Toast.confirmerFormulaire(event, this, 'Désabonner ce contact ?', { title: 'Désabonnement', confirmText: 'Désabonner' }) : false;">
                                                            <input type="hidden" name="newsletter_action" value="subscriber_unsubscribe" />
                                                            <input type="hidden" name="token" value="<?=e((string)($subscriber['unsubscribe_token'] ?? ''))?>" />
                                                            <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-700">Desabonner</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($subscribers)): ?>
                                                <tr><td colspan="3" class="py-8 text-center text-sm text-slate-500">Aucun abonne pour le moment.</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>

                            <div class="lg:col-span-8 space-y-6">
                                <section class="newsletter-card p-6" data-tuto="nl-campagne">
                                    <h2 class="text-xl font-semibold text-slate-100">Nouvelle campagne</h2>
                                    <form method="post" class="mt-4 grid gap-4">
                                        <input type="hidden" name="newsletter_action" value="campaign_create" />
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-300">Sujet</label>
                                            <input type="text" name="subject" class="newsletter-input" maxlength="255" required placeholder="Objet du message" />
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-300">Contenu texte</label>
                                            <textarea name="body_text" rows="9" class="newsletter-textarea" required placeholder="Redige ton message. Variables disponibles: {{nom}}, {{email}}, {{unsubscribe_url}}"></textarea>
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-300">Contenu HTML optionnel</label>
                                            <textarea name="body_html" rows="8" class="newsletter-textarea" placeholder="HTML optionnel. Si vide, le contenu texte est converti automatiquement."></textarea>
                                        </div>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-2 block text-sm font-semibold text-slate-300">Planifier pour</label>
                                                <input type="datetime-local" name="scheduled_at" class="newsletter-input" />
                                            </div>
                                            <div class="flex items-end">
                                                <label class="flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-slate-300">
                                                    <input type="checkbox" name="send_now" value="1" class="h-4 w-4" />
                                                    Envoyer immediatement apres enregistrement
                                                </label>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-3">
                                            <button type="submit" class="newsletter-btn newsletter-btn-primary">Enregistrer la campagne</button>
                                        </div>
                                    </form>
                                </section>

                                <section class="newsletter-card p-6" data-tuto="nl-campagnes">
                                    <h2 class="text-xl font-semibold text-slate-100">Campagnes</h2>
                                    <div class="mt-4 overflow-auto">
                                        <table class="newsletter-table">
                                            <thead>
                                            <tr>
                                                <th>Sujet</th>
                                                <th>Planification</th>
                                                <th>Resultat</th>
                                                <th>Etat</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($campaigns as $campaign): ?>
                                                <tr>
                                                    <td>
                                                        <div class="font-semibold text-slate-100"><?=e((string)$campaign['subject'])?></div>
                                                        <div class="text-xs text-slate-500">Par <?=e((string)($campaign['created_by'] ?? ''))?></div>
                                                    </td>
                                                    <td class="text-sm text-slate-400">
                                                        <?=e((string)($campaign['scheduled_at'] ?? ''))?><br />
                                                        <span class="text-xs text-slate-500">Envoye le <?=e((string)($campaign['sent_at'] ?? '—'))?></span>
                                                    </td>
                                                    <td class="text-sm text-slate-300">
                                                        <?= (int)($campaign['sent_total'] ?? 0) ?> envoyes<br />
                                                        <?= (int)($campaign['failed_total'] ?? 0) ?> echecs
                                                    </td>
                                                    <td>
                                                        <span class="newsletter-pill <?=($campaign['status'] ?? '') === 'sent' ? 'bg-emerald-500/15 text-emerald-300' : ((($campaign['status'] ?? '') === 'scheduled' || ($campaign['status'] ?? '') === 'draft') ? 'bg-amber-500/15 text-amber-300' : 'bg-slate-700 text-slate-300')?>"><?=e((string)($campaign['status'] ?? ''))?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($campaigns)): ?>
                                                <tr><td colspan="4" class="py-8 text-center text-sm text-slate-500">Aucune campagne pour le moment.</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <?php require('../init/footer.php'); ?>
            </div>
        </div>
    </div>
</body>
</html>
