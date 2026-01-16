<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repo
    ) {}

    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
}
