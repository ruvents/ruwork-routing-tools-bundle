<?php

declare(strict_types=1);

namespace Ruwork\RoutingToolsBundle\Tests;

use Ruwork\BundleTestCase\AbstractBundleTestCase;
use Ruwork\RouteOptionalPrefix\LoaderDecorator;
use Ruwork\RouteOptionalPrefix\RouterDecorator;
use Ruwork\RoutingToolsBundle\DependencyInjection\Compiler\ReplaceBridgeRoutingExtensionPass;
use Ruwork\RoutingToolsBundle\DependencyInjection\Compiler\RouteOptionalPrefixPass;
use Ruwork\RoutingToolsBundle\RuworkRoutingToolsBundle;
use Ruwork\RoutingToolsBundle\Twig\BridgeRoutingExtension;
use Ruwork\RoutingToolsBundle\Twig\RoutingHelpersExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\Router;

class RuworkRoutingToolsBundleTest extends AbstractBundleTestCase
{
    public function testCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        $container->getCompilerPassConfig()->addPass(new ReplaceBridgeRoutingExtensionPass());
        $container->getCompilerPassConfig()->addPass(new RouteOptionalPrefixPass());

        $this->assertContainerCompiles($container);
    }

    public function testOptionalPrefixDefault(): void
    {
        $this->compile();
        $container = $this->getContainer();

        $this->assertInstanceOf(RouterDecorator::class, $container->get('router'));
        $this->assertInstanceOf(LoaderDecorator::class, $container->get('routing.loader'));
        $this->assertSame(BridgeRoutingExtension::class, get_class($container->get('twig.extension.routing')));
        $this->assertContainerHasService(RoutingHelpersExtension::class);
    }

    public function testOptionalPrefixFalse(): void
    {
        $this->compile([
            'optional_prefix' => false,
            'twig' => [
                'object_as_parameters' => false,
                'routing_helpers' => false,
            ],
        ]);
        $container = $this->getContainer();

        $this->assertInstanceOf(Router::class, $container->get('router'));
        $this->assertInstanceOf(PhpFileLoader::class, $container->get('routing.loader'));
        $this->assertSame(RoutingExtension::class, get_class($container->get('twig.extension.routing')));
        $this->assertContainerNotHasService(RoutingHelpersExtension::class);
    }

    protected function setUpContainer(ContainerBuilder $container): void
    {
        $container->register(FileLocator::class);

        $container->register('request_stack', RequestStack::class);

        $container
            ->register('routing.loader', PhpFileLoader::class)
            ->setPublic(true)
            ->setArguments([
                '$locator' => new Reference(FileLocator::class),
            ]);

        $container
            ->register('router', Router::class)
            ->setPublic(true)
            ->setArguments([
                '$loader' => new Reference('routing.loader'),
                '$resource' => 'resource',
            ]);

        $container
            ->register('twig.extension.routing', RoutingExtension::class)
            ->setPublic(true)
            ->setArguments([
                '$generator' => new Reference('router'),
            ]);

        $container->register('property_accessor', PropertyAccessor::class)
            ->setPublic(true);

        $this->exposeService(RoutingHelpersExtension::class);
    }

    protected function getBundle(): BundleInterface
    {
        return new RuworkRoutingToolsBundle();
    }
}