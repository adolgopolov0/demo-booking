<?php

namespace App\Services;

use App\Contracts\BookingRepository;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function __construct(private BookingRepository $bookings) {}

    public function list(User $user): LengthAwarePaginator
    {
        return $this->bookings->paginateForUser($user->id);
    }

    /**
     * @param  array{room_id: int|string, title: string, starts_at: string, ends_at: string}  $data
     */
    public function store(User $user, array $data): Booking
    {
        $room = $this->bookings->findRoom((int) $data['room_id']);
        $startsAt = Carbon::parse($data['starts_at'])->timezone($room->office->timezone);
        $endsAt = Carbon::parse($data['ends_at'])->timezone($room->office->timezone);

        $startsUtc = $startsAt->copy()->utc();
        $endsUtc = $endsAt->copy()->utc();

        return DB::transaction(function () use ($user, $room, $data, $startsUtc, $endsUtc): Booking {
            $this->bookings->lockRoom($room->id);

            if ($this->bookings->overlaps($room->id, $startsUtc, $endsUtc)) {
                $this->reject('Комната занята');
            }

            $booking = $this->bookings->create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'starts_at' => $startsUtc,
                'ends_at' => $endsUtc,
            ]);

            Log::withContext(['booking_id' => $booking->id]);
            Log::channel('bookings')->info('Booking created');

            return $booking;
        });
    }

    public function delete(User $user, Booking $booking): void
    {
        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        $this->bookings->delete($booking);
    }

    /**
     * @return Collection<int, array{time: string, author: string}>
     */
    public function today(Room $room): Collection
    {
        $room->loadMissing('office');
        $timezone = $room->office->timezone;

        return $this->bookings->forRoomOnDate($room, today())->map(function (Booking $booking) use ($timezone): array {
            $startsAt = $booking->starts_at->copy()->timezone($timezone);
            $endsAt = $booking->ends_at->copy()->timezone($timezone);

            return [
                'time' => $startsAt->format('Y-m-d H:i:s').' – '.$endsAt->format('Y-m-d H:i:s'),
                'author' => $booking->user->name,
            ];
        });
    }

    private function reject(string $message): never
    {
        throw new HttpResponseException(response()->json(['error' => $message], 422));
    }
}
