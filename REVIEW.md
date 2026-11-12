# Ревью BookingController

Объект: `app/Http/Controllers/BookingController.php`. Маршруты в `routes/api.php` закрыты `auth:sanctum`. Действия остаются теми же: `index`, `store`, `destroy`, `today`. Новые HTTP-методы не предлагаются.

## Тесты

Покрыт только `index` в `tests/Feature/BookingIndexTest.php`:

- `test_returns_only_own_bookings`
- `test_requires_authentication`

Без тестов:

- `store` — создание брони, 409 при пересечении интервала, 422 вне окна 9–20, 401 без авторизации
- `destroy` — удаление своей брони, чужая бронь, отсутствующий id
- `today` — брони комнаты на сегодня и поле автора

## Валидация

`store` пишет `Booking::create($request->all())` без `$request->validate()` и без Form Request. Не проверяются `room_id`, `starts_at`, `ends_at`, `title`, условие `ends_at` позже `starts_at` и конец интервала. `Carbon::parse` на пустой дате отвечает 500. Проверка часа начала стоит после SQL.

## Типизация

Нет return type у `index`, `store`, `destroy`, `today`. У `destroy` аргумент `$id` без типа. В `today` замыкание `fn ($b)` без типа.

## N+1

`today` читает `$b->user->name` внутри `map` без `with('user')`: один запрос броней и отдельный запрос пользователя на каждую бронь.

## Улучшения

- Проверку занятости переписать через Eloquent с привязками параметров. Сейчас `room_id`, `starts_at` и `ends_at` подставляются в строку SQL.
- `user_id` брать у текущего пользователя. Поле входит в `#[Fillable]` модели `Booking`, тело запроса может подменить владельца.
- `destroy` разрешать только владельцу брони. Сейчас любой авторизованный пользователь удаляет любую запись.
- Проверку пересечения и `create` держать в одной транзакции с блокировкой. Два параллельных `store` могут занять одну комнату.
- `orderByRaw('starts_at DESC')` заменить на `orderByDesc('starts_at')`.
- `destroy($id)` перевести на implicit binding `Booking $booking`, как уже сделано в `today(Room $room)`.

## Слои

Контроллер оставить тонким HTTP-слоем с теми же четырьмя действиями: разбор запроса, код ответа, вызов операции.

Существующие операции вынести за интерфейс `App\Contracts\BookingRepository`: список своих броней, создание, удаление, брони комнаты на сегодня. Eloquent-реализацию зарегистрировать в контейнере в `app/Providers/AppServiceProvider.php`:

```php
$this->app->bind(BookingRepository::class, EloquentBookingRepository::class);
```

Контроллер получает интерфейс через конструктор. Реализацию подставляет контейнер.
