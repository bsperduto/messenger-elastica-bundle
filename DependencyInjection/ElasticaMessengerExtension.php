<?php

namespace BSperduto\ElasticaMessengerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use BSperduto\ElasticaMessengerBundle\Persister\MessengerProcessor;
use BSperduto\ElasticaMessengerBundle\Persister\MessengerPagerPersister;
use BSperduto\ElasticaMessengerBundle\Doctrine\SyncIndexWithObjectChangeProcessor;
use BSperduto\ElasticaMessengerBundle\Doctrine\SyncIndexWithObjectChangeListener;

class ElasticaMessengerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        if (!$config['enabled']) {
            return;
        }
        
        $bus = $container->getParameterBag()->resolveValue($config['message_bus']);

        $container->setAlias('elastica_messenger.bus', $bus);

        $container->register('elastica_messenger.messenger_processor', MessengerProcessor::class)
            ->addArgument(new Reference('fos_elastica.pager_provider_registry'))
            ->addArgument(new Reference('fos_elastica.pager_persister_registry'))
            ->addTag('messenger.message_handler')
        ;

        $container->register('elastica_messenger.queue_pager_perister', MessengerPagerPersister::class)
            ->addArgument(new Reference('elastica_messenger.bus'))
            ->addArgument(new Reference('fos_elastica.persister_registry'))
            ->addArgument(new Reference('event_dispatcher'))

            ->addTag('fos_elastica.pager_persister', ['persisterName' => 'messenger'])
            ->setPublic(true)
        ;

        if (false == empty($config['doctrine']['queue_listeners'])) {
            $doctrineDriver = $config['doctrine']['driver'];

            $container->register('elastica_messenger.doctrine.sync_index_with_object_change_processor', SyncIndexWithObjectChangeProcessor::class)
                ->addArgument(new Reference($this->getManagerRegistry($doctrineDriver)))
                ->addArgument(new Reference('fos_elastica.persister_registry'))
                ->addArgument(new Reference('fos_elastica.indexable'))
                ->addTag('messenger.message_handler')
            ;

            foreach ($config['doctrine']['queue_listeners'] as $listenerConfig) {
                $listenerId = sprintf(
                    'elastica_messenger.doctrine_queue_listener.%s.%s',
                    $listenerConfig['index_name'],
                    $listenerConfig['type_name']
                );

                $container->register($listenerId, SyncIndexWithObjectChangeListener::class)
                    ->setPublic(true)
                    ->addArgument(new Reference('elastica_messenger.bus'))
                    ->addArgument($listenerConfig['model_class'])
                    ->addArgument($listenerConfig)
                    ->addTag($this->getEventSubscriber($doctrineDriver), ['connection' => $listenerConfig['connection']])
                ;
            }
        }
    }

    private function getManagerRegistry(string $driver): string
    {
        switch ($driver) {
            case 'mongodb':
                return 'doctrine_mongodb';
                break;
            case 'orm':
            default:
                return 'doctrine';
        }
    }

    private function getEventSubscriber(string $driver): string
    {
        switch ($driver) {
            case 'mongodb':
                return 'doctrine_mongodb.odm.event_subscriber';
                break;
            case 'orm':
            default:
                return 'doctrine.event_subscriber';
        }
    }
}
