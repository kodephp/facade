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
use Kode\Facade\Facade;

echo "=== KodePHP Facade Complete Example ===\n\n";

// Create a simple container
$container = new SimpleContainer();

// Bind the mailer service
$container->set('mailer', new SmtpMailer());

// Bind the facade to the service ID
FacadeProxy::bind(Examples\Mail\MailFacade::class, 'mailer');

// Set the container for the facade
MailFacade::setContainer($container);

echo "=== Enhanced Facade Features ===\n";

// 1. Check if facade is bound
echo "1. Checking facade binding:\n";
$isBound = FacadeProxy::isBound(Examples\Mail\MailFacade::class);
echo "   Is MailFacade bound? " . ($isBound ? 'Yes' : 'No') . "\n";

// 2. Get service ID
echo "\n2. Getting service ID:\n";
$serviceId = FacadeProxy::getServiceId(Examples\Mail\MailFacade::class);
echo "   Service ID: {$serviceId}\n";

// 3. Check if facade is resolved
echo "\n3. Checking if facade is resolved:\n";
$isResolved = MailFacade::isResolved();
echo "   Is MailFacade resolved? " . ($isResolved ? 'Yes' : 'No') . "\n";

// 4. Get facade service ID
echo "\n4. Getting facade service ID:\n";
$facadeServiceId = MailFacade::getServiceId();
echo "   Facade service ID: {$facadeServiceId}\n";

// 5. Check if methods exist
echo "\n5. Checking method existence:\n";
$hasSend = MailFacade::hasMethod('send');
$hasGetDriver = MailFacade::hasMethod('getDriver');
$hasNonExistent = MailFacade::hasMethod('nonExistentMethod');
echo "   Has 'send' method? " . ($hasSend ? 'Yes' : 'No') . "\n";
echo "   Has 'getDriver' method? " . ($hasGetDriver ? 'Yes' : 'No') . "\n";
echo "   Has 'nonExistentMethod' method? " . ($hasNonExistent ? 'Yes' : 'No') . "\n";

echo "\n=== Using Facade Methods ===\n";

// 6. Send email using magic method
echo "6. Sending email using magic method:\n";
$result = MailFacade::send('user@example.com', 'Hello', 'This is a test email');
echo "   Result: " . ($result ? 'Success' : 'Failed') . "\n";

// 7. Get driver using magic method
echo "\n7. Getting driver using magic method:\n";
$driver = MailFacade::getDriver();
echo "   Driver: {$driver}\n";

// 8. Check if facade is now resolved
echo "\n8. Checking if facade is resolved after method calls:\n";
$isResolved = MailFacade::isResolved();
echo "   Is MailFacade resolved? " . ($isResolved ? 'Yes' : 'No') . "\n";

// 9. Use call method
echo "\n9. Sending email using call method:\n";
$result = MailFacade::call('send', ['another@example.com', 'Test Subject', 'Test Body']);
echo "   Result: " . ($result ? 'Success' : 'Failed') . "\n";

echo "\n=== Proxy Management ===\n";

// 10. Get all bindings
echo "10. Getting all bindings:\n";
$bindings = FacadeProxy::getBindings();
foreach ($bindings as $facade => $id) {
    echo "    {$facade} => {$id}\n";
}

echo "\n=== Mocking ===\n";

// 11. Mock the facade
echo "11. Mocking the facade:\n";
$mockMailer = new class implements Examples\Mail\MailerInterface {
    public function send(string $to, string $subject, string $body): bool {
        echo "    [MOCK] Sending email to {$to} with subject '{$subject}'\n";
        return true;
    }
    
    public function getDriver(): string {
        return 'mock-driver';
    }
};

MailFacade::mock($mockMailer);

echo "    Sending email using mocked facade:\n";
$result = MailFacade::send('mock@example.com', 'Mock Subject', 'Mock Body');
echo "    Result: " . ($result ? 'Success' : 'Failed') . "\n";

$driver = MailFacade::getDriver();
echo "    Mock driver: {$driver}\n";

echo "\n=== Cleanup ===\n";

// 12. Clear the facade instance
echo "12. Clearing the facade instance:\n";
MailFacade::clear();
$isResolved = MailFacade::isResolved();
echo "    Is MailFacade resolved after clear? " . ($isResolved ? 'Yes' : 'No') . "\n";

// 13. Clear all instances
echo "\n13. Clearing all instances:\n";
Facade::clearAll();
echo "    All instances cleared\n";

echo "\n=== Error Handling ===\n";

// 14. Test calling undefined method
echo "14. Testing error handling for undefined method:\n";
try {
    MailFacade::undefinedMethod();
} catch (Exception $e) {
    echo "    Caught exception: " . $e->getMessage() . "\n";
}

echo "\nComplete example finished successfully!\n";