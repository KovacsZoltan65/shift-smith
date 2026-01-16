<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * Whitelistelhető rendezhető mezők (biztonság + tisztaság).
     */
    private const ALLOWED_SORT_FIELDS = [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    public function model(): string
    {
        return User::class;
    }

    public function boot(): void
    {
        // Ha később Criteria-t akarsz (pl. query stringből automatikusan),
        // ez maradhat, de most a saját fetch úgyis felülírja a logikát.
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function fetch(Request $request): LengthAwarePaginator
    {
        // Prettus BaseRepository model példánya:
        $query = $this->model->newQuery();
        
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = ($perPage > 0) ? min($perPage, 100) : 10;

        $page   = (int) $request->integer('page', 1);
        
        $search = trim((string) $request->input('search', ''));

        $field = (string) $request->input('field', 'id');
        $order = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        
        $result = $query
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderBy($field, $order)
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
        
        // Inertia-hoz barátságos: query string megtartása lapozásnál
        return $result;
    }
}
