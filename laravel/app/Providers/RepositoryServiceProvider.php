<?php

namespace App\Providers;

use App\Repositories\Contracts\CobroRepositoryInterface;
use App\Repositories\Contracts\PagoCobroRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\CobroRepository;
use App\Repositories\Eloquent\PagoCobroRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de servicios encargado de enlazar los contratos de repositorios
 * con sus implementaciones correspondientes en el contenedor de dependencias.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Todos los enlaces de interfaz a implementacion de repositorios.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CobroRepositoryInterface::class     => CobroRepository::class,
        PagoCobroRepositoryInterface::class => PagoCobroRepository::class,
        UserRepositoryInterface::class      => UserRepository::class,
    ];

    /**
     * Registra servicios en el contenedor.
     */
    public function register(): void
    {
        // Los bindings declarados en la propiedad $bindings son enlazados automaticamente
    }

    /**
     * Inicializa los servicios.
     */
    public function boot(): void
    {
        //
    }
}
