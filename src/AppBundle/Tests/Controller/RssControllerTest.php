<?php

namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RssControllerTest extends WebTestCase
{
    public function testList()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/listRss');
    }

    public function testAdd()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/addRss');
    }

}
