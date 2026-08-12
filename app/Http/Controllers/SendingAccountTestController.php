<?php

namespace App\Http\Controllers;

use App\Contracts\OutboundTransport;
use App\Models\SendingAccount;
use App\Services\ImapConnectionTester;
use App\Services\SymfonySmtpTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class SendingAccountTestController extends Controller
{
    public function smtp(SendingAccount $sendingAccount, SymfonySmtpTransport $mailer)
    {
        return $this->run($sendingAccount, fn () => $mailer->testConnection($sendingAccount), 'SMTP connection succeeded.');
    }

    public function imap(SendingAccount $sendingAccount, ImapConnectionTester $tester)
    {
        return $this->run($sendingAccount, fn () => $tester->test($sendingAccount), 'IMAP connection succeeded.');
    }

    public function send(Request $request, SendingAccount $sendingAccount, OutboundTransport $mailer)
    {
        $request->validate(['recipient' => ['required', 'email', 'max:255']]);

        return $this->run($sendingAccount, fn () => $mailer->send($sendingAccount, $request->string('recipient'), 'AI Outreach CRM test email', 'This is a one-time SMTP test from AI Outreach CRM. No campaign was started.', Str::uuid().'@ai-outreach.local'), 'Test email sent.');
    }

    private function run(SendingAccount $account, callable $operation, string $success)
    {
        abort_unless($account->user_id === auth()->id(), 403);
        try {
            $operation();
            $account->update(['last_error' => null]);

            return back()->with('success', $success);
        } catch (Throwable $e) {
            $message = $this->safeMessage($e);
            $account->update(['last_error' => $message, 'status' => $this->authenticationFailure($e) ? 'error' : $account->status]);

            return back()->withErrors(['connection' => $message]);
        }
    }

    private function authenticationFailure(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'auth');
    }

    private function safeMessage(Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'auth')) {
            return 'Authentication failed. Check the username, password, and provider permissions.';
        }
        if (str_contains($message, 'timed out') || str_contains($message,'timeout')) {
            return 'The connection timed out. Check the host, port, encryption, and firewall.';
        }

        return 'Connection failed. Check the server settings and provider requirements.';
    }
}
