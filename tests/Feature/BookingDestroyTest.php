<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoBooking;
use Tests\TestCase;

class BookingDestroyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoBooking;

    public function test_anna_deletes_her_booking_and_no_longer_sees_it(): void
    {
        $this->seedDemo();
        $booking = Booking::factory()->for($this->anna())->create();

        $this->withToken('anna-token')
            ->deleteJson('/api/bookings/'.$booking->id)
            ->assertNoContent();

        $ids = collect($this->withToken('anna-token')->getJson('/api/bookings')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
    }

    public function test_boris_deletes_his_booking_and_no_longer_sees_it(): void
    {
        $this->seedDemo();
        $booking = Booking::factory()->for($this->boris())->create();

        $this->withToken('boris-token')
            ->deleteJson('/api/bookings/'.$booking->id)
            ->assertNoContent();

        $ids = collect($this->withToken('boris-token')->getJson('/api/bookings')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
    }

    public function test_anna_receives_403_when_deleting_boris_booking(): void
    {
        $this->seedDemo();
        $booking = Booking::factory()->for($this->boris())->create();

        $this->withToken('anna-token')
            ->deleteJson('/api/bookings/'.$booking->id)
            ->assertStatus(403);

        $this->assertModelExists($booking);
    }

    public function test_missing_booking_returns_404(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')
            ->deleteJson('/api/bookings/999999')
            ->assertNotFound();
    }
}
