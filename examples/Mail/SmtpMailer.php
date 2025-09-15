<?php

declare(strict_types=1);

namespace Examples\Mail;

class SmtpMailer implements MailerInterface
{
    /**
     * @inheritDoc
     */
    public function send(string $to, string $subject, string $body): bool
    {
        // In a real implementation, this would send an email via SMTP
        echo "Sending email to {$to} with subject '{$subject}'\n";
        echo "Body: {$body}\n";
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): string
    {
        return 'smtp';
    }
}