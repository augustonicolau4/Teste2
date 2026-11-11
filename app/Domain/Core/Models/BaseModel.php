<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base do autogest_nicola.
 * Guarda apenas 'id'/'uuid' (defesa em profundidade: a validação de entrada
 * é feita por Form Requests / DTOs antes de chegar ao modelo).
 */
abstract class BaseModel extends Model
{
    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [];
    }
}
