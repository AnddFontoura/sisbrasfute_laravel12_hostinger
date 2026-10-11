<?php

namespace App\Console\Commands;

use App\Repository\CourtInvitationRepository;
use Illuminate\Console\Command;

class ExpireCourtInvitations extends Command
{
    protected $signature = 'court:expire-invitations';

    protected $description = 'Expira convites de quadra pendentes cujo prazo de 48h já passou.';

    public function handle(CourtInvitationRepository $courtInvitationRepository): int
    {
        $count = $courtInvitationRepository->markExpiredBulk();

        $this->info("Convites de quadra expirados: {$count}.");

        return self::SUCCESS;
    }
}
