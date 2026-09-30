<?php

use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports that the api and database are connected', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'status' => 'ok',
                'database' => 'ok',
            ],
            'meta' => null,
            'errors' => null,
        ]);
});

it('uses the standard envelope for an unknown api route', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson([
            'data' => null,
            'meta' => null,
            'errors' => [
                'resource' => ['Record not found.'],
            ],
        ]);
});

it('returns warnings with http 409', function () {
    $response = ApiResponse::warnings([
        'A similar company name already exists.',
    ]);

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toBe([
            'data' => null,
            'meta' => null,
            'errors' => null,
            'warnings' => ['A similar company name already exists.'],
        ]);
});
