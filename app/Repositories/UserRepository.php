<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
    
    /**
     * Rekord lekérése azonosító alapján
     * 
     * @param int $id
     * @return User
     */
    public function getUser(int $id): User
    {
        /** @var User $u */
        $u = User::findOrFail($id);
        
        return $u;
    }
    
    /**
     * Rekord lekérése név alapján
     * 
     * @param string $name
     * @return User
     */
    public function getUserByName(string $name): User
    {
        /** @var User $u */
        $u = User::where('name', '=', $name)->firstOrFail();
        
        return $u;
    }
    
    /**
     * Új felhasználó mentése
     * 
     * @param Request $request
     * @return User
     */
    public function store(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('Pa$$w0rd'),
            ]);

            // küldjünk reset linket azonnal
            $status = Password::sendResetLink(['email' => $user->email]);
            
            if ($status !== Password::RESET_LINK_SENT) {
                return response()->json(['message' => __($status)], 422);
            }

            // cache, settings, stb ha kell…

            return $user;
        });
        
//        return DB::transaction(function(Request $request): User {
//            /** @var array<string,mixed> $data */
//            $data = $request->all();
//            
//            /** @var User $user */
//            $user = User::create($data);
//            
//            $this->createDefaultSettings($user);
//            $this->cacheService->forgetAll($this->tag);
//            
//            return $user;
//        });
    }
    
    /**
     * Felhasználó adatainak mentése
     * 
     * @param array $data
     * @param type $id
     * @return User
     */
    public function update(array $data, $id): User
    {
        return DB::transaction(function() use ($data, $id): User {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($id);
            
            $user->fill($data);
            $user->save();
            $user->refresh();
            
            return $user;
        });
    }
    
    public function bulkDelete(array $ids): int
    {
        $authId = auth()->id();
        abort_if(in_array($authId, $ids, true), 403, 'Saját fiókot nem törölhetsz.');
    
        return DB::transaction(function() use($ids): int {
            
            // Jogosultságok törlése
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('model_id', $ids)
                ->delete();
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->whereIn('model_id', $ids)
                ->delete();
            $int = User::query()->whereIn('id', $ids)->delete();
            
            $this->cacheService->forgetAll($this->tag);
            
            return $deleted;
        });
    }
    
    public function destroy(int $id): bool
    {
        return DB::transaction(function() use($id) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($id);
            
            abort_if(auth()->id() === $user->id, 403, 'Saját fiókot nem törölhetsz.');
            
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', '=', $id)
                ->delete();
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', '=', $id)
                ->delete();
            
            $deleted = (bool) $user->delete();
            
            // Beállítások törlése
            $this->deleteDefaultSettings($user);
            
            // Cache ürítése
            $this->cacheService->forgetAll($this->tag);

            return $deleted;
        });
    }
    
    private function createDefaultSettings(User $user): void{}
    
    private function updateDefaultSettings(User $user): void{}
    
    private function deleteDefaultSettings(User $user): void{}
    
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
}
