<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BookingRepository
{
    public function paginateForUser(int $userId): LengthAwarePaginator;

    public function findRoom(int $roomId): Room;

    public function lockRoom(int $roomId): void;

    /**
     * @param  array{room_id: int, user_id: int, title: string, starts_at: CarbonInterface, ends_at: CarbonInterface}  $attributes
     */
    public function create(array $attributes): Booking;

    public function overlaps(int $roomId, CarbonInterface $startsAt, CarbonInterface $endsAt): bool;

    public function delete(Booking $booking): void;

    public function forRoomOnDate(Room $room, CarbonInterface $date): Collection;
}
