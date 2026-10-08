<?php

namespace App\Mail\Concerns;

/**
 * Mantém a formatação básica do HTML gerado pelos editores do site
 * (parágrafos, quebras de linha, listas, ênfase e links) nos e-mails,
 * removendo tags perigosas e atributos que possam carregar scripts.
 *
 * Use este trait em qualquer Mailable que precise exibir conteúdo vindo
 * de campos de descrição ricos, para que a mensagem não fique "grudada".
 */
trait SanitizesRichText
{
    protected function sanitizeRichTextHtml(?string $html): string
    {
        $html = $html ?? '';

        if ($html === '') {
            return '';
        }

        // Allowlist de tags de formatação que os editores (ex.: Quill) produzem.
        $allowedTags = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><span>';
        $clean = strip_tags($html, $allowedTags);

        // Mantém apenas o href em links e força abertura segura em nova aba,
        // evitando injeção via on*, style, etc.
        $clean = preg_replace_callback(
            '/<a\b[^>]*>/i',
            function ($matches) {
                if (preg_match('/\bhref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s">]+)/i', $matches[0], $href)) {
                    return '<a ' . $href[0] . ' target="_blank" rel="noopener noreferrer">';
                }
                return '<a>';
            },
            $clean
        );

        // Remove atributos de todas as demais tags permitidas.
        $clean = preg_replace('/<(?!a\b)([a-z0-9]+)\b[^>]*>/i', '<$1>', $clean);

        return trim($clean);
    }
}
