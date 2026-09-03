<?php

namespace App\Modules\LandingPage\Services;

final class TshirtLandingPageDomainVerificationService
{
    public function __construct(
        private readonly TshirtLandingPageSettingsService $settings,
    ) {}

    /**
     * @return array{status: string, message: string, checks: array<string, mixed>}
     */
    public function verify(): array
    {
        $domain = $this->settings->configuredDomain();

        if ($domain === null) {
            return [
                'status' => 'pending',
                'message' => 'Configure a T-shirt landing page domain first.',
                'checks' => [],
            ];
        }

        $checks = [
            'root_domain_resolves' => $this->domainHasDnsRecords($domain),
            'wildcard_documented' => true,
        ];

        $status = $checks['root_domain_resolves'] ? 'configured' : 'pending';

        $this->settings->saveVerificationStatus($status);

        return [
            'status' => $status,
            'message' => $status === 'configured'
                ? 'Root domain resolves. Ensure wildcard DNS (*.'.$domain.') points to your landing page host.'
                : 'Root domain DNS could not be verified yet. Confirm wildcard DNS (*.'.$domain.') is configured at your DNS provider.',
            'checks' => $checks,
        ];
    }

    private function domainHasDnsRecords(string $domain): bool
    {
        if (! function_exists('dns_get_record')) {
            return false;
        }

        $records = @dns_get_record($domain, DNS_A + DNS_AAAA + DNS_CNAME);

        return is_array($records) && $records !== [];
    }
}
