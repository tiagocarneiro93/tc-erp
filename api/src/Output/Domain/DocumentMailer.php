<?php

declare(strict_types=1);

namespace App\Output\Domain;

interface DocumentMailer
{
    /**
     * @throws InvalidEmailRecipient when an address can never be delivered to (retrying is pointless)
     * @throws DocumentMailFailed    when the mail system could not take the message (retrying may help)
     */
    public function send(OutgoingDocumentEmail $email): void;
}
