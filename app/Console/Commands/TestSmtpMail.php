<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSmtpMail extends Command
{
    protected $signature = 'mail:test {recipient? : Test recipient email} {--check : Validate configuration without sending}';
    protected $description = 'Check Gmail SMTP configuration or send one test email without printing credentials';

    public function handle(): int
    {
        $smtp = config('mail.mailers.smtp');
        $username = $smtp['username'] ?? '';
        $password = $smtp['password'] ?? '';
        $from = config('mail.from.address');
        if (config('mail.default') !== 'smtp' || ($smtp['host'] ?? '') !== 'smtp.gmail.com'
            || (int) ($smtp['port'] ?? 0) !== 587 || ($smtp['scheme'] ?? '') !== 'smtp'
            || !($smtp['require_tls'] ?? false) || !empty($smtp['url'])) {
            $this->error('Invalid Gmail SMTP settings. Use smtp.gmail.com, port 587, smtp scheme, TLS, and no MAIL_URL override.');
            return self::FAILURE;
        }
        if (!filter_var($username, FILTER_VALIDATE_EMAIL) || $username !== $from || !$password) {
            $this->error('Enter your Gmail in MAIL_USERNAME and MAIL_FROM_ADDRESS, and a new Google App Password in MAIL_PASSWORD, directly in .env. Then run php artisan config:clear.');
            return self::FAILURE;
        }
        if ($this->option('check')) {
            $this->info('Local configuration is valid. SMTP authentication and delivery have not been tested.');
            return self::SUCCESS;
        }
        $recipient = $this->argument('recipient') ?: $this->ask('Test recipient email');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid recipient email address.');
            return self::FAILURE;
        }
        try {
            Mail::mailer('smtp')->raw('AESTHETIC SMTP test. Please confirm that you received this message.', function ($message) use ($recipient) {
                $message->to($recipient)->subject('AESTHETIC email delivery test');
            });
        } catch (\Throwable $error) {
            // Classify privately; never print or log transport exceptions or SMTP transcripts.
            $detail = strtolower($error->getMessage());
            $reason = match (true) {
                str_contains($detail, '535'), str_contains($detail, 'authenticate'), str_contains($detail, 'credentials') => 'Gmail authentication failed. Check the new App Password and Gmail username in .env.',
                str_contains($detail, 'certificate'), str_contains($detail, 'starttls'), str_contains($detail, 'crypto') => 'TLS negotiation failed. Check the PHP CA certificates and OpenSSL configuration.',
                str_contains($detail, 'timed out'), str_contains($detail, 'connection'), str_contains($detail, 'getaddrinfo') => 'SMTP connection failed. Check DNS, network access, and outbound port 587.',
                default => 'SMTP rejected the test or sending failed. Check Gmail SMTP settings and recipient address.',
            };
            $this->error($reason);
            return self::FAILURE;
        }
        $this->info('SMTP accepted the test email. Delivery is not confirmed until the recipient checks their inbox or spam folder.');
        return self::SUCCESS;
    }
}
