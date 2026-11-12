<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoBooking;
use Tests\TestCase;

class BookingAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoBooking;

    public function test_request_without_token_returns_403(): void
    {
        $this->seedDemo();

        $this->getJson('/api/bookings')->assertStatus(403);
    }

    public function test_request_without_token_and_accept_header_returns_403(): void
    {
        $this->seedDemo();

        $this->get('/api/bookings')->assertStatus(403);
    }
}
