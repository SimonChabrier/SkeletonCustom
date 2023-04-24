<?php

namespace App\MessageHandler;

use Predis\Client;
use Psr\Log\LoggerInterface;
use App\Message\RedisMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class RedisMessageHandler implements MessageHandlerInterface
{
    private $logger;
    private $redis;
    private $entityManager;

    public function __construct(MessageBusInterface $bus, 
            LoggerInterface $logger, 
            Client $redis,
            EntityManagerInterface $entityManager
    )
    {
        $this->logger = $logger;
        $this->redis = $redis;
        $this->entityManager = $entityManager;
    }

    public function __invoke(RedisMessage $message)
    {   

        // afficher le retour dans le terminal
        $this->logger->info('Received message from Redis: ' . $message->getChannel() . ' - ' . $message->getPayload());
        // sauvegarder le message dans la base de données
        
        $test = new \App\Entity\Message();
        $test->setContent($message->getPayload());
        $test->setChannel($message->getChannel());
        $this->entityManager->persist($test);
        $this->entityManager->flush();

        // envoyer le message à la file d'attente Redis
        return $this->redis->publish($message->getChannel(), $message->getPayload());
    }
}
