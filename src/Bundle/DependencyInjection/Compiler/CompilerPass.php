<?php

declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Bundle\DependencyInjection\Compiler;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tbessenreither\PhpMultithread\Service\MultithreadService;
use Tbessenreither\PhpMultithread\Service\MultithreadSignatureService;
use Tbessenreither\PhpMultithread\Service\ParallelExecService;
use Tbessenreither\PhpMultithread\Service\Runners\CommandRunner;
use Tbessenreither\PhpMultithread\Service\Runners\PcntlRunner;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;
use Throwable;

class CompilerPass implements CompilerPassInterface
{
    private const TEMPLATE_DIR = 'Templates';
    private const TEMPLATE_ALIAS = 'TbessenreitherPhpMultithread';

    public function process(ContainerBuilder $container): void
    {
        $this->processTwig($container);

        $this->registerAutowireTags($container);

        $this->processClass($container, classInstance: MultithreadService::class);
        $this->processClass($container, classInstance: PcntlRunner::class);
        $this->processClass($container, classInstance: CommandRunner::class);
        $this->processClass($container, classInstance: ParallelExecService::class);
        $this->processRuntimeAutowireService($container);
        $this->processClass($container, classInstance: MultithreadSignatureService::class);
    }

    private function processTwig(ContainerBuilder $container): void
    {
        if (!$container->has('twig')) {
            return;
        }

        $definition = $container->getDefinition('twig.loader.native_filesystem');

        $rootDir = $this->getRootDir();

        $definition->addMethodCall('addPath', [
            $rootDir . '/' . self::TEMPLATE_DIR,
            self::TEMPLATE_ALIAS,
        ]);
    }

    private function getRootDir(): string
    {
        return rtrim(dirname(__DIR__, 3), '/');
    }

    private function processClass(ContainerBuilder $container, string $classInstance): Definition
    {
        if (!$container->hasDefinition($classInstance)) {
            $definition = new Definition($classInstance);
            $definition->setAutowired(true);

            $definition->setAutoconfigured(true);
            $definition->setPublic(true);
            $container->setDefinition($classInstance, $definition);

            return $definition;
        } else {
            $definition = $container->getDefinition($classInstance);
            $definition->setPublic(true);
            return $definition;

        }
    }

    private function processRuntimeAutowireService(ContainerBuilder $container): Definition
    {
        $definition = $this->processClass($container, classInstance: RuntimeAutowireService::class);
        $services = [
            ParallelExecService::class => new Reference(ParallelExecService::class),
        ];

        foreach ($container->findTaggedServiceIds(RuntimeAutowireService::AUTOWIRE_TAG) as $id => $tags) {
            $services[$id] = new Reference($id);
        }

        $locator = new ServiceLocatorArgument($services);

        $definition->setArgument('$locator', $locator);

        return $definition;
    }

    /**
     * This method makes sure that all classes that have the AsTaggedItem attribute are properly tagged in the container and can be autowired at runtime.
     */
    private function registerAutowireTags(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            try {
                $class = $definition->getClass();
                if (!$class || !class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                $attributes = $reflection->getAttributes(AsTaggedItem::class);

                foreach ($attributes as $attr) {
                    $tag = $attr->newInstance()->index;
                    $definition->addTag($tag);
                }
            } catch(Throwable) {
                // Ignore any errors or we break the whole container compilation
            }
        }

    }

}
