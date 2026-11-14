<?php

namespace Tests\Unit;

use App\Enums\RequestStatus;
use PHPUnit\Framework\TestCase;

class RequestStatusTest extends TestCase
{
    public function test_every_status_has_its_label(): void
    {
        $labels = array_combine(
            array_map(fn (RequestStatus $s) => $s->value, RequestStatus::cases()),
            array_map(fn (RequestStatus $s) => $s->label(), RequestStatus::cases()),
        );

        $this->assertSame(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'], $labels);
    }
}
