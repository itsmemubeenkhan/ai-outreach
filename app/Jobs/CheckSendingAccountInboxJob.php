<?php

namespace App\Jobs;

use App\Models\SendingAccount;
use App\Services\ReplyIngestionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

class CheckSendingAccountInboxJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $uniqueFor = 300;

    public int $timeout = 120;

    public function __construct(public int $sendingAccountId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->sendingAccountId;
    }

    public function handle(ReplyIngestionService $ingestion): void
    {
        $account = SendingAccount::find($this->sendingAccountId);
        if (! $account || $account->status !== 'active' || ! $account->imap_host || ! $account->imap_password) {
            return;
        }
        try {
            $manager = new ClientManager(['options' => ['fetch' => IMAP::FT_PEEK]]);
            $client = $manager->make(['host' => $account->imap_host, 'port' => $account->imap_port, 'encryption' => $account->imap_encryption === 'none' ? false : $account->imap_encryption, 'validate_cert' => true, 'username' => $account->imap_username, 'password' => $account->imap_password, 'protocol' => 'imap', 'timeout' => 15]);
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->since(now()->subDays(7))->get();
            $max = $account->imap_last_uid;
            foreach ($messages as $mail) {
                $uid = (int) $mail->getUid();
                if ($uid <= $account->imap_last_uid) {
                    continue;
                }$from = $mail->getFrom()->first();
                $to = $mail->getTo()->first();
                $ingestion->ingest($account, ['internet_message_id' => (string) $mail->getMessageId(), 'in_reply_to' => (string) $mail->getInReplyTo(), 'references_header' => (string) $mail->getReferences(), 'from_email' => $from?->mail ?? '', 'from_name' => $from?->personal, 'to_email' => $to?->mail ?? $account->email, 'subject' => (string) $mail->getSubject(), 'body_text' => (string) $mail->getTextBody(), 'body_html' => (string) $mail->getHTMLBody(), 'received_at' => $mail->getDate()?->toDate(), 'raw_metadata' => ['uid' => $uid, 'attachments' => $mail->getAttachments()->map(fn ($a) => ['name' => $a->getName(), 'mime' => $a->getMimeType(), 'size' => $a->getSize()])->all()]]);
                $max = max($max, $uid);
            }$client->disconnect();
            $account->update(['imap_last_uid' => $max, 'imap_last_checked_at' => now(), 'imap_last_error' => null]);
        } catch (Throwable $e) {
            $account->update(['imap_last_checked_at' => now(), 'imap_last_error' => 'Mailbox check failed. Verify IMAP credentials and provider access.']);
            throw $e;
        }
    }
}
