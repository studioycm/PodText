<?php

namespace App\Enums;

/**
 * What one repair pass did to a media mutation operation — the vocabulary
 * MediaFilesystemMutationCoordinator::repair() answers in and the repair
 * command's summary/exit code reads. Distinct from MediaMutationStatus (an
 * operation's stored lifecycle state), even though a pass that leaves an
 * operation awaiting cleanup shares the `cleanup_pending` spelling with it.
 */
enum MediaMutationRepairResult: string
{
    case AlreadyComplete = 'already_complete';
    case CleanupPending = 'cleanup_pending';
    case CompletedCleanup = 'completed_cleanup';
    case LeaseActive = 'lease_active';
    case LeaseLost = 'lease_lost';
    case ManualReviewRequired = 'manual_review_required';
    case RecoveredCommitted = 'recovered_committed';
    case RolledBackUncommitted = 'rolled_back_uncommitted';
}
