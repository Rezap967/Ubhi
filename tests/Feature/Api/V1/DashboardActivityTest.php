<?php

use App\Models\Role;
use App\Models\Settings\UserProfile;
use App\Models\Store;
use App\Models\User;

function dashboardActivityUser(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'owner']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $store = Store::factory()->create(['owner_id' => $user->id]);

    UserProfile::query()->create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'full_name' => $user->name,
    ]);

    return $user;
}

test('dashboard activity requires authentication', function () {
    $this->getJson('/api/v1/dashboard/activity')
        ->assertUnauthorized();
});

test('dashboard activity returns the empty state with default limit', function () {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard/activity')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Data aktivitas berhasil diambil')
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.meta.limit', 10)
        ->assertJsonPath('data.meta.total', 0)
        ->assertJsonPath('data.empty_message', 'Belum ada aktivitas baru yang tercatat.');
});

test('dashboard activity accepts valid filters and preserves the limit', function () {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard/activity?limit=25&date=2026-08-21&module=store&user_id='.$user->id)
        ->assertSuccessful()
        ->assertJsonPath('data.meta.limit', 25)
        ->assertJsonPath('data.items', []);
});

test('dashboard activity rejects invalid filters', function (string $query, string $field) {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard/activity?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'limit above maximum' => ['limit=51', 'limit'],
    'invalid date' => ['date=21-08-2026', 'date'],
    'invalid module' => ['module=finance', 'module'],
]);

test('dashboard skeleton get endpoints return empty states', function (string $endpoint) {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard'.$endpoint)
        ->assertSuccessful()
        ->assertJsonPath('success', true);
})->with([
    'summary' => '',
    'widgets' => '/widgets',
    'activity' => '/activity',
    'tasks' => '/tasks',
    'notifications' => '/notifications',
    'sales chart' => '/charts/sales',
]);

test('dashboard skeleton mutation endpoints return default responses', function () {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->patchJson('/api/v1/dashboard/notifications/notification-1/read')
        ->assertSuccessful()
        ->assertJsonPath('data.is_read', true);

    $this->actingAs($user)
        ->patchJson('/api/v1/dashboard/tasks/task-1', ['is_completed' => true])
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $this->actingAs($user)
        ->putJson('/api/v1/dashboard/widgets/preferences', ['preferences' => []])
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

test('dashboard skeleton validates chart and notification filters', function (string $endpoint, string $query, string $field) {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->getJson('/api/v1/dashboard'.$endpoint.'?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'invalid chart period' => ['/charts/sales', 'period=quarter', 'period'],
    'invalid notification page' => ['/notifications', 'page=0', 'page'],
]);

test('dashboard skeleton validates task and preference mutations', function (string $method, string $url, array $payload, string $field) {
    $user = dashboardActivityUser();

    $this->actingAs($user)
        ->json($method, $url, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'task completion required' => ['PATCH', '/api/v1/dashboard/tasks/task-1', [], 'is_completed'],
    'preferences must be array' => ['PUT', '/api/v1/dashboard/widgets/preferences', ['preferences' => 'invalid'], 'preferences'],
]);
