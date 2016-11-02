<?php

namespace Maci\AdminBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
	/**
	 * @Incomplete
	 */
    public function testIndex()
    {
    	// $this->markTestSkipped('da fare...');

        $client = static::createClient();

        $crawler = $client->request('GET', '/mcm');

        // $this->assertTrue($crawler->filter('html:contains("Hello Fabien")')->count() > 0);
    }
}
