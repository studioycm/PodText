<?php

namespace App\Console\Commands;

use App\Auth\LegacyRoleBackfill\BackfillException;
use App\Auth\LegacyRoleBackfill\BackfillRefusalException;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillRollback;
use App\Auth\LegacyRoleBackfill\PrivateArtifactRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('authz:roles:rollback
    {receipt : Private receipt basename}
    {--accept-after= : Exact successful after-state fingerprint}
    {--confirm= : Must equal ROLLBACK-AUTHZ1-C}')]
#[Description('Roll back only receipt-inserted AUTHZ1-C assignments before cutover')]
class AuthzRollbackLegacyRolesCommand extends Command
{
    public function handle(PrivateArtifactRepository $artifacts, LegacyRoleBackfillRollback $rollback): int
    {
        try {
            $receiptName = (string) $this->argument('receipt');
            $receipt = $artifacts->loadBackfillReceipt($receiptName);
            $result = $rollback->rollback(
                receipt: $receipt,
                acceptedAfter: (string) $this->option('accept-after'),
                confirmation: (string) $this->option('confirm'),
            );

            $this->components->info('AUTHZ1-C rollback status: '.$result->status);
            $this->line('before_fingerprint: '.$result->beforeFingerprint);
            $this->line('after_fingerprint: '.$result->afterFingerprint);
            $this->line('deleted_assignments: '.$result->deletedAssignments);

            if ($result->receiptName !== null) {
                $this->line('rollback_receipt: '.$result->receiptName);
            }

            return self::SUCCESS;
        } catch (BackfillRefusalException $exception) {
            $this->components->error($exception->getMessage());

            return 2;
        } catch (BackfillException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('AUTHZ1-C rollback failed without exposing operation details.');

            return self::FAILURE;
        }
    }
}
