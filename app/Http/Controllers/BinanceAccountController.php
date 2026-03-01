<?php

namespace App\Http\Controllers;

use App\Models\BinanceAccount;
use App\Repositories\BinanceAccountRepository;
use App\Services\BinanceApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BinanceAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BinanceAccountRepository $repository,
        private BinanceApiService $binanceApi,
    ) {}

    /**
     * List all Binance accounts for current user.
     */
    public function index(Request $request): Response
    {
        $accounts = $this->repository->getByUser($request->user()->id);

        return Inertia::render('BinanceAccounts/Index', [
            'accounts' => $accounts->map(fn($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'masked_api_key' => $a->masked_api_key,
                'is_testnet' => $a->is_testnet,
                'is_active' => $a->is_active,
                'last_connected_at' => $a->last_connected_at?->diffForHumans(),
                'bots_count' => $a->bots()->count(),
                'created_at' => $a->created_at->format('d/m/Y'),
            ]),
        ]);
    }

    /**
     * Store a new Binance account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'api_key' => 'required|string|min:10',
            'api_secret' => 'required|string|min:10',
            'is_testnet' => 'boolean',
        ]);

        $validated['user_id'] = $request->user()->id;

        $this->repository->create($validated);

        return back()->with('success', 'Cuenta de Binance agregada exitosamente');
    }

    /**
     * Update a Binance account.
     */
    public function update(Request $request, BinanceAccount $binanceAccount): RedirectResponse
    {
        abort_if($binanceAccount->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'api_key' => 'sometimes|string|min:10',
            'api_secret' => 'sometimes|string|min:10',
            'is_testnet' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $this->repository->update($binanceAccount, $validated);

        return back()->with('success', 'Cuenta actualizada');
    }

    /**
     * Delete a Binance account.
     */
    public function destroy(Request $request, BinanceAccount $binanceAccount): RedirectResponse
    {
        abort_if($binanceAccount->user_id !== $request->user()->id, 403);

        if ($binanceAccount->bots()->where('status', 'active')->exists()) {
            return back()->with('error', 'No se puede eliminar una cuenta con bots activos');
        }

        $this->repository->delete($binanceAccount);

        return back()->with('success', 'Cuenta eliminada');
    }

    /**
     * Test connection to Binance (AJAX).
     */
    public function testConnection(Request $request, BinanceAccount $binanceAccount): JsonResponse
    {
        abort_if($binanceAccount->user_id !== $request->user()->id, 403);

        $result = $this->binanceApi->testConnection($binanceAccount);

        if ($result['success']) {
            return $this->successResponse($result['data'], $result['message']);
        }

        return $this->errorResponse($result['message']);
    }

    /**
     * Get account balance (AJAX).
     */
    public function balance(Request $request, BinanceAccount $binanceAccount): JsonResponse
    {
        abort_if($binanceAccount->user_id !== $request->user()->id, 403);

        $balance = $this->binanceApi->getAccountBalance($binanceAccount);

        return $this->successResponse($balance);
    }
}
