<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * No seal could be applied. A plain exception → generic 500 and Sentry: a
 * document that has to be sealed is never sent unsealed instead.
 */
final class SealingFailed extends \RuntimeException
{
}
