<?php
namespace BSperduto\ElasticaMessengerBundle\Messages;

class DoctrineChangeNotification
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

