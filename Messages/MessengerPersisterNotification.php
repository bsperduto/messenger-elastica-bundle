<?php
namespace BSperduto\ElasticaMessengerBundle\Messages;

class MessengerPersisterNotification
{
    
    /**
     * @var Array
     */
    private $content;

    public function __construct(Array $content)
    {
        $this->content = $content;
    }
    
    /**
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }
}

