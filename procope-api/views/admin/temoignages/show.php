<?php
/** Variables : $testimonial */
use App\Models\Testimonial;

$badgeClasses = [
    'en_attente' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'publie'     => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refuse'     => 'bg-red-50 text-red-700 ring-red-200',
];
$sourceClasses = [
    'public' => 'bg-sky-50 text-sky-700 ring-sky-200',
    'admin'  => 'bg-slate-100 text-slate-600 ring-slate-300',
];
$initials = strtoupper(mb_substr($testimonial['author_name'], 0, 1));
if (preg_match('/^(\S)\S*\s+(\S)/u', $testimonial['author_name'], $m)) {
    $initials = strtoupper($m[1] . $m[2]);
}
$photoUrl = Testimonial::photoUrl($testimonial['photo_path'] ?? null);
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <?php if ($photoUrl): ?>
            <img src="<?= e($photoUrl) ?>" alt=""
                 class="h-12 w-12 rounded-full object-cover ring-1 ring-slate-200">
        <?php else: ?>
            <span class="avatar h-12 w-12 bg-gradient-to-br from-brand-navy2 to-brand-blue text-base">
                <?= e($initials) ?>
            </span>
        <?php endif; ?>
        <div>
            <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
                <?= e($testimonial['author_name']) ?>
                <span class="badge <?= $badgeClasses[$testimonial['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                    <?= e(Testimonial::STATUT_LABELS[$testimonial['statut']] ?? $testimonial['statut']) ?>
                </span>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                <?= e($testimonial['role_title'] ?: '—') ?>
                — reçu le <?= format_datetime($testimonial['created_at']) ?>
            </p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="btn-ghost" href="/admin/temoignages">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour à la liste
        </a>
        <a class="btn-secondary" href="/admin/temoignages/<?= (int) $testimonial['id'] ?>/edit">
            <?= icon('pencil', 'h-4 w-4') ?> Modifier
        </a>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('chat-bubble', 'h-5 w-5') ?></span>
            Témoignage
        </h2>
        <?php if ($photoUrl): ?>
            <img src="<?= e($photoUrl) ?>" alt="Photo de <?= e($testimonial['author_name']) ?>"
                 class="mb-5 max-h-64 rounded-xl object-cover ring-1 ring-slate-200">
        <?php endif; ?>
        <div class="whitespace-pre-line rounded-xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-700 ring-1 ring-inset ring-slate-200">
            <?= e($testimonial['quote']) ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('information-circle', 'h-5 w-5') ?></span>
                Détails
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Source</dt>
                    <dd class="mt-1">
                        <span class="badge <?= $sourceClasses[$testimonial['source']] ?? '' ?>">
                            <?= e(Testimonial::SOURCE_LABELS[$testimonial['source']] ?? $testimonial['source']) ?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">E-mail</dt>
                    <dd class="mt-1 text-slate-700"><?= e($testimonial['author_email'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Publié le</dt>
                    <dd class="mt-1 text-slate-700"><?= format_datetime($testimonial['published_at'] ?? null) ?></dd>
                </div>
            </dl>
        </div>

        <div class="card space-y-3">
            <h2 class="card-title">Actions</h2>
            <?php if ($testimonial['statut'] !== 'publie'): ?>
                <form method="post" action="/admin/temoignages/<?= (int) $testimonial['id'] ?>/publish"
                      data-loading-submit data-loading-label="Publication…">
                    <?= csrf_field() ?>
                    <button class="btn-primary w-full" type="submit">
                        <?= icon('check', 'h-4 w-4') ?> Valider et publier
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($testimonial['statut'] !== 'refuse'): ?>
                <form method="post" action="/admin/temoignages/<?= (int) $testimonial['id'] ?>/refuse"
                      data-loading-submit data-loading-label="Refus…">
                    <?= csrf_field() ?>
                    <button class="btn-secondary w-full" type="submit">
                        <?= icon('x-mark', 'h-4 w-4') ?> Refuser
                    </button>
                </form>
            <?php endif; ?>
            <form method="post" action="/admin/temoignages/<?= (int) $testimonial['id'] ?>/delete"
                  data-loading-submit data-loading-label="Suppression…"
                  data-confirm="Supprimer définitivement le témoignage de <?= e($testimonial['author_name']) ?> ? Cette action est irréversible.">
                <?= csrf_field() ?>
                <button class="btn-danger w-full" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
