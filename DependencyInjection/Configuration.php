<?php

namespace Pourquoi\PaymentBe2billBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This file is part of the RezzzaPaymentBe2billBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        return $treeBuilder
            ->root('pourquoi_payment_be2bill', 'array')
                ->children()
                    ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                    ->scalarNode('identifier')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('default_3ds_display_mode')
                        ->defaultValue('main')
                        ->validate()
                            ->ifNotInArray(array('main', 'popup', 'top'))
                            ->thenInvalid('Invalid 3d secure display mode "%s"')
                        ->end()
                    ->end()
					->scalarNode('version')->isRequired()->cannotBeEmpty()->end()
                ->end()
            ->end()
        ;
    }
}
