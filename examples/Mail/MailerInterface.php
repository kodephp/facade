<?php

declare(strict_types=1);

namespace Examples\Mail;

interface MailerInterface
{
    /**
     * Send an email
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool;

    /**
     * Get the mailer driver name
     *
     * @return string
     */
    public function getDriver(): string;
}