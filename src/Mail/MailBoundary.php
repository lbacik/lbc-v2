<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Deployment-owned SES tenant boundary.
 *
 * Every value is resolved from deployment configuration at startup. Nothing
 * here may ever be influenced by HTTP input, template data, or headers: the
 * sender API accepts a ContactMessage only, so callers have no way to select
 * another tenant, configuration set, region, From identity, or recipient.
 */
final class MailBoundary
{
    public const CONFIGURATION_SET = 'lbc-first-contact-events';
    public const REGION = 'eu-central-1';
    public const FROM_ADDRESS = 'no-reply@lukaszbacik.com';
    public const DEFAULT_RECIPIENT = 'lukasz@lukaszbacik.com';

    public function __construct(
        public readonly string $tenantName,
        public readonly string $configurationSetName,
        public readonly string $region,
        public readonly string $fromAddress,
        public readonly string $operatorRecipient,
    ) {
    }

    /**
     * @throws InvalidMailBoundaryException when any boundary value is missing or wrong
     */
    public function assertValid(): void
    {
        if ('' === trim($this->tenantName) || 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/', $this->tenantName)) {
            throw new InvalidMailBoundaryException('SES tenant name is missing or invalid.');
        }

        if ($this->configurationSetName !== self::CONFIGURATION_SET) {
            throw new InvalidMailBoundaryException('SES configuration set is missing or invalid.');
        }

        if ($this->region !== self::REGION) {
            throw new InvalidMailBoundaryException('SES region is missing or invalid.');
        }

        if ($this->fromAddress !== self::FROM_ADDRESS) {
            throw new InvalidMailBoundaryException('SES From identity is missing or invalid.');
        }

        if ('' === trim($this->operatorRecipient) || false === filter_var($this->operatorRecipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidMailBoundaryException('Operator recipient is missing or invalid.');
        }
    }
}
