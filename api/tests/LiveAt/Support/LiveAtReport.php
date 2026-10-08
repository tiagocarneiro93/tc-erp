<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

/**
 * What a run sent and what AT answered, kept in `var/at-live/<run>/`:
 * `report.md` (one line per exchange) and, for e-Fatura calls, the request and
 * response XML. The password material in the SOAP header is blanked; nothing
 * else is, so treat the folder as sensitive anyway (it is git-ignored under
 * `var/`).
 */
final class LiveAtReport
{
    private int $sequence = 0;
    private readonly string $directory;
    /** @var list<string> */
    private array $rows = [];

    public function __construct(string $baseDirectory, public readonly string $runId)
    {
        $this->directory = $baseDirectory.'/'.gmdate('Ymd-His').'-'.$runId;

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Could not create '.$this->directory);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * @param string $detail what was sent, in a few words (not the whole body)
     */
    public function record(string $step, string $operation, string $detail, string $verdict, ?int $code, string $message): void
    {
        $this->rows[] = \sprintf('| %02d | %s | %s | %s | %s | %s | %s |', ++$this->sequence, $step, $operation, $detail, $verdict, null === $code ? '–' : (string) $code, str_replace(['|', "\n"], ['/', ' '], $message));

        $header = "# Live AT run {$this->runId}\n\n| # | Step | Operation | Sent | Verdict | AT code | AT message |\n|---|---|---|---|---|---|---|\n";
        file_put_contents($this->directory.'/report.md', $header.implode("\n", $this->rows)."\n");

        fwrite(\STDERR, \sprintf("  [AT] %-28s %-18s %-9s code=%s %s\n", $step, $operation, $verdict, null === $code ? '–' : (string) $code, $message));
    }

    public function saveExchange(string $step, string $operation, string $request, string $response): void
    {
        $name = \sprintf('%s/%02d-%s-%s', $this->directory, $this->sequence + 1, preg_replace('/[^A-Za-z0-9_-]+/', '_', $step) ?? 'step', $operation);
        file_put_contents($name.'.request.xml', self::redact($request));
        file_put_contents($name.'.response.xml', $response);
    }

    public static function redact(string $envelope): string
    {
        return preg_replace('#<(wss:(?:Password|Nonce|Created))>[^<]*</\1>#', '<$1>***</$1>', $envelope) ?? $envelope;
    }
}
