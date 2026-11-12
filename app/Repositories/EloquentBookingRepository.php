<?php

namespace App\Repositories;

use App\Contracts\BookingRepository;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentBookingRepository implements BookingRepository
{
    public function paginateForUser(int $userId): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->orderByDesc('starts_at')
            ->paginate(20);
    }

    public function findRoom(int $roomId): Room
    {
        return Room::query()->with('office')->findOrFail($roomId);
    }

    public function lockRoom(int $roomId): void
    {
        Room::query()->whereKey($roomId)->lockForUpdate()->first();
    }

    public function overlaps(int $roomId, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();
    }

    /**
     * @param  array{room_id: int, user_id: int, title: string, starts_at: CarbonInterface, ends_at: CarbonInterface}  $attributes
     */
    public function create(array $attributes): Booking
    {
        return Booking::query()->create($attributes);
    }

    public function delete(Booking $booking): void
    {
        $booking->delete();
    }

    public function forRoomOnDate(Room $room, CarbonInterface $date): Collection
    {
        return $room->bookings()
            ->with('user')
            ->whereDate('starts_at', $date)
            ->get();
    }
}
