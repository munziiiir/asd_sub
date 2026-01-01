<?php

namespace App\Console\Commands;

use App\Support\RoomStatusService;
use Illuminate\Console\Command;

class ResetTemporaryRoomStatuses extends Command
{
    protected $signature = 'rooms:reset-temporary-statuses';

    protected $description = 'Auto-revert temporary room statuses (e.g., Cleaning) back to their previous state.';

    public function handle(RoomStatusService $service): int
    {
        $count = $service->resetTemporaryStatuses();

        $this->info("Reverted {$count} room(s) from temporary statuses.");

        return Command::SUCCESS;
    }
}
