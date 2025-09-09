<?php
declare(strict_types=1);

namespace Nova\Support\Facades;

use Nova\Facade as BaseFacade;

/**
 * Compatibility facade class to allow importing Nova\Support\Facades\Facade
 * like Laravel/Lumen style: use Nova\Support\Facades\Facade as Foo;
 */
abstract class Facade extends BaseFacade {}