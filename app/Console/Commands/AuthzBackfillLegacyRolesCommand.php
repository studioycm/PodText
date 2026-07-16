<?php

namespace App\Console\Commands;

use App\Auth\LegacyRoleBackfill\BackfillException;
use App\Auth\LegacyRoleBackfill\BackfillRefusalException;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillApplier;
use App\Auth\LegacyRoleBackfill\PrivateArtifactRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('authz:roles:backfill
    {report : Private report basename}
    {--accept-source= : Exact source fingerprint}
    {--accept-report= : Exact report fingerprint}
    {--confirm= : Must equal AUTHZ1-C}')]
#[Description('Apply an accepted AUTHZ1-C legacy-role report transactionally')]
class AuthzBackfillLegacyRolesCommand extends Command
{
    public function handle(PrivateArtifactRepository $artifacts, LegacyRoleBackfillApplier $applier): int
    {
        try {
            $report = $artifacts->loadReport((string) $this->argument('report'));
            $result = $applier->apply(
                report: $report,
                acceptedSource: (string) $this->option('accept-source'),
                acceptedReport: (string) $this->option('accept-report'),
                confirmation: (string) $this->option('confirm'),
            );

            $this->components->info('AUTHZ1-C backfill status: '.$result->status);
            $this->line('source_fingerprint: '.$result->sourceFingerprint);
            $this->line('after_fingerprint: '.$result->afterFingerprint);
            $this->line('inserted_roles: '.$result->insertedRoles);
            $this->line('inserted_assignments: '.$result->insertedAssignments);
            $this->line('ownership_status: '.$result->ownershipStatus);
            $this->line('rollback_capable: '.($result->rollbackCapable ? 'yes' : 'no'));
            $this->line('cache_outcome: '.($result->cacheOutcome ?? 'not_applicable'));

            if ($result->receiptName !== null) {
                $this->line('receipt: '.$result->receiptName);
            }

            return self::SUCCESS;
        } catch (BackfillRefusalException $exception) {
            $this->components->error($exception->getMessage());

            return 2;
        } catch (BackfillException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('AUTHZ1-C backfill failed without exposing operation details.');

            return self::FAILURE;
        }
    }
}
