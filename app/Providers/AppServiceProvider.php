<?php

namespace App\Providers;

use App\Interfaces\CompanyRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\RoleRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\Company;
use App\Models\Employee;
use App\Observers\CompanyObserver;
use App\Observers\EmployeeObserver;
use App\Repositories\CompanyRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use function str_contains;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class, 
            UserRepository::class
        );
        $this->app->bind(
            CompanyRepositoryInterface::class, 
            CompanyRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class, 
            RoleRepository::class
        );
        $this->app->bind(
            EmployeeRepositoryInterface::class, 
            EmployeeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        \DB::listen(function ($query) {
//            \Log::info('SQL', [
//                'sql' => $query->sql,
//                'bindings' => $query->bindings,
//                'time_ms' => $query->time,
//            ]);
//        });

        Employee::observe(EmployeeObserver::class);
        Company::observe(CompanyObserver::class);
        
        if (!defined('APP_ACTIVE'))   define('APP_ACTIVE', 1);
        if (!defined('APP_INACTIVE')) define('APP_INACTIVE', 0);

        if (!defined('APP_TRUE'))     define('APP_TRUE', true);
        if (!defined('APP_FALSE'))    define('APP_FALSE', false);

        Inertia::share('flash', function () {
            return [
                'message' => Session::get('message'),
            ];
        });

        Inertia::share('csrf_token', function () {
            return csrf_token();
        });

        Builder::macro('whereLike', function ($attributes, string $search) {
            /** @phpstan-var \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $this */
            /** @phpstan-param array<int,string>|string $attributes */
            /** @phpstan-return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> */
            $attributes = Arr::wrap($attributes);

            $search = trim($search);
            if ($search === '') {
                return $this;
            }

            $grammar = $this->getQuery()->getGrammar();
            $like    = $grammar instanceof PostgresGrammar ? 'ilike' : 'like';

            $terms = preg_split('/\s+/', $search) ?: [$search];

            return $this->where(function ($q) use ($attributes, $terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($qq) use ($attributes, $term, $like) {
                        foreach ($attributes as $attr) {
                            if (str_contains($attr, '.')) {
                                [$relation, $relAttr] = explode('.', $attr, 2);
                                $qq->orWhereHas($relation, function ($rq) use ($relAttr, $term, $like) {
                                    $rq->where($relAttr, $like, "%{$term}%");
                                });
                            } else {
                                $qq->orWhere($attr, $like, "%{$term}%");
                            }
                        }
                    });
                }
            });
        });

        Builder::macro('orWhereLike', function ($attributes, string $search) {
            /** @phpstan-var \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $this */
            /** @phpstan-param array<int,string>|string $attributes */
            /** @phpstan-return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> */
            $attributes = Arr::wrap($attributes);
            $search     = trim($search);
            if ($search === '') {
                return $this;
            }

            $grammar = $this->getQuery()->getGrammar();
            $like    = $grammar instanceof PostgresGrammar ? 'ilike' : 'like';

            $terms = preg_split('/\s+/', $search) ?: [$search];

            return $this->orWhere(function ($q) use ($attributes, $terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($qq) use ($attributes, $term, $like) {
                        foreach ($attributes as $attr) {
                            if (str_contains($attr, '.')) {
                                [$rel, $relAttr] = explode('.', $attr, 2);
                                $qq->orWhereHas($rel, function ($rq) use ($relAttr, $term, $like) {
                                    $rq->where($relAttr, $like, "%{$term}%");
                                });
                            } else {
                                $qq->orWhere($attr, $like, "%{$term}%");
                            }
                        }
                    });
                }
            });
        });



        Vite::prefetch(concurrency: 3);
    }
}
