<?php

namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PocketControllerTest extends WebTestCase
{
    public function testSendtopocket()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/sendToPocket');
    }

    public function testPocketform()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/pocketForm');
    }

}
