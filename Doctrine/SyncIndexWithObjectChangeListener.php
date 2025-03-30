<?php
namespace BSperduto\ElasticaMessengerBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use BSperduto\ElasticaMessengerBundle\Doctrine\SyncIndexWithObjectChangeProcessor as SyncProcessor;
use BSperduto\ElasticaMessengerBundle\Messages\DoctrineChangeNotification;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postPersist, priority: 100)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 100)]
#[AsDoctrineListener(event: Events::preRemove, priority: 100)]
#[AsDoctrineListener(event: Events::postFlush, priority: 100)]
final class SyncIndexWithObjectChangeListener
{
    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @var array
     */
    private $scheduledForUpdateIndex = [];

    /**
     * @var array
     */
    private $config;

    public function __construct(MessageBusInterface $bus, $modelClass, array $config)
    {
        $this->bus = $bus;
        $this->modelClass = $modelClass;
        $this->config = $config;
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::UPDATE_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::INSERT_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if ($args->getObject() instanceof $this->modelClass) {
            $this->scheduledForUpdateIndex[] = [
                'action' => SyncProcessor::REMOVE_ACTION,
                'id'     => $this->extractId($args->getObject())
            ];
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (count($this->scheduledForUpdateIndex)) {
            foreach ($this->scheduledForUpdateIndex as $updateIndex) {
                $this->sendUpdateIndexMessage($updateIndex['action'], $updateIndex['id']);
            }

            $this->scheduledForUpdateIndex = [];
        }
    }

    /**
     * @param string $action
     * @param $id
     */
    private function sendUpdateIndexMessage($action, $id): void
    {
        $message = new DoctrineChangeNotification([
            'action' => $action,
            'model_class' => $this->modelClass,
            'model_id' => $this->config['model_id'],
            'id' => $id,
            'index_name' => $this->config['index_name'],
            'repository_method' => $this->config['repository_method'],
        ]);

        $this->bus->dispatch($message);
    }

    /**
     * @param $object
     * @return mixed
     * @throws \ReflectionException
     */
    private function extractId($object): mixed
    {
        $rp = (new \ReflectionClass($this->modelClass))->getProperty($this->config['model_id']);
        $rp->setAccessible(true);
        $id = $rp->getValue($object);
        $rp->setAccessible(false);

        return $id;
    }
}
