<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsDemoBooking;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoBooking;

    public function test_returns_only_own_bookings(): void
    {
        $user = User::factory()->create();
        $own = Booking::factory()->for($user)->create();
        Booking::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_anna_sees_only_her_bookings(): void
    {
        $this->seedDemo();
        $anna = $this->anna();
        $own = Booking::factory()->for($anna)->create();
        $foreign = Booking::factory()->for($this->boris())->create();

        $ids = collect($this->withToken('anna-token')->getJson('/api/bookings')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_boris_sees_only_his_bookings(): void
    {
        $this->seedDemo();
        $boris = $this->boris();
        $own = Booking::factory()->for($boris)->create();
        $foreign = Booking::factory()->for($this->anna())->create();

        $ids = collect($this->withToken('boris-token')->getJson('/api/bookings')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_anna_sees_an_empty_list_when_she_has_no_bookings(): void
    {
        $this->seedDemo();

        $this->withToken('anna-token')
            ->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_anna_bookings_are_newest_first_and_paginated_by_20(): void
    {
        $this->seedDemo();
        $anna = $this->anna();

        foreach (range(1, 21) as $day) {
            Booking::factory()->for($anna)->create([
                'starts_at' => Carbon::parse('2026-01-01')->addDays($day)->setTime(10, 0),
                'ends_at' => Carbon::parse('2026-01-01')->addDays($day)->setTime(11, 0),
            ]);
        }

        $newest = Booking::query()->where('user_id', $anna->id)->orderByDesc('starts_at')->firstOrFail();
        $oldest = Booking::query()->where('user_id', $anna->id)->orderBy('starts_at')->firstOrFail();

        $response = $this->withToken('anna-token')->getJson('/api/bookings')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('data.0.id', $newest->id);
        $this->assertFalse($ids->contains($oldest->id));
    }
}
