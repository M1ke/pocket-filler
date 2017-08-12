<?php

namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwitterControllerTest extends WebTestCase
{
    public function testTwitterauth()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/twitterAuth');
    }

}
