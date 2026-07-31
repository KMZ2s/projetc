<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    // =========================================================================
    // GET /account — visão geral
    // =========================================================================

    public function index()
    {
        $user   = Auth::user();
        $orders = $user->orders()
            ->with(['items'])
            ->latest('placed_at')
            ->paginate(5);

        return view('account.index', compact('user', 'orders'));
    }

    // =========================================================================
    // GET /account/pedidos
    // =========================================================================

    public function orders()
    {
        $user   = Auth::user();
        $orders = $user->orders()
            ->with(['items'])
            ->latest('placed_at')
            ->paginate(10);

        return view('account.orders', compact('user', 'orders'));
    }

    // =========================================================================
    // GET /account/pedidos/{orderNumber}
    // =========================================================================

    public function orderDetail(string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', Auth::id())
            ->with([
                'items',           // snapshot: product_name, variant_sku já gravados
                'shippingAddress',
                'coupon',
            ])
            ->firstOrFail();

        return view('account.order-detail', compact('order'));
    }

    // =========================================================================
    // GET /account/perfil
    // =========================================================================

    public function profile()
    {
        $user = Auth::user();
        return view('account.profile', compact('user'));
    }

    // =========================================================================
    // PUT /account/perfil
    // =========================================================================

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email,' . $user->id],
            'phone'      => ['nullable', 'string', 'max:20'],
            'cpf_cnpj'   => ['nullable', 'string', 'max:18'],
        ]);

        // O booted() do User sincroniza name automaticamente quando
        // first_name ou last_name são definidos (Abordagem B).
        // Se ambos estiverem vazios, preservamos o name atual.
        $data = [
            'first_name' => $request->input('first_name') ?: null,
            'last_name'  => $request->input('last_name') ?: null,
            'email'      => $request->input('email'),
            'phone'      => $request->input('phone') ?: null,
            'cpf_cnpj'   => $request->input('cpf_cnpj')
                ? preg_replace('/\D/', '', $request->input('cpf_cnpj'))
                : null,
        ];

        // Se nenhum first/last foi enviado, preserva o name existente
        // definindo explicitamente para evitar que o hook limpe o campo.
        if (empty($data['first_name']) && empty($data['last_name'])) {
            $data['name'] = $user->name;
        }

        $user->update($data);

        return back()->with('success', 'Perfil atualizado com sucesso!');
    }

    // =========================================================================
    // PUT /account/senha
    // =========================================================================

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Senha atual incorreta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Senha alterada com sucesso!');
    }

    // =========================================================================
    // GET /account/enderecos
    // =========================================================================

    public function addresses()
    {
        $user      = Auth::user();
        $addresses = $user->addresses()->latest()->get();

        return view('account.addresses', compact('user', 'addresses'));
    }

    // =========================================================================
    // POST /account/enderecos
    // =========================================================================

    public function storeAddress(Request $request)
    {
        $request->validate([
            'zipcode'      => ['required', 'string', 'max:10'],
            'street'       => ['required', 'string', 'max:255'],
            'number'       => ['required', 'string', 'max:20'],
            'complement'   => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:255'],
            'state'        => ['required', 'string', 'size:2'],
        ]);

        $user = Auth::user();

        if ($request->boolean('is_default')) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create([
            ...$request->only('zipcode', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'),
            'country'      => 'BR',
            'address_type' => 'both',
            'is_default'   => $request->boolean('is_default'),
        ]);

        return back()->with('success', 'Endereço adicionado!');
    }

    // =========================================================================
    // DELETE /account/enderecos/{id}
    // =========================================================================

    public function destroyAddress(int $id)
    {
        Auth::user()->addresses()->findOrFail($id)->delete();

        return back()->with('success', 'Endereço removido.');
    }
}