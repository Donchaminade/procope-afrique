<?php /** Variables : $result (rows,total,page,pages) */
use App\Models\User;

$users = $result['rows'];

$roleBadges = [
    'super_admin' => 'bg-brand-orange/10 text-amber-700 ring-brand-orange/30',
    'admin'       => 'bg-brand-blue/10 text-sky-700 ring-brand-blue/30',
    'operator'    => 'bg-slate-100 text-slate-600 ring-slate-300',
];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Utilisateurs</h1>
        <p class="mt-1 text-sm text-slate-500">Comptes de l'équipe ayant accès au back-office.</p>
    </div>
    <a class="btn-primary" href="/admin/users/create">
        <?= icon('user-plus', 'h-5 w-5') ?> Nouvel utilisateur
    </a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Utilisateur</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <?php
                            $initials = strtoupper(mb_substr($user['name'], 0, 1));
                            if (preg_match('/^(\S)\S*\s+(\S)/u', $user['name'], $m)) {
                                $initials = strtoupper($m[1] . $m[2]);
                            }
                            ?>
                            <span class="avatar bg-gradient-to-br from-brand-orange to-amber-600">
                                <?= e($initials) ?>
                            </span>
                            <span class="font-semibold text-brand-navy"><?= e($user['name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($user['email']) ?></td>
                    <td>
                        <span class="badge <?= $roleBadges[$user['role']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                            <?= e(User::ROLE_LABELS[$user['role']]) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int) $user['is_active']): ?>
                            <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">
                                <?= icon('check', 'h-3.5 w-3.5') ?> Actif
                            </span>
                        <?php else: ?>
                            <span class="badge bg-red-50 text-red-600 ring-red-200">
                                <?= icon('x-mark', 'h-3.5 w-3.5') ?> Désactivé
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap text-slate-500"><?= format_datetime($user['last_login_at']) ?></td>
                    <td>
                        <div class="flex items-center justify-end gap-1.5">
                            <a class="btn-icon" href="/admin/users/<?= (int) $user['id'] ?>/edit" title="Modifier">
                                <?= icon('pencil', 'h-4 w-4') ?>
                            </a>
                            <?php if ((int) $user['is_active']): ?>
                                <form class="inline-flex" method="post"
                                      action="/admin/users/<?= (int) $user['id'] ?>/delete"
                                      data-confirm="Désactiver ce compte ?">
                                    <?= csrf_field() ?>
                                    <button class="btn-icon-danger" type="submit" title="Désactiver le compte">
                                        <?= icon('trash', 'h-4 w-4') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/users') ?>
    <div class="mt-5 flex items-start gap-2.5 rounded-xl bg-slate-50 p-4 text-xs text-slate-500 ring-1 ring-inset ring-slate-200">
        <?= icon('shield-check', 'h-4 w-4 shrink-0 text-slate-400') ?>
        <p>
            Rôles : <strong class="text-slate-700">Opérateur</strong> consulte et valide les inscriptions ·
            <strong class="text-slate-700">Admin</strong> gère aussi les formations, exports et réglages ·
            <strong class="text-slate-700">Super admin</strong> gère en plus les utilisateurs.
        </p>
    </div>
</div>
