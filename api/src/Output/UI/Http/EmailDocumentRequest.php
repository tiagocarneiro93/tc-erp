<?php

declare(strict_types=1);

namespace App\Output\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class EmailDocumentRequest
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        #[Assert\Count(max: 10)]
        #[Assert\All([new Assert\NotBlank(), new Assert\Email()])]
        public readonly array $recipients = [],
        #[Assert\Length(max: 2000)]
        public readonly ?string $message = null,
    ) {
    }
}
