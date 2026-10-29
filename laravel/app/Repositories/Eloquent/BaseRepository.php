<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementacion base abstracta de repositorio con Eloquent ORM.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * Instancia del modelo gestionado.
     */
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $attributes): Model
    {
        return $this->model->create($attributes);
    }

    /**
     * @inheritDoc
     */
    public function update(int $id, array $attributes): bool
    {
        $record = $this->findById($id);

        if (!$record) {
            return false;
        }

        return $record->update($attributes);
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool
    {
        $record = $this->findById($id);

        if (!$record) {
            return false;
        }

        return (bool) $record->delete();
    }
}
