<?php

declare(strict_types=1);

namespace Tbessenreither\MultiLevelCache\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tbessenreither\PhpMultithread\Service\MultithreadService;


class CompilerPass implements CompilerPassInterface
{
	private const TEMPLATE_DIR = 'Templates';

	public function process(ContainerBuilder $container): void
	{
		if (!$container->has('twig')) {
			return;
		}

		$definition = $container->getDefinition('twig.loader.native_filesystem');

		$rootDir = $this->getRootDir();

		$definition->addMethodCall('addPath', [
			$rootDir . '/' . self::TEMPLATE_DIR,
			'TbessenreitherPhpMultithread',
		]);

		$this->processMultiLevelCacheFactory($container);
	}

	private function getRootDir(): string
	{
		return rtrim(dirname(__DIR__, 3), '/');
	}

	private function processMultiLevelCacheFactory(ContainerBuilder $container): void
	{
		if (!$container->hasDefinition(MultithreadService::class)) {
			$definition = new Definition(MultithreadService::class);
			$definition->setAutowired(true);
			$definition->setAutoconfigured(true);
			$definition->setPublic(true);
			$container->setDefinition(MultithreadService::class, $definition);
		} else {
			$container->getDefinition(MultithreadService::class)->setPublic(true);
		}
	}

}
