<?php

namespace MakinaCorpus\Drupal\Layout\DependencyInjection\Compiler;

use MakinaCorpus\Layout\Type\ItemTypeInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers item types
 */
class ItemTypeRegisterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('php_layout.type_registry')) {
            return;
        }
        $definition = $container->getDefinition('php_layout.type_registry');

        // Register custom action providers
        $taggedServices = $container->findTaggedServiceIds('php_layout.type');
        foreach ($taggedServices as $id => $attributes) {
            $def = $container->getDefinition($id);

            $class = $container->getParameterBag()->resolveValue($def->getClass());
            $refClass = new \ReflectionClass($class);

            if (!$refClass->implementsInterface(ItemTypeInterface::class)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, ItemTypeInterface::class));
            }

            $definition->addMethodCall('registerType', [new Reference($id)]);
        }
    }
}