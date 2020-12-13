<?php
namespace BSperduto\ElasticaMessengerBundle\Doctrine;

use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use BSperduto\ElasticaMessengerBundle\Messages\DoctrineChangeNotification;

final class SyncIndexWithObjectChangeProcessor implements MessageHandlerInterface
{
    const INSERT_ACTION = 'insert';

    const UPDATE_ACTION = 'update';

    const REMOVE_ACTION = 'remove';

    private $persisterRegistry;

    private $indexable;

    private $doctrine;

    public function __construct(Registry $doctrine, PersisterRegistry $persisterRegistry, IndexableInterface $indexable)
    {
        $this->persisterRegistry = $persisterRegistry;
        $this->indexable = $indexable;
        $this->doctrine = $doctrine;
    }

    public function __invoke(DoctrineChangeNotification $message)
    {
        $data = $message->getContent();

        if (false == isset($data['action'])) {
            throw new \LogicException('The message data misses action');
        }
        if (false == isset($data['model_class'])) {
            throw new \LogicException('The message data misses model_class');
        }
        if (false == isset($data['id'])) {
            throw new \LogicException('The message data misses id');
        }
        if (false == isset($data['index_name'])) {
            throw new \LogicException('The message data misses index_name');
        }
        if (false == isset($data['type_name'])) {
            throw new \LogicException('The message data misses type_name');
        }
        if (false == isset($data['repository_method'])) {
            throw new \LogicException('The message data misses repository_method');
        }

        $action = $data['action'];
        $modelClass = $data['model_class'];
        $id = $data['id'];
        $index = $data['index_name'];
        $repositoryMethod = $data['repository_method'];

        $repository = $this->doctrine->getManagerForClass($modelClass)->getRepository($modelClass);
        $persister = $this->persisterRegistry->getPersister($index);

        switch ($action) {
            case self::UPDATE_ACTION:
                if (false == $object = $repository->{$repositoryMethod}($id)) {
                    $persister->deleteById($id);

                    return true;
                }

                if ($persister->handlesObject($object)) {
                    if ($this->indexable->isObjectIndexable($index, $object)) {
                        $persister->replaceOne($object);
                    } else {
                        $persister->deleteOne($object);
                    }
                }

                return true;
            case self::INSERT_ACTION:
                if (false == $object = $repository->{$repositoryMethod}($id)) {
                    $persister->deleteById($id);

                    return true;
                }

                if ($persister->handlesObject($object) && $this->indexable->isObjectIndexable($index, $object)) {
                    $persister->insertOne($object);
                }

                return true;
            case self::REMOVE_ACTION:
                $persister->deleteById($id);

                return true;
            default:
                throw new \LogicException(sprintf('The action "%s" is not supported', $action));
        }
    }
}
