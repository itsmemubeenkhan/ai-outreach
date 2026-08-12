<?php

namespace App\Services;

use App\Models\SendingAccount;
use RuntimeException;

class ImapConnectionTester
{
    public function test(SendingAccount $account): void
    {
        if (! $account->imap_host || ! $account->imap_port || ! $account->imap_username || ! $account->imap_password) {
            throw new RuntimeException('Complete the IMAP settings before testing.');
        }
        $scheme = $account->imap_encryption === 'ssl' ? 'ssl' : 'tcp';
        $error = '';
        $number = 0;
        $socket = @stream_socket_client("{$scheme}://{$account->imap_host}:{$account->imap_port}", $number, $error, 10, STREAM_CLIENT_CONNECT);
        if (! $socket) {
            throw new RuntimeException('Could not connect to the IMAP server. Check the host, port, encryption, and firewall.');
        }
        stream_set_timeout($socket, 10);
        $this->read($socket);
        if ($account->imap_encryption === 'tls') {
            fwrite($socket, "A001 STARTTLS\r\n");
            if (! str_contains($this->read($socket, 'A001'), 'A001 OK') || ! stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException('The IMAP server did not accept STARTTLS.');
            }
        }
        $user = $this->quote($account->imap_username);
        $password = $this->quote($account->imap_password);
        fwrite($socket, "A002 LOGIN {$user} {$password}\r\n");
        $response = $this->read($socket, 'A002');
        fwrite($socket, "A003 LOGOUT\r\n");
        fclose($socket);
        if (! str_contains($response, 'A002 OK')) {
            throw new RuntimeException('IMAP authentication failed. Check the username, password, and provider settings.');
        }
    }

    private function read($socket, ?string $tag = null): string
    {
        $response = '';
        while (! feof($socket)) {
            $line = fgets($socket, 8192);
            if ($line === false) {
                break;
            } $response .= $line;
            if ($tag === null || str_starts_with($line, $tag)) {
                break;
            }
        }

return $response;
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value,'\\"').'"';
    }
}
