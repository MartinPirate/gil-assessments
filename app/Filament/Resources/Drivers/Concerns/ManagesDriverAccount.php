<?php

namespace App\Filament\Resources\Drivers\Concerns;

/**
 * The driver form collects two rows' worth of fields.
 *
 * Email and password belong to the user, everything else to the driver, and
 * both the create and the edit page need the same split — so it lives here
 * rather than being written out twice.
 */
trait ManagesDriverAccount
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function splitAccountFields(array $data): array
    {
        $account = array_filter([
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
        ], fn ($value) => filled($value));

        unset($data['email'], $data['password']);

        return [$data, $account];
    }
}
