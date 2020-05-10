<?php
namespace BSperduto\ElasticaMessengerBundle\Persister;

use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use BSperduto\ElasticaMessengerBundle\Messages\MessengerPersisterNotification;

final class MessengerProcessor implements MessageHandlerInterface
{
    private $pagerProviderRegistry;

    private $pagerPersisterRegistry;

    public function __construct(
        PagerProviderRegistry $pagerProviderRegistry,
        PagerPersisterRegistry $pagerPersisterRegistry)
    {
        $this->pagerPersisterRegistry = $pagerPersisterRegistry;
        $this->pagerProviderRegistry = $pagerProviderRegistry;
    }

    public function process(MessengerPersisterNotification $message)
    {

        $data = $message->getContent();

        if (! isset($data['options'])) {
            throw new \LogicException('The message is invalid. Missing options.');
        }
        if (! isset($data['page'])) {
            throw new \LogicException('The message is invalid. Missing page.');
        }
        if (! isset($data['options']['indexName'])) {
            throw new \LogicException('The message is invalid. Missing indexName option.');
        }
        if (! isset($data['options']['typeName'])) {
            throw new \LogicException('The message is invalid. Missing typeName option.');
        }

        $options = $data['options'];
        $options['first_page'] = $data['page'];
        $options['last_page'] = $data['page'];

        $provider = $this->pagerProviderRegistry->getProvider($options['indexName'], $options['typeName']);
        $pager = $provider->provide($options);
        $pager->setMaxPerPage($options['max_per_page']);
        $pager->setCurrentPage($options['first_page']);

        $pagerPersister = $this->pagerPersisterRegistry->getPagerPersister(InPlacePagerPersister::NAME);
        $pagerPersister->insert($pager, $options);

        return true;
    }

}