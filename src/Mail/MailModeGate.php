<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Deployment-owned mail mode gate.
 *
 * - disabled (default): no send is ever attempted.
 * - validation: operator-restricted (per-request token must match the deployed
 *   secret), time-bounded (MAIL_VALIDATION_UNTIL), and auditable (every send
 *   must be logged by the caller when requiresAudit() is true).
 * - enabled: normal operation.
 *
 * The gate carries no tenant/configuration-set/region/From values, so no mode
 * can ever select another mail boundary.
 */
final class MailModeGate
{
    private readonly MailMode $mode;
    private readonly ?\DateTimeImmutable $validationUntil;

    public function __construct(
        string $mode,
        string $validationUntil = '',
        private readonly string $validationToken = '',
    ) {
        try {
            $this->mode = MailMode::from($mode);
        } catch (\ValueError) {
            throw new \InvalidArgumentException(\sprintf('Unknown mail mode "%s".', $mode));
        }

        $until = null;
        if ('' !== $validationUntil) {
            try {
                $until = new \DateTimeImmutable($validationUntil);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Mail validation window is not a valid date.');
            }
        }
        $this->validationUntil = $until;

        if (MailMode::Validation === $this->mode && '' === $this->validationToken) {
            throw new \InvalidArgumentException('Validation mail mode requires a validation token.');
        }

        if (MailMode::Validation === $this->mode && null === $this->validationUntil) {
            throw new \InvalidArgumentException('Validation mail mode requires a time-bounded window.');
        }
    }

    public function mode(): MailMode
    {
        return $this->mode;
    }

    public function requiresAudit(): bool
    {
        return MailMode::Validation === $this->mode;
    }

    /**
     * @throws MailNotAllowedException
     */
    public function assertSendAllowed(?string $presentedToken, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();

        if (MailMode::Enabled === $this->mode) {
            return;
        }

        if (MailMode::Disabled === $this->mode) {
            throw new MailNotAllowedException('Mail sending is disabled.', 'disabled');
        }

        if (null === $this->validationUntil || $now > $this->validationUntil) {
            throw new MailNotAllowedException('Mail validation window has expired.', 'expired');
        }

        if (null === $presentedToken || !hash_equals($this->validationToken, $presentedToken)) {
            throw new MailNotAllowedException('Mail validation is restricted to operators.', 'forbidden');
        }
    }
}
