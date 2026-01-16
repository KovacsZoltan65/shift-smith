<?php

declare(strict_types=1);

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface UserRepositoryInterface
{
    public function fetch(Request $request): LengthAwarePaginator;
}
