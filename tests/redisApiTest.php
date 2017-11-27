<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

/**
 * Class redisApiTest
 */
class redisApiTest extends TestCase
{
    const BASE_URI = 'http://localhost';
    /**
     * @var Client
     */
    private $client;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $this->client = new Client(
            [
                "base_uri" => self::BASE_URI
            ]
        );
        parent::__construct($name, $data, $dataName);
    }


    /**
     * Function to test route post::'document/xxx'
     */
    public function testPost()
    {
        $data = '"Lorem ipsum dolor sit amet, consectetur adipiscing elit."';
        $request = $this->client->post("/document/155", null, json_encode($data));

        $this->assertEquals(201, $request->getStatusCode());
        $data = json_decode($request->getBody(true), true);
        $this->assertEquals($data, '"Document saved"');
    }

    /**
     * Function to test route get::'document/xxx'
     */
    public function testGet()
    {
        $data = '"Lorem ipsum dolor sit amet, consectetur adipiscing elit."';
        $request = $this->client->get("/document/155", null, null);

        $this->assertEquals(200, $request->getStatusCode());
        $dataGet = json_decode($request->getBody(true), true);
        $this->assertEquals($data, $dataGet);
    }

    /**
     * Function to test route get::'search?q[]=aaa&q[]=bbb'
     */
    public function testSearch()
    {
        $data = '["155"]';
        $request = $this->client->get("/search?q[]=consectetur&q[]=adipiscing", null, null);

        $this->assertEquals(200, $request->getStatusCode());
        $dataGet = json_decode($request->getBody(true), true);
        $this->assertEquals($data, $dataGet);
    }

    /**
     * Function to test route delete::'document/xxx'
     */
    public function testDelete()
    {
        $data = '"Document with ID 155 deleted"';
        $request = $this->client->delete("/document/155", null, null);

        $this->assertEquals(201, $request->getStatusCode());
        $dataDelete = json_decode($request->getBody(true), true);
        $this->assertEquals($data, $dataDelete);
    }
}