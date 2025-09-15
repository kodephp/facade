<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SimpleContainer.php';
require_once __DIR__ . '/Mail/MailerInterface.php';
require_once __DIR__ . '/Mail/SmtpMailer.php';
require_once __DIR__ . '/Mail/MailFacade.php';

use Examples\SimpleContainer;
use Examples\Mail\MailFacade;
use Examples\Mail\SmtpMailer;
use Kode\Facade\FacadeProxy;
use Psr\Container\ContainerInterface;

// Create a simple container
$container = new SimpleContainer();

// Bind the mailer service
$container->set('mailer', new SmtpMailer());

// Bind the facade to the service ID
FacadeProxy::bind(Examples\Mail\MailFacade::class, 'mailer');

// Set the container for the facade
MailFacade::setContainer($container);

// Now we can use the facade statically
echo "Sending email using the Mail facade:\n";
$result = MailFacade::send('user@example.com', 'Hello', 'This is a test email');
echo "Email sent: " . ($result ? 'success' : 'failed') . "\n";

echo "\nGetting driver info:\n";
$driver = MailFacade::getDriver();
echo "Mailer driver: {$driver}\n";

echo "\nExample completed successfully!\n";