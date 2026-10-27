<?php

use Bernskiold\ExportEngine\Tests\Fixtures\User;
use Bernskiold\ExportEngine\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Make a user and sign them in.
 */
function signIn(): User
{
    $user = makeUser();

    test()->actingAs($user);

    return $user;
}

function makeUser(string $name = 'Ada'): User
{
    return User::query()->create([
        'name' => $name,
        'email' => strtolower($name).'-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
}
