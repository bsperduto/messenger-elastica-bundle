<?php
namespace BSperduto\ElasticaMessengerBundle\Persister;

use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use BSperduto\ElasticaMessengerBundle\Messages\MessengerPersisterNotification;

final class MessengerProcessor implements MessageHandlerInterface
{
    private $pagerProviderRegistry;

    private $pagerPersisterRegistry;
    
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(PagerProviderRegistry $pagerProviderRegistry, PagerPersisterRegistry $pagerPersisterRegistry, EventDispatcherInterface $dispatcher)
    {
        $this->pagerPersisterRegistry = $pagerPersisterRegistry;
        $this->pagerProviderRegistry = $pagerProviderRegistry;
        $this->dispatcher = $dispatcher;
    }

    public function __invoke(MessengerPersisterNotification $message)
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

        $options = $data['options'];
        $options['first_page'] = $data['page'];
        $options['last_page'] = $data['page'];

        $provider = $this->pagerProviderRegistry->getProvider($options['indexName']);
        $pager = $provider->provide($options);
        $pager->setMaxPerPage($options['max_per_page']);
        $pager->setCurrentPage($options['first_page']);

        $pagerPersister = $this->pagerPersisterRegistry->getPagerPersister(InPlacePagerPersister::NAME);
        $pagerPersister->insert($pager, $options);
        
        //Cleanup resource leak with listeners getting stuck
        foreach($this->dispatcher->getListeners('elastica.pager_persister.post_insert_objects') as $listener) {
            $this->dispatcher->removeListener('elastica.pager_persister.post_insert_objects', $listener);
        }

        return true;
    }

}
