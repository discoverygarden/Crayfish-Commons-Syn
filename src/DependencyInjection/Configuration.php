<?php

namespace Islandora\Crayfish\Commons\Syn\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

  /**
   * {@inheritDoc}
   */
    public function getConfigTreeBuilder() : TreeBuilder
    {
        $treeBuilder = new TreeBuilder('crayfish_commons_syn');

        $treeBuilder->getRootNode()
        ->children()
          ->scalarNode('config_xml')->defaultValue(__DIR__ . '/../../assets/default_syn.xml')->end()
        ->end();

        return $treeBuilder;
    }
}
