<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoBooking;
use Tests\TestCase;

class BookingStoreTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoBooking;

    public function test_anna_stores_arbat_booking_in_moscow_office_time(): void
    {
        $this->seedDemo();
        $log = storage_path('logs/bookings.log');
        file_put_contents($log, '');

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Планёрка',
            'starts_at' => '2026-11-02T10:00:00+03:00',
            'ends_at' => '2026-11-02T11:00:00+03:00',
        ]);

        $response->assertSuccessful();

        $booking = Booking::query()->where('title', 'Планёрка')->first();
        $this->assertNotNull($booking);
        $this->assertSame('2026-11-02 10:00', $booking->starts_at->copy()->timezone('Europe/Moscow')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 11:00', $booking->ends_at->copy()->timezone('Europe/Moscow')->format('Y-m-d H:i'));
        $this->assertStringContainsString('Booking created', (string) file_get_contents($log));
    }

    public function test_boris_stores_booking_in_a_free_tverskaya_slot(): void
    {
        $this->seedDemo();

        $response = $this->withToken('boris-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Тверская')->id,
            'user_id' => $this->boris()->id,
            'title' => 'Созвон',
            'starts_at' => '2026-11-02T10:00:00+03:00',
            'ends_at' => '2026-11-02T11:00:00+03:00',
        ]);

        $response->assertSuccessful();
        $this->assertNotNull(Booking::query()->where('title', 'Созвон')->first());
    }

    public function test_boris_receives_422_when_arbat_slot_is_taken(): void
    {
        $this->seedDemo();
        $arbat = $this->room('Арбат');

        Booking::factory()->for($this->anna())->create([
            'room_id' => $arbat->id,
            'title' => 'Уже занято',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 23:00:00',
        ]);

        $response = $this->withToken('boris-token')->postJson('/api/bookings', [
            'room_id' => $arbat->id,
            'user_id' => $this->boris()->id,
            'title' => 'Повтор',
            'starts_at' => '2026-11-02T10:00:00+03:00',
            'ends_at' => '2026-11-02T11:00:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Комната занята');
    }

    public function test_anna_stores_ob_booking_in_novosibirsk_office_time(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Обь')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Новосибирск',
            'starts_at' => '2026-11-02T10:00:00+03:00',
            'ends_at' => '2026-11-02T11:00:00+03:00',
        ]);

        $response->assertSuccessful();

        $booking = Booking::query()->where('title', 'Новосибирск')->first();
        $this->assertNotNull($booking);
        $this->assertSame('2026-11-02T14:00:00+07:00', $booking->starts_at->copy()->timezone('Asia/Novosibirsk')->format('Y-m-d\TH:i:sP'));
        $this->assertSame('2026-11-02T15:00:00+07:00', $booking->ends_at->copy()->timezone('Asia/Novosibirsk')->format('Y-m-d\TH:i:sP'));
    }

    public function test_anna_receives_422_when_arbat_starts_before_opening(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Рано',
            'starts_at' => '2026-11-02T08:00:00+03:00',
            'ends_at' => '2026-11-02T09:00:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нерабочее время');
    }

    public function test_anna_receives_422_when_arbat_starts_at_closing(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Поздно',
            'starts_at' => '2026-11-02T20:00:00+03:00',
            'ends_at' => '2026-11-02T21:00:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нерабочее время');
    }
}
