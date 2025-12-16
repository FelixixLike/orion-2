<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Import\Events;

use App\Domain\Import\Models\Import;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Import $import) {}

    public function broadcastOn(): array
    {
        return [new Channel("imports")];
    }

    public function broadcastWith(): array
    {
        return [
            "id" => $this->import->id,
            "status" => $this->import->status,
            "processed_rows" => $this->import->processed_rows,
            "total_rows" => $this->import->total_rows,
        ];
    }
}
