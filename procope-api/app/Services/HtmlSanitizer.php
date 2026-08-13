<?php

namespace App\Services;

/**
 * Sanitisation STRICTE en liste blanche du HTML saisi dans l'éditeur visuel
 * des modèles d'e-mail.
 *
 * Liste blanche exacte :
 *   - balises : p, br, strong, b, em, i, u, ul, ol, li, a, h2, h3
 *   - attributs : href sur <a> uniquement, et seulement en http:// ou https://
 *     (mailto/javascript/data/... sont supprimés)
 *
 * Tout le reste est neutralisé :
 *   - script, style, iframe, object, embed, form, img... : supprimés AVEC leur
 *     contenu pour script/style/iframe/object/embed/noscript (le texte d'un
 *     <script> n'a rien à faire dans un e-mail) ;
 *   - les autres balises inconnues (div, span, table, font...) sont
 *     « déballées » : la balise disparaît mais son texte/enfants sont gardés,
 *     ce qui préserve le contenu collé depuis Word ou un autre e-mail ;
 *   - tous les attributs style / class / id / on* sont supprimés ;
 *   - les commentaires HTML sont supprimés.
 *
 * Les {{variables}} sont du texte : elles traversent le nettoyage intactes.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3',
    ];

    /** Balises supprimées avec tout leur contenu. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'svg', 'head', 'title',
    ];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Wrapper + déclaration d'encodage : évite le mojibake et isole le fragment
        $wrapped = '<?xml encoding="utf-8"?><div id="__sanitize_root__">' . $html . '</div>';
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_NOENT | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__sanitize_root__');
        if (!$root) {
            return '';
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    private static function sanitizeChildren(\DOMNode $node): void
    {
        // Copie statique : la liste live change pendant les remove/unwrap
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMCdataSection
                || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue; // texte : conservé tel quel
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Déballage : garder les enfants (nettoyés), retirer la balise
                self::sanitizeChildren($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::stripAttributes($child, $tag);
            self::sanitizeChildren($child);
        }
    }

    /** Ne conserve que href http(s) sur <a> ; supprime tout le reste (style, on*, ...). */
    private static function stripAttributes(\DOMElement $el, string $tag): void
    {
        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $toRemove[] = $attr->name;
        }
        $href = null;
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $candidate = trim($el->getAttribute('href'));
            if (preg_match('#^https?://#i', $candidate)) {
                $href = $candidate;
            }
        }
        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
        if ($href !== null) {
            $el->setAttribute('href', $href);
        }
    }
}
