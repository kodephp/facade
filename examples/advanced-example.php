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

echo "=== KodePHP Facade Advanced Example ===\n\n";

// Create a simple container
$container = new SimpleContainer();

// Bind the mailer service
$container->set('mailer', new SmtpMailer());

// Bind the facade to the service ID
FacadeProxy::bind(Examples\Mail\MailFacade::class, 'mailer');

// Set the container for the facade
MailFacade::setContainer($container);

// Demonstrate enhanced facade features
echo "1. Checking if facade is bound:\n";
$isBound = FacadeProxy::isBound(Examples\Mail\MailFacade::class);
echo "   MailFacade is bound: " . ($isBound ? 'yes' : 'no') . "\n";

echo "\n2. Getting service ID for facade:\n";
$serviceId = FacadeProxy::getServiceId(Examples\Mail\MailFacade::class);
echo "   Service ID: {$serviceId}\n";

echo "\n3. Checking if facade is resolved before calling methods:\n";
$isResolved = MailFacade::isResolved();
echo "   MailFacade is resolved: " . ($isResolved ? 'yes' : 'no') . "\n";

echo "\n4. Getting facade service ID:\n";
$facadeServiceId = MailFacade::getServiceId();
echo "   Facade service ID: {$facadeServiceId}\n";

echo "\n5. Sending email using the Mail facade:\n";
$result = MailFacade::send('user@example.com', 'Hello', 'This is a test email');
echo "   Email sent: " . ($result ? 'success' : 'failed') . "\n";

echo "\n6. Checking if facade is resolved after calling methods:\n";
$isResolved = MailFacade::isResolved();
echo "   MailFacade is resolved: " . ($isResolved ? 'yes' : 'no') . "\n";

echo "\n7. Getting driver info:\n";
$driver = MailFacade::getDriver();
echo "   Mailer driver: {$driver}\n";

echo "\n8. Getting all bindings:\n";
$bindings = FacadeProxy::getBindings();
foreach ($bindings as $facade => $id) {
    echo "   {$facade} => {$id}\n";
}

echo "\n9. Mocking the facade:\n";
// Mock the facade with a custom object
$mockMailer = new class implements Examples\Mail\MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "   [MOCK] Sending email to {$to} with subject '{$subject}'\n";
        return true;
    }
    
    public function getDriver(): string {
        return 'mock-driver';
    }
};

MailFacade::mock($mockMailer);

// Now calls will use the mock
echo "   Sending email using mocked facade:\n";
$result = MailFacade::send('mock@example.com', 'Mock Subject', 'Mock Body');
echo "   Mock email sent: " . ($result ? 'success' : 'failed') . "\n";

$driver = MailFacade::getDriver();
echo "   Mock driver: {$driver}\n";

echo "\n10. Clearing the facade instance:\n";
MailFacade::clear();
$isResolved = MailFacade::isResolved();
echo "    MailFacade is resolved after clear: " . ($isResolved ? 'yes' : 'no') . "\n";

echo "\nAdvanced example completed successfully!\n";