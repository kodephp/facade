<?php

declare(strict_types=1);

namespace Examples\Mail;

use Kode\Facade\Facade;

/**
 * Mail Facade
 *
 * @method static bool send(string $to, string $subject, string $body)
 * @method static string getDriver()
 * 
 * @see \Examples\Mail\MailerInterface
 */
class MailFacade extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function id(): string
    {
        return 'mailer';
    }
}