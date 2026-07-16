<?php

namespace App\Console\Commands;

use App\Auth\LegacyRoleBackfill\ArtifactVersionException;
use App\Auth\LegacyRoleBackfill\BackfillException;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillAnalyzer;
use App\Auth\LegacyRoleBackfill\PrivateArtifactRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('authz:roles:analyze {--report= : Optional private report basename}')]
#[Description('Analyze raw legacy roles and publish a privacy-safe immutable report')]
class AuthzAnalyzeLegacyRolesCommand extends Command
{
    public function handle(LegacyRoleBackfillAnalyzer $analyzer, PrivateArtifactRepository $artifacts): int
    {
        try {
            $report = $analyzer->analyze();
            $requestedName = $this->option('report');
            $name = $artifacts->publishReport(
                $report,
                is_string($requestedName) && $requestedName !== '' ? $requestedName : null,
            );
            $payload = $report->toArray();

            $this->components->info('AUTHZ1-C analysis status: '.$report->status());
            $this->line('source_total: '.(string) $payload['source']['total']);

            foreach ($payload['source']['per_role'] as $role => $count) {
                $this->line("source_{$role}: {$count}");
            }

            foreach ($payload['issue_totals'] as $code => $count) {
                $this->line("issue_{$code}: {$count}");
            }

            $this->line('source_fingerprint: '.$report->sourceFingerprint());
            $this->line('report_fingerprint: '.$report->reportFingerprint());
            $this->line('report: '.$name);

            return $report->isBlocked() ? 2 : self::SUCCESS;
        } catch (ArtifactVersionException $exception) {
            $this->components->error($exception->getMessage());

            return 2;
        } catch (BackfillException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('AUTHZ1-C analysis failed without publishing report details.');

            return self::FAILURE;
        }
    }
}
