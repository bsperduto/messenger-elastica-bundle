<?php
namespace BSperduto\ElasticaMessengerBundle\Persister;

use FOS\ElasticaBundle\Persister\Event\PostPersistEvent;
use FOS\ElasticaBundle\Persister\Event\PrePersistEvent;
use FOS\ElasticaBundle\Persister\PagerPersisterInterface;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use BSperduto\ElasticaMessengerBundle\Messages\MessengerPersisterNotification;

final class MessengerPagerPersister implements PagerPersisterInterface
{
    const NAME = 'messenger';

    private $context;

    /**
     * @var PersisterRegistry
     */
    private $registry;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    
    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(MessageBusInterface $bus, PersisterRegistry $registry, EventDispatcherInterface $dispatcher)
    {
        $this->bus = $bus;
        $this->dispatcher = $dispatcher;
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(PagerInterface $pager, array $options = array())
    {
        $pager->setMaxPerPage(empty($options['max_per_page']) ? 100 : $options['max_per_page']);

        $options = array_replace([
            'max_per_page' => $pager->getMaxPerPage(),
            'first_page' => $pager->getCurrentPage(),
            'last_page' => $pager->getNbPages()
        ], $options);

        $pager->setCurrentPage($options['first_page']);

        $objectPersister = $this->registry->getPersister($options['indexName'], $options['typeName']);

        $event = new PrePersistEvent($pager, $objectPersister, $options);
        $this->dispatcher->dispatch($event);
        $pager = $event->getPager();
        $options = $event->getOptions();

        $lastPage = min($options['last_page'], $pager->getNbPages());
        $page = $pager->getCurrentPage();
        $sentCount = 0;
        do {
            $pager->setCurrentPage($page);

            $filteredOptions = $options;
            unset(
                $filteredOptions['first_page'],
                $filteredOptions['last_page']
            );

            $message = new MessengerPersisterNotification([
                'options' => $filteredOptions,
                'page' => $page,
            ]);

            $this->bus->dispatch($message);

            $page++;
            $sentCount++;
        } while ($page <= $lastPage);

        $event = new PostPersistEvent($pager, $objectPersister, $options);
        $this->dispatcher->dispatch($event);
    }
}
