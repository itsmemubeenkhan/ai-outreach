<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSendingAccountRequest;
use App\Models\SendingAccount;

class SendingAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('sending-accounts.index', ['accounts' => SendingAccount::where('user_id', auth()->id())->latest()->paginate(20)]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('sending-accounts.form', ['sendingAccount' => new SendingAccount]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSendingAccountRequest $request)
    {
        SendingAccount::create($request->validated() + ['user_id' => $request->user()->id]);

        return redirect()->route('sending-accounts.index')->with('success', 'Sending account saved. Test it before activation.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SendingAccount $sendingAccount)
    {
        $this->authorizeOwner($sendingAccount);

        return redirect()->route('sending-accounts.edit', $sendingAccount);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SendingAccount $sendingAccount)
    {
        $this->authorizeOwner($sendingAccount);

        return view('sending-accounts.form', compact('sendingAccount'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreSendingAccountRequest $request, SendingAccount $sendingAccount)
    {
        $this->authorizeOwner($sendingAccount);
        $data = $request->validated();
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        } if (empty($data['imap_password'])) {
            unset($data['imap_password']);
        }
        $sendingAccount->update($data);

        return redirect()->route('sending-accounts.index')->with('success', 'Sending account updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SendingAccount $sendingAccount)
    {
        $this->authorizeOwner($sendingAccount);
        $sendingAccount->delete();

        return back()->with('success', 'Sending account deleted.');
    }

    private function authorizeOwner(SendingAccount $account): void
    {
        abort_unless($account->user_id === auth()->id(), 403);
    }
}
