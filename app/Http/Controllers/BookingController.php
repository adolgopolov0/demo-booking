<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyBookingRequest;
use App\Http\Requests\IndexBookingsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\TodayBookingsRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(IndexBookingsRequest $request): LengthAwarePaginator
    {
        return $this->bookings->list($this->user($request));
    }

    public function store(StoreBookingRequest $request): Booking
    {
        return $this->bookings->store($this->user($request), $request->validated());
    }

    public function destroy(DestroyBookingRequest $request, Booking $booking): Response
    {
        $this->bookings->delete($this->user($request), $booking);

        return response()->noContent();
    }

    public function today(TodayBookingsRequest $request, Room $room): Collection
    {
        return $this->bookings->today($room);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
