<?php

namespace App\Controller;

use Predis\Client;
use Predis\PubSub\Consumer;
use App\Message\RedisMessage;
use App\Repository\MessageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RedisController extends AbstractController
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @Route("/redis", name="app_redis")
     */
    public function index(): Response
    {
        try {
            $this->redis->ping();
            $message = 'Redis connection successful!';
        } catch (\Exception $e) {
            $message = 'Redis connection failed: ' . $e->getMessage();
        }

        return new Response($message);
    }

    /**
     * Set a key / value pair in Redis
     * @Route("/redis/set", name="app_redis_set")
     */
    public function setKey(): Response
    {
        $this->redis->set('foo', 'bar');
        $message = 'Redis set successful!';

        return new Response($message);
    }

    /**
     * Get a key / value pair from Redis
     * @Route("/redis/get", name="app_redis_get")
     */
    public function getKey(): Response
    {
        $value = $this->redis->get('foo');
        if (!$value) {
            $value = 'Aucune valeur pour la clé "foo" elle a été supprimée ou n\'a jamais existé';
        }

        return new Response($value);
    }

    /**
     * Delete a key / value pair from Redis
     * @Route("/redis/del", name="app_redis_del")
     */
    public function delKey(): Response
    {
        $this->redis->del('foo');
        $message = 'Redis del successful!';

        return new Response($message);
    }

    /**
     * Create new Canal in Redis
     * @Route("/redis/channel/create/{id}", name="app_redis_canal")
     */
    public function createChannel($id): Response
    {
        $key = 'channel-' . $id;

        $this->redis->sadd('channel', $key);
        $message = 'Redis canal created! ' . $key;

        return new Response(json_encode($message));
    }

    /**
     * Get all Canals data in Redis
     * @Route("/redis/channel/list", name="app_redis_canal_get")
     */
    public function getAllChannels(): Response
    {
        // on récupère tous les canaux
        $channels = $this->redis->smembers('channel');

        // si la liste des canaux est vide
        if (empty($channels)) {
            $message = 'Aucun canal créé actuellement';
        } else {
            // on affiche les messages par canal dans une liste html et on injecte le code dans la réponse
            $messageList = '<ul>';
            foreach ($channels as $channel) {
                $messages = $this->redis->lrange($channel, 0, -1);
                foreach ($messages as $message) {
                    $messageList .= '<li>' . $channel . ' : ' . $message . '</li>';
                }
            }
            $messageList .= '</ul>';
            $message = 'Canaux et messages : ' . $messageList;
        }

        return new Response($message);
    }

    /**
     * Publish a message to a channel in Redis
     * @Route("/redis/publish/{id}", name="app_redis_publish", methods={"GET", "POST"})
     */
    public function publish($id, MessageBusInterface $bus, Request $request): Response
    {
        $messages = [];
        $errors = [];

        // if ($request->isMethod('GET')) {
        //     return $this->render('redis/index.html.twig', [
        //         'id' => $id,
        //     ]);
        // }
        if($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $formMessage = $data['message'];

        } else {
            $formMessage = 'Pulication persistée dans la BDD de Redis - ok';
        }

        try {
            // test de la connexion à Redis
            // $this->redis->ping();
            // $messages[] = 'Connection à Redis - ok';

            // publish envoi le message à tous les subscribers du canal
            //$this->redis->publish('channel-' . $id , 'Publication directe dans le canal ' . $id . '!');
            // $this->redis->publish('channel-' . $id, $formMessage);
            // $messages[] = 'Publication directe sur un canal Redis - ok';

            // dispatch avec messenger pour utiliser l'async
            $bus->dispatch(new RedisMessage('channel-' . $id, $formMessage));
            $messages[] = 'Dispatch sur Messenger - ok';

            // // lpush persiste le message dans la bdd redis
            // $this->redis->lpush('channel-' . $id, $formMessage);
            // //$messages[] = 'Persisté dans la BDD de Redis - ok';
            // $messages[] = $formMessage;


        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        $response = '<h3>Résultats du traitement :</h3><ol>';

        foreach ($messages as $message) {
            $response .= '<li>' . $message . '</li>';
        }

        if (!empty($errors)) {
            $response .= '<li>Erreurs : ' . implode(', ', $errors) . '</li>';
        }

        $response .= '</ol>';

        return new Response($response);
    }


    /**
     * Subscribe to a channel in Redis in raw
     * @Route("/redis/channel/subscribe/raw", name="app_redis_subscribe_raw")
     */
    public function subscribeRaw(): Response
    {

        dump($this->redis->executeRaw(['SMEMBERS', 'channel']));
        $channels = $this->redis->executeRaw(['SMEMBERS', 'channel']);
        // subscribe to a channel in redis
        try {
            $this->redis->ping();
            $message = 'Redis connection successful!';
        } catch (\Exception $e) {
            $message = 'Redis connection failed: ' . $e->getMessage();
        }

        $result = [];

        foreach ($channels as $channel) {
            $result[] = $this->redis->executeRaw(['SUBSCRIBE', $channel]);
        }

        $message = 'Redis Result: ' . ' - ' . json_encode($result) . ' - ' . $message;

        return new Response($message);
    }

    /**
     * Subscribe to a channel in Redis
     *
     * @Route("/redis/channel/subscribe/{id}", name="app_redis_subscribe")
     */
    public function subscribe($id): Response
    {
        // subscribe to a channel in redis
        $consumer = new Consumer($this->redis);
        $consumer->getClient()->connect();
        $consumer->getClient()->getCommandFactory();

        try {
            $consumer->subscribe('channel-' . $id);
        } catch (\Exception $e) {
            return new Response('Redis connection failed: ' . $e->getMessage());
        }

        return new Response('Redis subscribe successful sur ' . 'channel-' . $id . ' !');
    }

    /**
     * Consume a channel in Redis
     * @Route("/redis/consume/{id}", name="app_redis_consume", methods={"GET"})
     */
    public function pubSub($id): Response
    {
        // je crée un nouveau contexte pubsub en utilisant la class AbstractConsumer
        // et la méthode pubSubLoop sur mon client redis
        $pubsub = $this->redis->pubSubLoop();
        // je m'abonne au canal
        $pubsub->subscribe('channel-' . $id);
        // je boucle sur les messages reçus
        foreach ($pubsub as $message) {
            // je vérifie que le message est bien un message
            if($message->kind === 'message') {
                // j'annule l'abonnement au canal pour ne pas rester en écoute permanente
                $pubsub->unsubscribe();
                // je retourne le message
                //return new Response($message->payload);

                return new JsonResponse($message->payload);
            }
        }

        return new JsonResponse('Redis consume failed!');

    }

    /**
     * Delete a channel in Redis
     * @Route("/redis/channel/delete", name="app_redis_delcanal")
     */
    public function removeAllChannels(): Response
    {
        // delete a channel in redis
        try {
            $this->redis->ping();
            $message = 'Redis connection successful!';
        } catch (\Exception $e) {
            $message = 'Redis connection failed: ' . $e->getMessage();
        }

        if(!$message === 'Redis connection successful!') {
            return new Response('Redis del canal failed!' . ' - ' . $message);
        }

        if ($message == 'Redis connection successful!') {
            try {
                $channels = $this->redis->smembers('channel');

                foreach ($channels as $channel) {
                    // remove each channel from set
                    $this->redis->srem('channel', $channel);
                }

            } catch (\Exception $e) {
                $message = 'Redis del canal failed: ' . $e->getMessage();
            }
        }

        return new Response('Redis del canal successful!' . ' - ' . $message);
    }


    /**
     * Flush all Redis
     * @Route("/redis/flush", name="app_redis_flush")
     */
    public function flushAll(): Response
    {
        // flush all redis
        try {
            $this->redis->ping();
            $message = 'Redis connection successful!';
        } catch (\Exception $e) {
            $message = 'Redis connection failed: ' . $e->getMessage();
        }

        if(!$message === 'Redis connection successful!') {
            return new Response('Redis flush failed!' . ' - ' . $message);
        }

        if ($message == 'Redis connection successful!') {
            try {
                $this->redis->flushall();
            } catch (\Exception $e) {
                $message = 'Redis flush failed: ' . $e->getMessage();
            }
        }

        return new Response('Redis flush successful!' . ' - ' . $message);
    }

    /**
     * test a channel in Redis
     * @Route("/redis/test", name="app_redis_test")
     */
    public function test(): Response
    {
        // test a channel in redis
        try {
            $this->redis->ping();
            $message = 'Redis connection successful!';
        } catch (\Exception $e) {
            $message = 'Redis connection failed: ' . $e->getMessage();
        }

        if(!$message === 'Redis connection successful!') {
            return new Response('Redis test failed!' . ' - ' . $message);
        }

        if ($message == 'Redis connection successful!') {
            try {
                $this->redis->sadd('channel-*');
            } catch (\Exception $e) {
                $message = 'Redis test failed: ' . $e->getMessage();
            }
        }

        return new Response('Redis test successful!' . ' - ' . $message);
    }

     /**
     * @Route("/stream", name="app_stream", methods={"GET"}, defaults={"waitTime"=1})
     */
    public function streamResponse()
{
    $response = new StreamedResponse();
    //set headers
    $response->headers->set('Content-Type', 'text/event-stream');
    $response->headers->set('Cache-Control', 'no-cache');
    //$response->headers->set('Connection', 'keep-alive');
    $response->headers->set('X-Accel-Buffering', 'no');
    //set callback

    $i = 1; // initialiser i à 1

    $response->setCallback(function () use (&$i) {

        if ($i <= 0) {
            return;
        } // si i est inférieur ou égal à 0, on arrête la boucle
        
        // create redis client
        $pubsub = $this->redis->pubSubLoop();
        // subscribe to channel
        $pubsub->subscribe('channel-1');
        // loop through messages received
        foreach ($pubsub as $message) {
            // check if message is valid
            if ($message->kind === 'message') {
                // unsubscribe from channel to stop listening
                $pubsub->unsubscribe('channel-1');
                // return message
                echo "data: {$message->payload}\n\n";
                ob_flush();
                flush();
                break;
            }
            $i = 0; // remettre i à 0 après le tour de boucle
        }
        $pubsub->unsubscribe();
        //sleep(1);
    });
    
    //send response
    return $response;
}

}

