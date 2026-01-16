<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\UserService;
use Inertia\Response as InertiaResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service
    ) {
        // Ha használsz Policy-t:
        // $this->authorizeResource(User::class, 'user');
    }

    public function index(IndexRequest $request): InertiaResponse
    {
        $this->authorize('viewAny', User::class);
        
        return Inertia::render('Users/Index', [
            'title'  => 'Felhasználók',
            'filter' => $request->validatedFilters(),
        ]);
    }

    public function fetch(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->service->fetch($request);
        
        \Log::info(print_r($users->items(), true));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
            ],
            'filter' => $request->validatedFilters(),
        ], Response::HTTP_OK);
    }

}
