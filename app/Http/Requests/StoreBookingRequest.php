<?php

namespace App\Http\Requests;

use App\Contracts\BookingRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Укажите комнату.',
            'room_id.integer' => 'Комната должна быть числом.',
            'room_id.exists' => 'Выбранная комната не существует.',
            'title.required' => 'Укажите название.',
            'title.string' => 'Название должно быть строкой.',
            'starts_at.required' => 'Укажите время начала.',
            'starts_at.date' => 'Время начала указано неверно.',
            'ends_at.required' => 'Укажите время окончания.',
            'ends_at.date' => 'Время окончания указано неверно.',
            'ends_at.after' => 'Время окончания должно быть позже начала.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(BookingRepository $bookings): array
    {
        return [
            function (Validator $validator) use ($bookings): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $room = $bookings->findRoom($this->integer('room_id'));
                $startsAt = Carbon::parse($this->string('starts_at')->toString())->timezone($room->office->timezone);
                $endsAt = Carbon::parse($this->string('ends_at')->toString())->timezone($room->office->timezone);

                $today = now()->timezone($startsAt->timezoneName)->toDateString();
                if ($startsAt->toDateString() < $today) {
                    $this->reject('Нельзя бронировать прошедшую дату');
                }

                $opens = $startsAt->copy()->setTime(9, 0);
                $closes = $startsAt->copy()->setTime(20, 0);
                if (! $startsAt->isSameDay($endsAt) || $startsAt->lt($opens) || $endsAt->gt($closes)) {
                    $this->reject('Нерабочее время');
                }

                $minutes = (int) round($startsAt->diffInMinutes($endsAt, true));
                if ($minutes < 15 || $minutes > 240) {
                    $this->reject('Длительность должна быть от 15 минут до 4 часов');
                }

                if (! $this->isQuarterHour($startsAt) || ! $this->isQuarterHour($endsAt)) {
                    $this->reject('Время должно быть кратно 15 минутам');
                }
            },
        ];
    }

    private function isQuarterHour(Carbon $time): bool
    {
        return $time->second === 0 && $time->micro === 0 && $time->minute % 15 === 0;
    }

    private function reject(string $message): never
    {
        throw new HttpResponseException(response()->json(['error' => $message], 422));
    }
}
