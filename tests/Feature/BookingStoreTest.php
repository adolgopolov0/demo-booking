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

    public function test_adjacent_arbat_bookings_are_created(): void
    {
        $this->seedDemo();
        $arbat = $this->room('Арбат')->id;

        $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $arbat,
            'user_id' => $this->anna()->id,
            'title' => 'Первая',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T11:00:00+03:00',
        ])->assertSuccessful();

        $this->withToken('boris-token')->postJson('/api/bookings', [
            'room_id' => $arbat,
            'user_id' => $this->boris()->id,
            'title' => 'Впритык',
            'starts_at' => '2026-11-12T11:00:00+03:00',
            'ends_at' => '2026-11-12T12:00:00+03:00',
        ])->assertSuccessful();

        $this->assertNotNull(Booking::query()->where('title', 'Впритык')->first());
    }

    public function test_partial_arbat_overlap_returns_422(): void
    {
        $this->seedDemo();
        $arbat = $this->room('Арбат')->id;

        $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $arbat,
            'user_id' => $this->anna()->id,
            'title' => 'Первая',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T11:00:00+03:00',
        ])->assertSuccessful();

        $response = $this->withToken('boris-token')->postJson('/api/bookings', [
            'room_id' => $arbat,
            'user_id' => $this->boris()->id,
            'title' => 'Пересечение',
            'starts_at' => '2026-11-12T10:30:00+03:00',
            'ends_at' => '2026-11-12T11:30:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Комната занята');
    }

    public function test_fifteen_minute_arbat_booking_is_created(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Четверть часа',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T10:15:00+03:00',
        ])->assertSuccessful();

        $this->assertNotNull(Booking::query()->where('title', 'Четверть часа')->first());
    }

    public function test_four_hour_arbat_booking_is_created(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Четыре часа',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T14:00:00+03:00',
        ])->assertSuccessful();

        $this->assertNotNull(Booking::query()->where('title', 'Четыре часа')->first());
    }

    public function test_ten_minute_booking_returns_422(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Короткая',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T10:10:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Длительность должна быть от 15 минут до 4 часов');
    }

    public function test_booking_longer_than_four_hours_returns_422(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Слишком длинная',
            'starts_at' => '2026-11-12T10:00:00+03:00',
            'ends_at' => '2026-11-12T14:15:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Длительность должна быть от 15 минут до 4 часов');
    }

    public function test_booking_off_fifteen_minute_grid_returns_422(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Не по сетке',
            'starts_at' => '2026-11-12T10:07:00+03:00',
            'ends_at' => '2026-11-12T10:22:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Время должно быть кратно 15 минутам');
    }

    public function test_booking_ending_at_closing_is_created(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'До закрытия',
            'starts_at' => '2026-11-12T19:00:00+03:00',
            'ends_at' => '2026-11-12T20:00:00+03:00',
        ])->assertSuccessful();

        $this->assertNotNull(Booking::query()->where('title', 'До закрытия')->first());
    }

    public function test_booking_ending_after_closing_returns_422(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'После закрытия',
            'starts_at' => '2026-11-12T19:00:00+03:00',
            'ends_at' => '2026-11-12T20:15:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нерабочее время');
    }

    public function test_ob_evening_in_moscow_offset_returns_422(): void
    {
        $this->seedDemo();

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Обь')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Поздно в Новосибирске',
            'starts_at' => '2026-11-12T17:00:00+03:00',
            'ends_at' => '2026-11-12T18:00:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нерабочее время');
    }

    public function test_past_date_returns_422(): void
    {
        $this->seedDemo();
        $this->travelTo('2026-11-12 12:00:00');

        $response = $this->withToken('anna-token')->postJson('/api/bookings', [
            'room_id' => $this->room('Арбат')->id,
            'user_id' => $this->anna()->id,
            'title' => 'Вчера',
            'starts_at' => '2026-11-11T10:00:00+03:00',
            'ends_at' => '2026-11-11T11:00:00+03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Нельзя бронировать прошедшую дату');
    }
}
