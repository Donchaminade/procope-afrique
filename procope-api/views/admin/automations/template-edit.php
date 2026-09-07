<?php
/** Variables : $name, $meta (label/description), $subject, $body, $variables (array), $isCustom (bool) */

// Templates dont le rendu ajoute automatiquement l'affiche (au-dessus) et le
// bouton CTA (en dessous) autour du corps éditable — voir Mailer::renderOverride.
$ctaLabels = [
    'annonce'         => "Je m'inscris",
    'offre'           => "Voir l'offre et postuler",
    'offre_rappel'    => 'Postuler maintenant',
    'offre_prolongee' => "Voir l'offre et postuler",
];
$autoCta = $ctaLabels[$name] ?? null;

// Corps affiché dans l'éditeur : texte brut -> échappé + <br> ; HTML -> tel quel
// (les overrides HTML sont sanitisés à l'enregistrement, les défauts viennent du code).
$bodyIsPlain = $body === strip_tags($body);
$editorHtml = $bodyIsPlain ? nl2br(e($body)) : $body;
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
            Modifier le modèle
            <span class="badge bg-slate-100 text-slate-500 ring-slate-200 font-mono text-sm"><?= e($name) ?></span>
            <?php if ($isCustom): ?>
                <span class="badge bg-brand-orange/10 text-brand-orange ring-brand-orange/30">Personnalisé</span>
            <?php endif; ?>
        </h1>
        <p class="mt-1 text-sm text-slate-500"><?= e($meta['label']) ?> — <?= e($meta['description']) ?></p>
    </div>
    <a class="btn-ghost" href="/admin/automations#templates">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="/admin/automations/templates/<?= e($name) ?>" id="tpl-form" class="max-w-3xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <div class="card">
        <label class="label" for="tpl-subject">Sujet de l'e-mail</label>
        <input class="input" id="tpl-subject" type="text" name="subject" maxlength="255"
               value="<?= e($subject) ?>" placeholder="Sujet avec {{variables}}">
        <p class="mt-1.5 text-xs text-slate-400">
            Le sujet accepte les mêmes <span class="font-mono">{{variables}}</span> que le contenu.
        </p>
    </div>

    <div class="card">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <span class="label mb-0">Contenu de l'e-mail</span>
            <span class="text-xs text-slate-400">Modifiez directement le texte dans l'aperçu ci-dessous.</span>
        </div>

        <!-- Barre d'outils de mise en forme (JS maison, admin.js) -->
        <div id="tpl-toolbar" class="mb-3 flex flex-wrap items-center gap-1 rounded-xl bg-slate-50 p-1.5 ring-1 ring-inset ring-slate-200"
             role="toolbar" aria-label="Mise en forme du texte">
            <button type="button" class="tpl-tool font-bold" data-editor-cmd="bold" title="Gras">G</button>
            <button type="button" class="tpl-tool italic" data-editor-cmd="italic" title="Italique">I</button>
            <button type="button" class="tpl-tool underline" data-editor-cmd="underline" title="Souligné">S</button>
            <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
            <button type="button" class="tpl-tool" data-editor-cmd="insertUnorderedList" title="Liste à puces">&bull; Liste</button>
            <button type="button" class="tpl-tool" data-editor-cmd="insertOrderedList" title="Liste numérotée">1. Liste</button>
            <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true"></span>
            <button type="button" class="tpl-tool" data-editor-cmd="createLink" title="Insérer un lien">Lien</button>
            <button type="button" class="tpl-tool" data-editor-cmd="removeFormat" title="Effacer la mise en forme">Effacer le style</button>
        </div>

        <!-- L'e-mail dans son cadre réel : en-tête et pied verrouillés, corps éditable -->
        <div class="rounded-xl bg-slate-200/70 p-3 sm:p-5">
            <div class="mx-auto max-w-[600px] overflow-hidden rounded-xl bg-white shadow-lg">
                <!-- En-tête (non modifiable) -->
                <div class="relative select-none bg-brand-navy px-8 py-5 text-center" aria-hidden="true">
                    <span class="absolute right-2 top-2 rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white/70">
                        Non modifiable
                    </span>
                    <img src="/assets/logo.png" alt="Logo PROCOPE Afrique" class="mx-auto h-12 w-auto">
                    <div class="mt-2 text-lg font-bold tracking-widest">
                        <span class="text-white">PROCOPE</span> <span class="text-brand-orange">AFRIQUE</span>
                    </div>
                    <div class="mt-0.5 text-[11px] tracking-wide text-slate-300/80">
                        Incubateur social — Entreprendre autrement en Afrique
                    </div>
                </div>
                <div class="h-1.5 bg-brand-orange" aria-hidden="true"></div>

                <div class="px-8 py-7">
                    <?php if ($autoCta !== null): ?>
                        <div class="mb-4 flex select-none items-center justify-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 py-8 text-xs font-medium text-slate-400"
                             aria-hidden="true">
                            Affiche (ajoutée automatiquement si disponible)
                        </div>
                    <?php endif; ?>

                    <!-- Corps éditable -->
                    <div id="tpl-editor" class="tpl-editor" contenteditable="true" spellcheck="true"
                         aria-label="Corps de l'e-mail (modifiable)"><?= $editorHtml ?></div>

                    <?php if ($autoCta !== null): ?>
                        <div class="mt-6 select-none text-center" aria-hidden="true">
                            <span class="inline-block cursor-default rounded-xl bg-brand-orange px-10 py-3.5 text-base font-bold text-brand-navy opacity-80">
                                <?= e($autoCta) ?>
                            </span>
                            <p class="mt-1.5 text-[11px] text-slate-400">Bouton ajouté automatiquement (non modifiable)</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pied de page (non modifiable) -->
                <div class="relative select-none border-t border-slate-100 bg-slate-50 px-8 py-5" aria-hidden="true">
                    <span class="absolute right-2 top-2 rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                        Non modifiable
                    </span>
                    <p class="text-[13px] font-bold text-brand-navy">PROCOPE Afrique</p>
                    <p class="mt-0.5 text-xs text-slate-500">Lomé, Togo · procopeafrique@gmail.com · +228 96 45 76 95</p>
                    <p class="mt-0.5 text-xs font-bold text-brand-orange">procopeafrique.vercel.app</p>
                    <p class="mt-1.5 text-[11px] text-slate-400">Cet e-mail a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                </div>
            </div>
        </div>

        <!-- Variables cliquables : insérées à la position du curseur -->
        <?php if ($variables): ?>
            <div class="mt-4">
                <p class="mb-2 text-xs font-medium text-slate-500">
                    Variables disponibles — cliquez pour insérer à l'endroit du curseur.
                    Chaque variable est remplacée par la valeur réelle au moment de l'envoi.
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($variables as $variable): ?>
                        <button type="button" data-editor-var="{{<?= e($variable) ?>}}"
                                class="rounded-full bg-brand-navy/5 px-3 py-1 font-mono text-xs text-brand-navy ring-1 ring-inset ring-brand-navy/15 transition hover:bg-brand-orange/15 hover:ring-brand-orange/40">
                            {{<?= e($variable) ?>}}
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mode texte (avancé) : le textarea brut, masqué par défaut -->
        <div id="tpl-source-wrap" class="mt-4 hidden">
            <label class="label" for="tpl-body">Code du contenu (mode avancé)</label>
            <textarea class="input font-mono text-xs leading-relaxed" id="tpl-body" name="body" rows="12"
                      spellcheck="false"><?= e($body) ?></textarea>
            <p class="mt-1.5 text-xs text-slate-400">
                Balises autorisées : <span class="font-mono">p, br, strong, em, u, ul, ol, li, a (http/https), h2, h3</span>.
                Tout le reste est retiré automatiquement à l'enregistrement.
            </p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> Enregistrer
        </button>
        <a class="btn-ghost" href="/admin/automations#templates">Annuler</a>
        <button type="button" id="tpl-source-toggle" class="ml-auto text-xs text-slate-400 underline decoration-dotted underline-offset-2 hover:text-slate-600">
            Mode texte (avancé)
        </button>
    </div>
</form>
