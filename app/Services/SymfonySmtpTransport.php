<?php

namespace App\Services;

use App\Contracts\OutboundTransport;
use App\Models\SendingAccount;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SymfonySmtpTransport implements OutboundTransport
{
    public function send(SendingAccount $account, string $recipient, string $subject, string $body, string $messageId): string
    {
        $transport = $this->transport($account);
        $email = (new Email)->from(new Address($account->email, $account->sender_name))->to($recipient)->subject($subject)->text($body);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        (new Mailer($transport))->send($email);
        $transport->stop();

        return $messageId;
    }

    public function testConnection(SendingAccount $account): void
    {
        $transport = $this->transport($account);
        $transport->start();
        $transport->stop();
    }

    private function transport(SendingAccount $account): EsmtpTransport
    {
        $tls = $account->smtp_encryption === 'ssl' ? true : ($account->smtp_encryption === 'none' ? false : null);
        $transport = new EsmtpTransport($account->smtp_host, $account->smtp_port, $tls);
        $transport->setAutoTls($account->smtp_encryption === 'tls');
        $transport->setUsername($account->smtp_username);
        $transport->setPassword($account->smtp_password);
        $transport->getStream()->setTimeout(10);

        return $transport;
    }
}
