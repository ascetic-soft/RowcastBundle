<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastBundle;

use AsceticSoft\RowcastBundle\DependencyInjection\RowcastExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RowcastBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new RowcastExtension();
    }
}
