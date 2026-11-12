<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoBooking;
use Tests\TestCase;

class BookingTodayTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoBooking;

    public function test_today_lists_arbat_bookings_with_time_and_author(): void
    {
        $this->seedDemo();
        $this->travelTo('2026-11-12 12:00:00');
        $arbat = $this->room('Арбат');

        Booking::factory()->for($this->anna())->create([
            'room_id' => $arbat->id,
            'starts_at' => '2026-11-12 10:00:00',
            'ends_at' => '2026-11-12 11:00:00',
        ]);
        Booking::factory()->for($this->boris())->create([
            'room_id' => $arbat->id,
            'starts_at' => '2026-11-12 12:00:00',
            'ends_at' => '2026-11-12 13:00:00',
        ]);

        $response = $this->withToken('anna-token')
            ->getJson('/api/rooms/'.$arbat->id.'/bookings/today')
            ->assertOk();

        $response->assertJsonCount(2);
        $response->assertJsonFragment(['author' => 'Анна']);
        $response->assertJsonFragment(['author' => 'Борис']);
        $this->assertArrayHasKey('time', $response->json(0));
    }

    public function test_yesterday_booking_is_excluded_from_today(): void
    {
        $this->seedDemo();
        $this->travelTo('2026-11-12 12:00:00');
        $arbat = $this->room('Арбат');

        Booking::factory()->for($this->anna())->create([
            'room_id' => $arbat->id,
            'starts_at' => '2026-11-11 10:00:00',
            'ends_at' => '2026-11-11 11:00:00',
        ]);

        $this->withToken('anna-token')
            ->getJson('/api/rooms/'.$arbat->id.'/bookings/today')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_ob_does_not_list_arbat_bookings(): void
    {
        $this->seedDemo();
        $this->travelTo('2026-11-12 12:00:00');

        Booking::factory()->for($this->anna())->create([
            'room_id' => $this->room('Арбат')->id,
            'starts_at' => '2026-11-12 10:00:00',
            'ends_at' => '2026-11-12 11:00:00',
        ]);

        $this->withToken('anna-token')
            ->getJson('/api/rooms/'.$this->room('Обь')->id.'/bookings/today')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_missing_room_returns_404(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')
            ->getJson('/api/rooms/999999/bookings/today')
            ->assertNotFound();
    }
}
