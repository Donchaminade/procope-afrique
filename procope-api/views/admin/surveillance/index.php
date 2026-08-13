<?php
/** Variables : $result (rows,total,page,pages), $filters, $users, $actions */
use App\Models\AuditLog;

/** Couleur du badge selon la famille d'action (préfixe avant le point). */
$actionBadge = static function (string $action): string {
    $family = str_contains($action, '.') ? explode('.', $action)[0] : $action;
    return match ($family) {
        'login', 'logout'            => 'bg-sky-50 text-sky-700 ring-sky-200',
        'formation', 'formations'    => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'inscription', 'inscriptions' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'user', 'users'              => 'bg-amber-50 text-amber-700 ring-amber-200',
        'contact_message'            => 'bg-violet-50 text-violet-700 ring-violet-200',
        default                      => 'bg-slate-100 text-slate-600 ring-slate-300',
    };
};

/** Lien vers la fiche de l'entité quand elle existe. */
$entityUrl = static function (string $entity, ?int $id): ?string {
    if (!$id) {
        return null;
    }
    return match ($entity) {
        'inscription'     => '/admin/inscriptions/' . $id,
        'formation'       => '/admin/formations/' . $id . '/edit',
        'user'            => '/admin/users/' . $id . '/edit',
        'contact_message' => '/admin/messages/' . $id,
        default           => null,
    };
};

$entityLabels = [
    'inscription'     => 'Inscription',
    'formation'       => 'Formation',
    'user'            => 'Utilisateur',
    'contact_message' => 'Message',
    'settings'        => 'Réglages',
];
?>
<div class="mb-8">
    <h1 class="text-2xl font-bold text-brand-navy">
        Surveillance
        <span class="ml-1 rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
            <?= (int) $result['total'] ?>
        </span>
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        Journal des actions effectuées dans le back-office. Les journaux sont conservés 30 jours.
    </p>
</div>

<!-- Filtres -->
<form method="get" action="/admin/surveillance" class="card mb-6 !p-5">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="label" for="s-user">Utilisateur</label>
            <select class="input" id="s-user" name="user_id">
                <option value="">Tous</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>"
                        <?= (int) ($filters['user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="s-action">Action</label>
            <select class="input" id="s-action" name="action">
                <option value="">Toutes</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                        <?= e(AuditLog::ACTION_LABELS[$action] ?? $action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="s-from">Du</label>
            <input class="input" id="s-from" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
        </div>
        <div>
            <label class="label" for="s-to">Au</label>
            <input class="input" id="s-to" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="submit">
                <?= icon('funnel', 'h-4 w-4') ?> Filtrer
            </button>
            <a class="btn-ghost" href="/admin/surveillance" title="Réinitialiser les filtres">
                <?= icon('arrow-path', 'h-4 w-4') ?>
            </a>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($result['rows']): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Date</th><th>Utilisateur</th><th>Action</th><th>Entité</th><th>IP</th><th>Détails</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td>
                            <?php if ($row['user_name']): ?>
                                <div class="font-semibold text-brand-navy"><?= e($row['user_name']) ?></div>
                                <div class="text-xs text-slate-400"><?= e($row['user_email']) ?></div>
                            <?php else: ?>
                                <span class="text-slate-400">Système</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $actionBadge($row['action']) ?>">
                                <?= e(AuditLog::ACTION_LABELS[$row['action']] ?? $row['action']) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php
                            $label = $entityLabels[$row['entity']] ?? ($row['entity'] !== '' ? $row['entity'] : null);
                            $url = $entityUrl($row['entity'], $row['entity_id'] !== null ? (int) $row['entity_id'] : null);
                            ?>
                            <?php if ($label): ?>
                                <?php if ($url): ?>
                                    <a class="font-medium text-brand-blue transition hover:text-brand-navy"
                                       href="<?= e($url) ?>">
                                        <?= e($label) ?> #<?= (int) $row['entity_id'] ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-600">
                                        <?= e($label) ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= e($row['ip'] ?? '—') ?></td>
                        <td>
                            <?php $meta = $row['meta'] ? json_decode($row['meta'], true) : null; ?>
                            <?php if (is_array($meta) && $meta): ?>
                                <details class="group">
                                    <summary class="cursor-pointer select-none text-sm font-medium text-brand-blue transition hover:text-brand-navy">
                                        Voir (<?= count($meta) ?>)
                                    </summary>
                                    <dl class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs ring-1 ring-inset ring-slate-200">
                                        <?php foreach ($meta as $key => $value): ?>
                                            <div class="flex gap-2">
                                                <dt class="shrink-0 font-semibold text-slate-500"><?= e((string) $key) ?> :</dt>
                                                <dd class="break-all text-slate-700">
                                                    <?= e(is_scalar($value) || $value === null
                                                        ? (string) ($value === true ? 'oui' : ($value === false ? 'non' : $value ?? '—'))
                                                        : json_encode($value, JSON_UNESCAPED_UNICODE)) ?>
                                                </dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                </details>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/surveillance', $filters) ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('shield-check', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucune entrée de journal ne correspond à ces critères.</p>
            <a class="btn-ghost btn-sm" href="/admin/surveillance">
                <?= icon('arrow-path', 'h-4 w-4') ?> Réinitialiser les filtres
            </a>
        </div>
    <?php endif; ?>
</div>
