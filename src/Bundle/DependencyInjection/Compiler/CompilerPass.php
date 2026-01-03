<?php

declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tbessenreither\PhpMultithread\Service\MultithreadService;
use Tbessenreither\PhpMultithread\Service\MultithreadSignatureService;
use Tbessenreither\PhpMultithread\Service\Runners\CommandRunner;
use Tbessenreither\PhpMultithread\Service\Runners\PcntlRunner;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;


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

		$this->processClass($container, classInstance: MultithreadService::class);
		$this->processClass($container, classInstance: PcntlRunner::class);
		$this->processClass($container, classInstance: CommandRunner::class);
		$this->processClass($container, classInstance: RuntimeAutowireService::class);
		$this->processClass($container, classInstance: MultithreadSignatureService::class);
	}

	private function getRootDir(): string
	{
		return rtrim(dirname(__DIR__, 3), '/');
	}

	private function processClass(ContainerBuilder $container, string $classInstance): void
	{
		if (!$container->hasDefinition($classInstance)) {
			$definition = new Definition($classInstance);
			$definition->setAutowired(true);

			$definition->setAutoconfigured(true);
			$definition->setPublic(true);
			$container->setDefinition($classInstance, $definition);
		} else {
			$container->getDefinition($classInstance)->setPublic(true);
		}
	}

}
