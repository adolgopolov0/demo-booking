<?php

namespace Tests\Concerns;

use App\Models\Room;
use App\Models\User;

trait SeedsDemoBooking
{
    protected function seedDemo(): void
    {
        $this->seed();
    }

    protected function anna(): User
    {
        return User::query()->where('email', 'anna@example.com')->firstOrFail();
    }

    protected function boris(): User
    {
        return User::query()->where('email', 'boris@example.com')->firstOrFail();
    }

    protected function room(string $name): Room
    {
        return Room::query()->where('name', $name)->firstOrFail();
    }
}
