<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ChainList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ChainListController extends Controller
{
    public function index()
    {
        $chains = ChainList::latest()->paginate(10);
        return view('admin.token.chain', compact('chains'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'chain_name' => 'required|string|max:50',
            'chain_id' => 'required|string|max:20',
            'chain_rpc_url' => 'nullable|string|max:255',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $iconName = null;
        if ($request->hasFile('icon')) {
            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $icon->move(public_path('uploads/chain_icons'), $iconName);
        }

        ChainList::create([
            'chain_name' => $request->chain_name,
            'chain_id' => $request->chain_id,
            'chain_rpc_url' => $request->chain_rpc_url,
            'icon' => $iconName,
            'status' => $request->status ? 1 : 0,
        ]);

        return back()->with('success', 'Chain added successfully!');
    }

    public function update(Request $request, $id)
    {
        $chain = ChainList::findOrFail($id);

        $request->validate([
            'chain_name' => 'required|string|max:50',
            'chain_id' => 'required|string|max:20',
            'chain_rpc_url' => 'nullable|string|max:255',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($chain->icon && File::exists(public_path('uploads/chain_icons/' . $chain->icon))) {
                File::delete(public_path('uploads/chain_icons/' . $chain->icon));
            }

            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $icon->move(public_path('uploads/chain_icons'), $iconName);
            $chain->icon = $iconName;
        }

        $chain->update([
            'chain_name' => $request->chain_name,
            'chain_id' => $request->chain_id,
            'chain_rpc_url' => $request->chain_rpc_url,
            'status' => $request->status ? 1 : 0,
        ]);

        return back()->with('success', 'Chain updated successfully!');
    }

    public function destroy($id)
    {
        $chain = ChainList::findOrFail($id);

        if ($chain->icon && File::exists(public_path('uploads/chain_icons/' . $chain->icon))) {
            File::delete(public_path('uploads/chain_icons/' . $chain->icon));
        }

        $chain->delete();
        return back()->with('success', 'Chain deleted successfully!');
    }
}
