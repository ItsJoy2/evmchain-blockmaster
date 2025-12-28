<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\TokenList;
use App\Models\ChainList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TokenListController extends Controller
{
    public function index()
    {
        $tokens = TokenList::with('chain')->latest()->paginate(10);
        $chains = ChainList::all();
        return view('admin.token.index', compact('tokens', 'chains'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'chain_id' => 'required|exists:chain_list,id',
            'token_name' => 'required|string|max:40',
            'symbol' => 'required|string|max:15',
            'contract_address' => 'required|string|max:100',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $iconName = null;
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $icon->move(public_path('uploads/token_icons'), $iconName);
        }

        TokenList::create([
            'chain_id' => $request->chain_id,
            'token_name' => $request->token_name,
            'symbol' => $request->symbol,
            'contract_address' => $request->contract_address,
            'icon' => $iconName,
            'status' => $request->status ? 1 : 0,
        ]);

        return back()->with('success', 'Token added successfully!');
    }

    public function update(Request $request, $id)
    {
        $token = TokenList::findOrFail($id);

        $request->validate([
            'chain_id' => 'required|exists:chain_list,id',
            'token_name' => 'required|string|max:40',
            'symbol' => 'required|string|max:15',
            'contract_address' => 'required|string|max:100',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($token->icon && File::exists(public_path('uploads/token_icons/' . $token->icon))) {
                File::delete(public_path('uploads/token_icons/' . $token->icon));
            }

            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $icon->move(public_path('uploads/token_icons'), $iconName);
            $token->icon = $iconName;
        }

        $token->update([
            'chain_id' => $request->chain_id,
            'token_name' => $request->token_name,
            'symbol' => $request->symbol,
            'contract_address' => $request->contract_address,
            'status' => $request->status ? 1 : 0,
        ]);

        return back()->with('success', 'Token updated successfully!');
    }

    public function destroy($id)
    {
        $token = TokenList::findOrFail($id);

        if ($token->icon && File::exists(public_path('uploads/token_icons/' . $token->icon))) {
            File::delete(public_path('uploads/token_icons/' . $token->icon));
        }

        $token->delete();
        return back()->with('success', 'Token deleted successfully!');
    }
}
