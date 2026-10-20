<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as a user using JWT and set the Authorization header.
     */
    protected function actingAsJwt(?User $user = null): static
    {
        $user = $user ?? User::factory()->create();
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
