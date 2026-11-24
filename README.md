# Лабораторна робота № 9

**Тема:** Laravel. Події, слухачі та обсерватори
**Проєкт:** REST API для системи управління завданнями (Task Tracker)

## Мета

Навчитися працювати з подіями (Events), слухачами (Listeners) та обсерваторами (Observers) у Laravel. У цій роботі я зробив так, щоб зміни в завданнях автоматично записувалися в лог, а при створенні завдання "сповіщався" виконавець (сповіщення імітується записом у лог).

## Структура файлів

```
app/Observers/
    TaskObserver.php
app/Events/
    TaskCreated.php
app/Listeners/
    NotifyAssignee.php
app/Providers/
    AppServiceProvider.php      (змінив)
app/Http/Controllers/Api/
    TaskController.php          (змінив)
```

Модель `Task`, міграції й маршрути залишились з попередніх лабораторних.

## Хід роботи

### Крок 0. Підготовка

Спочатку перевірив у `.env`, що логи пишуться у файл:

```
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

Також у моделі `Task` має бути `$fillable` з усіма полями, інакше `Task::create()` не спрацює:

```php
protected $fillable = [
    'title', 'description', 'status',
    'project_id', 'author_id', 'assigned_to', 'due_date',
];
```

### Крок 1. Observer для моделі Task

Створив спостерігач командою:

```
php artisan make:observer TaskObserver --model=Task
```

У ньому реалізував три методи:

- `created()` — коли завдання створене, пише в лог його ID та назву;
- `updated()` — перевіряє через `wasChanged('status')`, чи змінився статус, і якщо так, то пише старий та новий статус;
- `deleted()` — пише в лог, що завдання видалено.

Приклад методу `updated()`:

```php
public function updated(Task $task): void
{
    if ($task->wasChanged('status')) {
        Log::info('TaskObserver: змінено статус завдання', [
            'task_id'    => $task->id,
            'old_status' => $task->getOriginal('status'),
            'new_status' => $task->status,
        ]);
    }
}
```

Старий статус беру через `getOriginal('status')`, а новий — це просто `$task->status`.

Після цього зареєстрував спостерігач в `AppServiceProvider` у методі `boot()`:

```php
Task::observe(TaskObserver::class);
```

### Крок 2. Подія TaskCreated

Створив клас події:

```
php artisan make:event TaskCreated
```

У конструкторі подія приймає модель `Task` і зберігає її в публічну властивість `$task`:

```php
public function __construct(public Task $task)
{
}
```

### Крок 3. Слухач NotifyAssignee

Створив слухача для цієї події:

```
php artisan make:listener NotifyAssignee --event=TaskCreated
```

У методі `handle()` зробив таку логіку:

1. Беру завдання з події (`$event->task`).
2. Якщо в завданні не вказано `assigned_to`, то нічого не роблю.
3. Якщо виконавець є, знаходжу його в таблиці `users` і пишу в лог, що завдання призначено цьому користувачу.

У Laravel 11/12 слухач підключається сам, тому нічого додатково реєструвати не треба. Перевірив командою:

```
php artisan event:list
```

У списку з'явилось `TaskCreated` → `NotifyAssignee@handle`. Якщо в когось Laravel 10 або старіший, то слухача треба додати в `$listen` у `EventServiceProvider`.

### Крок 4. Виклик події в контролері

У методі `store()` класу `TaskController` після збереження завдання викликаю подію:

```php
$task = Task::create($validated);

TaskCreated::dispatch($task);

return response()->json($task, 201);
```

У `update()` та `destroy()` нічого додатково писати не потрібно, бо там використовуються `$task->update()` і `$task->delete()`, а на них Observer спрацьовує сам.

### Крок 5. Тестування

Запустив сервер:

```
php artisan serve
```

І в Postman зробив три запити (з заголовками `Accept: application/json` та `Content-Type: application/json`):

| № | Запит                 | Тіло                                                                                                                                                     | Результат        |
| - | --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| 1 | `POST /api/tasks`     | `{"title": "Підготувати звіт", "description": "Квартальний звіт", "status": "new", "project_id": 1, "author_id": 1, "assigned_to": 2, "due_date": "2026-12-01"}` | `201 Created`    |
| 2 | `PATCH /api/tasks/1`  | `{"status": "in_progress"}`                                                                                                                              | `200 OK`         |
| 3 | `DELETE /api/tasks/1` | —                                                                                                                                                        | `204 No Content` |

Потім відкрив `storage/logs/laravel.log` (можна ще через `php artisan pail`) і побачив такі записи:

```
[2026-11-24 12:00:01] local.INFO: TaskObserver: створено нове завдання {"task_id":1,"title":"Підготувати звіт"}
[2026-11-24 12:00:01] local.INFO: NotifyAssignee: сповіщення виконавця про нове завдання {"task_id":1,"task_title":"Підготувати звіт","assignee_id":2,"assignee_name":"Test User","message":"Завдання «Підготувати звіт» призначено користувачу Test User"}
[2026-11-24 12:00:15] local.INFO: TaskObserver: змінено статус завдання {"task_id":1,"old_status":"new","new_status":"in_progress"}
[2026-11-24 12:00:30] local.INFO: TaskObserver: видалено завдання {"task_id":1,"title":"Підготувати звіт"}
```

Все працює: є записи і від `TaskObserver`, і від `NotifyAssignee`. Якщо створити завдання без `assigned_to`, то запису від `NotifyAssignee` не буде, бо слухач одразу завершується.

## Проблеми, які можуть виникнути

- Observer нічого не пише в лог, якщо він не зареєстрований в `AppServiceProvider`. Допомагає `php artisan optimize:clear`.
- Observer не спрацьовує на масові запити типу `Task::where(...)->update(...)`, тільки на роботу з конкретною моделлю.
- У Laravel 11+ не потрібно реєструвати слухача вручну ще й через `Event::listen()`, інакше він спрацює двічі.
- Якщо при створенні завдання вилітає помилка про mass assignment, треба додати поля в `$fillable`.

## Висновки

У цій лабораторній роботі я навчився користуватись Events, Listeners та Observers у Laravel. Observer `TaskObserver` автоматично логує створення, зміну статусу та видалення завдань, і для цього не довелось нічого міняти в логіці контролера. Подія `TaskCreated` разом зі слухачем `NotifyAssignee` дозволили винести сповіщення виконавця в окремий клас, тому код контролера залишився простим. Правильність роботи я перевірив через Postman і за записами у файлі `storage/logs/laravel.log`.
