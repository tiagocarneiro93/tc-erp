<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * Despacho 8632/2014 §2.2.15: "A impressão de uma 2.ª via de um documento deve
 * preservar o seu conteúdo original, ainda que deva conter qualquer expressão
 * que indique não se tratar de um original." The Despacho prescribes no exact
 * wording for a copy — only that it be marked as not the original — so this is
 * the house convention: the first thing handed out is the "Original", every
 * later one "Duplicado", "Triplicado", then "n.ª via".
 *
 * (The Despacho's "Cópia do documento original" expression, §2.4/§2.5, is a
 * different case: documents re-created from a backup, not a reprint.)
 */
final class CopyLabel
{
    private function __construct()
    {
    }

    /**
     * @param int $previouslyHandedOut how many times this document was already printed, downloaded or e-mailed
     */
    public static function forNextCopy(int $previouslyHandedOut): string
    {
        return match (true) {
            $previouslyHandedOut <= 0 => 'Original',
            1 === $previouslyHandedOut => 'Duplicado',
            2 === $previouslyHandedOut => 'Triplicado',
            default => \sprintf('%d.ª via', $previouslyHandedOut + 1),
        };
    }
}
