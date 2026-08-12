<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuppressionRequest;
use App\Models\Suppression;

class SuppressionController extends Controller
{
    public function index()
    {
        return view('suppressions.index', ['suppressions' => Suppression::latest()->paginate(25)]);
    }

    public function store(StoreSuppressionRequest $request)
    {
        $data = $request->validated();
        $data['email'] = Suppression::normalize($data['email']);
        Suppression::create($data);

        return back()->with('success', 'Email suppressed.');
    }

    public function destroy(Suppression $suppression)
    {
        $suppression->delete();

        return back()->with('success', 'Suppression removed.');
    }
}
