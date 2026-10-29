<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas unitarias para UserRepository.
 */
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UserRepositoryInterface::class);
    }

    /**
     * Valida la busqueda de usuario por correo electronico.
     */
    public function test_find_by_email(): void
    {
        User::create([
            'name'     => 'Usuario Repo Test',
            'email'    => 'repo@example.test',
            'password' => 'Segura123!',
        ]);

        $user = $this->repository->findByEmail('repo@example.test');
        $this->assertNotNull($user);
        $this->assertEquals('Usuario Repo Test', $user->name);

        $notFound = $this->repository->findByEmail('desconocido@example.test');
        $this->assertNull($notFound);
    }
}
